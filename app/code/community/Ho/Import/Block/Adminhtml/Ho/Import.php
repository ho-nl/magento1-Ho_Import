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

class Ho_Import_Block_Adminhtml_Ho_Import extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    protected $_blockGroup = 'ho_import';

    public function __construct()
    {
        $this->_controller = 'adminhtml_ho_import';
        $this->_headerText = Mage::helper('ho_import')->__('H&amp;O Import');
//        $this->_addButtonLabel = Mage::helper('ho_import')->__('Create New Batch');
        parent::__construct();

//        if (! Mage::getSingleton('admin/session')->isAllowed('catalog/ho_inventoryupdates/actions/create')) {
            $this->_removeButton('add');
//        }
    }
}
