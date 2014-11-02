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
 * @category  Ho
 * @package   Ho_Import
 * @author    Paul Hachmang – H&O <info@h-o.nl>
 * @copyright 2014 Copyright © H&O (http://www.h-o.nl/)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @link      https://github.com/ho-nl/Ho_Import
 */
class Ho_Import_Helper_Import_Category extends Ho_Import_Helper_Import
{
    /**
     * Generate an URL-key from multiple fields
     *
     * @param array  $line   Imported line
     * @param array  $fields $this::_getMapper compatible array
     * @param string $glue   Join value
     * @param string $suffix Placed after the string eg. `.html`
     *
     * @return string
     */
    public function getUrlKey($line, $fields, $glue = '-', $suffix = '')
    {
        $string = Mage::helper('ho_import/import')
            ->getFieldCombine($line, $fields, $glue, $suffix);

        return Mage::getSingleton('catalog/product')
            ->formatUrlKey($string).$suffix;
    }
}
