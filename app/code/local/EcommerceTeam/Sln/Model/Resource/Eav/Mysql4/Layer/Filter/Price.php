<?php
/**
 * "Seo Layered Navigation" extension for "Magento Commerce" by "EcommerceTeam (www.ecommerce-team.com)"
 *
 * @category     Extension
 * @copyright    Copyright (c) 2014 EcommerceTeam (http://www.ecommerce-team.com)
 * @author       EcommerceTeam
 * @version      4.0.0
 */


class EcommerceTeam_Sln_Model_Resource_Eav_Mysql4_Layer_Filter_Price
    extends Mage_Core_Model_Mysql4_Abstract
{

    /**
     * Initialize connection and define main table name
     *
     */
    protected function _construct()
    {
        $this->_init('catalog/product_index_price', 'entity_id');
    }

    /**
     * Retrieve joined price index table alias
     *
     * @return string
     */
    protected function _getIndexTableAlias()
    {
        return 'price_index';
    }

    /**
     * Prepare response object and dispatch prepare price event
     *
     * Return response object
     *
     * @param Mage_Catalog_Model_Layer_Filter_Price $filter
     * @param Varien_Db_Select $select
     * @return Varien_Object
     */
    protected function _dispatchPreparePriceEvent($filter, $select)
    {
        // prepare response object for event
        $response = new Varien_Object();
        $response->setAdditionalCalculations(array());

        // prepare event arguments
        $eventArgs = array(
            'select'          => $select,
            'table'           => $this->_getIndexTableAlias(),
            'store_id'        => $filter->getStoreId(),
            'response_object' => $response
        );

        /**
         * @deprecated since 1.3.2.2
         */
        Mage::dispatchEvent('catalogindex_prepare_price_select', $eventArgs);

        /**
         * @since 1.4
         */
        Mage::dispatchEvent('catalog_prepare_price_select', $eventArgs);

        return $response;
    }

    /**
     * Retrieve minimal price for attribute
     *
     * @param Mage_Catalog_Model_Layer_Filter_Price $filter
     * @return float
     */
    public function getMinPrice($filter)
    {
        $select     = $this->_getSelect($filter);
        $connection = $this->_getReadAdapter();
        $response   = $this->_dispatchPreparePriceEvent($filter, $select);

        $table = $this->_getIndexTableAlias();

        $additional     = join('', $response->getAdditionalCalculations());
        $maxPriceExpr   = new Zend_Db_Expr("MIN({$table}.min_price {$additional})");

        $select->columns(array($maxPriceExpr));

        return $connection->fetchOne($select) * $filter->getCurrencyRate();
    }

    /**
     * Retrieve maximal price for attribute
     *
     * @param Mage_Catalog_Model_Layer_Filter_Price $filter
     * @return float
     */
    public function getMaxPrice($filter)
    {
        $select     = $this->_getSelect($filter);
        $connection = $this->_getReadAdapter();
        $response   = $this->_dispatchPreparePriceEvent($filter, $select);

        $table = $this->_getIndexTableAlias();

        $additional     = join('', $response->getAdditionalCalculations());
        $maxPriceExpr   = new Zend_Db_Expr("MAX({$table}.min_price {$additional})");

        $select->columns(array($maxPriceExpr));

        return $connection->fetchOne($select) * $filter->getCurrencyRate();
    }

    protected function _getSelect($filter)
    {
        $select = $filter->getLayer()->getSelectWithoutFilter('price');
        if (!$select) {
            $collection = $filter->getLayer()->getProductCollection();
            $collection->addPriceData($filter->getCustomerGroupId(), $filter->getWebsiteId());
            $select = clone $collection->getSelect();
        }

        // reset columns, order and limitation conditions
        $select->reset(Zend_Db_Select::COLUMNS);
        $select->reset(Zend_Db_Select::ORDER);
        $select->reset(Zend_Db_Select::LIMIT_COUNT);
        $select->reset(Zend_Db_Select::LIMIT_OFFSET);
        $select->reset(Zend_Db_Select::GROUP);

        return $select;
    }

    /**
     * Retrieve array with products counts per price range
     *
     * @param Mage_Catalog_Model_Layer_Filter_Price $filter
     * @param int $range
     * @return array
     */
    public function getCount($filter, $range)
    {
        $select     = $this->_getSelect($filter);
        $connection = $this->_getReadAdapter();
        $response   = $this->_dispatchPreparePriceEvent($filter, $select);

        $table = $this->_getIndexTableAlias();

        $additional = join('', $response->getAdditionalCalculations());
        $rate       = $filter->getCurrencyRate();
        $countExpr  = new Zend_Db_Expr("COUNT(DISTINCT e.entity_id)");
        $rangeExpr  = new Zend_Db_Expr("FLOOR((({$table}.min_price {$additional}) * {$rate}) / {$range}) + 1");

        $select->columns(array(
            'range' => $rangeExpr,
            'count' => $countExpr
        ));
        $select->where("{$table}.min_price > 0");
        $select->group('range');

        return $connection->fetchPairs($select);
    }

    /**
     * @param Mage_Catalog_Model_Layer_Filter_Abstract $filter
     * @param array $value
     * @param Zend_Db_Select $select
     * @param bool $rangeMode
     * @return $this
     */
    public function prepareSelect(
        Mage_Catalog_Model_Layer_Filter_Abstract $filter, array $value, Zend_Db_Select $select, $rangeMode = false)
    {
        $response   = $this->_dispatchPreparePriceEvent($filter, $select);
        $table      = $this->_getIndexTableAlias();
        $additional = join('', $response->getAdditionalCalculations());
        $rate       = $filter->getCurrencyRate();
        $priceExpr  = new Zend_Db_Expr("(({$table}.min_price {$additional}) * {$rate})");
        $where = array();

        if ($rangeMode) {
            $start = isset($value[0]) ? (int) $value[0] : null;
            $end   = isset($value[1]) ? (int) $value[1] : null;

            if ($start) {
                $select->where("{$priceExpr} >= {$start}");
            }
            if ($end) {
                $select->where("{$priceExpr} <= {$end}");
            }
        } else {
            foreach((array)$value as $_value){
                $where[] = sprintf($priceExpr . ' >= %s', ($_value['range'] * ($_value['index'] - 1))) . ' AND ' . sprintf($priceExpr . ' < %d', ($_value['range'] * $_value['index']));
            }
            $select->where(implode(' OR ', $where));
        }

        return $this;
    }

    /**
     * Apply attribute filter to product collection
     *
     * @param Mage_Catalog_Model_Layer_Filter_Price $filter
     * @param array $value
     * @param bool $rangeMode
     * @return EcommerceTeam_Sln_Model_Resource_Eav_Mysql4_Layer_Filter_Price
     */
    public function applyFilterToCollection($filter, $value, $rangeMode = false)
    {
        $this->prepareSelect($filter, $value, $filter->getLayer()->getProductCollection()->getSelect(), $rangeMode);
        $attributeCode = 'price';
        $baseSelect = $filter->getLayer()->getSelectWithoutFilter();
        foreach ($baseSelect as $code => $select) {
            if ($attributeCode != $code) {
                $this->prepareSelect($filter, $value, $select, $rangeMode);
            }
        }
        return $this;
    }
}
