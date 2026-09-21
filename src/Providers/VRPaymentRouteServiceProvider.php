<?php

declare(strict_types=1);

namespace VRPayment\Providers;

use Plenty\Plugin\RouteServiceProvider;
use Plenty\Plugin\Routing\Router;

/**
 * Class VRPaymentRouteServiceProvider
 *
 * Registers the routing rules for the VRPayment plugin, including webhook listeners
 * and storefront/checkout-related routes.
 */
class VRPaymentRouteServiceProvider extends RouteServiceProvider
{
    /**
     * Map the routes.
     *
     * @param Router $router The router instance.
     * @return void
     */
    public function map(Router $router): void
    {
        // Define local variables for repeated route prefixes, controller namespaces,
        // and webhook endpoints to simplify maintenance and avoid redundancy.
        $defaultPrefix = 'vrpayment/';
        $notificationController = 'VRPayment\Controllers\PaymentNotificationController@';
        $processController = 'VRPayment\Controllers\PaymentProcessController@';
        $storefrontPrefix = 'rest/storefront/vrpayment/';
        $transactionController = 'VRPayment\Controllers\PaymentTransactionController@';
        $webhookEndpoints = [
            'update-transaction',
        ];
        $webhookPrefix = 'rest/v1/vrpayment/';

        // Register webhook endpoints dynamically to make it easier to add new listeners.
        // We define both slash and non-slash patterns to prevent router mismatches
        // caused by Plentymarkets trailing slash configurations.
        foreach ($webhookEndpoints as $endpoint) {
            $router->post(
                $webhookPrefix . $endpoint,
                $notificationController . 'updateTransaction',
            );
            $router->post(
                $webhookPrefix . $endpoint . '/',
                $notificationController . 'updateTransaction',
            );
        }

        $router->get($defaultPrefix . 'fail-transaction/{id}', $processController . 'failTransaction')->where('id', '\d+');
        $router->post($defaultPrefix . 'pay-order', $processController . 'payOrder');
        // Documents are addressed by order id and order access key, never by the sequential
        // transaction id, so they cannot be enumerated by unauthorized visitors.
        // Both slash and non-slash patterns are registered for the same reason as the webhooks.
        $documentEndpoints = [
            'download-invoice' => 'downloadInvoice',
            'download-packing-slip' => 'downloadPackingSlip',
        ];

        foreach ($documentEndpoints as $endpoint => $action) {
            $router->get($defaultPrefix . $endpoint . '/{orderId}/{accessKey}', $transactionController . $action)
                ->where('orderId', '\d+')
                ->where('accessKey', '[A-Za-z0-9\-_]+');
            $router->get($defaultPrefix . $endpoint . '/{orderId}/{accessKey}/', $transactionController . $action)
                ->where('orderId', '\d+')
                ->where('accessKey', '[A-Za-z0-9\-_]+');
        }
        $router->get($defaultPrefix . 'redirect-check', $processController . 'redirectCheck');
        $router->get($defaultPrefix . 'return-failed/{id}', $processController . 'returnFailed')->where('id', '\d+');
        $router->post($storefrontPrefix . 'register-return', $processController . 'registerReturnContext');
        $router->post($storefrontPrefix . 'restore-cart', $processController . 'restoreCart');
        $router->get($storefrontPrefix . 'order-checkout-data', $processController . 'getOrderCheckoutData');
        $router->post($storefrontPrefix . 'pay-order', $processController . 'payOrderRest');
        $router->get($storefrontPrefix . 'transaction-failure/{id}', $processController . 'getTransactionFailure')->where('id', '\d+');
    }
}