<?php
/**
 * Copyright © 2017 H&O E-commerce specialisten B.V. (http://www.h-o.nl/)
 * See LICENSE.txt for license details.
 */

class Ho_Import_Model_Downloader_Ftp extends Ho_Import_Model_Downloader_Abstract
{
    public function download(Varien_Object $connectionInfo, $target)
    {
        $file = $connectionInfo->getFile();

        $this->_log($this->_getLog()->__("Connecting to FTP server %s", $connectionInfo->getHost()));

        $ftp = new Varien_Io_Ftp();
        $ftp->open(array(
            'host'      => $connectionInfo->getHost(),
            'port'      => $connectionInfo->getPort() ? $connectionInfo->getPort() : 21,
            'user'      => $connectionInfo->getUsername(),
            'password'  => $connectionInfo->getPassword(),
            'timeout'   => $connectionInfo->hasTimeout() ? $connectionInfo->getTimeout() : 10,
            'passive'   => $connectionInfo->hasPassive() ? $connectionInfo->getPassive() : true,
            'ssl'       => $connectionInfo->hasSsl() ? $connectionInfo->getSsl() : null,
            'file_mode' => $connectionInfo->hasFileMode() ? $connectionInfo->getFileMode() : null,
        ));

        if (! is_writable(Mage::getBaseDir().DS.$target)) {
            Mage::throwException($this->_getLog()->__(
                "Can not write file %s to %s, folder not writable (doesn't exist?)",
                $connectionInfo->getFile(), $target
            ));
        }

        $this->_log($this->_getLog()->__(
            "Downloading file %s from %s, to %s",
            $connectionInfo->getFile(), $connectionInfo->getHost(), $target
        ));

        $targetPath = $this->_getTargetPath($target, basename($file));
        $ftp->read($file, $targetPath);
        $ftp->close();
    }
}
