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

if ($installer->tableExists($installer->getTable('ho_import/entity'))) {
    $installer->getConnection()->dropTable($installer->getTable('ho_import/entity'));
}

/**
 * Create table 'ho_import/entity'
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('ho_import/entity'))

    ->addColumn('profile', Varien_Db_Ddl_Table::TYPE_TEXT, 60, array(
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '0',
        ), 'Profile Name')
    ->addColumn('entity_type_id', Varien_Db_Ddl_Table::TYPE_SMALLINT, null, array(
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '0',
        ), 'Entity Type ID')
    ->addColumn('entity_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'usigned' => true,
        'nullable' => false,
        'identity'  => false,
        'primary'   => false,
        ), 'Entity ID')
    ->addColumn('created_at', Varien_Db_Ddl_Table::TYPE_DATETIME, null, array(
        'nullable'  => false,
        ), 'Creation Time')
    ->addColumn('updated_at', Varien_Db_Ddl_Table::TYPE_DATETIME, null, array(
        'nullable'  => false,
        ), 'Update Time')
    ->addIndex(
        $installer->getIdxName(
            'ho_import/entity',
            array('profile', 'entity_type_id', 'entity_id'),
            Varien_Db_Adapter_Interface::INDEX_TYPE_UNIQUE
        ),
        array('profile', 'entity_type_id', 'entity_id'),
        array('type' => Varien_Db_Adapter_Interface::INDEX_TYPE_UNIQUE))
    ->addIndex(
        $installer->getIdxName(
            'ho_import/entity',
            array('profile', 'entity_type_id', 'updated_at'),
            Varien_Db_Adapter_Interface::INDEX_TYPE_INDEX
        ),
        array('profile', 'entity_type_id', 'updated_at'),
        array('type' => Varien_Db_Adapter_Interface::INDEX_TYPE_INDEX))
    ->addIndex(
        $installer->getIdxName(
            'ho_import/entity',
            array('entity_type_id', 'updated_at'),
            Varien_Db_Adapter_Interface::INDEX_TYPE_INDEX
        ),
        array('entity_type_id', 'entity_id'),
        array('type' => Varien_Db_Adapter_Interface::INDEX_TYPE_INDEX))
    ->addForeignKey(
        $installer->getFkName(
            'ho_import/entity',
            'entity_type_id',
            'eav/entity_type',
            'entity_type_id'
        ),
        'entity_type_id', $installer->getTable('eav/entity_type'), 'entity_type_id',
         Varien_Db_Ddl_Table::ACTION_CASCADE, Varien_Db_Ddl_Table::ACTION_CASCADE)
    ->setComment('Entity link with import');
$installer->getConnection()->createTable($table);


/**
 * Migrate product data to new table
 */
$productCollection = Mage::getResourceModel('catalog/product_collection');
$entityTypeId = $productCollection->getResource()->getTypeId();
$productCollection->getSelect()
    ->reset('columns')
    ->columns(array('entity_id','created_at','updated_at'));

$productCollection->joinAttribute(
    'ho_import_profile',
    'catalog_product/ho_import_profile',
    'entity_id',
    null,
    'inner'
);
$productCollection->getSelect()->where('`at_ho_import_profile_default`.`value` IS NOT NULL');

$entityProfileData = $installer->getConnection()->fetchAll($productCollection->getSelect());
$insertData = array();
foreach ($entityProfileData as $key => $entityProfile) {
    $profiles = explode(',', $entityProfile['ho_import_profile']);
    unset($entityProfile['ho_import_profile']);
    foreach ($profiles as $profile)
    {
        $entityProfile['profile'] = $profile;
        $entityProfile['entity_type_id'] = $entityTypeId;
        $insertData[] = $entityProfile;
    }
    unset($entityProfileData[$key]);
}

if ($insertData) {
    $installer->getConnection()->insertMultiple($installer->getTable('ho_import/entity'), $insertData);
    unset($insertData);
    unset($entityProfileData);
}


/**
 * Migrate product data to new table
 */
$categoryCollection = Mage::getResourceModel('catalog/category_collection');
$entityTypeId = $categoryCollection->getResource()->getTypeId();
$categoryCollection->getSelect()
    ->reset('columns')
    ->columns(array('entity_id','created_at','updated_at'));

$categoryCollection->joinAttribute(
    'ho_import_profile',
    'catalog_category/ho_import_profile',
    'entity_id',
    null,
    'inner'
);
$categoryCollection->getSelect()->where('`at_ho_import_profile`.`value` IS NOT NULL');

$entityProfileData = $installer->getConnection()->fetchAll($categoryCollection->getSelect());
$insertData = array();
foreach ($entityProfileData as $key => $entityProfile) {
    $profiles = explode(',', $entityProfile['ho_import_profile']);
    unset($entityProfile['ho_import_profile']);
    foreach ($profiles as $profile)
    {
        $entityProfile['profile'] = $profile;
        $entityProfile['entity_type_id'] = $entityTypeId;
        $insertData[] = $entityProfile;
    }
    unset($entityProfileData[$key]);
}

if ($insertData) {
    $installer->getConnection()->insertMultiple($installer->getTable('ho_import/entity'), $insertData);
    unset($insertData);
    unset($entityProfileData);
}

$installer->updateAttribute(
    Mage_Catalog_Model_Category::ENTITY,
    'ho_import_profile',
    'backend_model',
    'ho_import/entity_attribute_backend_profile'
);

$installer->updateAttribute(
    Mage_Catalog_Model_Product::ENTITY,
    'ho_import_profile',
    'backend_model',
    'ho_import/entity_attribute_backend_profile'
);

$installer->updateAttribute(
    Mage_Catalog_Model_Product::ENTITY,
    'ho_import_profile',
    'frontend_input_renderer',
    'ho_import/adminhtml_catalog_product_helper_form_profile'
);

$installer->updateAttribute(
    Mage_Catalog_Model_Category::ENTITY,
    'ho_import_profile',
    'frontend_input_renderer',
    'ho_import/adminhtml_catalog_product_helper_form_profile'
);


if (!$installer->getAttribute('customer', 'ho_import_profile')) {
    $entityTypeId     = $installer->getEntityTypeId('customer');
    $attributeSetId   = $installer->getDefaultAttributeSetId($entityTypeId);
    $attributeGroupId = $installer->getDefaultAttributeGroupId($entityTypeId, $attributeSetId);

    $installer->addAttribute('customer', 'ho_import_profile', array(
        'input'         => 'text',
        'type'          => 'text',
        'label'         => 'Import Profile',
        'visible'       => 1,
        'required'      => 0,
        'user_defined'  => 0,
        'backend'       => 'ho_import/entity_attribute_backend_profile'
    ));

    $installer->addAttributeToGroup($entityTypeId, $attributeSetId, $attributeGroupId, 'ho_import_profile', '130');
    $attribute = Mage::getSingleton('eav/config')->getAttribute('customer', 'ho_import_profile');
    $attribute->setData('used_in_forms', array('adminhtml_customer'));
    $attribute->setData('sort_order', 200);
    $attribute->save();
}

$installer->endSetup();
