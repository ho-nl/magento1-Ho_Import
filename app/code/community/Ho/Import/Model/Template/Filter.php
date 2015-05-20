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
 * @copyright 2015 Copyright © H&O (http://www.h-o.nl/)
 * @license   H&O Commercial License (http://www.h-o.nl/license)
 */
 
class Ho_Import_Model_Template_Filter extends Varien_Filter_Template
{
    /** @var Ho_Import_Model_Mapper */
    protected $_mapper;
    public function setMapper(Ho_Import_Model_Mapper $mapper)
    {
        $this->_mapper = $mapper;
        return $this;
    }

    protected $_line;
    public function setLine(&$line)
    {
        $this->_line = $line;
    }

    /**
     * Return variable value for var construction
     *
     * @param string $value raw parameters
     * @param string $default default value
     * @return string
     */
    protected function _getVariable($value, $default='{no_value_defined}')
    {
        Varien_Profiler::start("email_template_proccessing_variables");
        $tokenizer = new Varien_Filter_Template_Tokenizer_Variable();
        $tokenizer->setString($value);
        $stackVars = $tokenizer->tokenize();
        $result = $default;
        $last = 0;
        for($i = 0; $i < count($stackVars); $i ++) {
            if ($i == 0) {
                $value = $this->_mapper->map($stackVars[$i]['name']);
                if (! $value && isset($this->_line[$stackVars[$i]['name']])) {
                    $value = $this->_line[$stackVars[$i]['name']];
                }
                if (! $value && isset($this->_templateVars[$stackVars[$i]['name']])) {
                    $value =& $this->_templateVars[$stackVars[$i]['name']];
                }
            }

            if ($i == 0 && $value) {
                // Getting of template value
                $stackVars[$i]['variable'] =& $value;
            } elseif (isset($stackVars[$i-1]['variable'])) {
                // If object calling methods or getting properties
                if ($stackVars[$i]['type'] == 'property') {
                    $caller = 'get' . uc_words($stackVars[$i]['name'], '');
                    $stackVars[$i]['variable'] = method_exists($stackVars[$i-1]['variable'], $caller)
                        ? $stackVars[$i-1]['variable']->$caller()
                        : $stackVars[$i-1]['variable']->getData($stackVars[$i]['name']);
                } elseif ($stackVars[$i]['type'] == 'method') {
                    // Calling of object method
                    if (method_exists($stackVars[$i-1]['variable'], $stackVars[$i]['name'])
                        || substr($stackVars[$i]['name'], 0, 3) == 'get'
                    ) {
                        array_unshift($stackVars[$i]['args'], $this->_line);
                        $stackVars[$i]['variable'] = call_user_func_array(
                            array($stackVars[$i-1]['variable'], $stackVars[$i]['name']),
                            $stackVars[$i]['args']
                        );
                    }
                }
                $last = $i;
            }
        }

        if(isset($stackVars[$last]['variable'])) {
            // If value for construction exists set it
            $result = $stackVars[$last]['variable'];
        }
        Varien_Profiler::stop("email_template_proccessing_variables");
        return $result;
    }


    /**
     * @param $construction
     *
     * @return string
     */
    public function ifDirective($construction)
    {
        if (count($this->_templateVars) == 0) {
            return $construction[0];
        }

        if (strpos($construction[1], '==')) {
            list($construction[1], $condition) = explode('==', $construction[1]);

            if ($this->_getVariable($construction[1]) == trim($condition)) {
                return $construction[2];
            } else {
                if (isset($construction[3]) && isset($construction[4])) {
                    return $construction[4];
                }
                return '';
            }
        }

        if($this->_getVariable($construction[1], '') == '') {
            if (isset($construction[3]) && isset($construction[4])) {
                return $construction[4];
            }
            return '';
        } else {
            return $construction[2];
        }
    }
}
