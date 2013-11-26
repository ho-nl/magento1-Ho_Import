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
 * @copyright   Copyright © 2013 H&O (http://www.h-o.nl/)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @author      Paul Hachmang – H&O <info@h-o.nl>
 *
 * 
 */
 
class Ho_Import_Model_Downloader_Http extends Ho_Import_Model_Downloader_Abstract {

    function download($connectionInfo, $target) {
        $url = $connectionInfo->getUrl();
        if (! $url) {
            Mage::throwException($this->_getLog()->__("No valid URL given: %s", $url));
        }

        $this->_log($this->_getLog()->__("Downloading file %s from %s, to %s", basename($url), $url, $target));

        $targetInfo = pathinfo($target);
        $filename = isset($targetInfo['extension']) ? basename($target) : basename(parse_url($url, PHP_URL_PATH));
        $path = $this->_getTargetPath(dirname($target), $filename);

        $fp = fopen($path, 'w+');//This is the file where we save the information
        $ch = curl_init($url);//Here is the file we are downloading, replace spaces with %20
        curl_setopt($ch, CURLOPT_TIMEOUT, 50);
        curl_setopt($ch, CURLOPT_FILE, $fp); // write curl response to file
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_exec($ch); // get curl response
        curl_close($ch);
        fclose($fp);
    }
}
