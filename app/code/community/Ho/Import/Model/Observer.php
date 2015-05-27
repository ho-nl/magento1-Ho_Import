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
 * @copyright   Copyright © 2014 H&O (http://www.h-o.nl/)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @author      Paul Hachmang – H&O <info@h-o.nl>
 *
 *
 */
class Ho_Import_Model_Observer
{

    /**
     * Link imported products to profile.
     * @param Varien_Event_Observer $observer
     * @throws Zend_Db_Exception
     */
    public function linkProductsToProfile(Varien_Event_Observer $observer)
    {
        /** @var AvS_FastSimpleImport_Model_Import_Entity_Product $adapter */
        $adapter = $observer->getAdapter();

        /** @var $coreResource Mage_Core_Model_Resource */
        $coreResource = Mage::getSingleton('core/resource');
        $entityProfileTable = $coreResource->getTableName('ho_import/entity');
        $entityTypeId = $adapter->getEntityTypeId();
        $now = Mage::helper('ho_import')->getCurrentDatetime();

        while ($bunch = $adapter->getNextBunch()) {
            $entityProfileData = array();
            foreach ($bunch as $rowNum => $rowData) {
                if ($adapter->getRowScope($rowData) !== Mage_ImportExport_Model_Import_Entity_Product::SCOPE_DEFAULT) {
                    continue;
                }

                if (! isset($rowData['ho_import_profile'])) {
                    continue;
                }

                $entity = $adapter->getEntityBySku($rowData[$adapter::COL_SKU]);

                $entityProfileData[] = array(
                    'profile' => $rowData['ho_import_profile'],
                    'entity_type_id' => $entityTypeId,
                    'entity_id' => $entity['entity_id'],
                    'updated_at' => $now,
                    'created_at' => $now
                );
            }

            if ($entityProfileData) {
                $adapter->getConnection()->insertOnDuplicate($entityProfileTable, $entityProfileData, array('updated_at'));
            }
        }
    }


    /**
     * Schedule imports
     */
    public function schedule()
    {
        $importCollection = Mage::getResourceModel('ho_import/system_import_collection');
        foreach ($importCollection as $import) {
            /** @var $import Ho_Import_Model_System_Import */
            $import->schedule(false);
        }

        Mage::getConfig()->cleanCache();
        $importCollection->cleanupCron();
    }


    /**
     * Run an import through a cron job
     * @param Aoe_Scheduler_Model_Schedule|Mage_Cron_Model_Schedule $cron
     */
    public function process(Mage_Cron_Model_Schedule $cron)
    {
        //initialize the translations so that we are able to translate things.
        Mage::app()->loadAreaPart(
            Mage_Core_Model_App_Area::AREA_ADMINHTML,
            Mage_Core_Model_App_Area::PART_TRANSLATE
        );

        $cronName = $cron->getJobCode();
        $profile = str_replace('ho_import_', '', $cronName);
        $logHelper = Mage::helper('ho_import/log');

        try {
            /** @var Ho_Import_Model_Import $import */
            $import = Mage::getModel('ho_import/import');
            $import->setProfile($profile);
            $import->process();
        } catch (Exception $e) {
            $logHelper->log($logHelper->getExceptionTraceAsString($e), Zend_Log::CRIT);
            $cron->setStatus($cron::STATUS_ERROR);
        }

        $logHelper->done();
    }


    /**
     * We listen to several events and log it. This gives us more insight into the progress that has been made during
     * the import.
     * @param Varien_Event_Observer $event
     */
    public function progressLog(Varien_Event_Observer $event)
    {
        $name = str_replace('fastsimpleimport_', '', $event->getEvent()->getName());
        $name = str_replace('before_', '', $name);
        $name = ucfirst(str_replace('_', ' ', $name)).'...';

        Mage::helper('ho_import/log')->log($name);
    }

    
    /**
     * Lock product attributes
     * @event catalog_product_edit_action
     * @param Varien_Event_Observer $observer
     */
    public function catalogProductEditAction(Varien_Event_Observer $observer)
    {
        /** @var Mage_Catalog_Model_Product $product */
        $product = $observer->getProduct();
        $this->_lockAttributes($product);
    }


    /**
     * Lock category attributes
     * @param Varien_Event_Observer $observer
     */
    public function catalogCategoryEditAction(Varien_Event_Observer $observer)
    {
        /** @var Mage_Core_Controller_Request_Http $request */
        $request = $observer->getAction()->getRequest();

        if ($request->getControllerName() !== 'catalog_category'
           || $request->getModuleName() !== 'admin') {
            return;
        }

        $category = Mage::registry('current_category');
        $this->_lockAttributes($category);
    }


    /**
     * Lock the attributes of a product/category so that it can not be overwritten using Magento's backend.
     * @param Mage_Catalog_Model_Abstract $model
     */
    protected function _lockAttributes(Mage_Catalog_Model_Abstract $model)
    {
        // Is product assigned to import profile.
        if (! ($profiles = $model->getData('ho_import_profile'))) {
            return;
        }

        if (is_string($profiles)) {
            $profiles = [['profile' => $profiles]];
        }

        foreach ($profiles as $profileData) {
            $profile = $profileData['profile'];

            // Is lock attributes functionality enabled.
            $lockAttributes = sprintf('global/ho_import/%s/import_options/lock_attributes', $profile);
            $fieldMapNode = Mage::getConfig()->getNode($lockAttributes);
            if (!$fieldMapNode || !$fieldMapNode->asArray()) {
                continue;
            }

            // Get the mapper.
            /** @var Ho_Import_Model_Mapper $mapper */
            $mapper = Mage::getModel('ho_import/mapper');
            $mapper->setProfileName($profile);
            $storeCode = $model->getStore()->getCode();

            // Check if attributes need to be locked.
            $attributes = $model->getAttributes();
            foreach ($attributes as $attribute) {
                /** @var Mage_Catalog_Model_Resource_Eav_Attribute $attribute */
                $mapper->setStoreCode($attribute->isScopeStore() || $attribute->isScopeWebsite() ? $storeCode :'admin');

                $fieldConfig = $mapper->getFieldConfig($attribute->getAttributeCode());
                if (isset($fieldConfig['@'])) {
                    $this->_lockAttribute($attribute, $model, $profile);
                }
            }
        }
    }


    /**
     * Lock a specific category or product attribute so that it can not be editted through the interface.
     * @param Mage_Eav_Model_Entity_Attribute $attribute
     * @param Mage_Catalog_Model_Abstract     $model
     * @param                                 $profile
     */
    protected function _lockAttribute(
        Mage_Eav_Model_Entity_Attribute $attribute,
        Mage_Catalog_Model_Abstract $model,
        $profile
    ) {
        $note = $attribute->getNote() ? $attribute->getNote() : '';

        if ($attribute->getAttributeCode() == 'ho_import_profile') {
            return;
        }

        if ($note) {
            $note .= "<br />\n";
        }
        $note .= Mage::helper('ho_import')->__("Locked by import: <i>%s</i>", $profile);

        $model->lockAttribute($attribute->getAttributeCode());
        $attribute->setNote($note);
    }
}
