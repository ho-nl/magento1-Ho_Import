<?php
/**
 * Copyright © 2017 H&O E-commerce specialisten B.V. (http://www.h-o.nl/)
 * See LICENSE.txt for license details.
 */

require_once 'abstract.php';

class Ho_Import_Shell_Productimport extends Mage_Shell_Abstract
{
    public function __construct()
    {
        parent::__construct();

        Mage::setIsDeveloperMode(true); //always enable developer mode when run through the shell.

        if ($this->getArg('profiler') == '1') {
            Varien_Profiler::enable();
        }
    }

    /**
     * Run script
     *
     * @return void
     */
    public function run()
    {
        Mage::helper('ho_import/log')->setMode('cli');

        $action = $this->getArg('action');

        if (empty($action)) {
            echo $this->usageHelp();
        } else {
            Varien_Profiler::start("shell-productimport".$this->getArg('action'));

            // Disable the inline translator for the cli, breaks the import if it is enabled.
            Mage::getConfig()->setNode('stores/admin/dev/translate_inline/active', 0);

            // Initialize the translations so that we are able to translate things.
            Mage::app()->loadAreaPart(
                Mage_Core_Model_App_Area::AREA_ADMINHTML,
                Mage_Core_Model_App_Area::PART_TRANSLATE
            );

            $actionMethodName = $action.'Action';
            if (method_exists($this, $actionMethodName)) {
                $this->$actionMethodName();
            } else {
                echo "Action $action not found!\n";
                echo $this->usageHelp();
                exit(1);
            }

            Varien_Profiler::stop("shell-productimport-".$this->getArg('action'));

            /** @var Aoe_Profiler_Helper_Data $profiler */
            if (Mage::helper('core')->isModuleEnabled('aoe_profiler')
                && $profiler = Mage::helper('aoe_profiler')
                && $this->getArg('profiler') == '1') {
                $profiler->renderProfilerOutputToFile();
            }
        }
    }

    /**
     * Retrieve Usage Help Message
     *
     * @return string
     */
    public function usageHelp()
    {
        $help = 'Available actions: ' . "\n";
        $methods = get_class_methods($this);
        foreach ($methods as $method) {
            if (substr($method, -6) == 'Action') {
                $help .= '    -action ' . substr($method, 0, -6);
                $helpMethod = $method.'Help';
                if (method_exists($this, $helpMethod)) {
                    $help .= $this->$helpMethod();
                }
                $help .= "\n";
            }
        }

        return $help;
    }

    /**
     * Importing data entities
     */
    public function importAction()
    {
        if (! $profile = $this->getArg('profile')) {
            echo $this->importActionHelp();
            exit(1);
        }

        try {
            /** @var Ho_Import_Model_Import $import */
            $import = Mage::getModel('ho_import/import');
            $import->setProfile($profile);
            $import->setImportData($this->_args);

            /** @var Ho_Import_Model_Config $importConfig */
            $importConfig = Mage::getSingleton('ho_import/config');
            $importConfig->setConfig($this->_args);

            $import->process();
        } catch (Mage_Core_Exception $e) {
            Mage::helper('ho_import/log')->log($e->getMessage(), Zend_Log::CRIT);
            exit(1);
        } catch (Exception $e) {
            Mage::helper('ho_import/log')->log($e->getMessage(), Zend_Log::WARN);
            exit(1);
        }

        Mage::helper('ho_import/log')->log('Done');
    }


    public function importActionHelp()
    {
        /** @var Ho_Import_Model_Import $import */
        $import   = Mage::getModel('ho_import/import');
        $profiles = implode(", ", array_keys($import->getProfiles()));

        return
            "\n\t-profile profile_name   Available profiles:    {$profiles}"
          . "\n\t-skip_download 1        Skip the download"
          . "\n\t-skip_decompress 1      Skip the decompressing of files"
          . "\n\t-dryrun 1               Validate all data agains the Magento validator, but do not import anything"
          . "\n";
    }

    public function lineAction()
    {
        if (! $profile = $this->getArg('profile')) {
            echo $this->importActionHelp();
            exit(1);
        }

        try {
            /** @var Ho_Import_Model_Import $import */
            $import = Mage::getModel('ho_import/import');
            $import->setProfile($profile);
            $import->setImportData($this->_args);

            /** @var Ho_Import_Model_Config $importConfig */
            $importConfig = Mage::getSingleton('ho_import/config');
            $importConfig->setConfig($this->_args);

            $import->mapLines($this->getArg('line'));
        } catch (Mage_Core_Exception $e) {
            Mage::helper('ho_import/log')->log($e->getMessage(), Zend_Log::CRIT);
            exit(1);
        } catch (Exception $e) {
            Mage::helper('ho_import/log')->log($e->getMessage(), Zend_Log::WARN);
            exit(1);
        }
    }

    public function lineActionHelp()
    {
        /** @var Ho_Import_Model_Import $import */
        $import = Mage::getModel('ho_import/import');
        $profiles = implode(", ", array_keys($import->getProfiles()));

        return
            "\n\t-profile profile_name   Available profiles:    {$profiles}"
           ."\n\t-skip_download 1        Skip the download"
           ."\n\t-skip_decompress 1      Skip the decompressing of the downloaded file"
           ."\n\t-line 1,2,3             Commaseparated list of lines to be checked"
           ."\n\t-search sku=abd         Search for the value of a field."
           ."\n";
    }

    public function csvAction()
    {
        if (! $profile = $this->getArg('profile')) {
            echo $this->importActionHelp();
            exit(1);
        }

        try {
            /** @var Ho_Import_Model_Import $import */
            $import = Mage::getModel('ho_import/import');
            $import->setProfile($profile);
            $import->setImportData($this->_args);

            /** @var Ho_Import_Model_Config $importConfig */
            $importConfig = Mage::getSingleton('ho_import/config');
            $importConfig->setConfig($this->_args);

            $import->importCsv();
        } catch (Mage_Core_Exception $e) {
            Mage::helper('ho_import/log')->log($e->getMessage(), Zend_Log::CRIT);
            exit(1);
        } catch (Exception $e) {
            Mage::helper('ho_import/log')->log($e->getMessage(), Zend_Log::WARN);
            exit(1);
        }

        Mage::helper('ho_import/log')->log('Done');
    }

    public function csvActionHelp()
    {
        /** @var Ho_Import_Model_Import $import */
        $import = Mage::getModel('ho_import/import');
        $profiles = implode(", ", array_keys($import->getProfiles()));

        return
            "\n\tDebug method: doesn't fieldmap, only imports the current csv"
           ."\n\t-profile profile_name   Available profiles:    {$profiles}"
           ."\n\t-dryrun 1               Validate all data agains the Magento validator, but do not import anything"
           ."\n";
    }
}

$shell = new Ho_Import_Shell_Productimport();
$shell->run();
