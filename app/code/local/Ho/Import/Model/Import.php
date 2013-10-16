<?php
/**
 * Ho_Import
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the H&O Commercial License
 * that is bundled with this package in the file LICENSE_HO.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.h-o.nl/license
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to info@h-o.com so we can send you a copy immediately.
 *
 * @category    Ho
 * @package     Ho_Import
 * @copyright   Copyright © 2012 H&O (http://www.h-o.nl/)
 * @license     H&O Commercial License (http://www.h-o.nl/license)
 * @author      Paul Hachmang – H&O <info@h-o.nl>
 */
/**
 * @method Ho_Import_Model_Import setProfile(string $profile)
 * @method string getProfile()
 * @method Ho_Import_Model_Import setImportData(array $importData)
 * @method array getImportData()
 * @method Ho_Import_Model_Import setRowCount(int $rowCount)
 * @method int getRowCount()
 * @method Ho_Import_Model_Import setDryrun(bool $drurun)
 * @method int getDryrun()
 */
class Ho_Import_Model_Import extends Varien_Object
{
    const IMPORT_TYPE_PRODUCT  = 'catalog_product';
    const IMPORT_TYPE_CUSTOMER = 'customer';
    const IMPORT_TYPE_CATEGORY = 'catalog_category';

    const IMPORT_CONFIG_DOWNLOADER     = 'global/ho_import/%s/downloader';
    const IMPORT_CONFIG_MODEL          = 'global/ho_import/%s/source';
    const IMPORT_CONFIG_FIELDMAP       = 'global/ho_import/%s/fieldmap';
    const IMPORT_CONFIG_ENTITY_TYPE    = 'global/ho_import/%s/entity_type';
    const IMPORT_CONFIG_EVENTS         = 'global/ho_import/%s/events';
    const IMPORT_CONFIG_IMPORT_OPTIONS = 'global/ho_import/%s/import_options';

    protected $_sourceAdapter = null;

    protected $_fieldMap = null;

    protected $_fileName = null;

    protected function _construct() {
        ini_set('memory_limit', '2G');
    }

    /**
     * @throws Exception
     * @return \Ho_Import_Model_Import
     */
    public function process() {
        $this->_downloader();

        if (! array_key_exists($this->getProfile(), $this->getProfiles())) {
            Mage::throwException($this->_getLog()->__("Profile %s not found", $this->getProfile()));
        }

        $entity = $this->_getEntityType();
        $camel = new Zend_Filter_Word_UnderscoreToCamelCase();
        $method = '_import'.$camel->filter($entity);

        if (! method_exists($this, $method)) {
            Mage::throwException($this->_getLog()->__("Entity %s not supported", $entity));
        }

        $this->_getLog()->log($this->_getLog()->__('Mapping source fields and saving to temp csv file (%s)', $this->_getFileName()));
        $this->_createImportCsv();

        if ($this->getDryrun()) {
            $errors = $this->_dryRun();
        } else {
            $this->_getLog()->log($this->_getLog()->__('Importing %s rows from temp csv file (%s)', $this->getRowCount(), $this->_getFileName()));
            $errors = $this->$method();
        }

        $this->_logErrors($errors);
        $this->_debugErrors($errors);
    }

    public function mapLines($lines) {
        $this->_downloader();

        $lines = $lines ? explode(',',$lines) : array();

        /** @var SeekableIterator $sourceAdapter */
        $sourceAdapter = $this->getSourceAdapter();

        //search a line instead on specifying the line.
        $importData = $this->getImportData();
        if (! count($lines) && isset($importData['search'])) {
            $parts = explode('=',$importData['search']);

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
        } elseif (! count($lines)) {
            $lines = array(1);
        }

        $entities = array();
        $logEntities = array();
        $entityMap = array();
        $sourceRows = array();
        foreach ($lines as $line) {
            $this->_getLog()->log($this->_getLog()->__('Mapping %s:%s', $this->getProfile(), $line));

            $sourceAdapter->seek($line);
            if (! $sourceAdapter->valid()) {
                Mage::throwException($this->_getLog()->__("Line %s is not valid in %s", $line, $this->getProfile()));
            }

            $transport = $this->_getTransport();
            $sourceRows[$line] = $sourceAdapter->current();
            $transport->setData('items', array($sourceRows[$line]));
            $this->_runEvent('source_row_fieldmap_before', $transport);
            if ($transport->getData('skip')) {
                $this->_getLog()->log($this->_getLog()->__('This line (%s) would be skipped', $line), Zend_Log::WARN);
            }

            $i = 0;
            foreach ($transport->getData('items') as $preparedItem) {
                $results = $this->_fieldMapItem($preparedItem);

                foreach ($results as $result) {
                    $i++;

                    $entities[] = $result;
                    $logEntities[$line.':'.$i] = $result;
                    $entityMap[] = $line.':'.$i;
                }
            }
        }

        $this->_getLog()->log($sourceRows, Zend_Log::DEBUG);

        $errors = array();
        try {
            $errors = $this->_dryRun($entities);
        } catch (Exception $e) {
            $errors[$e->getMessage()] = $lines;
        }
        foreach ($errors as $error => $lines) {
            foreach ($lines as $line) {
                $key = $line - 1;
                $logEntities[$entityMap[$key]][$error] = '__ERROR__';
            }
        }
        $this->_getLog()->log($logEntities, Zend_Log::DEBUG);

        return true;
    }

    /** @var Varien_Object  */
    protected $_transport = NULL;
    protected function _getTransport() {
        if ($this->_transport === NULL) {
            return new Varien_Object();
        } else {
            $this->_transport->setData(array());
            $this->_transport->setOrigData(array());
            $this->_transport->setDataChanges(false);
        }
        return $this->_transport;
    }

    protected function _downloader() {
        $data = $this->getImportData();
        if (isset($data['skip_download']) && $data['skip_download'] == 1) {
            return;
        }

        $downloader = $this->_getConfigNode(self::IMPORT_CONFIG_DOWNLOADER);

        if (! $downloader) {
            return;
        }

        if (! $downloader->getAttribute('model')) {
            Mage::throwException($this->_getLog()->__("No attribute model found for <downloader> node"));
        }

        $model = Mage::getModel($downloader->getAttribute('model'));
        if (! $model) {
            Mage::throwException($this->_getLog()->__("Trying to load %s, model not found %s", $downloader));
        }

        if (! $model instanceof Ho_Import_Model_Downloader_Abstract) {
            Mage::throwException($this->_getLog()->__("Downloader model %s must be instance of Ho_Import_Model_Downloader_Abstract", $downloader->getAttribute('model')));
        }

        $target = $downloader->getAttribute('target') ?: 'var/import';

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

    protected function _createImportCsv() {
        /** @var SeekableIterator $sourceAdapter */
        $sourceAdapter = $this->getSourceAdapter();
        $timer = microtime(true);

        /** @var Mage_ImportExport_Model_Export_Adapter_Abstract $exportAdapter */
        $exportAdapter = Mage::getModel('importexport/export_adapter_csv', $this->_getFileName());

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
                    $exportAdapter->writeRow($row);
                }
            }

            $rowCount++;
            $sourceAdapter->next();
        }
        $this->setRowCount($rowCount);

        $seconds = round(microtime(true) - $timer, 2);
        $rowsPerSecond = round($this->getRowCount() / $seconds, 2);
        $this->_getLog()->log("Fieldmapping {$this->getProfile()} with {$this->getRowCount()} rows (done in $seconds seconds, $rowsPerSecond rows/s)");

        return true;
    }

    /**
     * Actual importmethod
     */
    protected function _importCustomer()
    {
        $this->_applyImportOptions();

        /* @var $import AvS_FastSimpleImport_Model_Import */
        $fastsimpleimport = Mage::getSingleton('fastsimpleimport/import');

        $importData = $this->getImportData();
        foreach ($importData as $key => $value) {
            $this->_getLog()->log($this->_getLog()->__('Setting option %s to %s', $key, $value));
            $fastsimpleimport->setDataUsingMethod($key, (string) $value);
        }

        $transport = $this->_getTransport();
        $transport->setData('object', $fastsimpleimport);
        $this->_runEvent('before_import');

        $errors = $this->_importData();

        $transport = $this->_getTransport();
        $transport->addData(array('object' => $fastsimpleimport, 'errors' => $errors));
        $this->_runEvent('after_import');
        return $errors;
    }


    /**
     * Actual importmethod
     */
    protected function _importCatalogProduct()
    {
        $this->_applyImportOptions();

        /* @var $import AvS_FastSimpleImport_Model_Import */
        $fastsimpleimport = Mage::getSingleton('fastsimpleimport/import');

        $importData = (array) $this->getImportData();
        if (isset($importData['dropdown_attributes'])) {
            $importData['dropdown_attributes'] = explode(',',$importData['dropdown_attributes']);
        }
        foreach ($importData as $key => $value) {
            $this->_getLog()->log($this->_getLog()->__('Setting option %s to %s', $key, $value));
            $fastsimpleimport->setDataUsingMethod($key, (string) $value);
        }

        $transport = $this->_getTransport();
        $transport->setData('object', $fastsimpleimport);
        $this->_runEvent('before_import');

        $errors = $this->_importData();

        $transport = $this->_getTransport();
        $transport->addData(array('object' => $fastsimpleimport, 'errors' => $errors));
        $this->_runEvent('after_import');
        return $errors;
    }

    protected function _importCatalogCategory()
    {
        $this->_applyImportOptions();

        /* @var $import AvS_FastSimpleImport_Model_Import */
        $fastsimpleimport = Mage::getSingleton('fastsimpleimport/import');
        $importData = $this->getImportData();

        if (isset($importData['ignoreErrors']) && $importData['ignoreErrors'] == 1) {
            $this->_getLog()->log('Continue after errors enabled');
            $fastsimpleimport->setContinueAfterErrors($importData['ignoreErrors']);
        }

        if (isset($importData['renameFiles']) && $importData['renameFiles'] == 0) {
            $fastsimpleimport->setAllowRenameFiles(false);
        }

        $transport = $this->_getTransport();
        $transport->setData('object', $fastsimpleimport);
        $this->_runEvent('before_import');

        $errors = $this->_importData();

        $transport = $this->_getTransport();
        $transport->addData(array('object' => $fastsimpleimport, 'errors' => $errors));
        $this->_runEvent('after_import');


//        /**
//         * CLEANUP START
//         */
//        /** @var AvS_FastSimpleImport_Model_Import_Entity_Category $adapter */
//        $adapter = $fastsimpleimport->getEntityAdapter();
//        $categoriesWithRoots = $adapter->getCategoriesWithRoots();
//
//        /** @var SeekableIterator $sourceAdapter */
//        $this->_sourceAdapter = null;
//        $sourceAdapter = $this->getSourceAdapter();
//
//        $rowCount = 0;
//        while ($sourceAdapter->valid()) {
//            $preparedItems = $this->_prepareItem($sourceAdapter->current());
//            foreach ($preparedItems as $preparedItem) {
//                $result = $this->_fieldMapItem($preparedItem);
//                foreach ($result as $item) {
//                    unset($categoriesWithRoots[$item['_root']][$item['_category']]);
//                }
//            }
//
//            $rowCount++;
//            $sourceAdapter->next();
//        }
//
//        $transport = $this->_getTransport();
//        $transport->setData('categories_with_roots', $categoriesWithRoots);
//        $this->_runEvent(self::IMPORT_CONFIG_AFTER_IMPORT, $transport);

        return $errors;
    }


    protected function _applyImportOptions() {
        /* @var $fastsimpleimport AvS_FastSimpleImport_Model_Import */
        $fastsimpleimport = Mage::getSingleton('fastsimpleimport/import');

        $options = $this->_getConfigNode(self::IMPORT_CONFIG_IMPORT_OPTIONS);
        if ($options) {
            foreach ($options->children() as $key => $value) {
                $this->_getLog()->log($this->_getLog()->__('Setting option %s to %s', $key, $value));
                if ($key == 'dropdown_attributes') {
                    $fastsimpleimport->setDataUsingMethod($key, explode(',',$value));
                } else {
                    $fastsimpleimport->setDataUsingMethod($key, (string) $value);
                }
            }
        }

        return $this;
    }

    protected function _runEvent($event, $transport = null) {
        $node = $this->_getConfigNode(self::IMPORT_CONFIG_EVENTS);
        if (! isset($node) || ! isset($node->$event) || ! $node->$event->getAttribute('helper')) {
            return $this;
        }

        $helperParts = explode('::',$node->$event->getAttribute('helper'));
        $helper = Mage::helper($helperParts[0]);
        if (! $helper) {
            Mage::throwException($this->_getLog()->__("Trying to run %s, helper not found %s", $event, $helperParts[0]));
        }

        $method = $helperParts[1];
        if (! method_exists($helper, $method)) {
            Mage::throwException($this->_getLog()->__("Trying to run %s, method %s::%s not found.", $event, $helperParts[0], $method));
        }

//        $this->_getLog()->log($this->_getLog()->__("Running event %s, %s::%s", $event, $helperParts[0], $method));

        $args = array_merge($node->$event->asArray());
        unset($args['@']);

        $transport = $transport !== null ? $transport : $this->_getTransport();
        call_user_func(array($helper, $method), $transport);
        return $transport;
    }



    /**
     * Get the column configuration for the current type
     *
     * @return array|mixed
     */
    protected function _getFieldMap()
    {
        $fieldMapPath = sprintf(self::IMPORT_CONFIG_FIELDMAP, $this->getProfile());
        if (! isset($this->_fieldMap[$fieldMapPath]))
        {
            $columns = Mage::getConfig()->getNode($fieldMapPath)->children();
            $columnsData = array();

            $stores = array();
            foreach (Mage::app()->getStores() as $store) {
                $stores[] = $store->getCode();
            }

            /** @var $column Mage_Core_Model_Config_Element */
            foreach ($columns as $key => $column) {

                foreach ($stores as $store) {
                    if ($column->store_view->$store) {
                        $columnsData[$store][$key] = $column->store_view->$store;
                    }
                }

                $columnsData['admin'][$key] = $columns->$key;
            }

            $this->_fieldMap[$fieldMapPath] = $columnsData;
        }

        return $this->_fieldMap[$fieldMapPath];
    }

    /**
     * Expand the fields to multiple rows, and preprocess the fields using a single helperfile.
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
     * @return array
     */
    protected function _fieldMapItem(&$item)
    {
        $itemRows = array();
        $fieldMap = $this->_getFieldMap();

        //Step 1. Get the fieldmap
        foreach ($fieldMap as $store => $columnData)
        {
            /** @var $column Mage_Core_Model_Config_Element */
            foreach ($columnData as $key => $column)
            {
                // ability to copy another field.
                if ($column->getAttribute('use')) {
                    $column = $columnData[$column->getAttribute('use')];
                }
                // get field value with a helper
                if ($column->getAttribute('helper')) {
                    //get the helper and method
                    $helperParts = explode('::',$column->getAttribute('helper'));
                    $helper = Mage::helper($helperParts[0]);
                    $method = $helperParts[1];

                    //prepare the arguments
                    $args = $column->asArray();
                    unset($args['@']);
                    unset($args['store_view']);
                    array_unshift($args, $item);

                    //get the results
                    $result = call_user_func_array(array($helper, $method), $args);

                    //add the values to the itemRows
                    if (is_array($result)) {
                        foreach ($result as $row => $value) {
                            $itemRows[$store][$row][$key] = $value;
                        }
                    } elseif($result !== null) {
                        $itemRows[$store][0][$key] = $result;
                    }
                }
                // get the exact value of a field
                elseif ($field = $column->getAttribute('field')) {

                    //allow us to traverse an array, keys split by a slash.
                    if (strpos($field, '/')) {
                        $fieldParts = explode('/',$field);

                        $value = $item;
                        foreach ($fieldParts as $part) {
                            $value = isset($value[$part]) ? $value[$part] : null;
                        }
                    } else {
                        $value = isset($item[$column->getAttribute('field')]) ? $item[$column->getAttribute('field')] : null;
                    }

                    //add the values to the itemRows
                    if (is_array($value)) {
                        foreach ($value as $row => $val) {
                            $itemRows[$store][$row][$key] = $val;
                        }
                    } elseif($value !== null) {
                        $itemRows[$store][0][$key] = $value;
                    }
                }
                // get a fixed value
                elseif ($column->getAttribute('value') !== null) {
                    $itemRows[$store][0][$key] = $column->getAttribute('value');
                }
            }
        }

        //Flatten all the rows.
        $flattenedRows = array();
        foreach ($itemRows as $store => $storeData)
        {
            foreach($storeData as $storeRow)
            {
                $flatRow = array();

                foreach($fieldMap[$store] as $key => $column) {
                    if (isset($storeRow[$key]) && (strlen($storeRow[$key]))) {
                        $flatRow[$key] = (string) $storeRow[$key];
                    }
                }

                if ($flatRow) {
                    //if a column is required we add it here.
                    foreach($fieldMap[$store] as $key => $column) {
                        if (! isset($flatRow[$key]) && $column->getAttribute('required')) {
                            $flatRow[$key] = '';
                        }
                    }

                    $flatRow['_store'] = $store == 'admin' ? '' : $store;
                    $flattenedRows[] = $flatRow;
                }
            }
        }
        unset($itemRows);
        unset($item);

        return $flattenedRows;
    }

    protected function _getFileName() {
        if ($this->_fileName === null) {
            $this->_fileName = Mage::getBaseDir('var') .DS.'import'.DS.$this->getProfile().'.csv';
        }

        return $this->_fileName;
    }

    /**
     * @return Ho_Import_Helper_Log
     */
    protected function _getLog() {
        return Mage::helper('ho_import/log');
    }

    /**
     * @return \Ho_Import_Model_Import
     */
    protected function _importData()
    {
        $timer = microtime(true);

        //importing
        /* @var $fastsimpleimport AvS_FastSimpleImport_Model_Import */
        $fastsimpleimport = Mage::getSingleton('fastsimpleimport/import');

        try {
            switch ($this->_getEntityType()) {
                case self::IMPORT_TYPE_PRODUCT:
                    $this->_getLog()->log($this->_getLog()->__('Start import %s', $this->_getEntityType()));
                    $fastsimpleimport->processProductImport($this->_getFileName());
                    break;

                case self::IMPORT_TYPE_CATEGORY:
                    $this->_getLog()->log($this->_getLog()->__('Start import %s', $this->_getEntityType()));
                    $fastsimpleimport->processCategoryImport($this->_getFileName());
                    break;

                case self::IMPORT_TYPE_CUSTOMER:
                    $this->_getLog()->log($this->_getLog()->__('Start import %s', $this->_getEntityType()));
                    $fastsimpleimport->processCustomerImport($this->_getFileName());
                    break;
                default:
                    $this->_getLog()->log($this->_getLog()->__('Type %s not found', $this->_getEntityType()));
                    break;
            }
        } catch (Exception $e) {
            $this->_getLog()->log($e->getMessage(), Zend_Log::ERR);
        }

        $seconds = round(microtime(true) - $timer, 2);
        $rowsPerSecond = round($this->getRowCount() / $seconds, 2);
        $productsPerSecond = round($this->getRowCount() / $seconds, 2);
        $this->_getLog()->log("Import {$this->getProfile()} done in $seconds seconds, $rowsPerSecond rows/s, $productsPerSecond items/s.");

        return $fastsimpleimport->getErrors();
    }

    protected function _dryRun($data = null) {
        if (is_null($data)) {
            $data = $this->_getFileName();
        }
        //importing
        /* @var $fastsimpleimport AvS_FastSimpleImport_Model_Import */
        $fastsimpleimport = Mage::getModel('fastsimpleimport/import');

        switch ($this->_getEntityType())
        {
            case self::IMPORT_TYPE_PRODUCT:
                $fastsimpleimport->dryrunProductImport($data);
                break;

            case self::IMPORT_TYPE_CATEGORY:
                $fastsimpleimport->dryrunCategoryImport($data);
                break;

            case self::IMPORT_TYPE_CUSTOMER:
                $fastsimpleimport->dryrunCustomerImport($data);
                break;
            default:
                $this->_getLog()->log($this->_getLog()->__('Type %s not found', $this->_getEntityType()));
                break;
        }

        return $fastsimpleimport->getErrors();
    }


    /**
     * @return SeekableIterator
     */
    public function getSourceAdapter()
    {
        if ($this->_sourceAdapter === null) {
            $source = $this->_getConfigNode(self::IMPORT_CONFIG_MODEL);

            if (! $source) {
                Mage::throwException($this->_getLog()->__('<source> not found for profile %s', $this->getProfile()));
            }

            $arguments = array();
            foreach ($source->children() as $key => $value) {
                $arguments[$key] = (string) $value;
            }

            if (isset($arguments['file']) && !is_readable($arguments['file'])) {
                $arguments['file'] = Mage::getBaseDir() .DS. (string) $source->file;
                if (!is_readable($arguments['file'])) {
                    Mage::throwException(Mage::helper('importexport')->__("%s file does not exists or is not readable", $source->file));
                }
            }

            if (count($arguments) == 1 && isset($arguments['file'])) {
                $arguments = $arguments['file'];
            }

            $this->_getLog()->log($this->_getLog()->__('Getting source adapter %s', $source->getAttribute('model')));
            $importModel = Mage::getModel($source->getAttribute('model'), $arguments);

            if (! $importModel) {
                Mage::throwException($this->_getLog()->__('Import model (%s) not found for profile %s', $source->getAttribute('model'), $this->getProfile()));
            }

            $this->_sourceAdapter = $importModel;
        }

        return $this->_sourceAdapter;
    }


    protected function _getEntityType() {
        return $this->_getConfigNode(self::IMPORT_CONFIG_ENTITY_TYPE);
    }

    protected function _getConfigNode($path) {
        return Mage::getConfig()->getNode(sprintf($path, $this->getProfile()));
    }

    protected function _logErrors($errors) {
        if ($errors) {
            foreach ($errors as $type => $errorLines) {
                $this->_getLog()->log($this->_getLog()->__("%s on lines %s",$type, implode(',',$errorLines)), Zend_Log::ERR);
            }
        } else {
            $this->_getLog()->log($this->_getLog()->__('No errors found in %s rows',$this->getRowCount()));
        }
    }

    protected function _debugErrors($errors) {
        if (! count($errors)) {
            return;
        }

        $errorLines = array();
        $errorsPerLine = array();
        foreach ($errors as $error => $lines) {

            if (strlen($error) > 80) {
                $error = substr($error, 0, 77).' ..';
            }

            $errorLines = array_merge($errorLines, $lines);
            foreach ($lines as $line) {

                $errorsPerLine[$line][] = $error;
            }
        }

        $errorLines = array_slice($errorLines, 0, 5);
        $this->_getLog()->log($this->_getLog()->__("Debugging first 5 error lines %s", implode(',',$errorLines)), Zend_Log::DEBUG);

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

//        while ($sourceAdapter->valid()) {
//            $result = $this->_fieldMapItem($sourceAdapter->current());
//            foreach ($result as $row) {
//                $exportAdapter->writeRow($row);
//            }
//            $rowCount++;
//            $sourceAdapter->next();
//        }
//        $this->setRowCount($rowCount);

//
//        $this->_sourceAdapter = null;
//        $this->mapLines($errorLines);
    }

    public function getProfiles() {
        $profileNodes =  Mage::getConfig()->getNode('global/ho_import');
        return $profileNodes->asArray();
    }
}