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

include_once 'spreadsheet-reader/php-excel-reader/excel_reader2.php';
include_once 'spreadsheet-reader/SpreadsheetReader.php';

class Ho_Import_Model_Source_Adapter_Spreadsheet extends SpreadsheetReader
{

    protected $_hasHeaders = false;
    /**
     * If the first line of the document contains headers, it will be stored here and each row will
     * return a key-value.
     *
     * @var array
     */
    protected $_colNames = array();

    public function __construct($config)
    {
        $source = is_string($config) ? $config : $config['file'];
        if (!is_string($source)) {
            Mage::throwException(Mage::helper('importexport')->__(
                    'Source file path must be a string'));
        }
        if (!is_readable($source)) {
            Mage::throwException(Mage::helper('importexport')->__(
                    "%s file does not exists or is not readable", $source));
        }
        if (!is_file($source)) {
            Mage::throwException(Mage::helper('importexport')->__(
                    "%s isn't a file, probably a folder.", $source));
        }

        Mage::helper('ho_import/log')->log(Mage::helper('ho_import')->__("Loading source file %s", $source));
        $this->_source = $source;
        parent::__construct($this->_source);

        if (isset($config['has_headers']) && is_numeric($config['has_headers'])) {
            $this->_hasHeaders = (bool) $config['has_headers'];
            $this->rewind();
        }

        return $this;
    }

    public function rewind()
    {
        parent::rewind();

        if ($this->_hasHeaders) {
            $current = $this->current();
            $this->_colNames = $current;
            $this->next();
        }
    }

    public function current()
    {
        if ($this->_hasHeaders && count($this->_colNames)) {
            $row = parent::current();
            foreach ($this->_colNames as $index => $key) {
                if(!isset($row[$index])){
                    if(isset($row[$key])){
                        $row[$key] = $row[$key];
                    }
                    else{
                        $row[$key] = null;
                    }
                }
                else{
                    $row[$key] = $row[$index];
                }
                unset($row[$index]);
            }

            return $row;
        } else {
            return parent::current();
        }
    }
}
