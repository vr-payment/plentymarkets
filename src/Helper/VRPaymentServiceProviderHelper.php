<?php
namespace VRPayment\Helper;

use IO\Services\BasketService;
use Plenty\Modules\Order\Contracts\OrderRepositoryContract;
use Plenty\Modules\Payment\Method\Contracts\PaymentMethodRepositoryContract;
use Plenty\Modules\Payment\Events\Checkout\GetPaymentMethodContent;
use Plenty\Modules\Payment\Events\Checkout\ExecutePayment;
use Plenty\Plugin\Events\Dispatcher;
use Plenty\Plugin\Log\Loggable;
use VRPayment\Helper\PaymentHelper;
use VRPayment\Services\PaymentService;

class VRPaymentServiceProviderHelper
{
    use Loggable;
    
    /**
     * @var $eventDispatcher
     */
    private $eventDispatcher;
    
    /**
     * @var $paymentHelper
     */
    private $paymentHelper;
    
    /**
     * @var $orderRepository
     */
    private $orderRepository;
    
    /**
     * @var $paymentService
     */
    private $paymentService;
    
    /**
     * @var $paymentMethodService
     */
    private $paymentMethodService;
    

    /**
     * Construct the helper
     *
     * @param  Dispatcher $eventDispatcher
     * @param  PaymentHelper $paymentHelper
     * @param  OrderRepositoryContract $orderRepository
     * @param  PaymentService $paymentService
     * @param  PaymentMethodRepositoryContract $paymentMethodService
     */
    public function __construct(
        Dispatcher $eventDispatcher,
        PaymentHelper $paymentHelper,
        OrderRepositoryContract $orderRepository,
        PaymentService $paymentService,
        PaymentMethodRepositoryContract $paymentMethodService
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->paymentHelper = $paymentHelper;
        $this->orderRepository = $orderRepository;
        $this->paymentService = $paymentService;
        $this->paymentMethodService = $paymentMethodService;
    }

    /**
     * Adds the get payment method content event listener for PWA
     * @return never
     */
    public function addGetPaymentMethodContentEventListener() {
        $this->eventDispatcher->listen(GetPaymentMethodContent::class, function (GetPaymentMethodContent $event) {
            
            $this->getLogger(__METHOD__)->error('VRPayment::GetPaymentMethodContentEvent_FIRED', [
                'mop' => $event->getMop()
            ]);
            
            try {
                // Check if this is a VR Payment method
                $isVRPayment = $this->paymentHelper->isVRPaymentPaymentMopId($event->getMop());
                
                if (!$isVRPayment) {
                    $this->getLogger(__METHOD__)->error('VRPayment::GetPaymentMethodContent_NotVRPayment', [
                        'mop' => $event->getMop()
                    ]);
                    return;
                }
                
                $this->getLogger(__METHOD__)->debug('VRPayment::IsVRPaymentMethodContent', [
                    'mop' => $event->getMop()
                ]);
                
                // Get VR Payment method object
                $eventMop = $this->paymentHelper->getVRPaymentMethodByMopId($event->getMop());
                
                if (!$eventMop) {
                    return;
                }
                
                // Handle PWA basket-based payment
                $result = $this->paymentService->executePaymentFromBasket($eventMop);
                
                $this->getLogger(__METHOD__)->debug('VRPayment::GetPaymentMethodContentResult', [
                    'result' => $result
                ]);
                
                $event->setValue($result['content'] ?? null);
                $event->setType($result['type'] ?? '');
                
            } catch (\Exception $e) {
                $this->getLogger(__METHOD__)->error('VRPayment::GetPaymentMethodContentException', [
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        });
    }

    /**
     * Adds the execute payment content event listener
     * @return never
     */
    public function addExecutePaymentContentEventListener() {
        // Listen with high priority (runs before other handlers)
        $this->eventDispatcher->listen(ExecutePayment::class, function (ExecutePayment $event) {
            
            $this->getLogger(__METHOD__)->error('VRPayment::ExecutePaymentEvent_FIRED', [
                'orderId' => $event->getOrderId(),
                'mop' => $event->getMop()
            ]);
            
            try {
                // Get the basket to check what payment method the user actually selected
                /** @var \IO\Services\BasketService $basketService */
                $basketService = pluginApp(\IO\Services\BasketService::class);
                $basket = $basketService->getBasket();
                
                $selectedPaymentMethodId = $basket->methodOfPaymentId ?? $event->getMop();
                
                $this->getLogger(__METHOD__)->error('VRPayment::CheckingPaymentMethod', [
                    'eventMop' => $event->getMop(),
                    'basketMethodOfPaymentId' => $basket->methodOfPaymentId ?? 'null',
                    'selectedPaymentMethodId' => $selectedPaymentMethodId
                ]);
                
                // Check if the selected payment method (from basket) is a VR Payment method
                $isVRPayment = $this->paymentHelper->isVRPaymentPaymentMopId($selectedPaymentMethodId);
                
                if (!$isVRPayment) {
                    $this->getLogger(__METHOD__)->error('VRPayment::NotVRPaymentMethod', [
                        'selectedPaymentMethodId' => $selectedPaymentMethodId
                    ]);
                    return;
                }
                
                $this->getLogger(__METHOD__)->error('VRPayment::IsVRPaymentMethod', [
                    'selectedPaymentMethodId' => $selectedPaymentMethodId
                ]);
                
                // Get VR Payment method object
                $eventMop = $this->paymentHelper->getVRPaymentMethodByMopId($selectedPaymentMethodId);
                
                if (!$eventMop) {
                    $this->getLogger(__METHOD__)->error('VRPayment::PaymentMethodNull', [
                        'mop' => $event->getMop()
                    ]);
                    return;
                }
                
                // Check if order exists
                $orderId = $event->getOrderId();
                
                if ($orderId == 0 || empty($orderId)) {
                    // PWA Pre-order flow: order not created yet
                    $this->getLogger(__METHOD__)->error('VRPayment::PWABasketPayment_PreOrder', [
                        'selectedPaymentMethodId' => $selectedPaymentMethodId
                    ]);
                    
                    // Handle PWA basket-based payment (creates transaction, returns redirect)
                    $result = $this->paymentService->executePaymentFromBasket($eventMop);
                    
                } else {
                    // Order exists: either traditional flow or PWA post-order-creation call
                    $eventOrderId = $this->orderRepository->findById($orderId);
                    if (!$eventOrderId) {
                        $this->getLogger(__METHOD__)->error('VRPayment::OrderNotFound', [
                            'orderId' => $orderId
                        ]);
                        return;
                    }
                    
                    $this->getLogger(__METHOD__)->error('VRPayment::ExecutingPaymentWithOrder', [
                        'orderId' => $eventOrderId->id,
                        'selectedPaymentMethodId' => $selectedPaymentMethodId,
                        'eventMop' => $event->getMop()
                    ]);

                    $result = $this->paymentService->executePayment(
                        $eventOrderId,
                        $eventMop
                    );
                }
                
                // Map GetPaymentMethodContent types to ExecutePayment types for PWA compatibility
                $type = isset($result['type']) ? $result['type'] : '';
                if ($type === GetPaymentMethodContent::RETURN_TYPE_REDIRECT_URL || $type === 'redirectUrl') {
                    $type = 'redirect';
                } elseif ($type === GetPaymentMethodContent::RETURN_TYPE_ERROR || $type === 'error') {
                    $type = 'error';
                } elseif ($type === GetPaymentMethodContent::RETURN_TYPE_CONTINUE || $type === 'continue') {
                    $type = 'continue';
                }
                
                $this->getLogger(__METHOD__)->error('VRPayment::ExecutePaymentResult', [
                    'result' => $result,
                    'originalType' => isset($result['type']) ? $result['type'] : '',
                    'mappedType' => $type,
                    'value' => isset($result['content']) ? $result['content'] : null
                ]);
                
                $event->setValue(isset($result['content']) ? $result['content'] : null);
                $event->setType($type);
                
                $this->getLogger(__METHOD__)->error('VRPayment::EventValuesAfterSet', [
                    'eventGetValue' => $event->getValue(),
                    'eventGetType' => $event->getType()
                ]);
            } catch (\Exception $e) {
                $this->getLogger(__METHOD__)->error('VRPayment::ExecutePaymentException', [
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        });
    }
}
