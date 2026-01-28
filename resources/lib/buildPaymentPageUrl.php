<?php
use VRPayment\Sdk\Service\TransactionPaymentPageService;

require_once __DIR__ . '/VRPaymentSdkHelper.php';

$spaceId = SdkRestApi::getParam('spaceId');
$id = SdkRestApi::getParam('id');

$client = VRPaymentSdkHelper::getApiClient(SdkRestApi::getParam('gatewayBasePath'), SdkRestApi::getParam('apiUserId'), SdkRestApi::getParam('apiUserKey'));
$service = new TransactionPaymentPageService($client);
return $service->paymentPageUrl($spaceId, $id);