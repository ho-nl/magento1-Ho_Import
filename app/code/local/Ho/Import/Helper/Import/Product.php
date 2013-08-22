<?php
/**
 * Ho_Import
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the H&O Commercial License
 * that is bundled with this package in the file LICENSE_HO.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.h-o.nl/license
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to info@h-o.com so we can send you a copy immediately.
 *
 * @category    Ho
 * @package     Ho_Import
 * @copyright   Copyright © 2012 H&O (http://www.h-o.nl/)
 * @license     H&O Commercial License (http://www.h-o.nl/license)
 * @author      Paul Hachmang – H&O <info@h-o.nl>
 *
 *
 */
class Ho_Import_Helper_Import_Product extends Mage_Core_Helper_Abstract
{
    protected $_today = null;

    /** @var null|array */
    protected $_websiteIds = null;

    /** @var null */
    protected $_fileUploader = null;

    /**
     * Import the product to all websites, this will return all the websites.
     *
     * @param $line
     * @return array|null
     */
    public function getAllWebsites($line) {
        if ($this->_websiteIds === null) {
            $this->_websiteIds = array();
            foreach (Mage::app()->getWebsites() as $website) {
                /** @var $website Mage_Core_Model_Website */

                $this->_websiteIds[] = $website->getCode();
            }
        }
        return $this->_websiteIds;
    }


    /**
     * Get a simple HTML comment (can't be added through XML due to XML limitations).
     *
     * @param        $line
     * @param string $comment
     *
     * @return string
     */
    public function getHtmlComment($line, $comment = '') {
        return '<!--'.$comment.'-->';
    }


    /**
     * Get the value of a field but fallback to a default if the value isn't present.
     *
     * @param $line
     * @param $field
     * @param $default
     *
     * @return mixed
     */
    public function getFieldDefault($line, $field, $default) {
        if (isset($line[$field]) && ! empty($line[$field])) {
            return $line[$field];
        }
        return $default;
    }


    /**
     * Combine multiple fields into one string
     *
     * @param        $line
     * @param        $fields
     * @param string $glue
     *
     * @return string
     */
    public function getFieldCombine($line, $fields, $glue = ' ') {
        return implode($glue, $this->getFieldMultiple($line, $fields));
    }


    /**
     * Checks if a field has a value
     *
     * @param $line
     * @param $field
     *
     * @return string
     */
    public function getFieldBoolean($line, $field) {
        return isset($line[$field]) ? '1' : '0';
    }


    /**
     * Get multiple rows in one field.
     *
     * @param $line
     * @param $fields
     *
     * @return array
     */
    public function getFieldMultiple($line, $fields) {
        $parts = array();
        foreach (array_keys($fields) as $field) {
            if ($line[$field]) {
                $parts[] = $line[$field];
            }
        }
        return $parts;
    }


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
        $string = $this->getFieldCombine($line, $fields, $glue, $suffix);
        return Mage::getSingleton('catalog/category')->formatUrlKey($string).$suffix;
    }


    /**
     * Download given file to ImportExport Tmp Dir (usually media/import)
     * @param string $url
     */
    protected $_fileCache = array();
    protected function _copyExternalImageFile($url)
    {
        if (isset($this->_fileCache[$url])) {
//            Mage::helper('ho_import/log')->log($this->__("Image already processed"), Zend_Log::DEBUG);
            return;
        }
        Mage::helper('ho_import/log')->log($this->__("Downloading image %s", $url));

        try {
            $this->_fileCache[$url] = true;
            $dir = $this->_getUploader()->getTmpDir();
            if (!is_dir($dir)) {
                mkdir($dir);
            }
            $fileName = $dir . DS . basename($url);
            $fileHandle = fopen($fileName, 'w+');
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_TIMEOUT, 50);
            curl_setopt($ch, CURLOPT_FILE, $fileHandle);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_exec($ch);

            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            fclose($fileHandle);

            if ($code !== 200) {
                $this->_fileCache[$url] = $code;
                Mage::helper('ho_import/log')->log($this->__("Returned status code %s while downloading image", $code), Zend_Log::ERR);
                unlink($fileName);
            }

        } catch (Exception $e) {
            Mage::throwException('Download of file ' . $url . ' failed: ' . $e->getMessage());
        }
    }


    /**
     * Returns an object for upload a media files
     */
    protected function _getUploader()
    {
        if (is_null($this->_fileUploader)) {
            $this->_fileUploader    = new Mage_ImportExport_Model_Import_Uploader();

            $this->_fileUploader->init();

            $tmpDir     = Mage::getConfig()->getOptions()->getMediaDir() . '/import';
            $destDir    = Mage::getConfig()->getOptions()->getMediaDir() . '/catalog/product';
            if (!is_writable($destDir)) {
                @mkdir($destDir, 0777, true);
            }
            if (!$this->_fileUploader->setTmpDir($tmpDir)) {
                Mage::throwException("File directory '{$tmpDir}' is not readable.");
            }
            if (!$this->_fileUploader->setDestDir($destDir)) {
                Mage::throwException("File directory '{$destDir}' is not writable.");
            }
        }
        return $this->_fileUploader;
    }

//    public function getExternalImage($line) {
//        try {
//            $dir = $this->_getUploader()->getTmpDir();
//            if (!is_dir($dir)) {
//                mkdir($dir);
//            }
//            $fileHandle = fopen($dir . DS . basename($url), 'w+');
//            $ch = curl_init($url);
//            curl_setopt($ch, CURLOPT_TIMEOUT, 50);
//            curl_setopt($ch, CURLOPT_FILE, $fileHandle);
//            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
//            curl_exec($ch);
//            curl_close($ch);
//            fclose($fileHandle);
//        } catch (Exception $e) {
//            Mage::throwException('Download of file ' . $url . ' failed: ' . $e->getMessage());
//        }
//    }
//
//    public function getAssociatedDefaultQty($product)
//    {
//        if ($product['type'] == 'grouped')
//        {
//            $qtys = array();
//            foreach ($this->getAssociatedSku($product) as $key => $product)
//            {
//                $qtys[] = 1;
//            }
//            return $qtys;
//        } else {
//            return '';
//        }
//    }
//
//
//    public function getAssociatedPosition($product)
//    {
//        if ($product['type'] == 'grouped')
//        {
//            $qtys = array();
//            $i = 1;
//            foreach ($this->getAssociatedSku($product) as $key => $product)
//            {
//                $qtys[] = $i++;
//            }
//            return $qtys;
//        } else {
//            return '';
//        }
//    }
//
//
//    public function getAssociatedSku($product)
//    {
//        if ($product['type'] == 'grouped')
//        {
//            $skus = explode(',',$product['associated_skus']);
//            return $skus;
//        } else {
//            return '';
//        }
//    }
//
//
//    public function getBackorders($product)
//    {
//        return $product['allow_backorders'] ? 2 : 0;
//    }
//
//
//    public function getCategory($product)
//    {
//        return explode('***', str_replace('\/','/',$product['category']));
//    }
//
//
//    public function getEntryDate($product)
//    {
//        $datetimeInGMT = new DateTime('@'.$product['entry_date'], new DateTimeZone("Europe/Amsterdam"));
//        $datetimeInGMT->setTimezone(new DateTimeZone("GMT"));
//
//        return $datetimeInGMT->format('Y-m-d H:i:s');
//    }
//
//
//    public function getImage($product)
//    {
//        if ($product['image'])
//        {
//            $image = explode('/',$product['image']);
//            return end($image);
//        }
//        return '';
//    }
//
//
//    public function getIsInStock($product)
//    {
//        return $product['qty'] ? 1 : 0;
//    }
//
//    public function getSpecialPrice($product)
//    {
//        return $product['price'] < $product['base_price'] ? $product['price'] : '';
//    }
//
//
//    public function getSpecialFromDate($product)
//    {
//        if ($this->_today === null)
//        {
//            $currentTimestamp = Mage::getSingleton('core/date')->timestamp(time());
//            $this->_today = date('d-m-Y', $currentTimestamp);
//        }
//
//        return $product['price'] < $product['base_price'] ? $this->_today : '';
//    }
//
//
//    protected static $_taxClassIds = array(
//        'high'        => 2,
//        'low'         => 5,
//        'zero'        => 8,
//        'margin_high' => 4,
//        'margin_low'  => 7,
//    );
//
//
//    public function getTaxClassId($product)
//    {
//        return isset(self::$_taxClassIds[$product['tax_class']])
//            ? self::$_taxClassIds[$product['tax_class']]
//            : '';
//    }
//
//
//    public function getUrlKey($product)
//    {
//        $urlParts = explode('-',$product['url_title']);
//        $urlParts[0] = $product['sku'];
//        return implode('-',$urlParts);
//    }
//
//
//    public function getUrlPath($product)
//    {
//        return $this->getUrlKey($product).'.html';
//    }
//
//
//    public function getVisibility($product)
//    {
//        return $product['is_child'] == '1' ? '1' : '4';
//    }
//
//
//    public function getWebsites($product)
//    {
//        return $product['show_on_fsc'] ? array('pb','fsc') : 'pb';
//    }
//
//
//    /**
//     * @param array $items
//     *
//     * @return array
//     */
//    public function processCollection(& $items)
//    {
//        $entryIds = array();
//        $products = array();
//
//        foreach($items as $item)
//        {
//            $entryIds[] = $item['entry_id'];
//            $products[$item['entry_id']] = $item;
//            $products[$item['entry_id']]['simple_products'] = array();
//        }
//
//        /* @var $itemCollection Ho_PostbeeldInventory_Model_Resource_Item_Collection */
//        $itemCollection = Mage::getModel('ho_postbeeldinventory/item')->getCollection();
//        $itemCollection->addFieldToFilter('entry_id', array('in' => $entryIds));
//        unset($entryIds);
//
//        Mage::helper('ho_import/log')->log('Loaded items, loading inventory...');
//
//        $connection = $itemCollection->getConnection();
//        $inventoryItems = $connection->fetchAll($itemCollection->getSelect());
//
//        foreach ($inventoryItems as $inventoryItem) {
//            $products[$inventoryItem['entry_id']]['simple_products'][] = $inventoryItem;
//        }
//
//        Mage::helper('ho_import/log')->log('Inventory loaded, processing rows...');
//
//        $productArray = array();
//        foreach ($products as $product)
//        {
//            foreach ($this->processProduct($product) as $productRow)
//            {
//                $productArray[] = $productRow;
//            }
//        }
//        unset($products);
//
//        return $productArray;
//    }
//
//
//    /**
//     * @param $product
//     * @return array
//     */
//    public function processProduct($product)
//    {
//        if (count($product['simple_products']) <= 0)
//        {
//           return array();
//        }
//
//        $newProducts = array();
//        $simpleData = $product;
//        unset($simpleData['simple_products']);
//
//        $groupedInventory = $product['simple_products'][0];
//        $groupedInventory['qty'] = 0;
//        $groupedInventory['notify_stock_qty'] = 0;
//        $groupedInventory['qty_sold'] = 0;
//        $groupedInventory['associated_skus'] = array();
//
//        foreach($product['simple_products'] as $simpleInventory)
//        {
//            unset($simpleInventory['col_id_11']);
//            if ($simpleInventory['allow_backorders'])
//            {
//                $groupedInventory['allow_backorders'] = '1';
//            }
//
//            $groupedInventory['qty'] += $simpleInventory['qty'];
//            $groupedInventory['qty_sold'] += $simpleInventory['qty_sold'];
//            if ($simpleInventory['notify_stock_qty'] > $groupedInventory['notify_stock_qty'])
//            {
//                $groupedInventory['notify_stock_qty'] = $simpleInventory['notify_stock_qty'];
//            }
//            $groupedInventory['associated_skus'][] = $simpleInventory['sku'];
//            $simpleInventory['type'] = 'simple';
//            $simpleInventory['is_child'] = '1';
//            $newProducts[] = array_merge($simpleData, $simpleInventory);
//        }
//
//        unset($product['simple_products']);
//
//        //we use a grouped / child relationship
//        if (count($groupedInventory['associated_skus']) >= 2)
//        {
//            $groupedInventory['base_price'] = '';
//            $groupedInventory['manage_stock'] = '1';
//            $groupedInventory['price'] = '';
//            $groupedInventory['condition'] = '';
//            $groupedInventory['buy_back_price'] = '';
//            $groupedInventory['exchange_price'] = '';
//            $groupedInventory['cost'] = '';
//            $groupedInventory['allow_backorders'] = '';
//            $groupedInventory['discount_amount'] = '';
//            $groupedInventory['discount_percentage'] = '';
//            $groupedInventory['condition_label'] = '';
//
//            unset($groupedInventory['col_id_11']);
//
//            $groupedProduct = array_merge($groupedInventory, $product);
//
//            $groupedProduct['associated_skus'] = implode(',',$groupedProduct['associated_skus']);
//            $groupedProduct['type'] = 'grouped';
//            $groupedProduct['is_child'] = '0';
//
//            $newProducts[] = $groupedProduct;
//        } else {
//            $newProducts[0]['is_child'] = '0';
//        }
//        unset($groupedProduct);
//        unset($groupedInventory);
//        unset($simpleData);
//        unset($simpleInventory);
//
//        return $newProducts;
//    }
}