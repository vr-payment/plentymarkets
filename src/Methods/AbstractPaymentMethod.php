<?php
namespace VRPayment\Methods;

use Plenty\Modules\Payment\Method\Contracts\PaymentMethodService;
use Plenty\Plugin\ConfigRepository;
use VRPayment\Services\PaymentService;
use Plenty\Modules\Payment\Contracts\PaymentRepositoryContract;

abstract class AbstractPaymentMethod extends PaymentMethodService
{

    /**
     *
     * @var ConfigRepository
     */
    protected $configRepo;

    /**
     *
     * @var PaymentService
     */
    protected $paymentService;

    /**
     *
     * @var PaymentRepositoryContract
     */
    protected $paymentRepository;

    /**
     * Constructor.
     *
     * @param ConfigRepository $configRepo
     * @param PaymentService $paymentService
     * @param PaymentRepositoryContract $paymentRepository
     */
    public function __construct(ConfigRepository $configRepo, PaymentService $paymentService, PaymentRepositoryContract $paymentRepository)
    {
        $this->configRepo = $configRepo;
        $this->paymentService = $paymentService;
        $this->paymentRepository = $paymentRepository;
    }

    protected function getBaseIconPath()
    {
        switch ($this->configRepo->get('vRPayment.resource_version')) {
            case 'V1':
                return \VRPayment\Services\VRPaymentSdkService::GATEWAY_BASE_PATH . '/s/' . $this->configRepo->get('vRPayment.space_id') . '/resource/icon/payment/method/';
            case 'V2':
                return \VRPayment\Services\VRPaymentSdkService::GATEWAY_BASE_PATH . '/s/' . $this->configRepo->get('vRPayment.space_id') . '/resource/web/image/payment/method/';
            default:
                return \VRPayment\Services\VRPaymentSdkService::GATEWAY_BASE_PATH . '/resource/web/image/payment/method/';
        }
    }

    protected function getImagePath($fileName)
    {
        return $this->getBaseIconPath() . $fileName . '?' . time();
    }

    public function isSwitchableTo($orderId)
    {
        return false;
    }

    public function isSwitchableFrom($orderId)
    {
        return true;
    }

    /**
     * Check if this payment method runs in the background.
     * Returns false because VR Payment requires redirecting to external payment page.
     *
     * @return bool
     */
    public function isBackgroundEnabled(): bool
    {
        try {
            /** @var \Plenty\Plugin\Log\Loggable $loggable */
            $loggable = pluginApp(\Plenty\Plugin\Log\Loggable::class);
            $loggable->getLogger('VRPayment')->error('VRPayment::isBackgroundEnabled_CALLED', [
                'class' => static::class,
                'returning' => false
            ]);
        } catch (\Exception $e) {
            // Silently fail if logging doesn't work
        }
        
        return false;
    }

    /**
     * Check if the payment method should be shown as an icon in checkout.
     *
     * @return bool
     */
    public function showIconInCheckout(): bool
    {
        return true;
    }

    /**
     * Get the payment redirect source URL.
     * PWA uses this for non-background payment methods.
     *
     * @param int $orderId
     * @return string
     */
    public function getSourceUrl(int $orderId): string
    {
        /** @var \Plenty\Plugin\Log\Loggable $loggable */
        $loggable = pluginApp(\Plenty\Plugin\Log\Loggable::class);
        $loggable->getLogger(__METHOD__)->error('VRPayment::getSourceUrl_CALLED', [
            'orderId' => $orderId,
            'paymentMethod' => get_class($this)
        ]);
        
        // For PWA: Return URL that triggers payment preparation
        // This should trigger the ExecutePayment event
        return '';
    }

}