<?php
namespace FutureActivities\Products\Api;
 
interface FilterInterface
{
   /**
    * Return a list of attributes and available filterable values
    *
    * @api
    * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
    * @return \FutureActivities\Products\Api\Data\Filter\AttributeInterface[]
    */
   public function getProductFilter(\Magento\Framework\Api\SearchCriteriaInterface $searchCriteria);
}