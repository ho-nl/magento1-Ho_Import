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
 * XML streamer based on https://github.com/prewk/XmlStreamer/blob/master/XmlStreamer.php
 */
class Ho_Import_Model_Source_Adapter_Csv implements SeekableIterator
{

    /**
     * Column names array.
     *
     * @var array
     */
    protected $_colNames;

    /**
     * Quantity of columns in first (column names) row.
     *
     * @var int
     */
    protected $_colQuantity;

    /**
     * Current row.
     *
     * @var array
     */
    protected $_currentRow = null;

    /**
     * Current row number.
     *
     * @var int
     */
    protected $_currentKey = null;

    /**
     * Source file path.
     *
     * @var string
     */
    protected $_source;

    /**
     * Field delimiter.
     *
     * @var string
     */
    protected $_delimiter = ',';

    /**
     * Field enclosure character.
     *
     * @var string
     */
    protected $_enclosure = '"';

    /**
     * Source file handler.
     *
     * @var resource
     */
    protected $_fileHandler;


    /**
     * Adapter object constructor.
     *
     * @param array $config
     * @return \Ho_Import_Model_Source_Adapter_Csv
     */
    final public function __construct($config)
    {
        if (is_string($config)) {
            $config = array('file' => $config);
        }

        if (!is_string($config['file'])) {
            Mage::throwException(Mage::helper('importexport')->__(
                    'Source file path must be a string'));
        }
        if (!is_readable($config['file'])) {
            Mage::throwException(Mage::helper('importexport')->__(
                    "%s file does not exists or is not readable", $config['file']));
        }
        if (!is_file($config['file'])) {
            Mage::throwException(Mage::helper('importexport')->__(
                    "%s isn't a file, probably a folder.", $config['file']));
        }

        Mage::helper('ho_import/log')->log(Mage::helper('ho_import')->__("Loading source file %s", $config['file']));
        $this->_source = $config['file'];

        if (isset($config['delimiter'])) {
            $this->_delimiter = (string) $config['delimiter'];
        }

        if (isset($config['enclosure'])) {
            if ($config['enclosure']) {
                $this->_enclosure = (string) $config['enclosure'];
            } else {
                $this->_enclosure = chr(0);
            }
        }

        $this->_init();
        if (isset($config['columns'])) {
            $this->_colNames = array_keys($config['columns']);
            if (count($this->_colNames) != count($this->_currentRow)) {
                Mage::helper('ho_import/log')->log(array($this->_colNames, $this->_currentRow), Zend_Log::DEBUG);
                Mage::throwException(Mage::helper('importexport')->__(
                    'Column names do not match (%s columns configured, %s in first row)',
                    count($this->_colNames),
                    count($this->_currentRow)
                ));
            }
        }

        // validate column names consistency
        if (is_array($this->_colNames) && !empty($this->_colNames)) {
            $this->_colQuantity = count($this->_colNames);

            if (count(array_unique($this->_colNames)) != $this->_colQuantity) {
                Mage::helper('ho_import/log')->log(array($this->_colNames), Zend_Log::DEBUG);
                Mage::throwException(Mage::helper('importexport')->__('Column names have duplicates'));
            }
        } else {
            Mage::throwException(Mage::helper('importexport')->__(
                    'Column names is empty or is not an array, there seems to be a problem reading the source file.'));
        }
    }

    /**
     * Object destructor.
     *
     * @return void
     */
    public function __destruct()
    {
        if (is_resource($this->_fileHandler)) {
            fclose($this->_fileHandler);
        }
    }

	/**
	 * @param $fileName
	 * @param string $mode
	 * @return resource
	 */
	public function fopenUTF8($fileName, $mode = 'r')
	{
		$handle = fopen($fileName, $mode);
		$bom = fread($handle, 2);
		rewind($handle);


		if($bom === chr(0xff).chr(0xfe)  || $bom === chr(0xfe).chr(0xff)){
			// UTF16 Byte Order Mark present
			$encoding = 'UTF-16';
		} else {
			$file_sample = fread($handle, 1000) + 'e'; //read first 1000 bytes
			// + e is a workaround for mb_string bug
			rewind($handle);

			$encoding = mb_detect_encoding($file_sample , 'UTF-8, UTF-7, ASCII, EUC-JP,SJIS, eucJP-win, SJIS-win, JIS, ISO-2022-JP');
		}
		if ($encoding){
			stream_filter_append($handle, 'convert.iconv.'.$encoding.'/UTF-8');
		}
		return  ($handle);
	}


    /**
     * Method called as last step of object instance creation. Can be overrided in child classes.
     *
     * @return Ho_Import_Model_Source_Adapter_Csv
     */
    protected function _init()
    {
        $this->_fileHandler = $this->fopenUTF8($this->_source, 'r');
        $this->rewind();
        return $this;
    }

    /**
     * Move forward to next element
     *
     * @return void Any returned value is ignored.
     */
    public function next()
    {
        $this->_currentRow = fgetcsv($this->_fileHandler, null, $this->_delimiter, $this->_enclosure);
        $this->_currentKey = $this->_currentRow ? $this->_currentKey + 1 : null;
    }

    /**
     * Rewind the Iterator to the first element.
     *
     * @return void Any returned value is ignored.
     */
    public function rewind()
    {
        // rewind resource, reset column names, read first row as current
        rewind($this->_fileHandler);

        $this->_colNames = fgetcsv($this->_fileHandler, null, $this->_delimiter, $this->_enclosure);
        $this->_currentRow = fgetcsv($this->_fileHandler, null, $this->_delimiter, $this->_enclosure);

        if ($this->_currentRow) {
            $this->_currentKey = 0;
        }
    }

    /**
     * Seeks to a position.
     *
     * @param int $position The position to seek to.
     * @throws OutOfBoundsException
     * @return void
     */
    public function seek($position)
    {
        if ($position != $this->_currentKey) {
            if (0 == $position) {
                $this->rewind();
                return;
            } elseif ($position > 0) {
                if ($position < $this->_currentKey) {
                    $this->rewind();
                }
                while ($this->_currentRow = fgetcsv($this->_fileHandler, null, $this->_delimiter, $this->_enclosure)) {
                    if (++ $this->_currentKey == $position) {
                        return;
                    }
                }
            }
            throw new OutOfBoundsException(Mage::helper('importexport')->__('Invalid seek position'));
        }
    }


    /**
     * Return the current element.
     *
     * @return mixed
     */
    public function current()
    {
        return array_combine(
            $this->_colNames,
            count($this->_currentRow) != $this->_colQuantity
                    ? array_pad($this->_currentRow, $this->_colQuantity, '')
                    : $this->_currentRow
        );
    }

    /**
     * Column names getter.
     *
     * @return array
     */
    public function getColNames()
    {
        return $this->_colNames;
    }

    /**
     * Return the key of the current element.
     *
     * @return int More than 0 integer on success, integer 0 on failure.
     */
    public function key()
    {
        return $this->_currentKey;
    }

    /**
     * Checks if current position is valid.
     *
     * @return boolean Returns true on success or false on failure.
     */
    public function valid()
    {
        return !empty($this->_currentRow);
    }

    /**
     * Check source file for validity.
     *
     * @return Ho_Import_Model_Source_Adapter_Csv
     */
    public function validateSource()
    {
        return $this;
    }
}