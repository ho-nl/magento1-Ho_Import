<?php
/**
 * Copyright © 2017 H&O E-commerce specialisten B.V. (http://www.h-o.nl/)
 * See LICENSE.txt for license details.
 */

/**
 * @method string getAction()
 * @method Ho_Import_Model_Config setAction(string $action)
 * @method string getProfile()
 * @method Ho_Import_Model_Config setProfile(string $profile)
 * @method bool getSkipDownload()
 * @method Ho_Import_Model_Config setSkipDownload(bool $skipDownload)
 * @method bool getSkipDecompress()
 * @method Ho_Import_Model_Config setSkipDecompress(bool $skipDecompress)
 * @method bool getDryrun()
 * @method Ho_Import_Model_Config setDryrun(bool $dryrun)
 */
class Ho_Import_Model_Config extends Varien_Object
{
    /**
     * @return array|null
     */
    public function getConfig()
    {
        return $this->getData();
    }

    /**
     * @param array $config
     */
    public function setConfig(array $config)
    {
        $this->setData($config);
    }
}
