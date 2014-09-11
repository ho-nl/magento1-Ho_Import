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
 
class Ho_Import_Model_Downloader_Http extends Ho_Import_Model_Downloader_Abstract {

    function download(Varien_Object $connectionInfo, $target) {
        $url = $connectionInfo->getUrl();
        if (! $url) {
            Mage::throwException($this->_getLog()->__("No valid URL given: %s", $url));
        }

        $this->_log($this->_getLog()->__("Downloading file %s from %s, to %s", basename($url), $url, $target));

        $targetInfo = pathinfo($target);
        $filename = isset($targetInfo['extension']) ? basename($target) : basename(parse_url($url, PHP_URL_PATH));
        $path = $this->_getTargetPath(dirname($target), $filename);

        $fp = fopen($path, 'w+');//This is the file where we save the information

        $cookie = tempnam(Mage::getBaseDir('tmp'), "CURLCOOKIE");
        $ch = curl_init($url);//Here is the file we are downloading, replace spaces with %20
        curl_setopt( $ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; rv:1.7.3) Gecko/20041001 Firefox/0.10.1" );
        curl_setopt( $ch, CURLOPT_COOKIEJAR, $cookie);
        curl_setopt( $ch, CURLOPT_ENCODING, "");
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt( $ch, CURLOPT_AUTOREFERER, true);
        curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt( $ch, CURLOPT_MAXREDIRS, 10);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_exec($ch);
        $response = curl_getinfo($ch);
        curl_close ($ch);
        fclose($fp);

        if ($response['http_code'] == 301 || $response['http_code'] == 302) {
            if ($headers = get_headers($response['url'])) {
                foreach( $headers as $value ) {
                    if (substr(strtolower($value), 0, 9) == "location:") {
                        $connectionInfo->setUrl(trim(substr($value, 9, strlen($value))));
                        Mage::throwException($this->_getLog()->__("Retrying with forwarded URL: %s", $connectionInfo->getUrl()));
                        return $this->download($connectionInfo, $target);
                    }
                }
            }
        }
    }
}
