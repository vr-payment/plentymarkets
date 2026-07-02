<?php
namespace VRPayment\Helper;

use Plenty\Modules\Frontend\Session\Storage\Contracts\FrontendSessionStorageFactoryContract;
use Plenty\Modules\Order\Contracts\OrderRepositoryContract;
use Plenty\Modules\Payment\Method\Contracts\PaymentMethodRepositoryContract;
use Plenty\Modules\Payment\Events\Checkout\GetPaymentMethodContent;
use Plenty\Modules\Payment\Events\Checkout\ExecutePayment;
use Plenty\Plugin\Events\Dispatcher;
use Plenty\Plugin\Http\Request;
use Plenty\Plugin\Log\Loggable;
use VRPayment\Helper\PaymentHelper;
use VRPayment\Services\PaymentService;
use Plenty\Modules\Order\Events\OrderCreated;

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
     *
     * @var FrontendSessionStorageFactoryContract
     */
    private $session;

    /**
     *
     * @var Request
     */
    private $request;

    /**
     * Construct the helper
     *
     * @param  Dispatcher $eventDispatcher
     * @param  PaymentHelper $paymentHelper
     * @param  OrderRepositoryContract $orderRepository
     * @param  PaymentService $paymentService
     * @param  PaymentMethodRepositoryContract $paymentMethodService
     * @param  FrontendSessionStorageFactoryContract $session
     * @param  Request $request
     */
    public function __construct(
        Dispatcher $eventDispatcher,
        PaymentHelper $paymentHelper,
        OrderRepositoryContract $orderRepository,
        PaymentService $paymentService,
        PaymentMethodRepositoryContract $paymentMethodService,
        FrontendSessionStorageFactoryContract $session,
        Request $request
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->paymentHelper = $paymentHelper;
        $this->orderRepository = $orderRepository;
        $this->paymentService = $paymentService;
        $this->paymentMethodService = $paymentMethodService;
        $this->session = $session;
        $this->request = $request;
    }

    /**
     * Returns true when the request originates from the PWA layer.
     * The PWA plugin calls registerReturnContext before dopreparepayment,
     * which stores vRPaymentOriginUrl in the session. CERES never sets this value.
     */
    /**
     * Determines whether the current request originates from the PWA theme.
     * Logs the lookup variables to assist in debugging session state issues.
     *
     * @return bool
     */
    private function isPwaContext(): bool
    {
        $originUrl = $this->session->getPlugin()->getValue('vRPaymentOriginUrl');
        $transactionId = $this->session->getPlugin()->getValue('vRPaymentTransactionId');

        $this->getLogger(__METHOD__)->debug('Checking PWA context variables', [
            'originUrl' => $originUrl ?? 'null',
            'transactionId' => $transactionId ?? 'null',
        ]);

        return !empty($originUrl);
    }

    /**
     * Short fingerprint of the plenty session cookie so log lines from the
     * prepare / order-created / execute requests can be matched to the same
     * (or a different) frontend session without writing the raw cookie to
     * the logs. A changing fingerprint between two steps of one checkout
     * means the session rotated or the requests used different sessions.
     */
    private function sessionFingerprint(): string
    {
        $cookie = (string) $this->request->header('Cookie');
        if (preg_match('/plentyID=([^;]+)/', $cookie, $matches)) {
            return substr(md5($matches[1]), 0, 12);
        }
        return $cookie !== '' ? 'no-plentyid-' . substr(md5($cookie), 0, 8) : 'no-cookie';
    }

    /**
     * Snapshot of all vRPayment session keys, attached to flow logs so we can
     * see exactly which key was missing when a redirect fails to happen.
     */
    private function vRPaymentSessionSnapshot(): array
    {
        return [
            'sessionFingerprint' => $this->sessionFingerprint(),
            'vRPaymentOriginUrl' => $this->session->getPlugin()->getValue('vRPaymentOriginUrl') ?? 'null',
            'vRPaymentTransactionId' => $this->session->getPlugin()->getValue('vRPaymentTransactionId') ?? 'null',
            'vRPaymentPendingRedirectUrl' => $this->session->getPlugin()->getValue('vRPaymentPendingRedirectUrl') ?? 'null',
            'vRPaymentOrderId' => $this->session->getPlugin()->getValue('vRPaymentOrderId') ?? 'null',
        ];
    }

    /**
     * Adds a listener to handle order creation and associate VRPayment transaction.
     * PWA only: CERES handles payment entirely via ExecutePayment.
     * @return void
     */
    public function addAfterOrderCreatedListener(): void
    {
        $this->eventDispatcher->listen(OrderCreated::class, function (OrderCreated $event) {
            $order = $event->getOrder();

            try {
                if (!is_object($order) || !isset($order->id)) {
                    return;
                }

                if (!$this->paymentHelper->isVRPaymentPaymentMopId($order->methodOfPaymentId ?? 0)) {
                    return;
                }

                // CERES processes payment in ExecutePayment — nothing to do here
                if (!$this->isPwaContext()) {
                    return;
                }

                $transactionId = $this->session->getPlugin()->getValue('vRPaymentTransactionId');

                // Link the basket-level transaction to the newly created order
                if ($transactionId) {
                    try {
                        /** @var \VRPayment\Services\VRPaymentSdkService $sdkService */
                        $sdkService = pluginApp(\VRPayment\Services\VRPaymentSdkService::class);
                        $sdkService->call('updateTransaction', [
                            'id' => $transactionId,
                            'merchantReference' => (string) $order->id,
                        ]);
                    } catch (\Exception $e) {
                        $this->getLogger(__METHOD__)->error('VRPayment::TransactionLinkFailed', [
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                $paymentMethod = $this->paymentHelper->getVRPaymentPaymentMethodByMopId($order->methodOfPaymentId);
                if (!$paymentMethod) {
                    $this->getLogger(__METHOD__)->error('VRPayment::PaymentMethodNotFound', [
                        'methodOfPaymentId' => $order->methodOfPaymentId,
                    ]);
                    return;
                }

                $result = $this->paymentService->executePayment($order, $paymentMethod);

                $type = $result['type'] ?? '';
                if ($type === GetPaymentMethodContent::RETURN_TYPE_REDIRECT_URL || $type === 'redirectUrl') {
                    $type = 'redirect';
                } elseif ($type === GetPaymentMethodContent::RETURN_TYPE_ERROR || $type === 'error') {
                    $type = 'error';
                } else {
                    $type = 'continue';
                }

                // Store redirect URL in session so ExecutePayment listener can return it to PWA
                if ($type === 'redirect' && !empty($result['content'])) {
                    $this->session->getPlugin()->setValue('vRPaymentPendingRedirectUrl', $result['content']);
                    $this->session->getPlugin()->setValue('vRPaymentOrderId', $order->id);
                }

            } catch (\Exception $e) {
                $this->getLogger(__METHOD__)->error('VRPayment::AfterOrderCreatedException', [
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        });
    }

    /**
     * Adds the get payment method content event listener.
     * PWA only: creates a basket-level transaction before order creation and stores the redirect URL in session.
     * CERES is skipped — its transaction is created in ExecutePayment after order creation.
     * @return void
     */
    public function addGetPaymentMethodContentEventListener(): void
    {
        $this->eventDispatcher->listen(GetPaymentMethodContent::class, function (GetPaymentMethodContent $event) {
            try {
                if (!$this->paymentHelper->isVRPaymentPaymentMopId($event->getMop())) {
                    return;
                }

                // CERES creates its transaction in ExecutePayment — skip here
                if (!$this->isPwaContext()) {
                    return;
                }

                $eventMop = $this->paymentHelper->getVRPaymentPaymentMethodByMopId($event->getMop());
                if (!$eventMop) {
                    $event->setType('continue');
                    $event->setValue('');
                    return;
                }

                $result = $this->paymentService->executePaymentFromBasket($eventMop);

                $event->setValue($result['content'] ?? null);
                $event->setType($result['type'] ?? '');

                $this->session->getPlugin()->setValue('vRPaymentPaymentSelectedMethodId', $event->getMop());
                if (!empty($result['content'])) {
                    $this->session->getPlugin()->setValue('vRPaymentPendingRedirectUrl', $result['content']);
                }

            } catch (\Exception $e) {
                $this->getLogger(__METHOD__)->error('VRPayment::GetPaymentMethodContentException', [
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        });
    }

    /**
     * Adds the execute payment content event listener.
     * PWA: returns redirect URL stored in session by addAfterOrderCreatedListener.
     * CERES: creates VRPayment transaction from the real order, returns 'redirectUrl' type for CERES redirect.
     * @return void
     */
    public function addExecutePaymentContentEventListener(): void
    {
        $this->eventDispatcher->listen(ExecutePayment::class, function (ExecutePayment $event) {
            try {
                $isPwa = $this->isPwaContext();
                $orderId = $event->getOrderId();

                if ($isPwa) {
                    // Primary source: URL stored by addAfterOrderCreatedListener.
                    // Fallback: URL stored by addGetPaymentMethodContentEventListener.
                    $redirectUrl = $this->session->getPlugin()->getValue('vRPaymentPendingRedirectUrl');

                    // Second fallback: rebuild URL from the transaction ID still in session.
                    if (empty($redirectUrl)) {
                        $transactionId = $this->session->getPlugin()->getValue('vRPaymentTransactionId');
                        if ($transactionId) {
                            /** @var \VRPayment\Services\VRPaymentSdkService $sdkService */
                            $sdkService = pluginApp(\VRPayment\Services\VRPaymentSdkService::class);
                            $paymentPageUrl = $sdkService->call('buildPaymentPageUrl', ['id' => $transactionId]);
                            if (!empty($paymentPageUrl) && !is_array($paymentPageUrl)) {
                                $redirectUrl = $paymentPageUrl;
                            }
                        }
                    }

                    if (!empty($redirectUrl)) {
                        $this->session->getPlugin()->unsetKey('vRPaymentPendingRedirectUrl');
                        $this->session->getPlugin()->unsetKey('vRPaymentOrderId');
                        // $this->session->getPlugin()->unsetKey('vRPaymentOriginUrl');
                        // $this->session->getPlugin()->unsetKey('vRPaymentTransactionId');
                        // $this->session->getPlugin()->unsetKey('vRPaymentPaymentSelectedMethodId');
                        $event->setType('redirect');
                        $event->setValue($redirectUrl);
                    } else {
                        // No pending URL and no transaction id in session: the prepare-phase
                        // events (GetPaymentMethodContent / OrderCreated) did not run for this
                        // checkout. The order already exists at this point, so recover by
                        // creating the transaction directly from the order, like CERES does.

                        $recoveredUrl = $this->recoverRedirectUrlFromOrder($orderId, $event->getMop());
                        if (!empty($recoveredUrl)) {
                            $event->setType('redirect');
                            $event->setValue($recoveredUrl);
                            return;
                        }

                        // Recovery failed too: the storefront receives 'continue' and
                        // will NOT redirect to the payment page.
                        $event->setType('continue');
                        $event->setValue('');
                    }
                    return;
                }

                // CERES: validate MOP, then create VRPayment transaction using the real order.
                $mopId = $event->getMop();
                if (!$this->paymentHelper->isVRPaymentPaymentMopId($mopId)) {
                    return;
                }

                $eventMop = $this->paymentHelper->getVRPaymentPaymentMethodByMopId($mopId);
                if (!$eventMop) {
                    return;
                }

                $eventOrderId = $this->orderRepository->findById($orderId);
                if (!$eventOrderId) {
                    return;
                }

                // Creates CONFIRMED transaction + plentyPayment (unaccountable=1) + assigns to order.
                $result = $this->paymentService->executePayment($eventOrderId, $eventMop);

                // Pass type directly — CERES expects 'redirectUrl' (not 'redirect') from ExecutePayment.
                $event->setType($result['type'] ?? '');
                $event->setValue($result['content'] ?? null);

            } catch (\Exception $e) {
                $this->getLogger(__METHOD__)->error('VRPayment::ExecutePaymentException', [
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $event->setType('error');
                $event->setValue('Payment failed: ' . $e->getMessage());
            }
        });
    }

    /**
     * Last-resort recovery for the PWA flow: when ExecutePayment finds neither a
     * pending redirect URL nor a transaction id in the session (observed when the
     * prepare-phase events were never dispatched), create the VRPayment transaction
     * directly from the already-created order and return the payment page URL.
     *
     * @param int|null $orderId
     * @param int|string|null $mopId
     * @return string|null
     */
    private function recoverRedirectUrlFromOrder($orderId, $mopId): ?string
    {
        try {
            if (empty($orderId) || !$this->paymentHelper->isVRPaymentPaymentMopId($mopId)) {
                $this->getLogger(__METHOD__)->error('VRPayment::PwaRecoveryNotApplicable', [
                    'orderId' => $orderId,
                    'mopId' => $mopId,
                ]);
                return null;
            }

            $paymentMethod = $this->paymentHelper->getVRPaymentPaymentMethodByMopId($mopId);
            $order = $this->orderRepository->findById($orderId);
            if (!$paymentMethod || !$order) {
                $this->getLogger(__METHOD__)->error('VRPayment::PwaRecoveryOrderOrMethodMissing', [
                    'orderId' => $orderId,
                    'mopId' => $mopId,
                    'orderFound' => !empty($order),
                    'methodFound' => !empty($paymentMethod),
                ]);
                return null;
            }

            $result = $this->paymentService->executePayment($order, $paymentMethod);

            if (($result['type'] ?? '') === GetPaymentMethodContent::RETURN_TYPE_REDIRECT_URL && !empty($result['content'])) {
                return (string) $result['content'];
            }
        } catch (\Exception $e) {
            $this->getLogger(__METHOD__)->error('VRPayment::PwaRecoveryException', [
                'orderId' => $orderId,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
        return null;
    }
}
