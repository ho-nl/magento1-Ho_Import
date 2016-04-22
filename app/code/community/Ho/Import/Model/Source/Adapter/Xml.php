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
class Ho_Import_Model_Source_Adapter_Xml implements SeekableIterator
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
     * Source file handler.
     *
     * @var resource
     */
    protected $_fileHandler;

    /**
     * Total size of file
     * @var
     */
    private $_totalBytes;

    /**
     * Bytes currently processed
     * @var int
     */
    private $_readBytes = 0;

    /* @var string */
    private $_chunk = "";

    /**
     * Chunk size that will be read on each read.
     * @var int
     */
    private $_chunkSize = 8192;

    private $single_chunk = false;

    /**
     * From wehere to start reading
     * @var
     */
    private $_readFromChunkPos;

    /**
     * Root element of the XML file
     * @var
     */
    private $_rootNode;

    /**
     * @var
     */
    protected $_customRootNode;


    protected $_customChildNode;


    protected $_fileEncoding = 'UTF-8';


    /**
     * Adapter object constructor.
     *
     * @param array $data
     *
     * @return \Ho_Import_Model_Source_Adapter_Xml
     */
    public function __construct($data)
    {
        $source = is_string($data) ? $data : $data['file'];
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

        if (is_array($data) && isset($data['rootNode'])) {
            $this->_customRootNode = (string) $data['rootNode'];
        }

        if (is_array($data) && isset($data['childNode'])) {
            $this->_customChildNode = (string) $data['childNode'];
        }

        $this->_init();

        // validate column names consistency
        if (is_array($this->_colNames) && !empty($this->_colNames)) {
            $this->_colQuantity = count($this->_colNames);

            if (count(array_unique($this->_colNames)) != $this->_colQuantity) {
                Mage::throwException(Mage::helper('importexport')->__('Column names have duplicates'));
            }
        } else {
            Mage::throwException(Mage::helper('importexport')->__('Column names is empty or is not an array'));
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
     * @return Mage_ImportExport_Model_Import_Adapter_Abstract
     */
    public function validateSource()
    {
        return $this;
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
     * @return Mage_ImportExport_Model_Import_Adapter_Abstract
     */
    protected function _init()
    {
        $this->_fileHandler = fopen($this->_source, 'r');
        $this->_totalBytes = filesize($this->_source);
        $this->rewind();
        return $this;
    }

    public function next()
    {
        $node = $this->_processNode($this->_nextNode());
        $this->_currentRow = $node;
        $this->_currentKey = $this->_currentRow ? $this->_currentKey + 1 : null;
        return $node;
    }


    /**
     * Rewind the Iterator to the first element.
     *
     * @return array
     */
    public function rewind()
    {
        $this->_getRootNode();
        $this->_readBytes = 0;

        $firstElement = $this->next();
        $this->_colNames = array_keys($firstElement);

        if ($this->_currentRow) {
            $this->_currentKey = 0;
        }
        return;
    }


    /**
     * Seeks to a position.
     *
     * @param int $position The position to seek to.
     *
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
                if ($this->_currentKey === null || $position < $this->_currentKey) {
                    $this->rewind();
                }
                while ($nodeStr = $this->_nextNode()) {
                    if (++$this->_currentKey == $position) {
                        $node = $this->_processNode($nodeStr);
                        $this->_currentRow = $node;
                        return;
                    }
                }
            }
            throw new OutOfBoundsException(Mage::helper('importexport')->__('Invalid seek position'));
        }
    }


    protected function _processNode($xmlString)
    {
        if (! strlen($xmlString)) {
            return null;
        }
        $doc = new DOMDocument();
        if ($this->_fileEncoding != 'UTF-8') {
            $xmlString = iconv($this->_fileEncoding, "UTF-8", $xmlString);
        }
        $doc->loadXML($xmlString);

        return $this->_nodeToArray($doc->documentElement);
    }

    protected function _nodeToArray($node)
    {
        $output = array();
        switch ($node->nodeType) {
            case XML_CDATA_SECTION_NODE:
            case XML_TEXT_NODE:
                $output = trim($node->textContent);
                break;
            case XML_ELEMENT_NODE:
                for ($i = 0, $m = $node->childNodes->length; $i < $m; $i++) {
                    $child = $node->childNodes->item($i);
                    $v     = $this->_nodeToArray($child);
                    if (isset($child->tagName)) {
                        $t = $child->tagName;
                        if (!isset($output[$t])) {
                            $output[$t] = array();
                        }
                        $output[$t][] = $v;
                    } elseif ($v || $v === '0') {
                        $output = (string)$v;
                    }
                }
                if ($node->attributes->length && !is_array($output)
                ) { //Has attributes but isn't an array
                    $output = array('@content' => $output); //Change output into an array.
                }
                if (is_array($output)) {
                    if ($node->attributes->length) {
                        $a = array();
                        foreach ($node->attributes as $attrName => $attrNode) {
                            $a[$attrName] = (string)$attrNode->value;
                        }
                        $output['@attributes'] = $a;
                    }
                    foreach ($output as $t => $v) {
                        if (is_array($v) && count($v) == 1 && $t != '@attributes') {
                            $output[$t] = $v[0];
                        }
                    }
                }
                break;
        }
        return $output;
    }

    /**
     * Move forward to next element
     *
     * @return string
     */
    protected function _nextNode()
    {
        $continue = true;
        while ($continue) {
            $fromChunkPos = substr($this->_chunk, $this->_readFromChunkPos);
            // Find element
            // $$-- Valiton change: changed pattern. XML1.0 standard allows almost all
            //                      Unicode characters even Chinese and Cyrillic.
            //                      see:
            //                      http://en.wikipedia.org/wiki/XML#International_use
            preg_match('/<([^>]+)>/', $fromChunkPos, $matches);
            //  --$$
            if (isset($matches[1])) {
                // Found element
                $element = $matches[1];
                // $$-- Valiton change: handle attributes inside elements. aswell as
                //                      when they are distributed over multiple lines.
                // Is there an end to this element tag?
                $spacePos = strpos($element, " ");
                $crPos    = strpos($element, "\r");
                $lfPos    = strpos($element, "\n");
                $tabPos   = strpos($element, "\t");
                // find min. (exclude false, as it would convert to int 0)
                $aPositionsIn = array($spacePos, $crPos, $lfPos, $tabPos);
                $aPositions = array();
                foreach ($aPositionsIn as $iPos) {
                    if ($iPos !== false) {
                        $aPositions[] = $iPos;
                    }
                }
                $minPos = count($aPositions) > 0 ? min($aPositions) : false;
                if ($minPos !== false && $minPos != 0) {
                    $sElementName = substr($element, 0, $minPos);
                    $endTag       = "</" . $sElementName . ">";
                } else {
                    $sElementName = $element;
                    $endTag       = "</$sElementName>";
                }
                $endTagPos = false;
                // try selfclosing first!
                // NOTE: selfclosing is inside the element
                $lastCharPos = strlen($element) - 1;
                if (substr($element, $lastCharPos) == "/") {
                    $endTag    = "/>";
                    $endTagPos = $lastCharPos;
                    $iPos = strpos($fromChunkPos, "<");
                    if ($iPos !== false) {
                        // correct difference between $element and $fromChunkPos
                        // "+1" is for the missing '<' in $element
                        $endTagPos += $iPos + 1;
                    }
                }
                if ($endTagPos === false) {
                    $endTagPos = strpos($fromChunkPos, $endTag);
                }
                // --$$
                if ($endTagPos !== false) {
                    // Found end tag
                    $endTagEndPos        = $endTagPos + strlen($endTag);
                    $elementWithChildren = substr($fromChunkPos, 0, $endTagEndPos);
                    // $$-- Valiton change
                    $elementWithChildren = trim($elementWithChildren);
                    // --$$
                    $this->_chunk = substr($this->_chunk, strpos($this->_chunk, $endTag) + strlen($endTag));
                    $this->_readFromChunkPos = 0;

                    return $elementWithChildren;
                } else {
                    $continue = $this->_readNextChunk();
                }
            } else {
                $continue = $this->_readNextChunk();
            }
        }

        return '';
    }


    protected function _getRootNode()
    {
        if (isset($this->_rootNode)) {
            return;
        }

        $continue = $this->_readNextChunk();
        do {
            $encodingPos = strpos($this->_chunk, 'encoding="');
            if ($encodingPos !== false) {
                $encodingStr = substr($this->_chunk, $encodingPos + 10);
                $encodingStr = substr($encodingStr, 0, strpos($encodingStr, '"'));
                $this->_fileEncoding = strtoupper($encodingStr);
            }
            // Find root node
            if (isset($this->_customRootNode)) {
                $customRootNodePos = strpos($this->_chunk, "<{$this->_customRootNode}");
                if ($customRootNodePos !== false) {
                    // Found custom root node
                    // Support attributes
                    $closer = strpos(substr($this->_chunk, $customRootNodePos), ">");
                    $readFromChunkPos = $customRootNodePos + $closer + 1;

                    // Custom child node?
                    if (isset($this->_customChildNode)) {
                        // Find it in the chunk
                        $customChildNodePos = strpos(
                            substr($this->_chunk, $readFromChunkPos),
                            "<{$this->_customChildNode}"
                        );
                        if ($customChildNodePos !== false) {
                            // Found it!
                            $readFromChunkPos = $readFromChunkPos + $customChildNodePos;
                        } else {
                            // Didn't find it - read a larger chunk and do everything again
                            Mage::throwException(Mage::helper('ho_import')->__(
                                    "Couldn't find child node in first chunk (chunk size %s)", $this->_chunkSize));
                        }
                    }

                    $this->_rootNode = $this->_customRootNode;
                    $this->_readFromChunkPos = $readFromChunkPos;
                    return;
                } else {
                    $this->_getRootNode();
                }
            } else {
                preg_match('/<([^>\?]+)>/', $this->_chunk, $matches);
                //  --$$
                if (isset($matches[1])) {
                    // Found root node
                    $this->_rootNode = $matches[1];
                    $this->_readFromChunkPos = strpos($this->_chunk, $matches[0]) + strlen($matches[0]);
                    return;
                } else {
                    $this->_getRootNode();
                }
            }
        } while ($continue);

        if (isset($this->_customRootNode)) {
            Mage::throwException(Mage::helper('ho_import')->__(
                    "Couldn't find custom root node (%s) in document", $this->_customRootNode));
        } else {
            Mage::throwException(Mage::helper('ho_import')->__(
                    "Couldn't find root node (%s) in document", $this->_rootNode));
        }
    }


    protected function _readNextChunk()
    {
        $this->_chunk .= fread($this->_fileHandler, $this->_chunkSize);
        $this->_readBytes += $this->_chunkSize;

        if ($this->_totalBytes <= $this->_chunkSize && !$this->single_chunk) {
            $this->single_chunk = true;
            return true;
        }

        if ($this->_readBytes >= $this->_totalBytes) {
            $this->_readBytes = $this->_totalBytes;
            return false;
        }
        return true;
    }
}