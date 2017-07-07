<?php
/**
 * Copyright © 2017 H&O E-commerce specialisten B.V. (http://www.h-o.nl/)
 * See LICENSE.txt for license details.
 */

class Ho_Import_Helper_Import extends Mage_Core_Helper_Abstract
{
    protected $_today = null;

    /** @var null|array */
    protected $_websiteIds = null;

    /** @var null */
    protected $_fileUploader = null;

    /**
     * Return import configuration.
     *
     * @return Ho_Import_Model_Config
     */
    public function getConfig()
    {
        return Mage::getSingleton('ho_import/config');
    }

    /**
     * @param array $line
     * @param $limit
     * @return array|null
     */
    public function getAllWebsites($line, $limit = null)
    {
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
    public function findReplace($line, $field, $findReplaces = array(), $trim = false)
    {
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


    public function callUserFunc($line, $func, $args)
    {
        foreach ($args as $key => $arg) {
            $args[$key] = $this->_getMapper()->mapItem($arg);
        }
        return call_user_func_array($func, $args);
    }

    public function parsePrice($line, $field)
    {
        $s = $this->_getMapper()->mapItem($field);
        // convert "," to "."
        $s = str_replace(',', '.', $s);

        // remove everything except numbers and dot "."
        $s = preg_replace("/[^0-9\.]/", "", $s);

        // remove all seperators from first part and keep the end
        $s = str_replace('.', '', substr($s, 0, -3)) . substr($s, -3);

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
    public function stripHtmlTags($line, $field, $allowedTags = '<p><a><br>')
    {
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
    public function getHtmlComment($line, $comment = '')
    {
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
    public function formatField($line, $format, $fields)
    {
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
    public function truncate($line, $field, $length = 80, $etc = '…', $breakWords = true)
    {
        $string = $this->_getMapper()->mapItem($field);
        $remainder = '';
        return Mage::helper('core/string')->truncate($string, $length, $etc, $remainder, $breakWords);
    }


    /**
     * Get multiple values
     *
     * @param $line
     * @param $values
     *
     * @return array
     */
    public function getValueMultiple($line, $values)
    {
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
    public function getFieldDefault($line, $field, $default)
    {
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
    public function getFieldCombine($line, $fields, $glue = ' ')
    {

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

        $result = $this->getFieldMultiple($line, $fields);
        if (! $result) {
            return null;
        }

        return implode($glue, array_filter($result));
    }


    /**
     * @param $line
     */
    public function getFieldFallback($line)
    {
        $fields = func_get_args();
        array_shift($fields);

        $values = $this->getFieldMultiple($line, $fields);
        foreach ($values as $value) {
            if ($value) {
                return $value;
            }
        }
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

        $result = $this->_getMapper()->mapItem($field);
        if (! $result) {
            return null;
        }

        return explode($split, $result);
    }


    /**
     * Checks if a field has a value
     *
     * @param $line
     * @param $field
     *
     * @return string
     */
    public function getFieldBoolean($line, $field)
    {
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
    public function getFieldMultiple($line, $fields, $withKeys = false)
    {
        $mapper = $this->_getMapper();

        $parts = array();
        foreach ($fields as $fieldConfig) {
            $values = $mapper->mapItem($fieldConfig);
            if (! is_array($values)) {
                $values = array($values);
            }

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
     * @param int|null $offset
     *
     * @return array
     */
    public function getFieldLimit($line, $field, $limit = null, $offset = null)
    {
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
    public function getFieldMap($line, $field, $mapping)
    {
        $values = $this->_getMapper()->mapItem($field);
        if (! is_array($values)) {
            $values = array($values);
        }

        foreach ($values as $key => $value) {
            foreach ($mapping as $map) {
                $from = html_entity_decode($map['@']['from']);
                if ($from == $value) {
                    $values[$key] = html_entity_decode($map['@']['to']);
                }
            }
        }

        return $values;
    }


    /** @var Ho_Import_Model_Template_Filter */
    protected $_filter;


    /**
     * @param $line
     * @param $template
     *
     * @return string
     * @throws Exception
     */
    public function templateEngine($line, $template)
    {
        if ($this->_filter === null) {
            $this->_filter = Mage::getModel('ho_import/template_filter');
            $this->_filter->setMapper($this->_getMapper());
            $this->_filter->setVariables(['helper' => $this]);
        }

        $template = trim($template, "\n ");
        $this->_filter->setLine($line);
        $result = $this->_filter->filter($template);
        return $result;
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
    public function getFieldCounter($line, $countConfig, $valueConfig = null)
    {
        $count = count($this->_getMapper()->mapItem($countConfig));
        $value = $this->_getMapper()->mapItem($valueConfig);
        $values = array();
        for ($i = 0; $i < $count; $i++) {
            $values[] = is_null($value) ? $i + 1 : (is_array($value) ? $value[$i] : $value);
        }
        return $values;
    }


    public function ifFieldsValue($line, $fields, $valueField)
    {
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


    /**
     * @param $line
     *
     * @deprecated
     * @return mixed
     */
    public function getMediaAttributeId($line)
    {
        return $this->getAttributeId($line, 'media_gallery');
    }


    protected $_attributeMapping = array();
    public function getAttributeId($line, $attribute)
    {
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

    public function getCurrentDate($line)
    {
        if ($this->_today === null) {
            $currentTimestamp = Mage::getSingleton('core/date')->timestamp(time());
            $this->_today = date('d-m-Y', $currentTimestamp);
        }

        return $this->_today;
    }


    public function timestampToDate($line, $field, $timezoneFrom = null, $offset = null)
    {
        $values = $this->_getMapper()->mapItem($field);
        if (! is_array($values)) {
            $values = array($values);
        }

        if ($timezoneFrom) {
            $timezoneFrom = new DateTimeZone($timezoneFrom);
        }

        foreach ($values as $key => $value) {
            $value = $value ?  '@'.$value : 'now';
            $datetime = new DateTime($value, $timezoneFrom);
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
     * Files can be downloaded through FTP by passing the FTP credentials in the url:
     * ftp://username:password@hostname
     * @param string $url
     */
    protected $_fileCache = array();
    protected function _copyExternalImageFile($url, $filename = null)
    {
        if (isset($this->_fileCache[$url])) {
            return;
        }

        try {
            $parsedUrl = parse_url($url);
            $this->_fileCache[$url] = true;
            $dir = $this->_getUploader()->getTmpDir();
            if (!is_dir($dir)) {
                mkdir($dir);
            }

            $fileName = $dir . DS . (! is_null($filename) ? $filename : basename($url));
            $fileHandle = fopen($fileName, 'w+');
            $ch = curl_init();
            /* todo reuse curl handle to increase performance
             * http://stackoverflow.com/questions/3787002/reusing-the-same-curl-handle-big-performance-increase
             * $this->_curlHandles = []
             * $this->_curlHandles[{url_without_file}] = {curlHandle}
             * if (isset($this->_curlHandles[{url_without_file}]) use curl handle
             * else create new curl handle
             */

            $statusOk = -1;
            if (! isset($parsedUrl['path'])) {
                Mage::throwException('No Path detected for URL');
            }

            if (! isset($parsedUrl['scheme'])) {
                Mage::throwException('No URL scheme detected ftp://, http://, etc.');
            }

            switch ($parsedUrl['scheme']) {
                case 'ftp':
                    $statusOk = 226;
                    if (!isset($parsedUrl['user'], $parsedUrl['pass'])) {
                        Mage::helper('ho_import/log')->log($this->__(
                                'Invalid URL scheme detected, please enter FTP credentials'), Zend_Log::ERR);
                    }
                    $url = $parsedUrl['scheme'] . '://' . $parsedUrl['host'] . $parsedUrl['path'];
                    curl_setopt($ch, CURLOPT_USERPWD, $parsedUrl['user'] . ':' . $parsedUrl['pass']);
                    curl_setopt($ch, CURLOPT_URL, $url);
                    break;
                case 'http':
                case 'https':
                    $statusOk = 200;
                    curl_setopt($ch, CURLOPT_URL, $url);
                    break;
                default:
                    Mage::helper('ho_import/log')->log($this->__(
                        $this->__('Invalid URL scheme detected')), Zend_Log::ERR);
            }

            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_FILE, $fileHandle);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_exec($ch);

            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            fclose($fileHandle);

            if ($code !== $statusOk) {
                $this->_fileCache[$url] = $code;
                Mage::helper('ho_import/log')->log($this->__(
                        "Returned status code %s while downloading image %s", $code, $url), Zend_Log::ERR);
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


    /**
     * @param $line
     * @param array $key Key to search
     * @param array $sourceModel Source model to search key in
     * @return bool|string
     */
    public function getOptionValue($line, $key, $sourceModel)
    {
        $key = $this->_getMapper()->mapItem($key);
        $sourceModel = $this->_getMapper()->mapItem($sourceModel);

        /** @var Mage_Eav_Model_Entity_Attribute_Source_Abstract $sourceModel */
        $sourceModel = Mage::getSingleton($sourceModel);

        return $sourceModel->getOptionText($key);
    }
}
