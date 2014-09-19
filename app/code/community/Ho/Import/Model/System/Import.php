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
 * @copyright   Copyright © 2014 H&O (http://www.h-o.nl/)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @author      Paul Hachmang – H&O <info@h-o.nl>
 *
 * 
 */
 
class Ho_Import_Model_System_Import extends Varien_Object
{
    const CONFIG_JOB_PREFIX = 'crontab/jobs/ho_import_%s';
    const CONFIG_TEMPLATE_CRON_EXPR = '/schedule';
    const CONFIG_TEMPLATE_CRON_MODEL = '/run/model';
    const CRON_MODEL_EXPR   = 'ho_import/observer::process';

    public function schedule($flushCache = true) {
        try {
            if (! $this->getSchedule()) {
                return false;
            }

            foreach ($this->getSchedule() as $key => $value) {
                $cronExpr = $this->getConfigCronExpr();
                Mage::getModel('core/config_data')
                    ->load($cronExpr.'/'.$key, 'path')
                    ->setValue($value)
                    ->setPath($cronExpr.'/'.$key)
                    ->save();
            }


            $model = $this->getConfigModel();
            Mage::getModel('core/config_data')
                ->load($model, 'path')
                ->setValue($this->getModelExpr())
                ->setPath($model)
                ->save();

            if ($flushCache) {
                Mage::getConfig()->cleanCache();
            }
        } catch (Exception $e) {
            Mage::throwException(Mage::helper('adminhtml')->__('Unable to save the cron expression.'));
        }

        return true;
    }

    public function getConfigCronExpr() {
        return $this->getConfigPath() . Ho_Import_Model_System_Import::CONFIG_TEMPLATE_CRON_EXPR;
    }

    public function getConfigModel() {
        return $this->getConfigPath() . Ho_Import_Model_System_Import::CONFIG_TEMPLATE_CRON_MODEL;
    }

    public function getConfigPath() {
        return sprintf(Ho_Import_Model_System_Import::CONFIG_JOB_PREFIX, $this->getId());
    }

    public function getModelExpr() {
        return Ho_Import_Model_System_Import::CRON_MODEL_EXPR;
    }

    public function process() {

    }

    public function scheduleNow() {
        if (! $this->schedule()) {
            return false;
        }

        Mage::getModel('cron/schedule') /* @var Aoe_Scheduler_Model_Schedule */
            ->setJobCode('ho_import_'.$this->getId())
            ->schedule()
            ->save();

        return true;
    }
}
