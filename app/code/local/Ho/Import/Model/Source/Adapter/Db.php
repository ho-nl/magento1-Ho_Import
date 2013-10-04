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
 * @copyright   Copyright © 2013 H&O (http://www.h-o.nl/)
 * @license     H&O Commercial License (http://www.h-o.nl/license)
 * @author      Paul Hachmang – H&O <info@h-o.nl>
 *
 * XML streamer based on https://github.com/prewk/XmlStreamer/blob/master/XmlStreamer.php
 */
class Ho_Import_Model_Source_Adapter_Db extends Zend_Db_Table_Rowset
{

    /**
     * Constructor.
     * @param array $config
     */
    public function __construct(array $config)
    {

        $model = $config['model'];
        $query = $config['query'];
        $config['readOnly'] = true;

        unset($config['model']);
        unset($config['query']);

        /** @var Zend_Db_Adapter_Abstract $db */
        $db = new $model($config);

        if (isset($config['limit'])) {
            $query = $db->limit($query, $config['limit'], 0);
        }
        $result = $db->fetchAll($query);
        $config['data'] = &$result;

        return parent::__construct($config);
    }

    protected function _loadAndReturnRow($position)
    {
        if (!isset($this->_data[$position])) {
            #require_once 'Zend/Db/Table/Rowset/Exception.php';
            throw new Zend_Db_Table_Rowset_Exception("Data for provided position does not exist");
        }

        return $this->_data[$position];
    }

}