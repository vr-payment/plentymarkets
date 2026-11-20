<?php
namespace VRPayment\Migrations;

use Plenty\Modules\Payment\Method\Contracts\PaymentMethodRepositoryContract;
use Plenty\Plugin\Log\Loggable;
use VRPayment\Helper\PaymentHelper;

/**
 * Registers the Wero and Klarna payment methods.
 *
 * These were added to CreatePaymentMethods after that migration had already run on existing
 * shops. plentymarkets executes each runOnBuild migration class at most once ever, so appending
 * new createPaymentMethod() calls to an already-executed class has no effect - hence a separate
 * migration class, which plentymarkets has never seen and will therefore run on the next deploy.
 */
class CreateWeroAndKlarnaPaymentMethodsNew
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
     * Creates the Wero and Klarna payment methods for the VR Payment plugin.
     */
    public function run()
    {
        $result = [];

        $result['Wero'] = $this->createPaymentMethod(1754840745162, 'Wero');
        $result['Klarna Pay Now'] = $this->createPaymentMethod(1864086284039, 'Klarna Pay Now');
        $result['Klarna Pay Later'] = $this->createPaymentMethod(1864086284619, 'Klarna Pay Later');
        $result['Klarna Slice It'] = $this->createPaymentMethod(1864086284016, 'Klarna Slice It');

        $this->getLogger(__METHOD__)->info('VRPayment::WeroAndKlarnaPaymentMethodMigrationFinished', $result);
    }

    /**
     * Registers a single payment method and reports what happened with it.
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
