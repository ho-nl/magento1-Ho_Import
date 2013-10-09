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
 * @copyright   Copyright © 2013 H&O (http://www.h-o.nl/)
 * @license     H&O Commercial License (http://www.h-o.nl/license)
 * @author      Paul Hachmang – H&O <info@h-o.nl>
 *
 * 
 */
?>
<?php
/* @var $installer Mage_Eav_Model_Entity_Setup */
$installer = $this;
$installer->startSetup();

$installer->addAttribute(Mage_Catalog_Model_Product::ENTITY, 'ho_import_profile', array(
    'input'                      => 'text',
    'type'                       => 'varchar',
    'label'                      => 'Import Profile Source',
    'required'                   => false,
    'user_defined'               => false,
    'sort_order'                 => 14,
    'global'                     => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,
    'group'                      => 'General',
    'note'                       => 'The name of the import profile the product belongs to.',
));

$attribute = $installer->getAttribute(Mage_Catalog_Model_Product::ENTITY, 'ho_import_profile');
$attributeId = $attribute['attribute_id'];

$attributeSetIds = $installer->getAllAttributeSetIds(Mage_Catalog_Model_Product::ENTITY);
foreach ($attributeSetIds as $attributeSetId) {
    $attributeGroupId = $installer->getDefaultAttributeGroupId(Mage_Catalog_Model_Product::ENTITY,  $attributeSetId);
    $installer->addAttributeToSet(Mage_Catalog_Model_Product::ENTITY, $attributeSetId, $attributeGroupId, $attributeId);
}

$installer->endSetup();
