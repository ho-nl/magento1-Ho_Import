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
 * @copyright   Copyright © 2013 H&O (http://www.h-o.nl/)
 * @license     H&O Commercial License (http://www.h-o.nl/license)
 * @author      Paul Hachmang – H&O <info@h-o.nl>
 *
 *
 */
class Ho_Import_Model_Observer
{
    public function updateMutatedProducts($event)
    {
        $limit = $event->getLimit() ?: '10000';
        $updateDate = $event->getData('updateDate') ?: Mage::getStoreConfig(Ho_Import_Model_Product::XML_PATH_UPDATE_DATE);

        try {
            /* @var $collection Ho_Import_Model_Resource_Product_Collection */
            $collection = Mage::getModel('ho_import/product')->getCollection();
            $collection->getSelect()->limit($limit);
            $collection->addFieldToFilter('`main_table`.`edit_date`',array('gt' => $updateDate));
            $collection->getSelect()->order('main_table.edit_date',$collection::SORT_ORDER_ASC);

            Mage::helper('ho_import/log')->log('Updating Mutated Postbeeld Products, limit: '.$limit.', last update date: '.$updateDate);

            /* @var $helper Ho_Import_Helper_Import_Product */
            $helper = Mage::helper('ho_import/import_product');

            Varien_Profiler::start('process-collection');
            $connection = $collection->getConnection();
            $items = $connection->fetchAll($collection->getSelect());
            Mage::helper('ho_import/log')->log('Importing: '.count($items).' products');
            if (count($items) <= 0)
            {
                Mage::helper('ho_import/log')->done();
                return;
            }
            $lastItem = end($items);
            $lastEditDate = $lastItem['edit_date'];

            $items = $helper->processCollection($items);
            Varien_Profiler::stop('process-collection');

            /* @var $importModel Ho_Import_Model_Import */
            $importModel = Mage::getModel('ho_import/import');

            // set options of import
            $partialIndexing = (bool) $event->getPartialIndexing()
                ?: Mage::getStoreConfigFlag(Ho_Import_Model_Product::XML_PATH_PARTIAL_INDEXING);
            $dropdownAttributes = Mage::getStoreConfig(Ho_Import_Model_Product::XML_PATH_AUTO_UPDATE_ATTRIBUTES);
            $continueAfterErrors = (bool) $event->getContinueAfterErrors()
                ?: Mage::getStoreConfig(Ho_Import_Model_Product::XML_PATH_CONTIUE_AFTER_ERRORS);

            if ($continueAfterErrors) {
                $importModel->setContinueAfterErrors($continueAfterErrors);
            }
            if ($partialIndexing) {
                $importModel->setPartialIndexing($partialIndexing);
            }
            if ($dropdownAttributes) {
                $importModel->setDropdownAttributes(explode(',',$dropdownAttributes));
            }
            $importModel->setAllowRenameFiles(false);
            if ($event->getData('debugLine')){
                $importModel->setDebugLine($event->getData('debugLine'));
            }

            Varien_Profiler::start('import-data');
            $errors = $importModel->importData($items, 'products', $importModel::IMPORT_TYPE_PRODUCT);
            Varien_Profiler::stop('import-data');

            if (! $errors)
            {
                Mage::helper('ho_import/log')->log('Last edit date is: '.$lastEditDate);
                Mage::getConfig()->saveConfig(Ho_Import_Model_Product::XML_PATH_UPDATE_DATE, $lastEditDate);
            }
        } catch (Exception $e)
        {
            Mage::helper('ho_import/log')->log($e->getMessage(), Zend_Log::ERR);
        }

        Mage::helper('ho_import/log')->done();
    }


    public function updateProducts($event)
    {

    }


    public function updateCategories($event)
    {
        $limit = $event->getLimit() ?: 10000;
        $offset = $event->getOffset() ?: 0;

        try {
            /* @var $collection Ho_Import_Model_Resource_Product_Category_Collection */
            $collection = Mage::getModel('ho_import/product_category')->getCollection();
            Mage::helper('ho_import/log')->log('Getting collection: '.get_class($collection).', limit: '.$limit.', offset: '.$offset);
            $collection->updateCategoryFlat();
            $collection->getSelect()->limit($limit, $offset);

            $items = $collection->getConnection()->fetchAll($collection->getSelect());

            /* @var $importModel Ho_Import_Model_Import */
            $importModel = Mage::getModel('ho_import/import');
            $importModel->importData($items, 'categories', $importModel::IMPORT_TYPE_CATEGORY);
        } catch (Exception $e)
        {
            Mage::helper('ho_import/log')->log($e->getMessage(), Zend_Log::ERR);
        }

        Mage::helper('ho_import/log')->done();
    }
}