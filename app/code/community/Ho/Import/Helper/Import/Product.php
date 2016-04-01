<?php
/**
 * Ho_Import
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * @category    Ho
 * @package     Ho_Import
 * @copyright   Copyright © 2012 H&O (http://www.h-o.nl/)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @author      Paul Hachmang – H&O <info@h-o.nl>
 *
 *
 */
class Ho_Import_Helper_Import_Product extends Ho_Import_Helper_Import
{
    /**
     * Generate an URL-key from multiple fields
     *
     * @param        $line
     * @param        $fields
     * @param string $glue
     * @param string $suffix
     *
     * @return string
     */
    public function getUrlKey($line, $fields, $glue = '-', $suffix = '')
    {
        $string = Mage::helper('ho_import/import')->getFieldCombine($line, $fields, $glue, $suffix);
        return Mage::getSingleton('catalog/category')->formatUrlKey($string).$suffix;
    }


    /**
     * @param $line
     * @param $price
     * @param $specialPrice
     *
     * @return bool|null|string
     */
    public function getSpecialFromDate($line, $price, $specialPrice)
    {
        $price = (float) $this->_getMapper()->mapItem($price);
        $specialPrice = (float) $this->_getMapper()->mapItem($specialPrice);
        if (! $specialPrice) {
            return null;
        }

        return $specialPrice < $price ? $this->getCurrentDate($line) : null;
    }


    public function getSpecialPrice($line, $price, $specialPrice)
    {
        $price = (float) $this->_getMapper()->mapItem($price);
        $specialPrice = (float) $this->_getMapper()->mapItem($specialPrice);
        if (! $specialPrice) {
            return null;
        }

        return $specialPrice < $price ? $specialPrice : null;
    }

    /**
     * Get the product URL-key and check for availability
     *
     * @param $line
     * @param $sku
     * @param $string
     * @return mixed
     */
    public function getAvailableUrlKey()
    {
        $args = func_get_args();
        $line = array_shift($args);
        $sku = trim($this->_getMapper()->mapItem(array_shift($args)));
        $string = trim($this->_getMapper()->mapItem(array_shift($args)));
        $fallbackFields = array_slice(func_get_args(), 3);

        $options = [Mage::getSingleton('catalog/product')->formatUrlKey($string)];
        foreach($fallbackFields as $field) {
            $options[] = Mage::getSingleton('catalog/product')
                ->formatUrlKey(trim($string .' '. trim($this->_getMapper()->mapItem($field))));
        }
        $options = array_unique($options);

        foreach($options as $urlKeyOption) {
            if ($this->_isAvailableUrl($sku, $urlKeyOption)) {
                return $urlKeyOption;
            } else {
                Mage::helper('ho_import/log')->log(
                    $this->__("url_key '%s' (sku: %s) already used, trying next option", $urlKeyOption, $sku),
                    Zend_Log::NOTICE
                );
            }
        }
        Mage::throwException($this->__('Could not generate URL key for %s, options: %s', $line['artikelnummer'], implode(', ', $options)));
    }

    /**
     * Check unique url_key value in catalog_category_entity_url_key table.
     *
     * @param string
     * @param string
     * @return bool
     */
    protected $_urls = [];
    protected function _isAvailableUrl($sku, $urlKey)
    {
        if (isset($this->_urls[$urlKey])) {
            return false;
        }

        /** @var Magento_Db_Adapter_Pdo_Mysql $connection */
        $connection = Mage::getSingleton('core/resource')->getConnection('core_read');

        $tableExists = $connection->isTableExists($connection->getTableName('catalog_product_entity_url_key'));
        if ($tableExists) { //after 1.9
            $select = $connection->select()
                ->from(['url_key' => $connection->getTableName('catalog_product_entity_url_key')], array('entity_id', 'store_id'))
                ->joinLeft(['e' => $connection->getTableName('catalog_product_entity')], 'url_key.entity_id = e.entity_id', ['sku'])
                ->where('url_path = ?', $urlKey)
                ->where('sku != ?', $sku)
                ->limit(1);
        } else {
            $select = $connection->select()
                ->from(['url_key' => $connection->getTableName('catalog_product_entity_varchar')], array('entity_id', 'store_id'))
                ->joinLeft(['e' => $connection->getTableName('catalog_product_entity')], 'url_key.entity_id = e.entity_id', ['sku'])
                ->where('value = ?', $urlKey)
                ->where('sku != ?', $sku)
                ->where('attribute_id = ?', Mage::getSingleton('catalog/product')->getResource()->getAttribute('url_key')->getId())
                ->limit(1);
        }

        $row = $connection->fetchRow($select);
        // we should allow save same url key for product in current store view
        // but not allow save existing url key in current store view from another store view
        if (empty($row)) {
            $this->_urls[$urlKey] = true;
        }
        return empty($row);
    }
}
