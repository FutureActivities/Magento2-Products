<?php

namespace FutureActivities\Products\Model\Config\Source;

class Behaviour implements \Magento\Framework\Option\ArrayInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => 'default', 'label' => __('Default')],
            ['value' => 'multiple', 'label' => __('Multiple')]
        ];
    }
}