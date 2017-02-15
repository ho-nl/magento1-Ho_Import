<?php

class Ho_Import_Model_ImportExport_Resource_Import_Data extends Mage_ImportExport_Model_Resource_Import_Data
{
    /**
     * Return behavior from import data table.
     *
     * @throws Exception
     * @return string
     */
    public function getBehavior()
    {
        $adapter = $this->_getReadAdapter();
        $behaviors = array_unique($adapter->fetchCol(
            $adapter->select()
                ->from($this->getMainTable(), array('behavior'))
        ));
        if (count($behaviors) != 1) {
            Mage::throwException(Mage::helper('importexport')->__('Error in data structure: behaviors are mixed'));
        }
        return $behaviors[0];
    }

    /**
     * Return entity type code from import data table.
     *
     * @throws Exception
     * @return string
     */
    public function getEntityTypeCode()
    {
        $adapter = $this->_getReadAdapter();
        $entityCodes = array_unique($adapter->fetchCol(
            $adapter->select()
                ->from($this->getMainTable(), array('entity'))
        ));
        if (count($entityCodes) != 1) {
            Mage::throwException(Mage::helper('importexport')->__('Error in data structure: entity codes are mixed'));
        }
        return $entityCodes[0];
    }
}