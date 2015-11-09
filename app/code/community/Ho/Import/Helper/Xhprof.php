<?php
/**
 * Ho_Import
 *
 * Copyright (c) 2015 H&O E-commerce specialists B.V. (http://www.h-o.nl/)
 * H&O Commercial License (http://www.h-o.nl/license)
 *
 * Author: H&O E-commerce specialists B.V. <info@h-o.nl> */
 
class Ho_Import_Helper_Xhprof extends Mage_Core_Helper_Abstract
{
    public function start($mode = 0, $path = null)
    {
        if (! extension_loaded('xhprof')) {
            Mage::throwException('XHProf not installed');
        }
        if ($mode == 0 || $mode > (XHPROF_FLAGS_NO_BUILTINS + XHPROF_FLAGS_CPU + XHPROF_FLAGS_MEMORY)) {
            Mage::throwException('Wrong XHProf modes set, available:
                XHPROF_FLAGS_NO_BUILTINS + XHPROF_FLAGS_CPU + XHPROF_FLAGS_MEMORY');
        }
        if (! $path) {
            Mage::throwException('XHProf path not set, please point to the utils directory,
                something like /usr/local/lib/php/xhprof_lib/utils/');
        }

        include_once $path.'xhprof_lib.php';
        include_once $path.'xhprof_runs.php';
        xhprof_enable($mode);
    }

    public function stop($profile)
    {
        $data = xhprof_disable();
        $runs = new XHProfRuns_Default();
        $runs->save_run($data, $profile);
    }
}