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
 * @category  Ho
 * @package   Ho_Import
 * @author    Paul Hachmang – H&O <info@h-o.nl>
 * @copyright 2014 Copyright © H&O (http://www.h-o.nl/)
 * @license   H&O Commercial License (http://www.h-o.nl/license)
 */

class Ho_Import_Block_Adminhtml_Catalog_Product_Helper_Form_Profile extends Varien_Data_Form_Element_Abstract
{

    /**
     * Only display attribute when there actually are profiles.
     * @return mixed|string
     */
    public function toHtml()
    {
        return parent::toHtml();
    }


    /**
     * Render a table with the profile data.
     * @return string
     */
    public function getElementHtml()
    {
        if (! $this->getValue()) {
            return '<em>None</em>';
        }

        $value = $this->getValue();
        $html = '<div class="grid"><table cellspacing="0" class="data border">';

        $html .= '<thead>';
        $html .= '<tr class="headings">';
        $html .= '    <th>'.Mage::helper('ho_import')->__('Profile').'</th>';
        $html .= '    <th>'.Mage::helper('ho_import')->__('Created At').'</th>';
        $html .= '    <th>'.Mage::helper('ho_import')->__('Updated At').'</th>';
//        $html .= '    <th></th>';
        $html .= '</tr>';
        $html .= '</thead>';

        $html .= '<tbody>';
        foreach ($value as $i => $data) {
            if ($i == count($value) - 1) {
                $html .= $i == count($value) - 1 ? '<tr class="last">' : '<tr>';
            }
            $html .= '    <td><strong>'.$data['profile'].'</strong></td>';
            $html .= '    <td>'.$data['created_at'].'</td>';
            $html .= '    <td>'.$data['updated_at'].'</td>';
//            $html .= '    <td class="last">[unlink]</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table></div>';
        return $html;
    }
}