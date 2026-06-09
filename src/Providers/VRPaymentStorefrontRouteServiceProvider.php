<?php

namespace VRPayment\Providers;

use Plenty\Plugin\RouteServiceProvider;
use Plenty\Modules\Webshop\REST\Routing\Router;

class VRPaymentStorefrontRouteServiceProvider extends RouteServiceProvider
{
    /**
     * @param Router $router
     * @return void
     */
    public function map(Router $router)
    {
        $router->post('rest/storefront/vrpayment/prepare', 'VRPayment\Controllers\PaymentProcessController@preparePayment');
        $router->get('rest/storefront/vrpayment/check-redirect', 'VRPayment\Controllers\PaymentProcessController@checkPendingRedirect');
        $router->post('rest/storefront/vrpayment/register-return', 'VRPayment\Controllers\PaymentProcessController@registerReturnContext');
        $router->post('rest/storefront/vrpayment/restore-cart', 'VRPayment\Controllers\PaymentProcessController@restoreCart');
    }
}