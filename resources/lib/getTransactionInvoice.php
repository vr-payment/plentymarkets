<?php
use VRPayment\Sdk\Service\TransactionInvoiceService;

require_once __DIR__ . '/VRPaymentSdkHelper.php';

$client = VRPaymentSdkHelper::getApiClient(SdkRestApi::getParam('gatewayBasePath'), SdkRestApi::getParam('apiUserId'), SdkRestApi::getParam('apiUserKey'));

$spaceId = SdkRestApi::getParam('spaceId');

$service = new TransactionInvoiceService($client);
$transactionInvoice = $service->read($spaceId, SdkRestApi::getParam('id'));

return VRPaymentSdkHelper::convertData($transactionInvoice);