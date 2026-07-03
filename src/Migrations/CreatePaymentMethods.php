<?php
namespace VRPayment\Migrations;

use Plenty\Modules\Payment\Method\Contracts\PaymentMethodRepositoryContract;
use Plenty\Plugin\Log\Loggable;
use VRPayment\Helper\PaymentHelper;

class CreatePaymentMethods
{

    use Loggable;

    /**
     *
     * @var PaymentMethodRepositoryContract
     */
    private $paymentMethodRepositoryContract;

    /**
     *
     * @var PaymentHelper
     */
    private $paymentHelper;

    /**
     * Constructor.
     *
     * @param PaymentMethodRepositoryContract $paymentMethodRepositoryContract
     * @param PaymentHelper $paymentHelper
     */
    public function __construct(PaymentMethodRepositoryContract $paymentMethodRepositoryContract, PaymentHelper $paymentHelper)
    {
        $this->paymentMethodRepositoryContract = $paymentMethodRepositoryContract;
        $this->paymentHelper = $paymentHelper;
    }

    /**
     * Creates the payment methods for the VR Payment plugin.
     */
    public function run()
    {
        $this->createPaymentMethod(1457546097602, 'Bank Transfer');
        $this->createPaymentMethod(1457546097597, 'Credit / Debit Card');
        $this->createPaymentMethod(1457546097601, 'Direct Debit (SEPA)');
        $this->createPaymentMethod(1457546097609, 'EPS');
        $this->createPaymentMethod(1461674005576, 'iDeal');
        $this->createPaymentMethod(1457546097598, 'Invoice');
        $this->createPaymentMethod(1460954915005, 'Online Banking');
        $this->createPaymentMethod(1457546097613, 'PayPal');
        $this->createPaymentMethod(1689233132073, 'Post Finance Pay');
        $this->createPaymentMethod(1457546097617, 'Przelewy24');
        $this->createPaymentMethod(1754840745162, 'Wero');
        $this->createPaymentMethod(1864086284039, 'Klarna Pay Now');
        $this->createPaymentMethod(1864086284619, 'Klarna Pay Later');
        $this->createPaymentMethod(1864086284016, 'Klarna Slice It');
    }

    private function createPaymentMethod($id, $name)
    {
        try {
            $this->paymentMethodRepositoryContract->createPaymentMethod([
                'pluginKey' => 'vRPayment',
                'paymentKey' => (string) $id,
                'name' => $name
            ]);
            $this->getLogger(__METHOD__)->error('Payment migration successul for payment method ' . $name);
        } catch (\Exception $e) {
            $this->getLogger(__METHOD__)->error('Payment migration failed for payment method ' . $name . ': ' . $e->getMessage());
        }
    }
}