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
     * Adds the execute payment content event listener
     * @return never
     */
    public function addExecutePaymentContentEventListener() {
        $this->eventDispatcher->listen(ExecutePayment::class, function (ExecutePayment $event) {
            
            $this->getLogger(__METHOD__)->debug('VRPayment::ExecutePaymentEvent', [
                'orderId' => $event->getOrderId(),
                'mop' => $event->getMop()
            ]);
            
            try {
                $eventOrderId = $this->orderRepository->findById($event->getOrderId());
                if (!$eventOrderId) {
                    $this->getLogger(__METHOD__)->error('VRPayment::OrderNotFound', [
                        'orderId' => $event->getOrderId()
                    ]);
                    return;
                }
                
                $eventMop = $this->paymentMethodService->findByPaymentMethodId($event->getMop());
                if (!$eventMop) {
                    $this->getLogger(__METHOD__)->debug('VRPayment::PaymentMethodNotFound', [
                        'mop' => $event->getMop()
                    ]);
                    return;
                }
                
                $isVRPayment = $this->paymentHelper->isVRPaymentPaymentMopId($event->getMop());
                
                if ($isVRPayment) {

                    $this->getLogger(__METHOD__)->debug('VRPayment::ExecutingPayment', [
                        'orderId' => $eventOrderId->id,
                        'mopId' => $event->getMop()
                    ]);

                    $result = $this->paymentService->executePayment(
                        $eventOrderId,
                        $eventMop
                    );
                    
                    // Map GetPaymentMethodContent types to ExecutePayment types for PWA compatibility
                    $type = isset($result['type']) ? $result['type'] : '';
                    if ($type === GetPaymentMethodContent::RETURN_TYPE_REDIRECT_URL || $type === 'redirectUrl') {
                        $type = 'redirect';
                    } elseif ($type === GetPaymentMethodContent::RETURN_TYPE_ERROR || $type === 'error') {
                        $type = 'error';
                    } elseif ($type === GetPaymentMethodContent::RETURN_TYPE_CONTINUE || $type === 'continue') {
                        $type = 'continue';
                    }
                    
                    $this->getLogger(__METHOD__)->debug('VRPayment::ExecutePaymentResult', [
                        'result' => $result,
                        'originalType' => isset($result['type']) ? $result['type'] : '',
                        'mappedType' => $type,
                        'value' => isset($result['content']) ? $result['content'] : null
                    ]);
                    
                    $event->setValue(isset($result['content']) ? $result['content'] : null);
                    $event->setType($type);
                } else {
                    $this->getLogger(__METHOD__)->debug('VRPayment::NotVRPaymentMethod', [
                        'mop' => $event->getMop(),
                        'isVRPayment' => false
                    ]);
                }
            } catch (\Exception $e) {
                $this->getLogger(__METHOD__)->error('VRPayment::ExecutePaymentException', [
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        });
    }
}
