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
class Ho_Import_Model_Resource_System_Import_Collection extends Varien_Data_Collection
{
    public function __construct()
    {
        $this->setItemObjectClass('ho_import/system_import');
    }

    protected $_data = null;

    /**
     * Load data
     *
     * @param bool $printQuery
     * @param bool $logQuery
     *
     * @return  Varien_Data_Collection
     */
    public function load($printQuery = false, $logQuery = false)
    {
        if ($this->isLoaded()) {
            return $this;
        }

        $this->_renderFilters()
            ->_renderOrders()
            ->_renderLimit();

        $data = $this->getData();
        $this->resetData();

        if (is_array($data)) {
            foreach ($data as $key => $row) {
                $row['id'] = $key;
                $item      = $this->getNewEmptyItem();
                $item->setIdFieldName('id');
                $item->addData($row);
                $this->addItem($item);
            }
        }

        $this->_setIsLoaded();

        return $this;
    }

    /**
     * Reset loaded for collection data array
     *
     * @return Varien_Data_Collection_Db
     */
    public function resetData()
    {
        $this->_data = null;
        return $this;
    }

    public function getData()
    {
        $profileNodes = Mage::getConfig()->getNode('global/ho_import');
        if ($profileNodes === false) {
            return array();
        }
        return $profileNodes->asArray();
    }

    /**
     * @return $this
     */
    public function cleanupCron()
    {
        $availableConfigs = array();
        foreach ($this as $import) {
            /** @var $import Ho_Import_Model_System_Import */
            if ($import->getSchedule()) {
                $availableConfigs[] = str_replace('crontab/jobs/', '', $import->getConfigPath());
            }
        }

        $jobsNode = Mage::getConfig()->getNode('default/crontab/jobs');
        if (false === $jobsNode) {
            return $this;
        }

        foreach ($jobsNode->children() as $cron => $data) {
            if (!in_array($cron, $availableConfigs)) {
                /** @var Mage_Core_Model_Resource_Config $resource */
                $resource = Mage::getConfig()->getResourceModel();
                /** @var Varien_Db_Adapter_Pdo_Mysql $select */
                $select = $resource->getReadConnection();

                $select->delete($resource->getMainTable(), "path LIKE 'crontab/jobs/{$cron}%'");
            }
        }
        return $this;
    }
}
