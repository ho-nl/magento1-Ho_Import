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
class Ho_Import_Helper_Import_Customer extends Ho_Import_Helper_Import
{
    public $_mapCountryIso2ToIso3 = null;
    public $_mapCountryIso3ToIso2 = null;

    public function mapCountryIso2ToIso3($line, $field, $fallback) {
        $value = $this->_getMapper()->mapItem($field);

        if ($this->_mapCountryIso2ToIso3 === null) {
            $this->_loadCountryIsoMap();
        }

        if (isset($this->_mapCountryIso2ToIso3[$value])) {
            return $this->_mapCountryIso2ToIso3[$value];
        }
        return null;
    }

    public function mapCountryIso3ToIso2($line, $field, $fallback) {
        $value = $this->_getMapper()->mapItem($field);

        if ($this->_mapCountryIso3ToIso2 === null) {
            $this->_loadCountryIsoMap();
        }

        if (isset($this->_mapCountryIso3ToIso2[$value])) {
            return $this->_mapCountryIso3ToIso2[$value];
        }
        return null;
    }

    protected function _loadCountryIsoMap() {
        $this->_mapCountryIso2ToIso3 = array();
        $this->_mapCountryIso3ToIso2 = array();

        /** @var Mage_Directory_Model_Resource_Country_Collection $countryCollection */
        $countryCollection = Mage::getModel('directory/country')->getCollection();
        foreach($countryCollection as $country) {
            /** @var $country Mage_Directory_Model_Country */
            $this->_mapCountryIso2ToIso3[$country->getIso2Code()] = $country->getIso3Code();
            $this->_mapCountryIso3ToIso2[$country->getIso3Code()] = $country->getIso2Code();
        }
    }
}
