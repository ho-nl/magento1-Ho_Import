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
    const IMPORT_CONFIG_MEMORY_LIMIT   = 'global/ho_import/%s/memory_limit';

    /**
     * @var AvS_FastSimpleImport_Model_Import
     */
    protected $_fastSimpleImport = NULL;

    protected function _construct()
    {
        parent::_construct();
        $this->_fastSimpleImport = Mage::getModel('fastsimpleimport/import');
    }

    /**
     * @throws Exception
     * @return \Ho_Import_Model_Import
     */
    public function process()
    {
        $logger = $this->_getLog();
        if ($level = $this->getLogLevel()) {
            $this->_getLog()->setMinLogLevel($level);
        }

        ini_set('memory_limit', $this->getMemoryLimit());

        if (!array_key_exists($this->getProfile(), $this->getProfiles())) {
            Mage::throwException($logger->__("Profile %s not found", $this->getProfile()));
        }

        $this->_applyImportOptions();
        $this->_downloader();
        $this->_decompressor();

        $logger->log($logger->__('Mapping source fields and saving to temp csv file (%s)', $this->_getFileName()));

        $this->_archiveOldCsv();
        $hasRows = $this->_createImportCsv();
        if (! $hasRows) {
            $this->_runEvent('process_after');
            return;
        }

        $importData = $this->getImportData();
        if (isset($importData['dryrun']) && $importData['dryrun'] == 1) {
            $logger->log($logger->__(
                'Dry run %s rows from temp csv file (%s)', $this->getRowCount(), $this->_getFileName()));

            $errors = $this->_dryRun();
        } else {
            $logger->log($logger->__(
                'Processing %s rows from temp csv file (%s)', $this->getRowCount(), $this->_getFileName()));

            $errors = $this->_importMain();
        }

        $this->_logErrors($errors);
        $this->_debugErrors($errors);

        $cleanRowCount = $this->_createEntityCleanCsv();
        $this->setRowCount($cleanRowCount);

        if ($cleanRowCount) {
            if (isset($importData['dryrun']) && $importData['dryrun'] == 1) {
                $logger->log($logger->__(
                    'Dry run cleaning %s rows from temp csv file (%s)', $cleanRowCount, $this->_getCleanFileName()));
                $errors = $this->_dryRunEntityCleanCsv();
            } else {
                $logger->log($logger->__(
                    'Clean %s rows from temp csv file (%s)', $cleanRowCount, $this->_getCleanFileName()));
                $errors = $this->_processEntityCleanCsv();
            }

            $this->_logErrors($errors);
            $this->_debugErrors($errors);
        }

        if (!is_null($this->_sourceFile) && !is_null($this->_sourceProcessedPath)) {
            if (!file_exists($this->_sourceProcessedPath)) {
                $this->_sourceProcessedPath = Mage::getBaseDir() . DS . (string) $this->_sourceProcessedPath;
            }
            if (is_dir_writeable($this->_sourceProcessedPath)) {
                if (!rename($this->_sourceFile, $this->_sourceProcessedPath . DS . basename($this->_sourceFile))) {
                    $logger->log($logger->__('Failed to move source file (%s) to archive directory (%s)',
                        $this->_sourceFile, $this->_sourceProcessedPath), Zend_Log::WARN);
                }
            } else {
                $logger->log($logger->__('Failed to move source file, archive directory (%s) non-existant or not writable',
                    $this->_sourceProcessedPath), Zend_Log::WARN);
            }
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

                    $transportData = $transport->getData();
                    $transport = $this->_getTransport()
                        ->setItems($results)
                        ->setData($transportData);

                    $this->_runEvent('source_row_fieldmap_after', $transport);
                    $this->_configurableExtractRow($transport, $preparedItem);

                    foreach ($transport->getItems() as $result) {
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
                $sourceAdapter->next();
                continue;
            }
            foreach ($transport->getItems() as $preparedItem) {
                $results = $this->_fieldMapItem($preparedItem);

                $transportData = $transport->getData();
                $transport = $this->_getTransport()
                    ->setItems($results)
                    ->setData($transportData);

                $this->_runEvent('source_row_fieldmap_after', $transport);
                $this->_configurableExtractRow($transport, $preparedItem);
                foreach ($transport->getItems() as $row) {
                    $rowCount++;
                    $exportAdapter->writeRow(array_merge($fieldNames, $row));
                }
            }
            $sourceAdapter->next();
        }
        $transport = $this->_getTransport();
        $this->_runEvent('source_fieldmap_after', $transport);
        $this->_configurableGetConfigurables($transport);
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
        $mapper = $this->_getMapper();
        $mapper->setSymbolIgnoreFields($this->_fastSimpleImport->getSymbolIgnoreFields());
        $mapper->setItem($item);
        $fieldConfig = $this->_getMapper()->getFieldConfig();
        $itemRows    = $this->_fieldMapItemExtract($fieldConfig);
        return $this->_fieldMapItemFlatten($itemRows, $fieldConfig);
    }

    /**
     * @param $item
     * @param $fieldConfig
     *
     * @return array
     */
    protected function _fieldMapItemExtract($fieldConfig)
    {
        $itemRows = array();

        $mapper = $this->_getMapper();
        $symbolForClearField = $this->_fastSimpleImport->getSymbolEmptyFields();

        //Step 1: Get the values for the fields
        foreach ($fieldConfig as $storeCode => $fields) {
            $mapper->setStoreCode($storeCode);
            /** @var $column Mage_Core_Model_Config_Element */
            foreach ($fields as $fieldName => $fieldConfig) {
                $result = $this->_getMapper()->mapItem($fieldConfig);

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
        return $itemRows;
    }


    /**
     * @param $itemRows
     * @param $fieldConfig
     */
    protected function _fieldMapItemFlatten(&$itemRows, $fieldConfig)
    {
        $profile = $this->getProfile();

        //Step 2: Flatten all the rows.
        $flattenedRows = array();
        foreach ($itemRows as $store => $storeData) {
            foreach ($storeData as $storeKey => $storeRow) {
                $flatRow = array();
                foreach ($fieldConfig[$store] as $key => $column) {
                    if (isset($storeRow[$key]) && (strlen($storeRow[$key]))) {
                        $flatRow[$key] = (string)$storeRow[$key];
                    }
                }

                if ($store == 'admin' && $storeKey == 0 && !isset($flatRow['ho_import_profile'])) {
                    $flatRow['ho_import_profile'] = $profile;
                }

                if ($flatRow) {
                    //if a column is required we add it here.
                    foreach ($fieldConfig[$store] as $key => $column) {
                        if (!isset($flatRow[$key]) && isset($column['@']) && isset($column['@']['required'])) {
                            $flatRow[$key] = '';
                        }
                    }

                    if ($this->_getEntityType() != 'customer') {
                        $flatRow['_store'] = $store == 'admin' ? '' : $store;
                    }
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
        return Mage::getSingleton('ho_import/mapper')->setImporter($this);
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
    protected $_sourceAdapter = null;
    protected $_sourceFile = null;
    protected $_sourceProcessedPath = null;
    public function getSourceAdapter()
    {
        $source = $this->_getConfigNode(self::IMPORT_CONFIG_MODEL);

        if ($this->_sourceAdapter !== null) {
            return $this->_sourceAdapter;
        }

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
                if (is_string($value)) {
                    $arguments[$key] = $value;
                } else {
                    $arguments[$key] = $value->asArray();
                }
            }
        }

        if (isset($arguments['glob'])) {
            /** @noinspection PhpUndefinedFieldInspection */
            $files = glob(Mage::getBaseDir() . DS . (string) $source->glob, GLOB_BRACE);

            if (!is_array($files) || !count($files)) {
                /** @noinspection PhpUndefinedFieldInspection */
                Mage::throwException(Mage::helper('importexport')->__(
                    "The glob \"%s\" does not match any files", $source->glob));
            }
            sort($files);

            $arguments['file'] = array_shift($files);
        } elseif (isset($arguments['file']) && !is_readable($arguments['file'])) {

            /** @noinspection PhpUndefinedFieldInspection */
            $arguments['file'] = Mage::getBaseDir() . DS . (string) $source->file;
        }

        if (isset($arguments['file'])) {
            if (!is_readable($arguments['file'])) {

                /** @noinspection PhpUndefinedFieldInspection */
                Mage::throwException(Mage::helper('importexport')->__(
                    "%s file does not exists or is not readable", $source->file));
            }

            $this->_sourceFile = $arguments['file'];

            if (isset($arguments['archive_path'])) {
                $this->_sourceProcessedPath = $arguments['archive_path'];
            }
        }

        if (count($arguments) == 1 && isset($arguments['file'])) {
            $arguments = $arguments['file'];
        }

        $this->_runEvent('source_adapter_before', $this->getProfile());
        $this->_getLog()->log($this->_getLog()->__('Getting source adapter %s', $source->getAttribute('model')));
        $importData = $this->getImportData();
        if (is_array($arguments)) {
            $arguments = array_merge($arguments, is_null($importData) ? [] : $importData);
        }
        $this->_sourceAdapter = Mage::getModel($source->getAttribute('model'), $arguments);

        if (!$this->_sourceAdapter) {
            Mage::throwException($this->_getLog()->__(
                'Import model (%s) not found for profile %s',
                $source->getAttribute('model'), $this->getProfile()
            ));
        }

        return $this->_sourceAdapter;
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
     * @return string
     */
    protected function getMemoryLimit()
    {
        if ($limit = $this->_getConfigNode(self::IMPORT_CONFIG_MEMORY_LIMIT)) {
            return $limit;
        }
        return '2048M';
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
        $allowArchive = (bool) ((string)$this->_getConfigNode(self::IMPORT_CONFIG_IMPORT_OPTIONS.'/archive_import_files'));

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

        $select = $this->_getCleanSelect();
        if (! $select) {
            return 0;
        }
        $entities = $adapter->fetchAll($select);

        /** @var Mage_ImportExport_Model_Export_Adapter_Abstract $exportAdapter */
        $exportAdapter = Mage::getModel('importexport/export_adapter_csv', $this->_getCleanFileName());

        $rowCount = 0;
        foreach ($entities as $entity) {
            $rowCount++;
            switch ($this->_getEntityType()) {
                case 'catalog_category':
                    $parts = explode('/', $entity['_root']);
                    $entity['_root'] = $parts[1];
            }
            $exportAdapter->writeRow($entity);
        }

        if ($rowCount) {
            $this->_getLog()->log($this->_getLog()->__(
                'Prepared cleaning of %s entities with mode %s',
                $rowCount, $this->_getCleanMode()
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
        $cleanMode = $this->_getCleanMode();
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
                $select->join(
                    array('entity' => $resource->getTableName('catalog/category')),
                    'entity.entity_id = entity_profile.entity_id',
                    array('_category' => 'entity.entity_id', '_root' => 'entity.path')
                );

                switch($cleanMode) {
                    case 'hide':
                        $this->_getLog()->log('Hiding categories is not yet implemented yet, skipping.');
                        return false;

                        $notVisible = Mage::helper('catalog')->__('No');
                        $select->columns(['include_in_menu' => new Zend_Db_Expr("'$notVisible'")]);
                        $select->columns(['m_show_in_layered_navigation' => new Zend_Db_Expr("'$notVisible'")]);
                        break;

                    case 'disable':
                        $this->_getLog()->log('Disabling categories is not yet implemented yet, skipping.');
                        return false;

                        $disabled = Mage::helper('catalog')->__('No');
                        $select->columns(['is_active' => new Zend_Db_Expr("'$disabled'")]);
                        break;
                }

                break;
            case 'catalog_product':
                $select->join(
                    array('entity' => $resource->getTableName('catalog/product')),
                    'entity.entity_id = entity_profile.entity_id',
                    array('sku')
                );

                switch($cleanMode) {
                    case 'hide':
                        $notVisible = Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE;
                        $select->columns(['visibility' => new Zend_Db_Expr("'$notVisible'")]);
                        break;

                    case 'disable':
                        $disabled = Mage_Catalog_Model_Product_Status::STATUS_DISABLED;
                        $select->columns(['status' => new Zend_Db_Expr("'$disabled'")]);
                        break;
                }

                break;
            default:
                $this->_getLog()->log(sprintf("Cleaning '%s' entities not yet implemented, skipping.", $this->_getEntityType()), Zend_Log::WARN);
                return false;
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

    protected function _dryRunEntityCleanCsv()
    {
        // -
    }

    /**
     * @throws Mage_Core_Exception
     */
    protected function _cleanEntityLinkTable()
    {
        /**
         * Skip this procedure if entity type is catalog_category_product.
         * Magento does not recognise such type(\Ho_Import_Model_Import::_getEntityTypeId() fails) neither needed in this method at all.
         */
        if ($this->_getEntityType() == self::IMPORT_TYPE_CATEGORY_PRODUCT) {
            $this->_getLog()->log(
                $this->_getLog()->__("Entity %s cleaned with mode %s",
                    self::IMPORT_TYPE_CATEGORY_PRODUCT,
                    $this->_fastSimpleImport->getBehavior()
                )
            );

            return;
        }

        $resource = Mage::getSingleton('core/resource');

        /** @var Magento_Db_Adapter_Pdo_Mysql $adapter */
        $adapter = $resource->getConnection('write');
        $select = $adapter
            ->select()
            ->from(
                array('entity_profile' => $resource->getTableName('ho_import/entity'))
            )->where(
                'entity_profile.entity_type_id = ?', $this->_getEntityTypeId()
            )->where(
                'entity.entity_id IS NULL'
            );

        switch ($this->_getEntityType()) {
            case 'catalog_category':
                $select->joinLeft(
                    array('entity' => $resource->getTableName('catalog/category')),
                    'entity.entity_id = entity_profile.entity_id'
                );
                break;
            case 'catalog_product':
                $select->joinLeft(
                    array('entity' => $resource->getTableName('catalog/product')),
                    'entity.entity_id = entity_profile.entity_id'
                );
                break;
            default:
                $this->_getLog()->log(sprintf("Cleaning '%s' entities not yet implemented.", $this->_getEntityType()), Zend_Log::WARN);
                return;
        }

        $resource->getConnection('core_write')->query($adapter->deleteFromSelect($select, 'entity_profile'));
    }

    const IMPORT_CONFIG_CB = 'global/ho_import/%s/configurable_builder';
    const IMPORT_CONFIG_CB_SKU = 'global/ho_import/%s/configurable_builder/sku';
    const IMPORT_CONFIG_CB_ATTRIBUTES = 'global/ho_import/%s/configurable_builder/attributes';
    const IMPORT_CONFIG_CB_FIELDMAP = 'global/ho_import/%s/configurable_builder/fieldmap';
    const IMPORT_CONFIG_CB_CALCULATE_PRICE = 'global/ho_import/%s/configurable_builder/calculate_price';
    const IMPORT_CONFIG_CB_CALCULATE_PRICE_IN_STOCK = 'global/ho_import/%s/configurable_builder/calculate_price_in_stock';

    protected $_configurables = [];

    protected function _configurableInitProduct($sku, &$preparedItem)
    {
        $mapper = $this->_getMapper();
        $mapper->setSymbolIgnoreFields($this->_fastSimpleImport->getSymbolIgnoreFields());
        $mapper->setItem($preparedItem);
        $this->_configurables[$sku] = $this->_fieldMapItemExtract($this->_configurableFieldConfig());

        $calculatePrice = (bool) $this->_getConfigNode(self::IMPORT_CONFIG_CB_CALCULATE_PRICE);
        if ($calculatePrice) {
            $this->_configurables[$sku]['admin'][0]['_instock_price'] = PHP_INT_MAX;
            $this->_configurables[$sku]['admin'][0]['_allprod_price'] = PHP_INT_MAX;
            $this->_configurables[$sku]['admin'][0]['_instock_special_price'] = PHP_INT_MAX;
            $this->_configurables[$sku]['admin'][0]['_allprod_special_price'] = PHP_INT_MAX;
        }

        return $this;
    }


    protected function _configurableExtractRow(Ho_Import_Model_Import_Transport $transport, &$preparedItem)
    {
        if (! $this->_getConfigNode(self::IMPORT_CONFIG_CB)) {
            return $this;
        }

        if ($this->_getEntityType() != self::IMPORT_TYPE_PRODUCT) {
            return $this;
        }

        $configurableSku = $this->_getConfigNode(self::IMPORT_CONFIG_CB_SKU);
        $calculatePrice = (bool) $this->_getConfigNode(self::IMPORT_CONFIG_CB_CALCULATE_PRICE);
        $calculatePriceInStock = (bool) $this->_getConfigNode(self::IMPORT_CONFIG_CB_CALCULATE_PRICE_IN_STOCK);
        $sku = $this->_getMapper()->mapItem($configurableSku);
        if (! $sku) {
            return $this;
        }

        // Force array if single attribute is given with the 'value' parameter.
        $configurableAttributes = (array) $this->_getMapper()->mapItem(
            $this->_getConfigNode(self::IMPORT_CONFIG_CB_ATTRIBUTES)
        );

        if (! isset($this->_configurables[$sku])) {
            $this->_configurableInitProduct($sku, $preparedItem);
        }

        $rowNum = 0;
        foreach ($this->_configurables[$sku]['admin'] as $row) {
            if (isset($row['_super_products_sku'])) {
                $rowNum++;
            }
        }

        foreach ($transport->getItems() as $item) {
            if (! isset($item['sku']) || isset($item['_custom_option_sku'])) {
                continue;
            }

            $price = (float) $item['price'];
            $specialPrice = (float) (isset($item['special_price']) ? $item['special_price'] : 0);

            //lowest instock price
            if ($price && $calculatePrice
                && (!$calculatePriceInStock || $item['is_in_stock'])
                && $price < $this->_configurables[$sku]['admin'][0]['_instock_price']) {
                $this->_configurables[$sku]['admin'][0]['_instock_price'] = $price;
            }

            //lowest instock special_price
            if ($specialPrice > 0 && $calculatePrice
                && (!$calculatePriceInStock || $item['is_in_stock'])
                && $specialPrice < $this->_configurables[$sku]['admin'][0]['_instock_special_price']) {
                $this->_configurables[$sku]['admin'][0]['_instock_special_price'] = $specialPrice;
            }

            //lowest allprod price
            if ($price && $calculatePrice
                && $price < $this->_configurables[$sku]['admin'][0]['_allprod_price']) {
                $this->_configurables[$sku]['admin'][0]['_allprod_price'] = $price;
            }

            //lowest allprod special_price
            if ($specialPrice > 0 && $calculatePrice
                && $specialPrice < $this->_configurables[$sku]['admin'][0]['_allprod_price']) {
                $this->_configurables[$sku]['admin'][0]['_allprod_special_price'] = $specialPrice;
            }

            foreach ($configurableAttributes as $attribute) {
                $this->_configurables[$sku]['admin'][$rowNum]['_super_products_sku'] = $item['sku'];
                $this->_configurables[$sku]['admin'][$rowNum]['_super_attribute_code'] = $attribute;
                $this->_configurables[$sku]['admin'][$rowNum]['_super_attribute_option'] = $item[$attribute];
                $this->_configurables[$sku]['admin'][$rowNum]['_super_attribute_price_corr'] = null;
                $this->_configurables[$sku]['admin'][$rowNum]['_super_attribute_final_price'] =
                    (float) ($specialPrice > 0 ? $specialPrice : $price);

                $rowNum++;
            }
        }

        return $this;
    }

    protected function _configurableFieldConfig()
    {
        $fieldConfig = $this->_getMapper()->getFieldConfig();
        $fieldConfigAdd = $this->_getMapper()->getFieldConfig(null, null, self::IMPORT_CONFIG_CB_FIELDMAP);

        foreach ($fieldConfig as $store => &$config) {
            if (! isset($fieldConfigAdd[$store])) {
                continue;
            }

            $config = $fieldConfigAdd[$store] + $config;
        }
        $fieldConfig['admin']['sku'] = $this->_getConfigNode(self::IMPORT_CONFIG_CB_SKU);

        return $fieldConfig;
    }

    protected function _configurableGetConfigurables(Ho_Import_Model_Import_Transport $transport)
    {
        if (! $this->_getConfigNode(self::IMPORT_CONFIG_CB)) {
            return $this;
        }

        $calculatePrice = (bool) $this->_getConfigNode(self::IMPORT_CONFIG_CB_CALCULATE_PRICE);
        $fieldConfig = $this->_configurableFieldConfig();
        $fieldConfig['admin']['_super_products_sku'] = [];
        $fieldConfig['admin']['_super_attribute_code'] = [];
        $fieldConfig['admin']['_super_attribute_option'] = [];
        $fieldConfig['admin']['_super_attribute_price_corr'] = [];

        foreach ($this->_configurables as $configurable) {
            // Recalculate price.
            if ($calculatePrice) {
                //No simple products are in stock, we need to calculate the price based on all the products instead of only the products in stock.
                $noInStockPrice = $configurable['admin'][0]['_instock_price'] == PHP_INT_MAX;
                $priceKey =        $noInStockPrice ? '_allprod_price'         : '_instock_price';
                $specialPriceKey = $noInStockPrice ? '_allprod_special_price' : '_instock_special_price';

                //Check if special_price on the configurable is smaller than the price, if so, use the special price for calculation of the price_corr
                $hasSpecialPrice = $configurable['admin'][0][$specialPriceKey] < $configurable['admin'][0][$priceKey];
                $finalPriceKey = $hasSpecialPrice ? $specialPriceKey : $priceKey;

                $minPrice = $configurable['admin'][0][$finalPriceKey];
                $configurable['admin'][0]['price'] = $configurable['admin'][0][$priceKey];
                $configurable['admin'][0]['special_price'] =
                    $configurable['admin'][0][$specialPriceKey] != PHP_INT_MAX
                    && $configurable['admin'][0][$specialPriceKey] > 0
                    && $configurable['admin'][0][$specialPriceKey] < $configurable['admin'][0][$priceKey]
                        ? $configurable['admin'][0][$specialPriceKey]
                        : $this->_fastSimpleImport->getSymbolEmptyFields();

                foreach ($configurable['admin'] as &$row) {
                    if (! isset($row['_super_attribute_final_price'])) {
                        continue;
                    }

                    $row['_super_attribute_price_corr'] = ($row['_super_attribute_final_price']) - $minPrice;
                }
            }

            $items = $this->_fieldMapItemFlatten($configurable, $fieldConfig);
            foreach ($items as $item) {
                $transport->addItem($item);
            }
        }

        return $this;
    }
}
