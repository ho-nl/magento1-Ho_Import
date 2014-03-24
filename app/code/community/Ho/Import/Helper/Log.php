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
 * @copyright   Copyright © 2012 H&O (http://www.h-o.nl/)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @author      Paul Hachmang – H&O <info@h-o.nl>
 */
class Ho_Import_Helper_Log extends Mage_Core_Helper_Abstract
{
    const LOG_MODE_CLI = 'cli';
    const LOG_MODE_NOTIFICATION = 'notification';

    const LOG_SUCCESS = 8;

    protected $_logfile = 'ho_import.log';

    protected $_minLogLevel = self::LOG_SUCCESS;

    protected $_mode = self::LOG_MODE_NOTIFICATION;

    protected $_logEntries = array();

    public function setMinLogLevel($level) {
        if (! is_numeric($level)) {
            Mage::throwException($this->__('The min log level should be numeric, %s given', $level));
        }

        if ($level > self::LOG_SUCCESS) {
            Mage::throwException($this->__('The log level can be %s maximum, %s given', self::LOG_SUCCESS, $level));
        }

        $this->_minLogLevel = (int) $level;
    }

    /**
     * @param     $message
     * @param int $level
     *
     * @return $this
     */
    public function log($message, $level = Zend_Log::INFO)
    {
        if (empty($message)) {
            return $this;
        }

        if ($this->_mode == self::LOG_MODE_CLI) {
            $date = date("H:i:s", Mage::getModel('core/date')->timestamp(time()));

            if (is_array($message) && is_array(reset($message))) {
                $message = $this->_renderCliTable($message, $level);
            }

            echo "\033[0;37m".$date.' - '.$this->convert(memory_get_usage(true)) . " ";
            echo $this->_getCliColor($level);
            print_r($message);
            echo "\033[37m\n";

        } else {
            $this->_logEntries[$level][] = $message;
            Mage::log($message, $level, $this->_logfile, true);
        }

        return $this;
    }

    protected function _getCliColor($level = Zend_Log::INFO) {
        switch($level)
        {
            case Zend_Log::EMERG:
            case Zend_Log::ALERT:
            case Zend_Log::CRIT:
            case Zend_Log::ERR:
            case Zend_Log::WARN:
                return "\033[31m";
                break;
            case Zend_Log::NOTICE:
                return "\033[33m";
                break;
            case Zend_Log::INFO:
                return "\033[36m";
                break;
            case Ho_Import_Helper_Log::LOG_SUCCESS:
                return "\033[32m";
                break;
            default:
                return "\033[37m";
                break;
        }
    }

    protected function _renderCliTable($arrays, $level = Zend_Log::INFO) {
        $maxWidth = exec('tput cols') ?: 200;
        if (count($arrays) > 5) {
            $arrays = array_slice($arrays, 0, 5);
        }

        $columnsOrig = array();
        foreach ($arrays as $row) {
            foreach ($row as $col => $value) {
                $columnsOrig[$col] = $col;
            }
        }

        array_unshift($arrays, $columnsOrig);
        $flippedArray = array();
        foreach($arrays as $row){
            foreach ($columnsOrig as $column) {
                if (isset($row[$column])) {

                    if (is_array($row[$column]) || is_object($row[$column])) {
                        $row[$column] = json_encode($row[$column]);
                    }

                    if (strpos($row[$column], "\n")) {
                        $row[$column] = str_replace("\n",'\n',$row[$column]);
                    }

                    $flippedArray[$column][] = $row[$column];
                } else {
                    $flippedArray[$column][] = '';
                }
            }
        }

        $columns = array();
        foreach ($flippedArray as $row) {
            foreach ($row as $col => $value) {
                if (! isset($columns[$col])) {
                    $columns[$col] = 0;
                }

                if (strlen($col) > $columns[$col]) {
                    $columns[$col] = strlen($col);
                }

                if (strlen($value) > $columns[$col]) {
                    $columns[$col] = strlen($value);
                }
            }
        }

        $maxColumnWidth = ($maxWidth - reset($columns)) / (count($columns) - 1) - 10;
        foreach ($columns as $col => $width) {
            if ($col == 'key') continue;
            if ($columns[$col] > $maxColumnWidth) {
                $columns[$col] = $maxColumnWidth;
            }
        }

        $lines = "\n";

        $line  = '| '.str_pad('key', $columns[0]).' |';
        $lineTwo = '+-'.str_pad('-', $columns[0], '-').'-+';
        $i = 0;
        array_shift($arrays);
        foreach($arrays as $key => $array) {
            $i++;

            $search = preg_match_all('/__.*?__/', $key, $matches);
            if ($search) {
                $str = str_pad($key, $columns[$i]);
                foreach ($matches[0] as $match) {
                    $str = str_replace($match, "\033[31m".str_replace('__','', $match).$this->_getCliColor($level), $str);
                }
                $padding = count($matches[0]) * 4;
                $str = str_pad($str, strlen($str) + $padding);
            } else {
                $str = str_pad($key, $columns[$i]);
            }
            $line .= ' '.$str.' |';

            $lineTwo.= '-'.str_pad('-', $columns[$i], '-').'-+';
        }
        $lines.= $lineTwo . "\n";
        $lines.= $line . "\n";
        $lines.= $lineTwo . "\n";

        foreach ($flippedArray as $row) {
            $line = '|';
            foreach ($columns as $column => $length) {
                if (isset($row[$column])) {
                    $row[$column] = Mage::helper('core/string')->truncate($row[$column], $length, '…');
                    $search = preg_match_all('/__.*?__/', $row[$column], $matches);

                    if ($search) {
                        $str = str_pad($row[$column], $length);
                        foreach ($matches[0] as $match) {
                            $str = str_replace($match, "\033[31m".str_replace('__','', $match).$this->_getCliColor($level), $str);
                        }
                        $padding = count($matches[0]) * 4;
                        $str = str_pad($str, strlen($str) + $padding);
                    } else {
                        $str = str_pad($row[$column], $length);
                    }

                    $line .= ' '.$str.' |';
                }
            }
            $lines.= $line . "\n";
        }

        $lines .= $lineTwo;
        return $lines;
    }


    /**
     * When logging to the admin notification inbox.
     */
    public function done()
    {
        if ($this->_mode == self::LOG_MODE_NOTIFICATION) {
            /* @var $inbox Mage_AdminNotification_Model_Inbox */
            $inbox = Mage::getModel('adminnotification/inbox');

            $lowestLevel = array_search(min($this->_logEntries), $this->_logEntries);
            if ($lowestLevel > $this->_minLogLevel) {
                return;
            }

            switch($lowestLevel)
            {
                case Zend_Log::EMERG:
                case Zend_Log::ALERT:
                case Zend_Log::CRIT:
                case Zend_Log::ERR:
                case Zend_Log::WARN:
                    $firstRow = reset($this->_logEntries);
                    $inbox->addCritical(reset($firstRow), $this->getLogHtml());
                    break;
                case Zend_Log::DEBUG:
                    $firstRow = reset($this->_logEntries);
                    $inbox->addNotice(reset($firstRow), $this->getLogHtml());
                    break;
                default:
                    $firstRow = reset($this->_logEntries);
                    $inbox->addMinor(reset($firstRow), $this->getLogHtml());
                    break;
            }
        }

        $this->_logEntries = array();
    }


    /**
     * @param string $mode
     * @return $this
     */
    public function setMode($mode)
    {
        $this->_mode = $mode;
        return $this;
    }

    public function getMode() {
        return $this->_mode;
    }

    public function isModeCli() {
        return $this->_mode == self::LOG_MODE_CLI;
    }


    /**
     * @return string
     */
    public function getLogHtml()
    {
        $html = '';
        foreach ($this->_logEntries as $level => $entries)
        {
            foreach ($entries as $entry)
            {
                if (is_array($entry)) {
                    $html .= sprintf("%s:<br/>\n <pre>%s</pre><br/>\n", $level, $this->_renderCliTable($entry));
                } else {
                    $html .= sprintf("%s - %s<br/>\n", $level, $entry);
                }
            }

        }

        return $html;
    }


    /**
     * Get a human readable format
     * @param int $size
     * @return string
     */
    public function convert($size)
    {
        $unit=array('B','KB','MB','GB','TB','PB');
        return @round($size/pow(1024,($i=floor(log($size,1024))))).$unit[$i];
    }
}