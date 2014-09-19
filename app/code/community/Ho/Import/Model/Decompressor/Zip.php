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
 
class Ho_Import_Model_Decompressor_Zip extends Ho_Import_Model_Decompressor_Abstract {

    /**
     * Extract a file
     * @param Varien_Object $object
     * @return mixed
     */
    public function decompress(Varien_Object $object) {
        $source = $object->getSource();
        $target = $object->getTarget();

        $this->_log($this->_getLog()->__("Decompressing file %s to %s", $source, $target));

        if (! $source || ! $target) {
            Mage::throwException($this->_getLog()->__("Source and target must me speficied (source: %s, target %s)", $source, $target));
        }

        $zip = new ZipArchive;
        $zip->open($this->_getFilePath(dirname($source), basename($source)));
        $zip->extractTo($this->_getFilePath(dirname($target), basename($target)));
        $zip->close();
    }
}
