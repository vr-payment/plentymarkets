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
        $result = [];

        $result['Bank Transfer'] = $this->createPaymentMethod(1457546097602, 'Bank Transfer');
        $result['Credit / Debit Card'] = $this->createPaymentMethod(1457546097597, 'Credit / Debit Card');
        $result['Direct Debit (SEPA)'] = $this->createPaymentMethod(1457546097601, 'Direct Debit (SEPA)');
        $result['EPS'] = $this->createPaymentMethod(1457546097609, 'EPS');
        $result['iDeal'] = $this->createPaymentMethod(1461674005576, 'iDeal');
        $result['Invoice'] = $this->createPaymentMethod(1457546097598, 'Invoice');
        $result['Online Banking'] = $this->createPaymentMethod(1460954915005, 'Online Banking');
        $result['PayPal'] = $this->createPaymentMethod(1457546097613, 'PayPal');
        $result['Post Finance Pay'] = $this->createPaymentMethod(1689233132073, 'Post Finance Pay');
        $result['Przelewy24'] = $this->createPaymentMethod(1457546097617, 'Przelewy24');
        $result['Wero'] = $this->createPaymentMethod(1754840745162, 'Wero');
        $result['Klarna Pay Now'] = $this->createPaymentMethod(1864086284039, 'Klarna Pay Now');
        $result['Klarna Pay Later'] = $this->createPaymentMethod(1864086284619, 'Klarna Pay Later');
        $result['Klarna Slice It'] = $this->createPaymentMethod(1864086284016, 'Klarna Slice It');

        // The deployment itself reports nothing about the payment methods, so this single summary
        // is what tells us afterwards whether they were registered, skipped or rejected.
        $this->getLogger(__METHOD__)->info('VRPayment::PaymentMethodMigrationFinished', $result);
    }

    /**
     * Registers a single payment method and reports what happened with it.
     *
     * A store that cannot take another payment method makes createPaymentMethod() throw. Letting
     * that through would skip every payment method after it, so each one is registered on its own.
     *
     * @param number $id
     * @param string $name
     * @return string
     */
    private function createPaymentMethod($id, $name): string
    {
        try {
            if ($this->paymentHelper->getPaymentMopId($id) != 'no_paymentmethod_found') {
                return 'already exists';
            }

            $this->paymentMethodRepositoryContract->createPaymentMethod([
                'pluginKey' => 'vRPayment',
                'paymentKey' => (string) $id,
                'name' => $name
            ]);

            return 'created';
        } catch (\Exception $e) {
            $this->getLogger(__METHOD__)->error('VRPayment::PaymentMethodCreationFailed', [
                'paymentKey' => $id,
                'name' => $name,
                'message' => $e->getMessage(),
            ]);

            return 'failed';
        }
    }
}
