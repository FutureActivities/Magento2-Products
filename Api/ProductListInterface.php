<?php
namespace FutureActivities\Products\Api;
 
interface ProductListInterface
{
   /**
    * Returns a list of top level products
    * 
    * @api
    * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
    * @return \Magento\Catalog\Api\Data\ProductSearchResultsInterface
    */
   public function getProductList(\Magento\Framework\Api\SearchCriteriaInterface $searchCriteria);
}