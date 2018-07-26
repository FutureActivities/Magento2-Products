<?php

namespace FutureActivities\Products\Helper;

use \Magento\Framework\App\Helper\AbstractHelper;
use \Magento\Store\Model\ScopeInterface;
 
class Filter extends AbstractHelper
{
    public function __construct(\Magento\Framework\App\Helper\Context $context) 
    {
        parent::__construct($context);
    }

    /**
     * Remove the last filter group from the search criteria
     * 
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     */
    public function removeLastFilterGroup(\Magento\Framework\Api\SearchCriteriaInterface $searchCriteria)
    {
        $filterGroups = $searchCriteria->getFilterGroups();
        array_pop($filterGroups);
        $searchCriteria->setFilterGroups($filterGroups);
        
        return $searchCriteria;
    }
    
    /**
     * Get the last filter in the search criteria
     * 
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     */
    public function getLastFilter(\Magento\Framework\Api\SearchCriteriaInterface $searchCriteria)
    {
        $filterGroups = $searchCriteria->getFilterGroups();
        $lastGroup = array_pop($filterGroups);
        
        $filters = $lastGroup->getFilters();
        $lastFilter = array_pop($filters);
        
        return $lastFilter;
    }
}