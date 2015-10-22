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
 * @category  Ho
 * @package   Ho_Import
 * @author    Paul Hachmang – H&O <info@h-o.nl>
 * @copyright 2014 Copyright © H&O (http://www.h-o.nl/)
 * @license   H&O Commercial License (http://www.h-o.nl/license)
 */



/**
 * Class Ho_Import_Model_Import_Transport
 * @method Ho_Import_Model_Import_Transport setSkip(bool $skip)
 * @method bool getSkip()
 * @method Ho_Import_Model_Import_Transport setData()
 */
class Ho_Import_Model_Import_Transport extends Varien_Object
{
    protected $_items = array();


    /**
     * @param array $items
     * @return $this
     */
    public function setItems(array $items)
    {
        $this->_items = $items;
        return $this;
    }


    /**
     * @param array $items
     *
     * @return $this
     */
    public function addItems(array $items)
    {
        foreach ($items as $item)
        {
            $this->addItem($item);
        }
        return $this;
    }


    /**
     * @param $item
     *
     * @return $this
     */
    public function addItem($item)
    {
        $this->_items[] = $item;
        return $this;
    }


    /**
     * @return array
     */
    public function getItems()
    {
        return $this->_items;
    }


    /**
     * Clean up the transport object, without having to reinstantiate a new class (saves memory)
     */
    public function reset()
    {
        $this->_items = array();
        $this->setData(array());
        $this->setOrigData(null, array());
        $this->setDataChanges(false);
    }
}
