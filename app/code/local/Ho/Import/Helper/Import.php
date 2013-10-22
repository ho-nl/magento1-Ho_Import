<?php
/**
 * Ho_Import
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the H&O Commercial License
 * that is bundled with this package in the file LICENSE_HO.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.h-o.nl/license
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to info@h-o.com so we can send you a copy immediately.
 *
 * @category    Ho
 * @package     Ho_Import
 * @copyright   Copyright © 2012 H&O (http://www.h-o.nl/)
 * @license     H&O Commercial License (http://www.h-o.nl/license)
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
     * Import the product to all websites, this will return all the websites.
     *
     * @param array $line
     * @param $limit
     *
     * @return array|null
     */
    public function getAllWebsites($line, $limit) {
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
        if (! isset($line[$field]) && empty($line[$field])) {
            return '';
        }

        $value = $line[$field];
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


    /**
     * @param string      $line
     * @param null|string $field
     * @param string      $allowedTags
     *
     * @return string
     */
    public function stripHtmlTags($line, $field, $allowedTags = '<p><a><br>') {
        $content = trim(strip_tags($line[$field], $allowedTags));
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
     *
     * @param $line
     * @param $field
     * @param $default
     *
     * @return mixed
     */
    public function getFieldDefault($line, $field, $default) {
        if (isset($line[$field]) && ! empty($line[$field])) {
            return $line[$field];
        }
        return $default;
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
        return implode($glue, $this->getFieldMultiple($line, $fields));
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
        return isset($line[$field]) ? '1' : '0';
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
        $parts = array();
        foreach ($fields as $field) {
            if (isset($field['@']['ifvalue'])
                && (isset($line[$field['@']['ifvalue']]) && $line[$field['@']['ifvalue']]) == false) {
                continue;
            }

            $value = '';
            //field value
            if (isset($field['@']['field'])) {
                $fieldName = $field['@']['field'];

                if (isset($line[$fieldName])) {
                    $value = $line[$fieldName];
                }
            }

            //value support
            if (empty($value) && isset($field['@']['value'])) {
                $value = $field['@']['value'];
            }

            if ($withKeys && isset($fieldName)) {
                $parts[$fieldName] = $value;
            } else {
                $parts[] = $value;
            }
        }

        return $parts;
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
        $value = is_string($line) ? $line : $line[$field];

        foreach($mapping as $map) {
            if ($map['@']['from'] == $value) {
                $value = $map['@']['to'];
            }
        }

        return $value;
    }


    /**
     * Combine getFieldMultiple and getFieldMap
     *
     * @param $line
     * @param $fields
     * @param $mapping
     *
     * @return array
     */
    public function getFieldMultipleMap($line, $fields, $mapping) {
        $multipleFields = $this->getFieldMultiple($line, $fields, true);

        $results = array();
        foreach($multipleFields as $field => $value) {
            $results[] = $this->getFieldMap($value, $field, $mapping);
        }

        return $results;
    }



    /**
     * Download given file to ImportExport Tmp Dir (usually media/import)
     * @param string $url
     */
    protected $_fileCache = array();
    protected function _copyExternalImageFile($url)
    {
        if (isset($this->_fileCache[$url])) {
//            Mage::helper('ho_import/log')->log($this->__("Image already processed"), Zend_Log::DEBUG);
            return;
        }
        Mage::helper('ho_import/log')->log($this->__("Downloading image %s", $url));
        
        try {
            $this->_fileCache[$url] = true;
            $dir = $this->_getUploader()->getTmpDir();
            if (!is_dir($dir)) {
                mkdir($dir);
            }
            $fileName = $dir . DS . basename($url);
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