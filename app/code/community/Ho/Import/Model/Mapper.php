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
class Ho_Import_Model_Mapper
{
    const IMPORT_FIELD_CONFIG_PATH = 'global/ho_import/%s/fieldmap';

    /**
     * The fieldmap config for the current profile
     * @var null
     */
    protected $_fieldConfig = null;

    /** @var null|array */
    protected $_item = null;

    /** @var null|string */
    protected $_profileName = null;

    /** @var null|string */
    protected $_storeCode = null;


    /**
     * Set the item, array of xml-config.
     * @param array $item
     * @return $this
     */
    public function setItem(array &$item) {
        $this->_item = &$item;
        return $this;
    }


    /**
     * Get the item, which is the xml-config array
     * @return null|array
     */
    public function getItem() {
        return $this->_item;
    }


    /**
     * Set the profile_name
     * @param string $profileName
     * @return $this
     */
    public function setProfileName($profileName) {
        $this->_profileName = $profileName;
        return $this;
    }


    /**
     * Get the profile_name that is currently set.
     * @return null|string
     */
    public function getProfileName() {
        return $this->_profileName;
    }


    /**
     * Set the store_code
     * @param string $storeCode
     * @return $this
     */
    public function setStoreCode($storeCode) {
        $this->_storeCode = $storeCode;
        return $this;
    }


    /**
     * Get the store_code that is currently set.
     * @return null|string
     */
    public function getStoreCode() {
        return $this->_storeCode;
    }


    /**
     * Higher level method to get the values of a single field, for more details take a look at the
     * mapItem method.
     *
     * @param string $fieldName name of the field to be mapped
     * @return array|mixed
     */
    public function map($fieldName) {
        return $this->mapItem($this->getFieldConfig($fieldName));
    }


    /**
     * Get the value for a specific fieldConfig
     *
     * @param $fieldConfig
     * @return array|mixed
     */
    public function mapItem($fieldConfig) {
        if ($fieldConfig instanceof Mage_Core_Model_Config_Element) {
            $fieldConfig = $fieldConfig->asArray();
        }

        if ($fieldConfig === null) {
            return null;
        }

        $item = $this->getItem();
        $result = null;
        $attributes = isset($fieldConfig['@']) ? $fieldConfig['@'] : array();

        //iffieldvalue
        if (isset($attributes['iffieldvalue'])) {
            $field = $attributes['iffieldvalue'];
            if (! isset($item[$field]) || empty($item[$field])) {
                return null;
            }
        }

        //unlessfieldvalue
        if (isset($attributes['unlessfieldvalue'])) {
            $field = $attributes['unlessfieldvalue'];
            if (isset($item[$field]) && !empty($item[$field])) {
               return null;
           }
        }

        // use: ability to copy another field's value
        if (isset($attributes['use'])) {
            return $this->map($attributes['use']);
        }

        // helper: get field value with a helper
        if (isset($attributes['helper'])) {
            //get the helper and method
            //@todo add helper caching, usually there are about 3-4 helpers in total, which makes it
            //a bit unnecessary to load them each time.
            $helperParts = explode('::', $attributes['helper']);
            $helper = Mage::helper($helperParts[0]);
            $method = $helperParts[1];

            if (! method_exists($helper, $method)) {
                Mage::throwException(Mage::helper('ho_import')->__('Method %s could not be found for helper %s', $method, $helperParts[0]));
            }

            //prepare the arguments
            $args = $fieldConfig;
            unset($args['@']);
            unset($args['store_view']);
            array_unshift($args, $item);

            //get the results
            $result = call_user_func_array(array($helper, $method), $args);
        }

        /*
         * @todo sometimes there might be multiple elements, don't get properly loaded right now
         * 'images/image/src' should result in an array of two elements, currently just returns
         * one element.
         *  <product>
         *      <images>
         *          <image>
         *              <alt>alt text1</alt>
         *              <src>http://someurl.jpg</src>
         *          </image>
         *          <image>
         *              <alt>alt text1</alt>
         *              <src>http://someurl.jpg</src>
         *          </image>
         *      </images>
         *  </product>
         */
        // field: get the exact value of a field
        if (isset($attributes['field'])) {
            $field = $attributes['field'];
            //allow us to traverse an array, keys split by a slash.
            if (strpos($field, '/')) {
                $fieldParts = explode('/',$field);

                $value = $item;
                foreach ($fieldParts as $part) {
                    $value = isset($value[$part]) ? $value[$part] : null;
                }
            } else {
                $value = isset($item[$attributes['field']]) ? $item[$attributes['field']] : null;
            }

            $result = $value;
        }

        // value: get a fixed value
        if (isset($attributes['value'])) {
            $result = $attributes['value'];
        }

        // defaultvalue
        if (isset($attributes['defaultvalue']) && empty($result)) {
            $result = $attributes['defaultvalue'];
        }

        return is_array($result) ? array_values($result) : $result;
    }


    /**
     * Get the config for a specific field or the config for all the fields.
     * @param null $fieldName
     * @param null $profile
     *
     * @return mixed
     */
    public function getFieldConfig($fieldName = null, $profile = null) {
        if (is_null($profile)) {
            $profile = $this->getProfileName();
        }

        $fieldMapPath = sprintf(self::IMPORT_FIELD_CONFIG_PATH, $profile);

        if (! isset($this->_fieldConfig[$fieldMapPath]))
        {
            $fieldMapNode = Mage::getConfig()->getNode($fieldMapPath);
            if (! $fieldMapNode) {
                Mage::throwException(sprintf("Config path not found %s", $fieldMapPath));
            }

            if ($usePath = $fieldMapNode->getAttribute('use')) {
                $fieldMapPath = sprintf(self::IMPORT_FIELD_CONFIG_PATH, $usePath);
                $fieldMapNode = Mage::getConfig()->getNode($fieldMapPath);

                if (! $fieldMapNode) {
                    Mage::throwException(sprintf("Incorrect 'use' in <fieldmap use=\"%s\" />", $usePath));
                }
            }

            $columns = $fieldMapNode->children();
            $columnsData = array();

            $stores = array();
            foreach (Mage::app()->getStores() as $store) {
                /** @var $store Mage_Core_Model_Store */
                $stores[] = $store->getCode();
            }

            /** @var $column Mage_Core_Model_Config_Element */
            foreach ($columns as $key => $column) {

                foreach ($stores as $storeCode) {
                    if ($column->store_view->$storeCode) {
                        $columnsData[$storeCode][$key] = $column->store_view->$storeCode->asArray();
                    }
                }

                $columnsData['admin'][$key] = $columns->$key->asArray();
            }

            $this->_fieldConfig[$fieldMapPath] = $columnsData;
        }

        if (! is_null($fieldName)) {
            if (! isset($this->_fieldConfig[$fieldMapPath][$this->getStoreCode()][$fieldName])) {
                return null;
            }
            return $this->_fieldConfig[$fieldMapPath][$this->getStoreCode()][$fieldName];
        }

        return $this->_fieldConfig[$fieldMapPath];
    }


    /**
     * Get an array of all the fields including the store view specific fields
     * We use this to generate the column headers of the imported CSV.
     *
     * @param null $profile
     *
     * @return array
     */
    public function getFieldNames($profile = null) {
        if (is_null($profile)) {
            $profile = $this->getProfileName();
        }

        $fieldMapPath = sprintf(self::IMPORT_FIELD_CONFIG_PATH, $profile);
        $fieldMapNode = Mage::getConfig()->getNode($fieldMapPath);
        if (! $fieldMapNode) {
            Mage::throwException(sprintf("Config path not found %s", $fieldMapPath));
        }
        $columns = $fieldMapNode->children();
        $columnNames = array('_store' => '_store');
        foreach ($columns as $columnName => $columnData) {
            $columnNames[$columnName] = '';
        }
        return $columnNames;
    }
}
