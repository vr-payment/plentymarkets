<?php
namespace VRPayment\Providers;

use Plenty\Plugin\RouteServiceProvider;
use Plenty\Plugin\Routing\Router;

class VRPaymentRouteServiceProvider extends RouteServiceProvider
{

    /**
     *
     * @param Router $router
     */
    public function map(Router $router)
    {
        $router->post('vrpayment/update-transaction', 'VRPayment\Controllers\PaymentNotificationController@updateTransaction');
        $router->get('vrpayment/fail-transaction/{id}', 'VRPayment\Controllers\PaymentProcessController@failTransaction')->where('id', '\d+');
        $router->post('vrpayment/pay-order', 'VRPayment\Controllers\PaymentProcessController@payOrder');
        $router->post('rest/storefront/vrpayment/prepare', 'VRPayment\Controllers\PaymentProcessController@preparePayment');
        $router->get('vrpayment/download-invoice/{id}', 'VRPayment\Controllers\PaymentTransactionController@downloadInvoice')->where('id', '\d+');
        $router->get('vrpayment/download-packing-slip/{id}', 'VRPayment\Controllers\PaymentTransactionController@downloadPackingSlip')->where('id', '\d+');
    }
}