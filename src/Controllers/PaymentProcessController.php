<?php
namespace VRPayment\Controllers;

use IO\Services\NotificationService;
use Plenty\Plugin\ConfigRepository;
use Plenty\Plugin\Controller;
use Plenty\Plugin\Http\Request;
use Plenty\Plugin\Http\Response;
use Plenty\Plugin\Log\Loggable;
use VRPayment\Services\VRPaymentSdkService;
use VRPayment\Helper\PaymentHelper;
use Plenty\Plugin\Templates\Twig;
use Plenty\Modules\Payment\Contracts\PaymentRepositoryContract;
use Plenty\Modules\Order\Contracts\OrderRepositoryContract;
use Plenty\Modules\Payment\Contracts\PaymentOrderRelationRepositoryContract;
use Plenty\Modules\Payment\Models\PaymentProperty;
use IO\Services\OrderService;
use Plenty\Modules\Authorization\Services\AuthHelper;
use IO\Constants\OrderPaymentStatus;
use Plenty\Modules\Order\Models\Order;
use Plenty\Modules\Frontend\PaymentMethod\Contracts\FrontendPaymentMethodRepositoryContract;
use Plenty\Modules\Order\Property\Models\OrderPropertyType;
use Plenty\Modules\Payment\Method\Contracts\PaymentMethodRepositoryContract;
use VRPayment\Services\PaymentService;
use Plenty\Modules\Payment\Events\Checkout\GetPaymentMethodContent;
use IO\Services\OrderTotalsService;
use IO\Models\LocalizedOrder;
use IO\Services\SessionStorageService;
use VRPayment\Helper\OrderHelper;
use Plenty\Modules\Frontend\Session\Storage\Contracts\FrontendSessionStorageFactoryContract;

class PaymentProcessController extends Controller
{

    use Loggable;

    /**
     *
     * @var Response
     */
    private $response;

    /**
     *
     * @var VRPaymentSdkService
     */
    private $sdkService;

    /**
     *
     * @var NotificationService
     */
    private $notificationService;

    /**
     *
     * @var PaymentService
     */
    private $paymentService;

    /**
     *
     * @var PaymentHelper
     */
    private $paymentHelper;

    /**
     *
     * @var PaymentRepositoryContract
     */
    private $paymentRepository;

    /**
     *
     * @var OrderRepositoryContract
     */
    private $orderRepository;

    /**
     *
     * @var PaymentOrderRelationRepositoryContract
     */
    private $paymentOrderRelationRepository;

    /**
     *
     * @var OrderHelper
     */
    private $orderHelper;

    /**
     *
     * @var OrderService
     */
    private $orderService;

    /**
     *
     * @var FrontendPaymentMethodRepositoryContract
     */
    private $frontendPaymentMethodRepository;

    /**
     *
     * @var PaymentMethodRepositoryContract
     */
    private $paymentMethodService;

    /**
     *
     * @var SessionStorageService
     */
    private $sessionStorage;

    /**
     *
     * @var FrontendSessionStorageFactoryContract
     */
    private $frontendSession;
    
    /**
     *
     * @var ConfigRepository
     */
    private $config;

    /**
     * Constructor.
     *
     * @param Response $response
     * @param VRPaymentSdkService $sdkService
     * @param NotificationService $notificationService
     * @param PaymentService $paymentService
     * @param PaymentHelper $paymentHelper
     * @param PaymentRepositoryContract $paymentRepository
     * @param OrderRepositoryContract $orderRepository
     * @param PaymentOrderRelationRepositoryContract $paymentOrderRelationRepository
     * @param OrderHelper $orderHelper
     * @param OrderService $orderService
     * @param FrontendPaymentMethodRepositoryContract $frontendPaymentMethodRepository
     * @param PaymentMethodRepositoryContract $paymentMethodService
     * @param SessionStorageService $sessionStorage
     * @param FrontendSessionStorageFactoryContract $frontendSession
     * @param ConfigRepository $config
     */
    public function __construct(Response $response, VRPaymentSdkService $sdkService, NotificationService $notificationService, PaymentService $paymentService, PaymentHelper $paymentHelper, PaymentRepositoryContract $paymentRepository, OrderRepositoryContract $orderRepository, PaymentOrderRelationRepositoryContract $paymentOrderRelationRepository, OrderHelper $orderHelper, OrderService $orderService, FrontendPaymentMethodRepositoryContract $frontendPaymentMethodRepository, PaymentMethodRepositoryContract $paymentMethodService, SessionStorageService $sessionStorage, FrontendSessionStorageFactoryContract $frontendSession, ConfigRepository $config)
    {
        parent::__construct();
        $this->response = $response;
        $this->sdkService = $sdkService;
        $this->notificationService = $notificationService;
        $this->paymentService = $paymentService;
        $this->paymentHelper = $paymentHelper;
        $this->paymentRepository = $paymentRepository;
        $this->orderRepository = $orderRepository;
        $this->paymentOrderRelationRepository = $paymentOrderRelationRepository;
        $this->orderHelper = $orderHelper;
        $this->orderService = $orderService;
        $this->frontendPaymentMethodRepository = $frontendPaymentMethodRepository;
        $this->paymentMethodService = $paymentMethodService;
        $this->sessionStorage = $sessionStorage;
        $this->frontendSession = $frontendSession;
        $this->config = $config;
    }

    /**
     *
     * @param int $id
     */
    public function failTransaction(Twig $twig, int $id)
    {
        $transaction = $this->sdkService->call('getTransaction', [
            'id' => $id
        ]);
        // Get the current language from session storage
        $lang = $this->sessionStorage->getLang();

        if (is_array($transaction) && isset($transaction['error'])) {
            $confirmUrl = sprintf('%s/confirmation', $lang);
            return $this->response->redirectTo($confirmUrl);
        }

        $payments = $this->paymentRepository->getPaymentsByPropertyTypeAndValue(PaymentProperty::TYPE_TRANSACTION_ID, $transaction['id']);
        $payment = $payments[0];

        $orderRelation = $this->paymentOrderRelationRepository->findOrderRelation($payment);
        $order = $this->orderRepository->findOrderById($orderRelation->orderId);

        $paymentMethodId = $this->orderHelper->getOrderPropertyValue($order, OrderPropertyType::PAYMENT_METHOD);

        $errorMessage = $this->frontendSession->getPlugin()->getValue('vRPaymentPayErrorMessage');
        if ($errorMessage) {
            $this->notificationService->error($errorMessage);
            $this->frontendSession->getPlugin()->unsetKey('vRPaymentPayErrorMessage');
        } elseif (isset($transaction['userFailureMessage']) && ! empty($transaction['userFailureMessage'])) {
            $this->notificationService->error($transaction['userFailureMessage']);
            $this->paymentHelper->updatePlentyPayment($transaction);
        }

        if (! is_null($order) && ! ($order instanceof LocalizedOrder)) {
            $order = LocalizedOrder::wrap($order, $this->sessionStorage->getLang());
        }

        return $twig->render('vRPayment::Failure', [
            'transaction' => $transaction,
            'payment' => $payment,
            'bodyClasses' => ['page-confirmation'],
            'orderData' => $order,
            'totals' => pluginApp(OrderTotalsService::class)->getAllTotals($order->order),
            'currentPaymentMethodId' => $paymentMethodId,
            'allowSwitchPaymentMethod' => $this->allowSwitchPaymentMethod($order->order->id),
            'paymentMethodListForSwitch' => $this->getPaymentMethodListForSwitch($paymentMethodId, $order->order->id),
            'payOrderFormUrl' => sprintf('/%s/vrpayment/pay-order/', $lang)
        ]);
    }

    /**
     * Prepare payment for PWA (before order is created)
     *
     * @param Request $request
     * @return Response
     */
    public function preparePayment(Request $request)
    {
        $paymentMethodId = $request->get('paymentMethodId', '');
        
        $this->getLogger(__METHOD__)->error('VRPayment::PreparePayment_CALLED', [
            'paymentMethodId' => $paymentMethodId,
            'requestData' => $request->all()
        ]);
        
        try {
            if (empty($paymentMethodId)) {
                return $this->response->json([
                    'type' => 'error',
                    'value' => 'Payment method ID is required'
                ]);
            }
            
            // Get the payment method
            $paymentMethod = $this->paymentMethodService->findByPaymentMethodId($paymentMethodId);
            
            if (!$paymentMethod) {
                return $this->response->json([
                    'type' => 'error',
                    'value' => 'Payment method not found'
                ]);
            }
            
            // Check if this is a VR Payment method
            if (!$this->paymentHelper->isVRPaymentPaymentMopId($paymentMethodId)) {
                return $this->response->json([
                    'type' => 'continue',
                    'value' => ''
                ]);
            }
            
            // Execute payment from basket (PWA flow)
            $result = $this->paymentService->executePaymentFromBasket($paymentMethod);
            
            $this->getLogger(__METHOD__)->error('VRPayment::PreparePaymentResult', [
                'result' => $result
            ]);
            
            return $this->response->json([
                'type' => $result['type'] === GetPaymentMethodContent::RETURN_TYPE_REDIRECT_URL ? 'redirect' : ($result['type'] === GetPaymentMethodContent::RETURN_TYPE_ERROR ? 'error' : 'continue'),
                'value' => $result['content'] ?? ''
            ]);
            
        } catch (\Exception $e) {
            $this->getLogger(__METHOD__)->error('VRPayment::PreparePaymentException', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->response->json([
                'type' => 'error',
                'value' => 'An error occurred while preparing the payment'
            ]);
        }
    }

    public function payOrder(Request $request)
    {
        $orderId = $request->get('orderId', '');
        $paymentMethodId = $request->get('paymentMethod', '');

        /** @var AuthHelper $authHelper */
        $authHelper = pluginApp(AuthHelper::class);
        $orderRepo = $this->orderRepository;
        $order = $authHelper->processUnguarded(function () use ($orderId, $orderRepo) {
            return $orderRepo->findOrderById($orderId);
        });

        $this->switchPaymentMethodForOrder($order, $paymentMethodId);
        $result = $this->paymentService->executePayment($order, $this->paymentMethodService->findByPaymentMethodId($paymentMethodId));
        // Get the current language from session storage
        $lang = $this->sessionStorage->getLang();

        if ($result['type'] == GetPaymentMethodContent::RETURN_TYPE_REDIRECT_URL) {
            return $this->response->redirectTo($result['content']);
        } elseif (isset($result['transactionId'])) {
            if (isset($result['content'])) {
                $this->frontendSession->getPlugin()->setValue('vRPaymentPayErrorMessage', $result['content']);
            }
            // Construct the URL with the language
            $failUrl = sprintf('%s/vrpayment/fail-transaction/%s', $lang, $result['transactionId']);
            return $this->response->redirectTo($failUrl);
        } else {
            $confirmUrl = sprintf('%s/confirmation', $lang);
            return $this->response->redirectTo($confirmUrl);
        }
    }

    private function switchPaymentMethodForOrder(Order $order, $paymentMethodId)
    {
        $orderId = $order->id;
        $orderRepo = $this->orderRepository;
        $currentPaymentMethodId = 0;
        $newOrderProperties = [];
        $orderProperties = $order->properties;

        if (count($orderProperties)) {
            foreach ($orderProperties as $key => $orderProperty) {
                $newOrderProperties[$key] = [
                    'typeId' => $orderProperty->typeId,
                    'value' => (string) $orderProperty->value
                ];
                if ($orderProperty->typeId == OrderPropertyType::PAYMENT_METHOD) {
                    $currentPaymentMethodId = (int) $orderProperty->value;
                    $newOrderProperties[$key]['value'] = (string) $paymentMethodId;
                }
            }
        }

        if ($paymentMethodId !== $currentPaymentMethodId) {
            /** @var AuthHelper $authHelper */
            $authHelper = pluginApp(AuthHelper::class);
            $order = $authHelper->processUnguarded(function () use ($orderId, $newOrderProperties, $orderRepo) {
                return $orderRepo->updateOrder([
                    'properties' => $newOrderProperties
                ], $orderId);
            });

            if (! is_null($order)) {
                return $order;
            }
        } else {
            return $order;
        }
    }

    private function getPaymentMethodListForSwitch($paymentMethodId, $orderId)
    {
        $lang = $this->sessionStorage->getLang();
        $paymentMethods = $this->frontendPaymentMethodRepository->getCurrentPaymentMethodsList();
        $paymentMethodsForSwitch = [];
        foreach ($paymentMethods as $paymentMethod) {
            if ($paymentMethod->pluginKey == 'vRPayment') {
                $paymentMethodsForSwitch[] = [
                    'id' => $paymentMethod->id,
                    'name' => $this->frontendPaymentMethodRepository->getPaymentMethodName($paymentMethod, $lang),
                    'icon' => $this->frontendPaymentMethodRepository->getPaymentMethodIcon($paymentMethod, $lang),
                    'description' => $this->frontendPaymentMethodRepository->getPaymentMethodDescription($paymentMethod, $lang)
                ];
            }
        }
        return $paymentMethodsForSwitch;
    }

    private function allowSwitchPaymentMethod($orderId)
    {
        /** @var AuthHelper $authHelper */
        $authHelper = pluginApp(AuthHelper::class);
        $orderRepo = $this->orderRepository;

        $order = $authHelper->processUnguarded(function () use ($orderId, $orderRepo) {
            return $orderRepo->findOrderById($orderId);
        });

        if ($order->paymentStatus !== OrderPaymentStatus::UNPAID) {
            // order was paid
            return false;
        }

        $statusId = $order->statusId;
        $orderCreatedDate = $order->createdAt;

        if ($this->checkOrderRetryStatus($statusId)
            || $statusId <= 3.4
            || ($statusId == 5 && $orderCreatedDate->toDateString() == date('Y-m-d'))) {
            return true;
        } else {
            return false;
        }
    }
    
    private function checkOrderRetryStatus($statusId) {
        $orderRetryStatusString = $this->config->get('vRPayment.order_retry_status');
        if (!empty($orderRetryStatusString)) {
            $orderRetryStatus = array_map('trim', explode(';', $orderRetryStatusString));
            return in_array($statusId, $orderRetryStatus);
        } else {
            return false;
        }
    }
}