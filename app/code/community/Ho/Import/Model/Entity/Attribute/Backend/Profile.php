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
 * @category  Ho
 * @package   Ho_Import
 * @author    Paul Hachmang – H&O <info@h-o.nl>
 * @copyright 2014 Copyright © H&O (http://www.h-o.nl/)
 * @license   H&O Commercial License (http://www.h-o.nl/license)
 */
 
class Ho_Import_Model_Entity_Attribute_Backend_Profile
    extends Mage_Eav_Model_Entity_Attribute_Backend_Abstract
{
    /**
     * Retrieve resource instance
     * @return Ho_Import_Model_Resource_Attribute_Backend_Profile
     */
    protected function _getResource()
    {
        return Mage::getResourceSingleton('ho_import/attribute_backend_profile');
    }

    /**
     * Assign group prices to product data
     *
     * @param Mage_Catalog_Model_Abstract $object
     * @return $this
     */
    public function afterLoad($object)
    {
        $data = $this->_getResource()->loadProfileData($object->getId(), $object->getEntityTypeId());

        $object->setData($this->getAttribute()->getName(), count($data) ? $data : null);
        $object->setOrigData($this->getAttribute()->getName(), count($data) ? $data : null);

        return $this;
    }

    /**
     * After Save Attribute manipulation
     *
     * @param Mage_Catalog_Model_Abstract $object
     * @return $this
     */
    public function afterSave($object)
    {
        $hasChanges = $object->dataHasChangedFor($this->getAttribute()->getName());
        if (! $hasChanges) {
            return $this;
        }
        $object->getData($this->getAttribute()->getName());

        return $this;
    }
}
