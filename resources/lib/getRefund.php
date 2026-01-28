<?php
use VRPayment\Sdk\Service\RefundService;

require_once __DIR__ . '/VRPaymentSdkHelper.php';

$client = VRPaymentSdkHelper::getApiClient(SdkRestApi::getParam('gatewayBasePath'), SdkRestApi::getParam('apiUserId'), SdkRestApi::getParam('apiUserKey'));

$spaceId = SdkRestApi::getParam('spaceId');

$service = new RefundService($client);
$refund = $service->read($spaceId, SdkRestApi::getParam('id'));

return VRPaymentSdkHelper::convertData($refund);