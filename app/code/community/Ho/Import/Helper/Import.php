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
 *
 *
 */
class Ho_Import_Helper_Import extends Mage_Core_Helper_Abstract
{
    protected $_today = null;

    /** @var null|array */
    protected $_websiteIds = null;

    /** @var null */
    protected $_fileUploader = null;


    /**
     * @param array $line
     * @param $limit
     * @return array|null
     */
    public function getAllWebsites($line, $limit = null) {
        if ($this->_websiteIds === null) {
            $this->_websiteIds = array();
            foreach (Mage::app()->getWebsites() as $website) {
                /** @var $website Mage_Core_Model_Website */

                $this->_websiteIds[] = $website->getCode();
            }
        }

        if ($limit) {
            return array_slice($this->_websiteIds, 0, $limit);
        }

        return $this->_websiteIds;
    }


    /**
     * @param       $line
     * @param       $field
     * @param array $findReplaces
     * @param bool  $trim
     *
     * @return mixed|string
     */
    public function findReplace($line, $field, $findReplaces = array(), $trim = false) {
        $value = $this->_getMapper()->mapItem($field);
        if (! $value) {
            return '';
        }

        foreach ($findReplaces as $findReplace) {
            if (strpos($value, $findReplace['@']['find']) !== false) {
                $value = str_replace($findReplace['@']['find'], $findReplace['@']['replace'], $value);
            }
        }

        if ($trim) {
            return trim($value);
        }

        return $value;
    }


    public function callUserFunc($line, $func, $args) {
        foreach ($args as $key => $arg) {
            $args[$key] = $this->_getMapper()->mapItem($arg);
        }
        return call_user_func_array($func, $args);
    }

    public function parsePrice($line, $field) {
        $s = $this->_getMapper()->mapItem($field);
        // convert "," to "."
        $s = str_replace(',', '.', $s);

        // remove everything except numbers and dot "."
        $s = preg_replace("/[^0-9\.]/", "", $s);

        // remove all seperators from first part and keep the end
        $s = str_replace('.', '',substr($s, 0, -3)) . substr($s, -3);

        // return float
        return (float) $s;
    }


    /**
     * @param string      $line
     * @param null|string $field
     * @param string      $allowedTags
     *
     * @return string
     */
    public function stripHtmlTags($line, $field, $allowedTags = '<p><a><br>') {
        $value = $this->_getMapper()->mapItem($field);
        $content = trim(strip_tags($value, $allowedTags));
        return $content ? $content : '<!--empty-->';
    }


    /**
     * Get a simple HTML comment (can't be added through XML due to XML limitations).
     *
     * @param        $line
     * @param string $comment
     *
     * @return string
     */
    public function getHtmlComment($line, $comment = '') {
        return '<!--'.$comment.'-->';
    }


    /**
         * Allows you to format a field using vsprintf
     *
     * @param $line
     * @param $format
     * @param $fields
     *
     * @return string
     */
    public function formatField($line, $format, $fields) {
        $values = array();
        foreach ($fields as $key => $field) {
            $value = $this->_getMapper()->mapItem($field);
            $values[$key] = is_array($value) ? reset($value) : $value;
        }

        $result = vsprintf($format, $values);
        return $result;
    }


    /**
     * @param        $line
     * @param        $field
     * @param int    $length
     * @param string $etc
     * @param bool   $breakWords
     */
    public function truncate($line, $field, $length = 80, $etc = '…', $breakWords = true) {
        $string = $this->_getMapper()->mapItem($field);
        return Mage::helper('core/string')->truncate($string, $length, $etc, $remainder = '', $breakWords);
    }


    /**
     * Get multiple values
     *
     * @param $line
     * @param $values
     *
     * @return array
     */
    public function getValueMultiple($line, $values) {
        return array_values($values);
    }

    /**
     * Get the value of a field but fallback to a default if the value isn't present.
     * @deprecated use defaultvalue attribute
     * @param $line
     * @param $field
     * @param $default
     *
     * @return mixed
     */
    public function getFieldDefault($line, $field, $default) {
        $value = $this->_getMapper()->mapItem($field);
        return $value ? $value : $default;
    }


    /**
     * Combine multiple fields into one string
     *
     * @param        $line
     * @param        $fields
     * @param string $glue
     *
     * @return string
     */
    public function getFieldCombine($line, $fields, $glue = ' ') {

        switch ($glue) {
            case '\n': //linefeed (LF or 0x0A (10) in ASCII)
                $glue = "\n";
                break;
            case '\r': //carriage return (CR or 0x0D (13) in ASCII)
                $glue = "\r";
                break;
            case '\t': //horizontal tab (HT or 0x09 (9) in ASCII)
                $glue = "\t";
                break;
            case '\v': //vertical tab (VT or 0x0B (11) in ASCII) (since PHP 5.2.5)
                $glue = "\v";
                break;
            case '\e': //escape (ESC or 0x1B (27) in ASCII) (since PHP 5.4.0)
                $glue = "\e";
                break;
            case '\f': //form feed (FF or 0x0C (12) in ASCII) (since PHP 5.2.5)
                $glue = "\f";
                break;
        }

        return implode($glue, $this->getFieldMultiple($line, $fields));
    }


    /**
     * @param array $line
     * @param array $field
     * @param string $split
     * @return array
     */
    public function getFieldSplit($line, $field, $split = ' ')
    {
        switch ($split) {
            case '\n': //linefeed (LF or 0x0A (10) in ASCII)
                $split = "\n";
                break;
            case '\r': //carriage return (CR or 0x0D (13) in ASCII)
                $split = "\r";
                break;
            case '\t': //horizontal tab (HT or 0x09 (9) in ASCII)
                $split = "\t";
                break;
            case '\v': //vertical tab (VT or 0x0B (11) in ASCII) (since PHP 5.2.5)
                $split = "\v";
                break;
            case '\e': //escape (ESC or 0x1B (27) in ASCII) (since PHP 5.4.0)
                $split = "\e";
                break;
            case '\f': //form feed (FF or 0x0C (12) in ASCII) (since PHP 5.2.5)
                $split = "\f";
                break;
        }
        return explode($split, $this->_getMapper()->mapItem($field));
    }


    /**
     * Checks if a field has a value
     *
     * @param $line
     * @param $field
     *
     * @return string
     */
    public function getFieldBoolean($line, $field) {
        $value = $this->_getMapper()->mapItem($field);
        return $value ? '1' : '0';
    }


    /**
     * Get multiple rows in one field.
     *
     * @param      $line
     * @param      $fields
     *
     * @param bool $withKeys
     *
     * @return array
     */
    public function getFieldMultiple($line, $fields, $withKeys = false) {

        $mapper = $this->_getMapper();

        $parts = array();
        foreach ($fields as $fieldConfig) {
            $values = $mapper->mapItem($fieldConfig);
            if (! is_array($values)) { $values = array($values); }

            foreach ($values as $value) {
                if ($withKeys && isset($fieldName)) {
                    $parts[$fieldName] = $value;
                } else {
                    $parts[] = $value;
                }
            }
        }

        return $parts;
    }


    /**
     * @param $line
     * @param $field
     * @param $limit
     *
     * @return array
     */
    public function getFieldLimit($line, $field, $limit = null, $offset = null) {
        $values = $this->_getMapper()->mapItem($field);
        $limit = $this->_getMapper()->mapItem($limit);
        $offset = $this->_getMapper()->mapItem($offset) ?: 0;

        if (! is_array($values)) {
            $values = array($values);
        }

        return array_slice($values, $offset, $limit);
    }


    /**
     * Map fields
     *
     * @param array|string $line
     * @param string $field
     * @param array $mapping
     *
     * @return string
     */
    public function getFieldMap($line, $field, $mapping) {
        $values = $this->_getMapper()->mapItem($field);
        if (! is_array($values)) {
            $values = array($values);
        }

        foreach ($values as $key => $value) {
            foreach($mapping as $map) {
                $from = html_entity_decode($map['@']['from']);
                if ($from == $value) {
                    $values[$key] = htmlspecialchars_decode($map['@']['to']);
                }
            }
        }

        return $values;
    }


    /**
     * Count another field an give it or a list or a value.
     * @param      $line
     * @param      $countConfig
     * @param null $valueConfig
     *
     * @internal param $countField
     * @return array|null
     */
    public function getFieldCounter($line, $countConfig, $valueConfig = null) {
        $count = count($this->_getMapper()->mapItem($countConfig));
        $value = $this->_getMapper()->mapItem($valueConfig);
        $values = array();
        for ($i = 0; $i < $count; $i++) {
            $values[] = is_null($value) ? $i : $value;
        }
        return $values;
    }


    public function ifFieldsValue($line, $fields, $valueField) {
        $values = $this->getFieldMultiple($line, $fields);
        $valid = true;
        foreach ($values as $value) {
            if (is_array($value)) {
                foreach ($value as $valueItem) {
                    if (empty($valueItem)) {
                        $valid = false;
                        break 2;
                    }
                }
            } else {
                if (empty($value)) {
                    $valid = false;
                    break;
                }
            }
        }

        if ($valid) {
            return $this->_getMapper()->mapItem($valueField);
        }
        return null;
    }


    public function getMediaAttributeId($line) {
        return $this->getMediaAttributeId('media_gallery');
    }


    protected $_attributeMapping = array();
    public function getAttributeId($line, $attribute) {
        $attributeCode = is_string($attribute)
            ? $attribute : $this->_getMapper()->mapItem($attribute);

        if (! isset($this->_attributeMapping[$attributeCode])) {

            $this->_attributeMapping[$attributeCode] =
                Mage::getSingleton('catalog/product')->getResource()
                            ->getAttribute($attributeCode)->getId();
        }
        return $this->_attributeMapping[$attributeCode];
    }


    public function getMediaImage($line, $image, $limit = null, $filename = null, $ext = null)
    {
        $images = (array) $this->_getMapper()->mapItem($image);
        $images = array_filter($images);
        $filenameBase = $this->_getMapper()->mapItem($filename);
        $ext = $this->_getMapper()->mapItem($ext);

        if ($limit) {
            $images = array_slice($images, 0, $limit);
        }
        foreach ($images as $key => $image) {
            $image = str_replace(' ', '%20', $image);
            if ($ext == null) {
                $ext = pathinfo($image, PATHINFO_EXTENSION);
            }
            if ($filenameBase !== null) {
                if (count($images) > 1) {
                    $filename = $filenameBase.'-'.($key+1).'.'.$ext;
                } else {
                    $filename = $filenameBase.'.'.$ext;
                }
            } else {
                $filename = basename($image);
            }

            if (!is_file($this->_getUploader()->getTmpDir() . DS . $filename)) {
                $this->_copyExternalImageFile($image, $filename, $ext);
            }

            if ($filename !== $images[$key]) {
                $images[$key] = '/'.$filename;
            }
        }

        return array_values($images);
    }


    public function timestampToDate($line, $field, $timezoneFrom = null, $offset = null) {
        $values = $this->_getMapper()->mapItem($field);
        if (! is_array($values)) {
            $values = array($values);
        }

        if ($timezoneFrom) {
            $timezoneFrom = new DateTimeZone($timezoneFrom);
        }

        foreach ($values as $key => $value) {
            $datetime = new DateTime('@'.$value, $timezoneFrom);
            $datetime->setTimezone(new DateTimeZone(date_default_timezone_get()));

            if ($offset) {
                $dateInterval = DateInterval::createFromDateString($offset);
                $datetime->add($dateInterval);
            }

            $values[$key] = $datetime->format('c');
        }

        return $values;
    }


    /**
     * @return Ho_Import_Model_Mapper
     */
    protected function _getMapper()
    {
        return Mage::getSingleton('ho_import/mapper');
    }


    /**
     * Download given file to ImportExport Tmp Dir (usually media/import)
     * @param string $url
     */
    protected $_fileCache = array();
    protected function _copyExternalImageFile($url, $filename = null)
    {
        if (isset($this->_fileCache[$url])) {
            return;
        }
//        Mage::helper('ho_import/log')->log($this->__("Downloading image %s", $url));
        
        try {
            $this->_fileCache[$url] = true;
            $dir = $this->_getUploader()->getTmpDir();
            if (!is_dir($dir)) {
                mkdir($dir);
            }

            $fileName = $dir . DS . (! is_null($filename) ? $filename : basename($url));
            $fileHandle = fopen($fileName, 'w+');
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_FILE, $fileHandle);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_exec($ch);

            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            fclose($fileHandle);

            if ($code !== 200) {
                $this->_fileCache[$url] = $code;
                Mage::helper('ho_import/log')->log($this->__("Returned status code %s while downloading image", $code), Zend_Log::ERR);
                unlink($fileName);
            }

        } catch (Exception $e) {
            Mage::throwException('Download of file ' . $url . ' failed: ' . $e->getMessage());
        }
    }


    /**
     * Returns an object for upload a media files
     */
    protected function _getUploader()
    {
        if (is_null($this->_fileUploader)) {
            $this->_fileUploader    = new Mage_ImportExport_Model_Import_Uploader();

            $this->_fileUploader->init();

            $tmpDir     = Mage::getConfig()->getOptions()->getMediaDir() . '/import';
            $destDir    = Mage::getConfig()->getOptions()->getMediaDir() . '/catalog/product';
            if (!is_writable($destDir)) {
                @mkdir($destDir, 0777, true);
            }
            if (!$this->_fileUploader->setTmpDir($tmpDir)) {
                Mage::throwException("File directory '{$tmpDir}' is not readable.");
            }
            if (!$this->_fileUploader->setDestDir($destDir)) {
                Mage::throwException("File directory '{$destDir}' is not writable.");
            }
        }
        return $this->_fileUploader;
    }
}