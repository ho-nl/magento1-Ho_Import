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
/* @var $installer Mage_Eav_Model_Entity_Setup */
$installer = $this;
$installer->startSetup();

// Reset frontend_input_render.
$installer->updateAttribute(
    Mage_Catalog_Model_Product::ENTITY,
    'ho_import_profile',
    'frontend_input_renderer',
    NULL
);

$installer->updateAttribute(
    Mage_Catalog_Model_Category::ENTITY,
    'ho_import_profile',
    'frontend_input_renderer',
    NULL
);

// Set frontend input.
$installer->updateAttribute(
    Mage_Catalog_Model_Product::ENTITY,
    'ho_import_profile',
    'frontend_input',
    'ho_import_profile'
);

$installer->updateAttribute(
    Mage_Catalog_Model_Category::ENTITY,
    'ho_import_profile',
    'frontend_input',
    'ho_import_profile'
);

$installer->updateAttribute(
    'customer',
    'ho_import_profile',
    'frontend_input',
    'ho_import_profile'
);

$installer->endSetup();
