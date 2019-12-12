<?php

class Ho_Import_Model_Source_Profiles
{
    public function toOptionArray()
    {
        $profiles = Mage::getModel('ho_import/import')->getProfiles();
        if (count($profiles) > 0) {
            $options = ['' => ['value' => '', 'label' => 'none']];
            foreach ($profiles as $key => $value) {
                $options[$key] = [
                    'value' =>  $key,
                    'label' => $key,
                ];
            }
            return $options;
        }
    }
}