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
?>
<?php
/* @var $installer Mage_Eav_Model_Entity_Setup */
$installer = $this;
$installer->startSetup();

if (!$installer->getAttribute(Mage_Catalog_Model_Product::ENTITY, 'ho_import_profile')) {
    $installer->addAttribute(Mage_Catalog_Model_Product::ENTITY, 'ho_import_profile', array(
        'label'                      => 'Import Profile',
        'group'                      => 'General',
        'sort_order'                 => 100,
        'type'                       => 'text',
        'note'                       => '',
        'default'                    => null,                                                     // eav_attribute.default_value                         admin input default value
        'input'                      => 'text',                                                   // eav_attribute.frontend_input                        admin input type (select, text, textarea etc)
        'required'                   => false,                                                     // eav_attribute.is_required                           required in admin
        'user_defined'               => false,                                                    // eav_attribute.is_user_defined                       editable in admin attributes section, false for not
        'unique'                     => false,                                                    // eav_attribute.is_unique                             unique value required
        'global'                     => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,  // catalog_eav_attribute.is_global                     (products only) scope
        'visible'                    => true,                                                     // catalog_eav_attribute.is_visible                    (products only) visible on admin
        'visible_on_front'           => false,                                                    // catalog_eav_attribute.is_visible_on_front           (products only) visible on frontend (store) attribute table
        'used_in_product_listing'    => false,                                                     // catalog_eav_attribute.used_in_product_listing       (products only) made available in product listing
        'searchable'                 => false,                                                    // catalog_eav_attribute.is_searchable                 (products only) searchable via basic search
        'visible_in_advanced_search' => false,                                                    // catalog_eav_attribute.is_visible_in_advanced_search (products only) searchable via advanced search
        'filterable'                 => false,                                                    // catalog_eav_attribute.is_filterable                 (products only) use in layered nav
        'filterable_in_search'       => false,                                                    // catalog_eav_attribute.is_filterable_in_search       (products only) use in search results layered nav
        'comparable'                 => false,                                                    // catalog_eav_attribute.is_comparable                 (products only) comparable on frontend
        'is_html_allowed_on_front'   => false,                                                     // catalog_eav_attribute.is_visible_on_front           (products only) seems obvious, but also see visible
        'apply_to'                   => NULL,                                                     // catalog_eav_attribute.apply_to                      (products only) which product types to apply to
        'is_configurable'            => false,                                                    // catalog_eav_attribute.is_configurable               (products only) used for configurable products or not
        'used_for_sort_by'           => false,                                                     // catalog_eav_attribute.used_for_sort_by              (products only) available in the 'sort by' menu
        'position'                   => 0,                                                        // catalog_eav_attribute.position                      (products only) position in layered naviagtion
        'used_for_promo_rules'       => false,                                                    // catalog_eav_attribute.is_used_for_promo_rules       (products only) available for use in promo rules
    ));
}


$installer->endSetup();
