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
 * @method Ho_Import_Model_Import setEntityCount(int $rowCount)
 * @method int getEntityCount()
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
    const IMPORT_CONFIG_DECOMPRESSOR   = 'global/ho_import/%s/decompressor';
    const IMPORT_CONFIG_MODEL          = 'global/ho_import/%s/source';
    const IMPORT_CONFIG_ENTITY_TYPE    = 'global/ho_import/%s/entity_type';
    const IMPORT_CONFIG_EVENTS         = 'global/ho_import/%s/events';
    const IMPORT_CONFIG_IMPORT_OPTIONS = 'global/ho_import/%s/import_options';
    const IMPORT_CONFIG_CLEAN          = 'global/ho_import/%s/clean';
    const IMPORT_CONFIG_LOG_LEVEL      = 'global/ho_import/%s/log_level';

    /**
     * @var AvS_FastSimpleImport_Model_Import
     */
    protected $_fastSimpleImport = NULL;

    protected function _construct()
    {
        parent::_construct();
        ini_set('memory_limit', '2048M');
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
        $this->_decompressor();

        $this->_getLog()->log($this->_getLog()->__(
                'Mapping source fields and saving to temp csv file (%s)', $this->_getFileName()));

        $this->_archiveOldCsv();
        $hasRows = $this->_createImportCsv();
        if (! $hasRows) {
            $this->_runEvent('process_after');
            return;
        }

        $importData = $this->getImportData();
        if (isset($importData['dryrun']) && $importData['dryrun'] == 1) {
            $this->_getLog()->log($this->_getLog()->__(
                'Dry run %s rows from temp csv file (%s)', $this->getRowCount(), $this->_getFileName()));

            $errors = $this->_dryRun();
        } else {
            $this->_getLog()->log($this->_getLog()->__(
                'Processing %s rows from temp csv file (%s)', $this->getRowCount(), $this->_getFileName()));

            $errors = $this->_importMain();
        }

        $this->_logErrors($errors);
        $this->_debugErrors($errors);

        $cleanRowCount = $this->_createEntityCleanCsv();
        $this->setRowCount($cleanRowCount);

        if ($cleanRowCount) {
            if (isset($importData['dryrun']) && $importData['dryrun'] == 1) {
                $this->_getLog()->log($this->_getLog()->__(
                    'Dry run cleaning %s rows from temp csv file (%s)', $cleanRowCount, $this->_getCleanFileName()));
                $errors = $this->_dryRunEntityCleanCsv();
            } else {
                $this->_getLog()->log($this->_getLog()->__(
                    'Clean %s rows from temp csv file (%s)', $cleanRowCount, $this->_getCleanFileName()));
                $errors = $this->_processEntityCleanCsv();
            }

            $this->_logErrors($errors);
            $this->_debugErrors($errors);
        }

        $this->_getLog()->log($this->_getLog()->__('Clean entity link table'));
        $this->_cleanEntityLinkTable();
        $this->_runEvent('process_after');
    }

    public function importCsv()
    {
        if ($level = $this->getLogLevel()) {
            $this->_getLog()->setMinLogLevel($level);
        }

        if (!array_key_exists($this->getProfile(), $this->getProfiles())) {
            Mage::throwException($this->_getLog()->__("Profile %s not found", $this->getProfile()));
        }

        $this->_applyImportOptions();

        $this->_getLog()->log($this->_getLog()->__(
            'Mapping source fields and saving to temp csv file (%s)', $this->_getFileName()));

        $this->_runEvent('process_before', $this->_getTransport()->setData('adapter', $this->getSourceAdapter()));

        $importData = $this->getImportData();
        if (isset($importData['dryrun']) && $importData['dryrun'] == 1) {
            $this->_getLog()->log($this->_getLog()->__(
                'Dry run %s rows from temp csv file (%s)', $this->getRowCount(), $this->_getFileName()));
            $errors = $this->_dryRun();
        } else {
            $this->_getLog()->log($this->_getLog()->__(
                'Processing %s rows from temp csv file (%s)', $this->getRowCount(), $this->_getFileName()));
            $errors = $this->_importData();
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
        if (! is_string($this->getProfile())) {
            Mage::throwException($this->_getLog()->__("No profile specified"));
        }
        if (!array_key_exists($this->getProfile(), $this->getProfiles())) {
            Mage::throwException($this->_getLog()->__("Profile %s not found", $this->getProfile()));
        }

        $this->_downloader();
        $this->_decompressor();

        if (! $lines) {
            $lines = array(0);
        } else {
            $lines = $lines ? explode(',', $lines) : array();
        }

        /** @var SeekableIterator $sourceAdapter */
        $sourceAdapter = $this->getSourceAdapter();
        $this->_runEvent('process_before', $this->_getTransport()->setData('adapter', $sourceAdapter));

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
                Mage::throwException($this->_getLog()->__(
                        "Couldn't find  %s=%s in %s", $parts[1], $parts[0], $this->getProfile()));
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
            $transport->setItems(array($sourceRows[$line]));
            $this->_runEvent('source_row_fieldmap_before', $transport);
            if ($transport->getSkip()) {
                $this->_getLog()->log($this->_getLog()->__(
                        'Skip flag is set for line (%s) in event source_row_fieldmap_before', $line), Zend_Log::WARN);
                $this->_getLog()->log($transport->getItems(), Zend_Log::DEBUG);
                return false;
            } else {
                $this->_getLog()->log($transport->getItems(), Zend_Log::DEBUG);

                $i = 0;
                foreach ($transport->getItems() as $preparedItem) {
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

        return true;
    }

    /** @var Ho_Import_Model_Import_Transport */
    protected $_transport = null;


    /**
     * @return Ho_Import_Model_Import_Transport
     */
    protected function _getTransport()
    {
        if ($this->_transport === NULL) {
            $this->_transport = Mage::getModel('ho_import/import_transport');
        } else {
            $this->_transport->reset();
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
            Mage::throwException($this->_getLog()->__(
                    "Trying to load %s, model not found", $downloader->getAttribute('model')));
        }

        if (!$model instanceof Ho_Import_Model_Downloader_Abstract) {
            Mage::throwException($this->_getLog()->__(
                "Downloader model %s must be instance of Ho_Import_Model_Downloader_Abstract",
                $downloader->getAttribute('model')
            ));
        }

        /** @noinspection PhpUndefinedFieldInspection */
        $target = $downloader->target ? : 'var/import';

        $args = array_merge($downloader->asArray());
        unset($args['@']);

        $transport = $this->_getTransport();
        $transport->addData($args);

        try {
            $model->download($transport, $target);
        } catch (Exception $e) {
            $this->_getLog()->log($this->_getLog()->__(
                    "Error while downloading external file (%s):", $downloader->getAttribute('model')), Zend_Log::ERR);
            Mage::throwException($e->getMessage());
        }
    }


    protected function _decompressor()
    {
        $data = $this->getImportData();
        if (isset($data['skip_decompress']) && $data['skip_decompress'] == 1) {
            return;
        }

        $decompressor = $this->_getConfigNode(self::IMPORT_CONFIG_DECOMPRESSOR);

        if (!$decompressor) {
            return;
        }

        if (!$decompressor->getAttribute('model')) {
            Mage::throwException($this->_getLog()->__("No attribute model found for <downloader> node"));
        }

        $model = Mage::getModel($decompressor->getAttribute('model'));
        if (!$model) {
            Mage::throwException($this->_getLog()->__(
                    "Trying to load %s, model not found", $decompressor->getAttribute('model')));
        }

        if (!$model instanceof Ho_Import_Model_Decompressor_Abstract) {
            Mage::throwException($this->_getLog()->__(
                "Decompressor model %s must be instance of Ho_Import_Model_Decompressor_Abstract",
                $decompressor->getAttribute('model')
            ));
        }

        $args = array_merge($decompressor->asArray());
        unset($args['@']);

        $transport = $this->_getTransport();
        $transport->addData($args);

        try {
            $model->decompress($transport);
        } catch (Exception $e) {
            $this->_getLog()->log($this->_getLog()->__(
                    "Error while decompressing file (%s):", $decompressor->getAttribute('model')), Zend_Log::ERR);
            Mage::throwException($e->getMessage());
        }
    }

    protected function _createImportCsv()
    {
        /** @var SeekableIterator $sourceAdapter */
        $sourceAdapter = $this->getSourceAdapter();
        $this->_runEvent('process_before', $this->_getTransport()->setData('adapter', $sourceAdapter));
        $timer = microtime(true);
        /** @var Mage_ImportExport_Model_Export_Adapter_Abstract $exportAdapter */
        $exportAdapter = Mage::getModel('importexport/export_adapter_csv', $this->_getFileName());
        $fieldNames    = $this->_getMapper()->getFieldNames();
        $rowCount    = 0;
        $entityCount = 0;
        while ($sourceAdapter->valid()) {
            $entityCount++;
            $transport = $this->_getTransport()->setItems(array($sourceAdapter->current()));
            $this->_runEvent('source_row_fieldmap_before', $transport);
            if ($transport->getSkip()) {
                $rowCount++;
                $sourceAdapter->next();
                continue;
            }
            foreach ($transport->getItems() as $preparedItem) {
                $result = $this->_fieldMapItem($preparedItem);
                $transport = $this->_getTransport()->setItems($result);
                $this->_runEvent('source_row_fieldmap_after', $transport);
                foreach ($transport->getItems() as $row) {
                    $rowCount++;
                    $exportAdapter->writeRow(array_merge($fieldNames, $row));
                }
            }
            $sourceAdapter->next();
        }
        $transport = $this->_getTransport();
        $this->_runEvent('source_fieldmap_after', $transport);
        if ($transport->getItems()) {
            foreach ($transport->getItems() as $item) {
                $rowCount++;
                $exportAdapter->writeRow(array_merge($fieldNames, $item));
            }
        }
        $this->setRowCount($rowCount);
        $this->setEntityCount($entityCount);
        $seconds       = round(microtime(true) - $timer, 2);
        $rowsPerSecond = $seconds ? round($this->getRowCount() / $seconds, 2) : $this->getRowCount();
        $this->_getLog()->log(
            $this->_getLog()->__(
                'Fieldmapping %s with %s rows, %s entities (done in %s seconds, %s rows/s)',
                $this->getProfile(),
                $this->getRowCount(),
                $this->getEntityCount(),
                $seconds,
                $rowsPerSecond
            )
        );
        if ($this->getRowCount()) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @return Ho_Import_Model_Import
     */
    protected function _importMain()
    {
        $importData = $this->getImportData();
        if (is_array($importData)) {
            if (isset($importData['dropdown_attributes'])) {
                $importData['dropdown_attributes'] = explode(',', $importData['dropdown_attributes']);
            }

            foreach ($importData as $key => $value) {
                $this->_getLog()->log($this->_getLog()->__('Setting option %s to %s', $key, $value));
                $this->_fastSimpleImport->setDataUsingMethod($key, (string)$value);
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

    protected function _applyImportOptions()
    {
        $options = $this->_getConfigNode(self::IMPORT_CONFIG_IMPORT_OPTIONS);
        if ($options) {
            foreach ($options->children() as $key => $child) {
                /** @var Mage_Core_Model_Config_Element $child */

                $value = $child->asArray();
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
            Mage::throwException($this->_getLog()->__(
                    "Trying to run %s, helper not found %s", $event, $helperParts[0]));
        }

        $method = $helperParts[1];
        if (!method_exists($helper, $method)) {
            Mage::throwException($this->_getLog()->__(
                    "Trying to run %s, method %s::%s not found.", $event, $helperParts[0], $method));
        }

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
        $mapper->setSymbolIgnoreFields($this->_fastSimpleImport->getSymbolIgnoreFields());
        $mapper->setItem($item);
        $symbolForClearField = $this->_fastSimpleImport->getSymbolEmptyFields();
        $profile = $this->getProfile();


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
                    && (
                        !isset($itemRows[$storeCode][0][$fieldName])
                        || $itemRows[$storeCode][0][$fieldName] === NULL
                    )) {
                    $itemRows[$storeCode][0][$fieldName] = $symbolForClearField;
                }
            }
        }

        //Step 2: Flatten all the rows.
        $flattenedRows = array();
        foreach ($itemRows as $store => $storeData) {
            foreach ($storeData as $storeKey => $storeRow) {
                $flatRow = array();
                foreach ($allFieldConfig[$store] as $key => $column) {
                    if (isset($storeRow[$key]) && (strlen($storeRow[$key]))) {
                        $flatRow[$key] = (string)$storeRow[$key];
                    }
                }

                if ($store == 'admin' && $storeKey == 0 && !isset($flatRow['ho_import_profile'])) {
                    $flatRow['ho_import_profile'] = $profile;
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


    /**
     * Get the intermediate filename for importing the entities
     * @return string
     */
    protected function _getFileName()
    {
        return Mage::getBaseDir('var') . DS . 'import' . DS . $this->getProfile() . '.csv';
    }


    /**
     * get the intermediate filename for cleaning up entities
     * @return string
     */
    protected function _getCleanFileName()
    {
        return Mage::getBaseDir('var') . DS . 'import' . DS . $this->getProfile() . '_clean.csv';
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
    protected function _importData($fileName = null, $profile = null)
    {
        $timer = microtime(true);
        $fileName = $fileName ?: $this->_getFileName();
        $profile = $profile ?: $this->getProfile();

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
                $this->_getLog()->log($this->_getLog()->__('Start profile %s', $fileName));
                $this->_fastSimpleImport->{$importMethods[$entityType]}($fileName);
            } else {
                $this->_getLog()->log($this->_getLog()->__('Type %s not found', $entityType));
            }
        } catch (Exception $e) {
            $seconds  = round(microtime(true) - $timer, 2);
            $this->_getLog()->log($this->_getLog()->__(
                'Exception while running profile %s, ran for %s seconds',
                $profile, $seconds
            ), Zend_Log::CRIT);

            $this->_getLog()->log($this->_getLog()->getExceptionTraceAsString($e), Zend_Log::CRIT);
            throw $e;
        }

        $seconds           = round(microtime(true) - $timer, 2);
        $rowsPerSecond     = round($this->getRowCount() / $seconds, 2);
        $entitiesPerSecond = round($this->getEntityCount() / $seconds, 2);
        $this->_getLog()->log($this->_getLog()->__(
            'Profile %s done in %s seconds, %s entities/s, %s rows/s.',
            $profile, $seconds, $entitiesPerSecond, $rowsPerSecond
        ));

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
            /** @var Mage_Core_Model_Config_Element $value */
            //asArray doesn't actually always return an array, it can also return a string.
            $arguments[$key] = $value->asArray();
        }

        if ($this->getSourceOptions() && is_array($this->getSourceOptions())) {
            foreach ($this->getSourceOptions() as $key => $value) {
                /** @var Mage_Core_Model_Config_Element $value */
                $arguments[$key] = $value->asArray();
            }
        }

        if (isset($arguments['file']) && !is_readable($arguments['file'])) {

            /** @noinspection PhpUndefinedFieldInspection */
            $arguments['file'] = Mage::getBaseDir() . DS . (string)$source->file;
            if (!is_readable($arguments['file'])) {

                /** @noinspection PhpUndefinedFieldInspection */
                Mage::throwException(Mage::helper('importexport')->__(
                        "%s file does not exists or is not readable", $source->file));
            }
        }

        if (count($arguments) == 1 && isset($arguments['file'])) {
            $arguments = $arguments['file'];
        }

        $this->_getLog()->log($this->_getLog()->__('Getting source adapter %s', $source->getAttribute('model')));
        $importModel = Mage::getModel($source->getAttribute('model'), $arguments);

        if (!$importModel) {
            Mage::throwException($this->_getLog()->__(
                'Import model (%s) not found for profile %s',
                $source->getAttribute('model'), $this->getProfile()
            ));
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
                $this->_getLog()->log($this->_getLog()->__(
                        "%s on lines %s", $type, implode(',', $errorLines)), Zend_Log::ERR);
            }
        } else {
            $this->_getLog()->log($this->_getLog()->__(
                    'No errors found in %s rows', $this->getRowCount()), Ho_Import_Helper_Log::LOG_SUCCESS);
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
        $this->_getLog()->log($this->_getLog()->__(
                "Debugging first 5 error lines %s", implode(',', $errorLines)), Zend_Log::DEBUG);

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
        return $this->setData('profile', $profile);
    }



    /**
     * Archive the old file.
     * @return $this
     */
    protected function _archiveOldCsv()
    {
        $allowArchive = (bool) $this->_getConfigNode(self::IMPORT_CONFIG_IMPORT_OPTIONS.'/archive_import_files');

        $fileName = $this->_getFileName();
        if (!$allowArchive || !file_exists($fileName)) {
            return $this;
        }

        $pathInfo = pathinfo($this->_getFileName());
        $fileTime = date('Ymd-His', filemtime($this->_getFileName()));
        $newFileName = $pathInfo['dirname'].DS.$pathInfo['basename'] .'-'.$fileTime.'.'.$pathInfo['extension'];

        $this->_getLog()->log($this->_getLog()->__(
                "Archiving old import file, renaming to %s", basename($newFileName)), Zend_Log::INFO);

        rename($fileName, $newFileName);
        return $this;
    }


    protected function _getCleanMode()
    {
        return (string) $this->_getConfigNode(self::IMPORT_CONFIG_CLEAN.'/mode');
    }


    /**
     * @return int
     */
    protected function _createEntityCleanCsv()
    {
        if (! $this->_getCleanMode()) {
            return 0;
        }
        Mage::helper('ho_import')->getCurrentDatetime();
        $resource = Mage::getSingleton('core/resource');

        /** @var Magento_Db_Adapter_Pdo_Mysql $adapter */
        $adapter = $resource->getConnection('write');

        /** @var Mage_Eav_Model_Entity_Type $entityType */
        $cleanMode = $this->_getCleanMode();

        $skus = $adapter->fetchCol($this->_getCleanSelect());

        /** @var Mage_ImportExport_Model_Export_Adapter_Abstract $exportAdapter */
        $exportAdapter = Mage::getModel('importexport/export_adapter_csv', $this->_getCleanFileName());

        $rowCount = 0;
        foreach ($skus as $sku) {
            $rowCount++;

            $rowData = array('sku' => $sku);
            switch($cleanMode) {
                case 'hide':
                    $rowData['visibility'] = Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE;
                    break;

                case 'disable':
                    $rowData['status'] = Mage_Catalog_Model_Product_Status::STATUS_DISABLED;
                    break;

                case 'delete':
                    break;
            }
            $exportAdapter->writeRow($rowData);
        }

        if ($rowCount) {
            $this->_getLog()->log($this->_getLog()->__(
                'Prepared cleaning of %s entities with mode %s',
                $rowCount, $cleanMode
            ));
        }

        return $rowCount;
    }

    protected function _getEntityTypeId()
    {
        /** @var Mage_Eav_Model_Entity_Type $entityType */
        $entityType = Mage::getSingleton('eav/config')->getEntityType((string) $this->_getEntityType());

        return $entityType->getId();
    }

    protected function _getCleanSelect()
    {
        $resource = Mage::getSingleton('core/resource');

        /** @var Magento_Db_Adapter_Pdo_Mysql $adapter */
        $adapter = $resource->getConnection('write');
        $select = $adapter
            ->select()
            ->from(
                array('entity_profile' => $resource->getTableName('ho_import/entity')),
                array()
            )->where(
                'entity_profile.profile=?', $this->getProfile()
            )->where(
                'entity_profile.updated_at<?', Mage::helper('ho_import')->getCurrentDatetime()
            )->where(
                'entity_profile.entity_type_id=?', $this->_getEntityTypeId()
            );

        switch ($this->_getEntityType()) {
            case 'catalog_category':
                Mage::throwException('Cleaning categories not yet implemented');
                break;
            case 'catalog_product':
                $select->join(
                    array('entity' => $resource->getTableName('catalog/product')),
                    'entity.entity_id = entity_profile.entity_id',
                    array('sku')
                );
                break;
            case 'customer':
                Mage::throwException('Cleaning customers not yet implemented');
                break;
        }

        return $select;
    }


    /**
     * @return Ho_Import_Model_Import
     */
    protected function _processEntityCleanCsv()
    {
        $cleanMode = $this->_getCleanMode();
        $this->_applyImportOptions();
        if ($cleanMode == 'delete') {
            $this->_fastSimpleImport->setBehavior(Mage_ImportExport_Model_Import::BEHAVIOR_DELETE);
        } else {
            $this->_fastSimpleImport->setBehavior(Mage_ImportExport_Model_Import::BEHAVIOR_APPEND);
        }

        $errors = $this->_importData($this->_getCleanFileName(), $this->getProfile().'_clean');
        return $errors;
    }


    /**
     *
     */
    protected function _dryRunEntityCleanCsv()
    {

    }


    /**
     * @throws Mage_Core_Exception
     */
    protected function _cleanEntityLinkTable()
    {
        $resource = Mage::getSingleton('core/resource');

        /** @var Magento_Db_Adapter_Pdo_Mysql $adapter */
        $adapter = $resource->getConnection('write');
        $select = $adapter
            ->select()
            ->from(
                array('entity_profile' => $resource->getTableName('ho_import/entity'))
            )->where(
                'entity_profile.entity_type_id=?', $this->_getEntityTypeId()
            )->where(
                'entity.entity_id IS NULL'
            );

        switch ($this->_getEntityType()) {
            case 'catalog_category':
                $this->_getLog()->log('Cleaning category entities not yet implemented', Zend_Log::WARN);
                break;
            case 'catalog_product':
                $select->joinLeft(
                    array('entity' => $resource->getTableName('catalog/product')),
                    'entity.entity_id = entity_profile.entity_id'
                );
                break;
            case 'customer':
                $this->_getLog()->log('Cleaning customer entities not yet implemented', Zend_Log::WARN);
                break;
        }

        $adapter->deleteFromSelect($select, 'entity_profile');
    }
}
