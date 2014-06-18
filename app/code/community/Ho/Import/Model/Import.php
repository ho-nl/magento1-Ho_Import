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
 * @copyright   Copyright © 2012 H&O (http://www.h-o.nl/)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @author      Paul Hachmang – H&O <info@h-o.nl>
 */

/**
 * @method string getProfile()
 * @method Ho_Import_Model_Import setImportData(array $importData)
 * @method array getImportData()
 * @method Ho_Import_Model_Import setRowCount(int $rowCount)
 * @method int getRowCount()
 * @method Ho_Import_Model_Import setDryrun(bool $drurun)
 * @method int getDryrun()
 * @method Ho_Import_Model_Import setSourceOptions(array $sourceOptions)
 * @method null|array getSourceOptions()
 */
class Ho_Import_Model_Import extends Varien_Object
{
    const IMPORT_TYPE_PRODUCT          = 'catalog_product';
    const IMPORT_TYPE_CUSTOMER         = 'customer';
    const IMPORT_TYPE_CATEGORY         = 'catalog_category';
    const IMPORT_TYPE_CATEGORY_PRODUCT = 'catalog_category_product';

    const IMPORT_CONFIG_DOWNLOADER     = 'global/ho_import/%s/downloader';
    const IMPORT_CONFIG_MODEL          = 'global/ho_import/%s/source';
    const IMPORT_CONFIG_ENTITY_TYPE    = 'global/ho_import/%s/entity_type';
    const IMPORT_CONFIG_EVENTS         = 'global/ho_import/%s/events';
    const IMPORT_CONFIG_IMPORT_OPTIONS = 'global/ho_import/%s/import_options';
    const IMPORT_CONFIG_LOG_LEVEL      = 'global/ho_import/%s/log_level';

    /**
     * @var AvS_FastSimpleImport_Model_Import
     */
    protected $_fastSimpleImport = NULL;

    protected function _construct()
    {
        parent::_construct();
        ini_set('memory_limit', '2G');
        $this->_fastSimpleImport = Mage::getModel('fastsimpleimport/import');
    }

    /**
     * @throws Exception
     * @return \Ho_Import_Model_Import
     */
    public function process()
    {
        if ($level = $this->getLogLevel()) {
            $this->_getLog()->setMinLogLevel($level);
        }

        if (!array_key_exists($this->getProfile(), $this->getProfiles())) {
            Mage::throwException($this->_getLog()->__("Profile %s not found", $this->getProfile()));
        }

        $this->_applyImportOptions();
        $this->_downloader();

        $entity = (string)$this->_getEntityType();
        $method = '_import' . ucfirst(Mage::helper('ho_import')->underscoreToCamelCase($entity));

        if (FALSE === method_exists($this, $method)) {
            Mage::throwException($this->_getLog()->__("Entity %s not supported", $entity));
        }

        $this->_getLog()->log($this->_getLog()->__('Mapping source fields and saving to temp csv file (%s)', $this->_getFileName()));
        $hasRows = $this->_createImportCsv();
        if (! $hasRows) {
            $this->_runEvent('process_after');
            return;
        }

        if ($this->getDryrun()) {
            $this->_getLog()->log($this->_getLog()->__('Dry run %s rows from temp csv file (%s)', $this->getRowCount(), $this->_getFileName()));
            $errors = $this->_dryRun();
        } else {
            $this->_getLog()->log($this->_getLog()->__('Processing %s rows from temp csv file (%s)', $this->getRowCount(), $this->_getFileName()));
            $errors = $this->$method();
        }

        $this->_runEvent('process_after');

        $this->_logErrors($errors);
        $this->_debugErrors($errors);
    }

    /**
     * @param string|array $lines
     *
     * @return bool
     */
    public function mapLines($lines)
    {
        if (!array_key_exists($this->getProfile(), $this->getProfiles())) {
            Mage::throwException($this->_getLog()->__("Profile %s not found", $this->getProfile()));
        }

        $this->_downloader();

        if ($lines === '0') {
            $lines = array(0);
        } else {
            $lines = $lines ? explode(',', $lines) : array();
        }

        /** @var SeekableIterator $sourceAdapter */
        $sourceAdapter = $this->getSourceAdapter();
        $this->_runEvent('process_before', $this->_getTransport()->setAdapter($sourceAdapter));
        $this->_applyImportOptions();

        //search a line instead on specifying the line.
        $importData = $this->getImportData();
        if (!count($lines) && isset($importData['search'])) {
            $parts = explode('=', $importData['search']);

            while ($sourceAdapter->valid()) {
                $current = $sourceAdapter->current();
                if ($current[$parts[0]] == $parts[1]) {
                    break;
                }
                $sourceAdapter->next();
            }

            if ($sourceAdapter->key() == NULL) {
                Mage::throwException($this->_getLog()->__("Couldn't find  %s=%s in %s", $parts[1], $parts[0], $this->getProfile()));
            }

            $lines = array($sourceAdapter->key());
        } elseif (!count($lines)) {
            $lines = array(1);
        }

        $entities    = array();
        $logEntities = array();
        $entityMap   = array();
        $sourceRows  = array();
        foreach ($lines as $line) {
            $this->_getLog()->log($this->_getLog()->__('Mapping %s:%s', $this->getProfile(), $line));

            $sourceAdapter->seek($line);
            if (!$sourceAdapter->valid()) {
                Mage::throwException($this->_getLog()->__("Line %s is not valid in %s", $line, $this->getProfile()));
            }

            $transport         = $this->_getTransport();
            $sourceRows[$line] = $sourceAdapter->current();
            $transport->setData('items', array($sourceRows[$line]));
            $this->_runEvent('source_row_fieldmap_before', $transport);
            if ($transport->getData('skip')) {
                $this->_getLog()->log($this->_getLog()->__('Skip flag is set for line (%s) in event source_row_fieldmap_before', $line), Zend_Log::WARN);
                $this->_getLog()->log($transport->getData('items'), Zend_Log::DEBUG);
                return;
            } else {
                $this->_getLog()->log($transport->getData('items'), Zend_Log::DEBUG);

                $i = 0;
                foreach ($transport->getData('items') as $preparedItem) {
                    $results = $this->_fieldMapItem($preparedItem);

                    foreach ($results as $result) {
                        $i++;

                        $entities[]                    = $result;
                        $logEntities[$line . ':' . $i] = $result;
                        $entityMap[]                   = $line . ':' . $i;
                    }
                }
            }
        }

        $this->_runEvent('process_after');

        $errors = array();
        try {
            $errors = $this->_dryRun($entities);
        } catch (Exception $e) {
            $errors[$e->getMessage()] = $lines;
        }
        foreach ($errors as $error => $lines) {
            foreach ($lines as $line) {
                $key                                   = $line - 1;
                if (isset($entityMap[$key])) {
                    $logEntities[$entityMap[$key]][$error] = '__ERROR__';
                }
            }
        }
        $this->_getLog()->log($logEntities, Zend_Log::DEBUG);

        return TRUE;
    }

    /** @var Varien_Object */
    protected $_transport = NULL;

    protected function _getTransport()
    {
        if ($this->_transport === NULL) {
            return new Varien_Object();
        } else {
            $this->_transport->setData(array());
            $this->_transport->setOrigData(array());
            $this->_transport->setDataChanges(FALSE);
        }
        return $this->_transport;
    }

    protected function _downloader()
    {
        $data = $this->getImportData();
        if (isset($data['skip_download']) && $data['skip_download'] == 1) {
            return;
        }

        $downloader = $this->_getConfigNode(self::IMPORT_CONFIG_DOWNLOADER);

        if (!$downloader) {
            return;
        }

        if (!$downloader->getAttribute('model')) {
            Mage::throwException($this->_getLog()->__("No attribute model found for <downloader> node"));
        }

        $model = Mage::getModel($downloader->getAttribute('model'));
        if (!$model) {
            Mage::throwException($this->_getLog()->__("Trying to load %s, model not found %s", $downloader));
        }

        if (!$model instanceof Ho_Import_Model_Downloader_Abstract) {
            Mage::throwException($this->_getLog()->__("Downloader model %s must be instance of Ho_Import_Model_Downloader_Abstract", $downloader->getAttribute('model')));
        }

        $target = $downloader->target ? : 'var/import';

        $args = array_merge($downloader->asArray());
        unset($args['@']);

        $transport = $this->_getTransport();
        $transport->addData($args);

        try {
            $model->download($transport, $target);
        } catch (Exception $e) {
            Mage::throwException($e->getMessage());
        }
    }

    protected function _createImportCsv()
    {
        /** @var SeekableIterator $sourceAdapter */
        $sourceAdapter = $this->getSourceAdapter();
        $this->_runEvent('process_before', $this->_getTransport()->setAdapter($sourceAdapter));
        $timer = microtime(TRUE);

        /** @var Mage_ImportExport_Model_Export_Adapter_Abstract $exportAdapter */
        $exportAdapter = Mage::getModel('importexport/export_adapter_csv', $this->_getFileName());
        $fieldNames    = $this->_getMapper()->getFieldNames();

        $rowCount = 0;
        while ($sourceAdapter->valid()) {
            $transport = $this->_getTransport();
            $transport->setData('items', array($sourceAdapter->current()));
            $this->_runEvent('source_row_fieldmap_before', $transport);
            if ($transport->getData('skip')) {
                $rowCount++;
                $sourceAdapter->next();
                continue;
            }

            foreach ($transport->getData('items') as $preparedItem) {
                $result = $this->_fieldMapItem($preparedItem);

                foreach ($result as $row) {
                    $rowCount++;
                    $exportAdapter->writeRow(array_merge($fieldNames, $row));
                }
            }

            $sourceAdapter->next();
        }
        $this->setRowCount($rowCount);

        $seconds       = round(microtime(TRUE) - $timer, 2);
        $rowsPerSecond = $seconds ? round($this->getRowCount() / $seconds, 2) : $this->getRowCount();
        $this->_getLog()->log("Fieldmapping {$this->getProfile()} with {$this->getRowCount()} rows (done in $seconds seconds, $rowsPerSecond rows/s)");

        if ($this->getRowCount()) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param $type
     *
     * @return Ho_Import_Model_Import
     */
    protected function _importMain($type = '')
    {
        $importData = $this->getImportData();
        if (is_array($importData)) {
            if (isset($importData['dropdown_attributes'])) {
                $importData['dropdown_attributes'] = explode(',', $importData['dropdown_attributes']);
            }

            if ($type !== self::IMPORT_TYPE_CATEGORY) { // @SchumacherFM I'm not sure if that is needed
                foreach ($importData as $key => $value) {
                    $this->_getLog()->log($this->_getLog()->__('Setting option %s to %s', $key, $value));
                    $this->_fastSimpleImport->setDataUsingMethod($key, (string)$value);
                }
            }

            if (isset($importData['ignoreErrors']) && (int)$importData['ignoreErrors'] === 1) {
                $this->_getLog()->log('Continue after errors enabled');
                $this->_fastSimpleImport->setContinueAfterErrors($importData['ignoreErrors']);
            }

            if (isset($importData['renameFiles']) && (int)$importData['renameFiles'] === 0) {
                $this->_fastSimpleImport->setAllowRenameFiles(FALSE);
            }
        }

        $transport = $this->_getTransport();
        $transport->setData('object', $this->_fastSimpleImport);
        $this->_runEvent('import_before', $transport);

        $errors = $this->_importData();

        $transport = $this->_getTransport();
        $transport->addData(array('object' => $this->_fastSimpleImport, 'errors' => $errors));
        $this->_runEvent('import_after', $transport);
        return $errors;
    }

    /**
     * Actual importmethod
     * @return Ho_Import_Model_Import
     */
    protected function _importCustomer()
    {
        return $this->_importMain(self::IMPORT_TYPE_CUSTOMER);
    }

    /**
     * Actual import method
     * @return Ho_Import_Model_Import
     */
    protected function _importCatalogProduct()
    {
        return $this->_importMain(self::IMPORT_TYPE_PRODUCT);
    }

    /**
     * Actual import method
     * @return Ho_Import_Model_Import
     */
    protected function _importCatalogCategory()
    {
        return $this->_importMain(self::IMPORT_TYPE_CATEGORY);
    }

    /**
     * Actual import method
     * @return Ho_Import_Model_Import
     */
    protected function _importCatalogCategoryProduct()
    {
        return $this->_importMain(self::IMPORT_TYPE_CATEGORY_PRODUCT);
    }

    protected function _applyImportOptions()
    {
        $options = $this->_getConfigNode(self::IMPORT_CONFIG_IMPORT_OPTIONS);
        if ($options) {
            foreach ($options->children() as $key => $value) {
                $value      = $value->asArray();
                $printValue = is_array($value) ? implode(',', $value) : $value;
                $this->_getLog()->log($this->_getLog()->__('Setting option %s to %s', $key, $printValue));
                $this->_fastSimpleImport->setDataUsingMethod($key, $value);
            }
        }

        //always disable the image preprocessor, this tries to write back to the source, which isn't supported.
        $this->_fastSimpleImport->setDisablePreprocessImageData(true);

        return $this;
    }

    protected function _runEvent($event, $transport = NULL)
    {
        $node = $this->_getConfigNode(self::IMPORT_CONFIG_EVENTS);
        if (!isset($node) || !isset($node->$event) || !$node->$event->getAttribute('helper')) {
            return $this;
        }

        $helperParts = explode('::', $node->$event->getAttribute('helper'));
        $helper      = Mage::helper($helperParts[0]);
        if (!$helper) {
            Mage::throwException($this->_getLog()->__("Trying to run %s, helper not found %s", $event, $helperParts[0]));
        }

        $method = $helperParts[1];
        if (!method_exists($helper, $method)) {
            Mage::throwException($this->_getLog()->__("Trying to run %s, method %s::%s not found.", $event, $helperParts[0], $method));
        }

//        $this->_getLog()->log($this->_getLog()->__("Running event %s, %s::%s", $event, $helperParts[0], $method));

        $args = array_merge($node->$event->asArray());
        unset($args['@']);

        $transport = $transport !== NULL ? $transport : $this->_getTransport();
        call_user_func(array($helper, $method), $transport);
        return $transport;
    }

    /**
     * Expand the fields to multiple rows
     *
     * $item = array(
     *      'admin' => array(
     *          'column1' => 'value',
     *          'column2' => 'value2'
     *      ),
     *      'frech' => array(
     *          'column1' => 'special value for french'
     *      )
     * )
     *
     * @param array $item
     *
     * @return array
     */
    protected function _fieldMapItem(&$item)
    {
        $itemRows = array();

        $mapper = $this->_getMapper();
        $mapper->setItem($item);
        $symbolForClearField = $this->_fastSimpleImport->getSymbolEmptyFields();

        $allFieldConfig = $mapper->getFieldConfig();
        //Step 1: Get the values for the fields
        foreach ($allFieldConfig as $storeCode => $fields) {
            $mapper->setStoreCode($storeCode);
            /** @var $column Mage_Core_Model_Config_Element */
            foreach ($fields as $fieldName => $fieldConfig) {
                $result = $this->_getMapper()->map($fieldName, $storeCode);

                //add the values to the itemRows
                if (is_array($result)) {
                    foreach ($result as $row => $val) {
                        $itemRows[$storeCode][$row][$fieldName] = $val;
                    }
                } elseif ($result !== NULL) {
                    $itemRows[$storeCode][0][$fieldName] = $result;
                }

                if ($symbolForClearField && isset($fieldConfig['@'])
                    && (!isset($itemRows[$storeCode][0][$fieldName]) || $itemRows[$storeCode][0][$fieldName] === NULL)) {
                    $itemRows[$storeCode][0][$fieldName] = $symbolForClearField;
                }
            }
        }

        //Step 2: Flatten all the rows.
        $flattenedRows = array();
        foreach ($itemRows as $store => $storeData) {
            foreach ($storeData as $storeRow) {
                $flatRow = array();
                foreach ($allFieldConfig[$store] as $key => $column) {
                    if (isset($storeRow[$key]) && (strlen($storeRow[$key]))) {
                        $flatRow[$key] = (string)$storeRow[$key];
                    }
                }

                if ($flatRow) {
                    //if a column is required we add it here.
                    foreach ($allFieldConfig[$store] as $key => $column) {
                        if (!isset($flatRow[$key]) && isset($column['@']) && isset($column['@']['required'])) {
                            $flatRow[$key] = '';
                        }
                    }

                    $flatRow['_store'] = $store == 'admin' ? '' : $store;
                    $flattenedRows[]   = $flatRow;
                }
            }
        }
        unset($itemRows);
        unset($item);

        return $flattenedRows;
    }

    /**
     * Get the mapper model that has the possibily to process nodes.
     *
     * @return Ho_Import_Model_Mapper
     */
    protected function _getMapper()
    {
        return Mage::getSingleton('ho_import/mapper');
    }

    protected function _getFileName()
    {
        return Mage::getBaseDir('var') . DS . 'import' . DS . $this->getProfile() . '.csv';
    }

    /**
     * @return Ho_Import_Helper_Log
     */
    protected function _getLog()
    {
        return Mage::helper('ho_import/log');
    }


    /**
     * @throws Exception
     * @return \Ho_Import_Model_Import
     */
    protected function _importData()
    {
        $timer = microtime(TRUE);

        //importing
        $entityType    = (string)$this->_getEntityType();
        $importMethods = array(
            self::IMPORT_TYPE_PRODUCT          => 'processProductImport',
            self::IMPORT_TYPE_CATEGORY         => 'processCategoryImport',
            self::IMPORT_TYPE_CUSTOMER         => 'processCustomerImport',
            self::IMPORT_TYPE_CATEGORY_PRODUCT => 'processCategoryProductImport',
        );

        try {
            if (isset($importMethods[$entityType])) {
                $this->_getLog()->log($this->_getLog()->__('Start import %s', $entityType));
                $this->_fastSimpleImport->{$importMethods[$entityType]}($this->_getFileName());
            } else {
                $this->_getLog()->log($this->_getLog()->__('Type %s not found', $entityType));
            }
        } catch (Exception $e) {
            $seconds  = round(microtime(TRUE) - $timer, 2);
            $this->_getLog()->log("Exception while running profile  {$this->getProfile()}, ran for $seconds seconds.", Zend_Log::CRIT);
            Mage::printException($e);
            exit;
        }

        $seconds           = round(microtime(TRUE) - $timer, 2);
        $rowsPerSecond     = round($this->getRowCount() / $seconds, 2);
        $productsPerSecond = round($this->getRowCount() / $seconds, 2);
        $this->_getLog()->log("Import {$this->getProfile()} done in $seconds seconds, $rowsPerSecond rows/s, $productsPerSecond items/s.");

        return $this->_fastSimpleImport->getErrors();
    }

    /**
     * @param null $data
     *
     * @return array
     */
    protected function _dryRun($data = NULL)
    {
        if (is_null($data)) {
            $data = $this->_getFileName();
        }
        //importing
        $entityType    = (string)$this->_getEntityType();
        $importMethods = array(
            self::IMPORT_TYPE_PRODUCT          => 'dryrunProductImport',
            self::IMPORT_TYPE_CATEGORY         => 'dryrunCategoryImport',
            self::IMPORT_TYPE_CUSTOMER         => 'dryrunCustomerImport',
            self::IMPORT_TYPE_CATEGORY_PRODUCT => 'dryrunCategoryProductImport',
        );

        if (isset($importMethods[$entityType])) {
            $this->_fastSimpleImport->{$importMethods[$entityType]}($data);
        } else {
            $this->_getLog()->log($this->_getLog()->__('Type %s not found', $entityType));
        }
        return $this->_fastSimpleImport->getErrors();
    }

    /**
     * @return SeekableIterator
     */
    public function getSourceAdapter()
    {
        $source = $this->_getConfigNode(self::IMPORT_CONFIG_MODEL);

        if (!$source) {
            Mage::throwException($this->_getLog()->__('<source> not found for profile %s', $this->getProfile()));
        }

        $arguments = array();
        foreach ($source->children() as $key => $value) {
            $arguments[$key] = (string)$value;
        }

        if ($this->getSourceOptions() && is_array($this->getSourceOptions())) {
            foreach ($this->getSourceOptions() as $key => $value) {
                $arguments[$key] = (string)$value;
            }
        }

        if (isset($arguments['file']) && !is_readable($arguments['file'])) {
            $arguments['file'] = Mage::getBaseDir() . DS . (string)$source->file;
            if (!is_readable($arguments['file'])) {
                Mage::throwException(Mage::helper('importexport')->__("%s file does not exists or is not readable", $source->file));
            }
        }

        if (count($arguments) == 1 && isset($arguments['file'])) {
            $arguments = $arguments['file'];
        }

        $this->_getLog()->log($this->_getLog()->__('Getting source adapter %s', $source->getAttribute('model')));
        $importModel = Mage::getModel($source->getAttribute('model'), $arguments);

        if (!$importModel) {
            Mage::throwException($this->_getLog()->__('Import model (%s) not found for profile %s', $source->getAttribute('model'), $this->getProfile()));
        }

        return $importModel;
    }

    /**
     * @return Mage_Core_Model_Config_Element
     */
    protected function _getEntityType()
    {
        return $this->_getConfigNode(self::IMPORT_CONFIG_ENTITY_TYPE);
    }

    /**
     * @param string $path Enter a %s to substitute with the current profile.
     *
     * @return Mage_Core_Model_Config_Element
     */
    protected function _getConfigNode($path)
    {
        return Mage::getConfig()->getNode(sprintf($path, $this->getProfile()));
    }

    /**
     * @return int
     */
    protected function getLogLevel()
    {
        if ($level = (int)$this->_getConfigNode(self::IMPORT_CONFIG_LOG_LEVEL)) {
            return $level;
        }
        return Ho_Import_Helper_Log::LOG_SUCCESS;
    }

    /**
     * Format the errors and pass them to the logger
     *
     * @param $errors
     *
     * @return $this
     */
    protected function _logErrors($errors)
    {
        if ($errors) {
            foreach ($errors as $type => $errorLines) {
                $this->_getLog()->log($this->_getLog()->__("%s on lines %s", $type, implode(',', $errorLines)), Zend_Log::ERR);
            }
        } else {
            $this->_getLog()->log($this->_getLog()->__('No errors found in %s rows', $this->getRowCount()), Ho_Import_Helper_Log::LOG_SUCCESS);
        }
        return $this;
    }

    /**
     * When an error occurs, try and to find the row where it occured and print that row with the
     * errors below.
     *
     * @param array $errors
     *
     * @return $this
     */
    protected function _debugErrors($errors)
    {
        if (!count($errors)) {
            return $this;
        }

        $errorLines    = array();
        $errorsPerLine = array();
        foreach ($errors as $error => $lines) {
            $errorLines = array_merge($errorLines, $lines);
            foreach ($lines as $line) {

                $errorsPerLine[$line][] = $error;
            }
        }

        $errorLines = array_slice($errorLines, 0, 5);
        $this->_getLog()->log($this->_getLog()->__("Debugging first 5 error lines %s", implode(',', $errorLines)), Zend_Log::DEBUG);

        /** @var SeekableIterator $sourceAdapter */
        $sourceAdapter = Mage::getModel('importexport/import_adapter_csv', $this->_getFileName());

        $logData = array();
        foreach ($errorLines as $errorLine) {
            $sourceAdapter->seek($errorLine - 1);
            $logData[$errorLine] = $sourceAdapter->current();

            foreach ($errorsPerLine[$errorLine] as $error) {
                $logData[$errorLine][$error] = '__ERROR__';
            }
        }

        $this->_getLog()->log($logData, Zend_Log::DEBUG);
        return $this;
    }

    /**
     * Get an array of all the available profiles.
     * @return array
     */
    public function getProfiles()
    {
        $profileNodes = Mage::getConfig()->getNode('global/ho_import');
        if ($profileNodes) {
            return $profileNodes->asArray();
        }
        return array();
    }

    /**
     * @param string $profile
     *
     * @return $this
     */
    public function setProfile($profile)
    {
        $this->_getMapper()->setProfileName($profile);
        return parent::setProfile($profile);
    }
}