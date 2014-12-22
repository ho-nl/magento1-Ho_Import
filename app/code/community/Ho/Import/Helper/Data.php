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
 *
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
     * Convert strings with underscores into CamelCase
     *
     * @param string $string
     * @param bool   $first_char_caps
     *
     * @return mixed
     */
    public function underscoreToCamelCase($string, $firstCharCaps = true)
    {
        if ($firstCharCaps === true) {
            $string[0] = strtoupper($string[0]);
        }
        $func = create_function('$c', 'return strtoupper($c[1]);');
        return preg_replace_callback('/_([a-z])/', $func, $string);
    }
}