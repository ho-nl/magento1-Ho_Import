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
 
class Ho_Import_Model_Downloader_Ftp extends Ho_Import_Model_Downloader_Abstract {

    function download(Varien_Object $connectionInfo, $target) {

        $file = $connectionInfo->getFile();

        $this->_log($this->_getLog()->__("Connecting to FTP server %s", $connectionInfo->getHost()));

        $ftp = new Varien_Io_Ftp();
        $ftp->open(array(
            'host'     => $connectionInfo->getHost(),
            'user'     => $connectionInfo->getUsername(),
            'password' => $connectionInfo->getPassword(),
            'timeout'  => $connectionInfo->hasTimeout() ? $connectionInfo->getTimeout() : 10,
            'passive'  => $connectionInfo->hasPassive() ? $connectionInfo->getPassive() : true,
            'ssl'      => $connectionInfo->hasSsl() ? $connectionInfo->getSsl() : null,
            'file_mode'      => $connectionInfo->hasFileMode() ? $connectionInfo->getFileMode() : null,
        ));

        $this->_log($this->_getLog()->__("Downloading file %s from %s, to %s", $connectionInfo->getFile(), $connectionInfo->getHost(), $target));

        $targetPath = $this->_getTargetPath($target, basename($file));
        $ftp->read($file, $targetPath);
        $ftp->close();
    }
}
