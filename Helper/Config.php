<?php

namespace FutureActivities\Products\Helper;

use \Magento\Framework\App\Helper\AbstractHelper;
use \Magento\Store\Model\ScopeInterface;

class Config extends AbstractHelper
{
    const XML_PATH_MAGENTO2API = 'magento2products/general';
    
    public function __construct(\Magento\Framework\App\Helper\Context $context) 
    {
        parent::__construct($context);;
    }
    
    /**
     * Returns value of specified field.
     * 
     * @param string $field The handle for the field you want the value of.
     * @param int $storeId Optional. The ID of the store view to filter by.
     */
	public function getConfigValue($field, $storeId = null)
	{
		return $this->scopeConfig->getValue(
			$field, ScopeInterface::SCOPE_STORE, $storeId
		);
	}

    /**
     * Shorthand version of getConfigalue(), Returns value of specified field.
     * 
     * @param string $code .
     * @param int $storeId Optional. The ID of the store view to filter by.
     */
	public function getGeneralConfig($code, $storeId = null)
	{
		return $this->getConfigValue(self::XML_PATH_MAGENTO2API .'/'. $code, $storeId);
	}
}