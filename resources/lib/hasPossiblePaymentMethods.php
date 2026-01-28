<?php
use VRPayment\Sdk\Service\TransactionService;

require_once __DIR__ . '/VRPaymentSdkHelper.php';

$client = VRPaymentSdkHelper::getApiClient(SdkRestApi::getParam('gatewayBasePath'), SdkRestApi::getParam('apiUserId'), SdkRestApi::getParam('apiUserKey'));

$spaceId = SdkRestApi::getParam('spaceId');
$transactionId = SdkRestApi::getParam('transactionId');

$service = new TransactionService($client);
$possiblePaymentMethods = $service->fetchPaymentMethods($spaceId, $transactionId, 'iframe');
if ($possiblePaymentMethods != null && ! empty($possiblePaymentMethods)) {
    return true;
} else {
    return false;
}