<?php
/**
 * Copyright © 2017 H&O E-commerce specialisten B.V. (http://www.h-o.nl/)
 * See LICENSE.txt for license details.
 */

class Ho_Import_Helper_Data extends Mage_Core_Helper_Abstract
{
    protected $_currentDatetime = null;

    public function getCurrentDatetime()
    {
        if ($this->_currentDatetime === null) {
            $this->_currentDatetime = Mage::getModel('core/date')->date('Y-m-d H:i:s');
        }

        return $this->_currentDatetime;
    }

    /**
     * Convert strings with underscores into CamelCase.
     *
     * @param string    $string
     * @param bool      $firstCharCaps
     *
     * @return string
     */
    public function underscoreToCamelCase($string, $firstCharCaps = true)
    {
        if ($firstCharCaps === true) {
            $string[0] = strtoupper($string[0]);
        }

        $func = create_function('$c', 'return strtoupper($c[1]);');

        return preg_replace_callback('/_([a-z])/', $func, $string);
    }

    /**
     * Checks if the current environment is in the shop's admin area.
     *
     * @return bool
     */
    public function isAdmin()
    {
        if (Mage::app()->getStore()->isAdmin()) {
            return true;
        }

        // Fallback check in case the previous check returns a false negative.
        if (Mage::getDesign()->getArea() === 'adminhtml') {
            return true;
        }

        return false;
    }
}
