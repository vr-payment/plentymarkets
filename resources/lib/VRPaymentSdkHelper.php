<?php
use VRPayment\Sdk\ApiClient;

class VRPaymentSdkHelper
{

    /**
     *
     * @param string $gatewayBasePath
     * @param string $userId
     * @param string $userKey
     * @return \VRPayment\Sdk\ApiClient
     */
    public static function getApiClient($gatewayBasePath, $userId, $userKey): ApiClient
    {
        $client = new ApiClient($userId, $userKey);
        $client->setBasePath($gatewayBasePath . '/api');
        $client->addDefaultHeader('x-shop-system', 'plentymarkets');
        return $client;
    }

    /**
     *
     * @param float $amount
     * @param number $currencyDecimalPlaces
     * @return float
     */
    public static function roundAmount($amount, $currencyDecimalPlaces = 2)
    {
        return round($amount, $currencyDecimalPlaces);
    }

    /**
     *
     * @param \VRPayment\Sdk\Model\LineItem[] $lineItems
     * @return float
     */
    public static function calculateLineItemTotalAmount(array $lineItems)
    {
        $total = 0;
        foreach ($lineItems as $lineItem) {
            $total += $lineItem->getAmountIncludingTax();
        }
        return $total;
    }

    /**
     * Returns the amount of the line item's reductions.
     *
     * @param \VRPayment\Sdk\Model\LineItem[] $lineItems
     * @param \VRPayment\Sdk\Model\LineItemReduction[] $reductions
     * @param int $currencyDecimalPlaces
     * @return float
     */
    public static function getReductionAmount(array $lineItems, array $reductions, $currencyDecimalPlaces = 2)
    {
        $lineItemMap = array();
        foreach ($lineItems as $lineItem) {
            $lineItemMap[$lineItem->getUniqueId()] = $lineItem;
        }

        $amount = 0;
        foreach ($reductions as $reduction) {
            $lineItem = $lineItemMap[$reduction->getLineItemUniqueId()];
            $unitPrice = $lineItem->getAmountIncludingTax() / $lineItem->getQuantity();
            $amount += $unitPrice * $reduction->getQuantityReduction();
            $amount += $reduction->getUnitPriceReduction() * ($lineItem->getQuantity() - $reduction->getQuantityReduction());
        }

        return self::roundAmount($amount, $currencyDecimalPlaces);
    }

    /**
     * Convert data to string|array.
     *
     * @param mixed $data
     *            the data to string|array
     * @return string|array
     */
    public static function convertData($data)
    {
        return \VRPayment\Sdk\ObjectSerializer::sanitizeForSerialization($data);
    }

    /**
     * Creates and returns a new entity filter.
     *
     * @param string $fieldName
     * @param mixed $value
     * @param string $operator
     * @return \VRPayment\Sdk\Model\EntityQueryFilter
     */
    public static function createEntityFilter($fieldName, $value, $operator = \VRPayment\Sdk\Model\CriteriaOperator::EQUALS)
    {
        $filter = new \VRPayment\Sdk\Model\EntityQueryFilter();
        $filter->setType(\VRPayment\Sdk\Model\EntityQueryFilterType::LEAF);
        $filter->setOperator($operator);
        $filter->setFieldName($fieldName);
        $filter->setValue($value);
        return $filter;
    }

    /**
     * Creates and returns a new entity order by.
     *
     * @param string $fieldName
     * @param mixed $sortOrder
     * @return \VRPayment\Sdk\Model\EntityQueryOrderBy
     */
    public static function createEntityOrderBy($fieldName, $sortOrder = \VRPayment\Sdk\Model\EntityQueryOrderByType::DESC)
    {
        $orderBy = new \VRPayment\Sdk\Model\EntityQueryOrderBy();
        $orderBy->setFieldName($fieldName);
        $orderBy->setSorting($sortOrder);
        return $orderBy;
    }

    /**
     * @param $id
     * @param $array
     * @param $key
     * @return string
     */
    public static function checkForDuplicatePrefix($id, $array, $key) {
      if(!in_array($id, $array)) {
        return '';
      }

      return '_duplicate_' . $key;
    }
}