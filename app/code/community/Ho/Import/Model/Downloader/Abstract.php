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
 * @copyright   Copyright © 2013 H&O (http://www.h-o.nl/)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @author      Paul Hachmang – H&O <info@h-o.nl>
 *
 * 
 */
 
abstract class Ho_Import_Model_Downloader_Abstract {

    /**
     * Download the external file to disk.
     *
     * @param $connectionInfo
     * @param $target
     *
     * @return mixed
     */
    abstract function download(Varien_Object $connectionInfo, $target);


    protected function _getTargetPath($folder, $filename) {
        return Mage::getBaseDir() . DS . trim($folder, '/') . DS . $filename;
    }


    /**
     * @param     $message
     * @param int $level
     *
     * @return $this
     */
    protected function _log($message, $level = Zend_Log::DEBUG) {
        $this->_getLog()->log($message, $level);
        return $this;
    }


    /**
     * @return Ho_Import_Helper_Log
     */
    protected function _getLog() {
        return Mage::helper('ho_import/log');
    }
}
