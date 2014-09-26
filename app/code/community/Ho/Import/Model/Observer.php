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
    public function schedule() {
        $importCollection = Mage::getResourceModel('ho_import/system_import_collection');
        foreach ($importCollection as $import) {
            /** @var $import Ho_Import_Model_System_Import */
            $import->schedule(false);
        }

        Mage::getConfig()->cleanCache();
        $importCollection->cleanupCron();
    }

    public function process(Mage_Cron_Model_Schedule $cron) {
        //initialize the translations so that we are able to translate things.
        Mage::app()->loadAreaPart(
            Mage_Core_Model_App_Area::AREA_ADMINHTML,
            Mage_Core_Model_App_Area::PART_TRANSLATE
        );

        $cronName = $cron->getJobCode();
        $profile = str_replace('ho_import_', '', $cronName);

        try {
            /** @var Ho_Import_Model_Import $import */
            $import = Mage::getModel('ho_import/import');
            $import->setProfile($profile);
            $import->process();
        } catch (Exception $e) {
            Mage::helper('ho_import/log')->log($e->getMessage(), Zend_Log::CRIT);
            Mage::helper('ho_import/log')->log($e->getTraceAsString(), Zend_Log::CRIT);
        }

        Mage::helper('ho_import/log')->done();
    }

    public function progressLog(Varien_Event_Observer $event) {
        $name = str_replace('fastsimpleimport_', '', $event->getEvent()->getName());
        $name = str_replace('before_', '', $name);
        $name = ucfirst(str_replace('_',' ',$name)).'...';

        Mage::helper('ho_import/log')->log($name);
    }

    
    /**
     * @event catalog_product_edit_action
     * @param Varien_Event_Observer $observer
     */
    public function catalogProductEditAction(Varien_Event_Observer $observer) {
        /** @var Mage_Catalog_Model_Product $product */
        $product = $observer->getProduct();
        $this->_lockAttributes($product);
    }


    /**
     * @param Varien_Event_Observer $observer
     */
    public function catalogCategoryEditAction(Varien_Event_Observer $observer) {
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
     * @param Mage_Catalog_Model_Abstract $model
     */
    protected function _lockAttributes(Mage_Catalog_Model_Abstract $model) {
        // Is product assigned to import profile.
        if (! ($profiles = $model->getData('ho_import_profile'))){
            return;
        }

        $profiles = explode(',',$profiles);
        foreach ($profiles as $profile) {
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
                    $note = $attribute->getNote() ? $attribute->getNote() : '';

                    //scope global, website
                    if (! $model->isLockedAttribute($attribute->getAttributeCode())) {
                        if ($note) {
                            $note .= "<br />\n";
                        }
                        $note .= Mage::helper('ho_import')->__("Locked by Ho_Import");
                    }

                    $model->lockAttribute($attribute->getAttributeCode());
                    $attribute->setNote($note);
                }
            }
        }
    }
}
