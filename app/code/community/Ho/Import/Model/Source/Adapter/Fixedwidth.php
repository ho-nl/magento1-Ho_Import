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
 * @copyright   Copyright © 2016 H&O (http://www.h-o.nl/)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @author      Leon Bogaert – Tim_online <info@tim-online.nl>
 *
 */
class Ho_Import_Model_Source_Adapter_Fixedwidth implements SeekableIterator
{

    /**
     * List of columnnames (key) with character length per column (value)
     *
     * @var array
     */
    protected $_columns = array();

    /**
     * Flag indicating of values in columns should be trimmed
     *
     * @var bool
     */
    protected $_trim = false;

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

        if (isset($config['trim'])) {
            if ($config['trim']) {
                $this->_trim = true;
            } else {
                $this->_trim = false;
            }
        }

        if (isset($config['columns'])) {
            $this->_columns = $config['columns'];
        }

        $this->_init();
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
     * Method called as last step of object instance creation. Can be overrided in child classes.
     *
     * @return Ho_Import_Model_Source_Adapter_Csv
     */
    protected function _init()
    {
        $this->_fileHandler = fopen($this->_source, 'r');
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
        $line = $this->readLine();
        $values = $this->parseLine($line);

        // trim values if enabled
        if ($this->_trim) {
            foreach ($values as $k => $v) {
                $values[$k] = trim($v);
            }
        }

        $this->_currentRow = $values;
        $this->_currentKey = $this->_currentRow ? $this->_currentKey + 1 : null;
    }

    /**
     * Reads the current line
     *
     * @return string
     */
    public function readLine()
    {
        $line = fgets($this->_fileHandler);
        if ($line === false) {
            // eof reached
            throw new OutOfBoundsException(Mage::helper('importexport')->__('Invalid seek position'));
        }
        return $line;
    }

    // @TODO: describe method
    public function parseLine($line)
    {
        $format = $this->getUnpackFormat();
        $values = unpack($format, $line);
        return $values;
    }

    public function getUnpackFormat()
    {
        $pieces = array();
        foreach ($this->_columns as $k => $v) {
            $name = $k;
            $length = $v;
            if ($length == '') {
                $length = '*';
            }
            $pieces[] = "A{$length}{$name}";
        }

        $format = implode('/', $pieces);
        return $format;
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

        $this->_currentKey = 0;
        $this->next();
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
        $position = (int)$position;

        if ($position == $this->_currentKey) {
            return;
        }

        if (0 == $position) {
            $this->rewind();
            return;
        }

        if ($position < $this->_currentKey) {
            $this->rewind();
        }

        while ($this->_currentKey < $position) {
            $this->next();
        }
    }


    /**
     * Return the current element.
     *
     * @return mixed
     */
    public function current()
    {
        return $this->_currentRow;
    }

    /**
     * Column names getter.
     *
     * @return array
     */
    public function getColNames()
    {
        return array_key($this->_columns);
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
