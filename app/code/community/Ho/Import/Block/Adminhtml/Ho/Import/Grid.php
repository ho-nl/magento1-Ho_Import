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
 */

class Ho_Import_Block_Adminhtml_Ho_Import_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('ho_import_grid');
        $this->setPagerVisibility(false);
        $this->setFilterVisibility(false);
        $this->setSortable(false);
    }

    /**
     * Retrieve collection class
     * @return string
     */
    protected function _getCollectionClass()
    {
        return'ho_import/system_import_collection';
    }


    /**
     * @return Ho_Import_Block_Adminhtml_Ho_Import_Grid
     */
    protected function _prepareCollection()
    {
        if (! $this->getCollection()) {
            /** @var Ho_Import_Model_Resource_System_Import_Collection $collection */
            $collection = Mage::getResourceModel($this->_getCollectionClass());
            $this->setCollection($collection);
        }
        return parent::_prepareCollection();
    }

    protected function _prepareColumns()
    {
        $this->addColumn('id', array(
            'header'=> Mage::helper('ho_import')->__('ID'),
            'width' => '1px',
            'type'  => 'text',
            'index' => 'id',
        ));

        $this->addColumn('entity_type', array(
            'header'=> Mage::helper('ho_import')->__('Entity Type'),
            'width' => '1px',
            'type'  => 'text',
            'index' => 'entity_type',
        ));

        $this->addColumn('schedule_render', array(
            'header'=> Mage::helper('ho_import')->__('Schedule'),
            'width' => '1px',
            'type'  => 'text',
            'index' => 'schedule_render',
            'frame_callback' => array($this, 'decorateSchedule')
        ));

        return parent::_prepareColumns();
    }
    public function decorateSchedule($value, $row, $column, $isExport) {
        $schedule = $row->getSchedule();
        if (! $schedule || is_string($schedule)) {
            return $schedule;
        }

        $html = '';
        foreach ($schedule as $key => $value) {
            $html .= sprintf('%s: %s<br /', $key, $value);
        }
        return $html;
    }

    protected function _prepareMassaction()
    {
        return $this;
    }

    public function getRowUrl($row)
    {
//        if (Mage::getSingleton('admin/session')->isAllowed('system/ho_import/actions/view')) {
//           return $this->getUrl('*/*/view', array('schedule' => $row->getId()));
//        }
        return null;
    }

    public function getGridUrl()
    {
        return $this->getUrl('*/*/grid', array('_current'=>true));
    }
}
