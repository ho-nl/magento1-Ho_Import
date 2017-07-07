<?php
/**
 * Copyright © 2017 H&O E-commerce specialisten B.V. (http://www.h-o.nl/)
 * See LICENSE.txt for license details.
 */
 
class Ho_Import_Model_Entity_Attribute_Backend_Profile extends Mage_Eav_Model_Entity_Attribute_Backend_Abstract
{
    /**
     * Retrieve resource instance.
     *
     * @return Ho_Import_Model_Resource_Attribute_Backend_Profile
     */
    protected function _getResource()
    {
        return Mage::getResourceSingleton('ho_import/attribute_backend_profile');
    }

    /**
     * Assign group prices to product data.
     *
     * @param Mage_Catalog_Model_Abstract $object
     *
     * @return Ho_Import_Model_Entity_Attribute_Backend_Profile
     */
    public function afterLoad($object)
    {
        if (! Mage::helper('ho_import')->isAdmin()) {
            return $this;
        }

        $data = $this->_getResource()->loadProfileData($object->getId(), $object->getEntityTypeId());

        $object->setData($this->getAttribute()->getName(), count($data) ? $data : null);
        $object->setOrigData($this->getAttribute()->getName(), count($data) ? $data : null);

        return $this;
    }

    /**
     * After save attribute manipulation.
     *
     * @param Mage_Catalog_Model_Abstract $object
     *
     * @return Ho_Import_Model_Entity_Attribute_Backend_Profile
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
