<?php
use VRPayment\Sdk\Service\TransactionService;

require_once __DIR__ . '/VRPaymentSdkHelper.php';

$client = VRPaymentSdkHelper::getApiClient(SdkRestApi::getParam('gatewayBasePath'), SdkRestApi::getParam('apiUserId'), SdkRestApi::getParam('apiUserKey'));

$spaceId = SdkRestApi::getParam('spaceId');

$service = new TransactionService($client);
$invoiceDocument = $service->getPackingSlip($spaceId, SdkRestApi::getParam('id'));

return VRPaymentSdkHelper::convertData($invoiceDocument);