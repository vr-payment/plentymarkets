<?php
namespace VRPayment\Providers;

use Plenty\Plugin\Events\Dispatcher;
use Plenty\Plugin\ServiceProvider;
use Plenty\Modules\Basket\Events\Basket\AfterBasketCreate;
use Plenty\Modules\Basket\Events\Basket\AfterBasketChanged;
use Plenty\Modules\Basket\Contracts\BasketRepositoryContract;
use Plenty\Modules\Order\Contracts\OrderRepositoryContract;
use Plenty\Modules\Payment\Events\Checkout\GetPaymentMethodContent;
use Plenty\Modules\Payment\Method\Contracts\PaymentMethodContainer;
use Plenty\Modules\Payment\Events\Checkout\ExecutePayment;
use Plenty\Modules\Payment\Method\Contracts\PaymentMethodRepositoryContract;
use Plenty\Modules\EventProcedures\Services\EventProceduresService;
use Plenty\Modules\EventProcedures\Services\Entries\ProcedureEntry;
use Plenty\Modules\Cron\Services\CronContainer;
use VRPayment\Contracts\WebhookRepositoryContract;
use VRPayment\Helper\PaymentHelper;
use VRPayment\Helper\VRPaymentServiceProviderHelper;
use VRPayment\Methods\BankTransferPaymentMethod;
use VRPayment\Methods\CreditDebitCardPaymentMethod;
use VRPayment\Methods\DirectDebitSepaPaymentMethod;
use VRPayment\Methods\EpsPaymentMethod;
use VRPayment\Methods\IDealPaymentMethod;
use VRPayment\Methods\InvoicePaymentMethod;
use VRPayment\Methods\OnlineBankingPaymentMethod;
use VRPayment\Methods\PayPalPaymentMethod;
use VRPayment\Methods\PostFinancePayPaymentMethod;
use VRPayment\Methods\Przelewy24PaymentMethod;
use VRPayment\Methods\WeroPaymentMethod;
use VRPayment\Methods\KlarnaPayNowPaymentMethod;
use VRPayment\Methods\KlarnaPayLaterPaymentMethod;
use VRPayment\Methods\KlarnaSliceItPaymentMethod;
use VRPayment\Procedures\RefundEventProcedure;
use VRPayment\Repositories\WebhookRepository;
use VRPayment\Services\PaymentService;
use VRPayment\Services\WebhookCronHandler;
use IO\Services\BasketService;

class VRPaymentServiceProvider extends ServiceProvider
{
    use \Plenty\Plugin\Log\Loggable;

    public function register()
    {
        $this->getApplication()->register(VRPaymentRouteServiceProvider::class);
        $this->getApplication()->bind(WebhookRepositoryContract::class, WebhookRepository::class);
        $this->getApplication()->bind(RefundEventProcedure::class);
    }

    /**
     * Boot services of the VR Payment plugin.
     *
     * @param PaymentMethodContainer $payContainer
     */
    public function boot(
        PaymentMethodContainer $payContainer,
        EventProceduresService $eventProceduresService,
        CronContainer $cronContainer,
        VRPaymentServiceProviderHelper $vRPaymentServiceProviderHelper,
        PaymentService $paymentService
    ) {
        $this->registerPaymentMethod($payContainer, 1457546097602, BankTransferPaymentMethod::class);
        $this->registerPaymentMethod($payContainer, 1457546097597, CreditDebitCardPaymentMethod::class);
        $this->registerPaymentMethod($payContainer, 1457546097601, DirectDebitSepaPaymentMethod::class);
        $this->registerPaymentMethod($payContainer, 1457546097609, EpsPaymentMethod::class);
        $this->registerPaymentMethod($payContainer, 1461674005576, IDealPaymentMethod::class);
        $this->registerPaymentMethod($payContainer, 1457546097598, InvoicePaymentMethod::class);
        $this->registerPaymentMethod($payContainer, 1460954915005, OnlineBankingPaymentMethod::class);
        $this->registerPaymentMethod($payContainer, 1457546097613, PayPalPaymentMethod::class);
        $this->registerPaymentMethod($payContainer, 1689233132073, PostFinancePayPaymentMethod::class);
        $this->registerPaymentMethod($payContainer, 1457546097617, Przelewy24PaymentMethod::class);
        $this->registerPaymentMethod($payContainer, 1754840745162, WeroPaymentMethod::class);
        $this->registerPaymentMethod($payContainer, 1864086284039, KlarnaPayNowPaymentMethod::class);
        $this->registerPaymentMethod($payContainer, 1864086284619, KlarnaPayLaterPaymentMethod::class);
        $this->registerPaymentMethod($payContainer, 1864086284016, KlarnaSliceItPaymentMethod::class);

        // Register Refund Event Procedure
        $eventProceduresService->registerProcedure('plentyVRPayment', ProcedureEntry::PROCEDURE_GROUP_ORDER, [
            'de' => 'Rückzahlung der VR Payment-Zahlung',
            'en' => 'Refund the VR Payment payment'
        ], 'VRPayment\Procedures\RefundEventProcedure@run');

        // Register payment event listeners for PWA
        $vRPaymentServiceProviderHelper->addGetPaymentMethodContentEventListener();
        $vRPaymentServiceProviderHelper->addExecutePaymentContentEventListener();

        $cronContainer->add(CronContainer::EVERY_FIFTEEN_MINUTES, WebhookCronHandler::class);
    }

    private function registerPaymentMethod($payContainer, $id, $class)
    {
        $payContainer->register('vRPayment::' . $id, $class, [
            AfterBasketChanged::class,
            AfterBasketCreate::class
        ]);
    }
}
