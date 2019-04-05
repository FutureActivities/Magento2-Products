<?php
namespace FutureActivities\Products\Model;

use FutureActivities\Products\Api\ProductListInterface;

class ProductList implements ProductListInterface
{
    protected $helper;
    protected $productRepository;
    protected $searchResultsFactory;
    
    public function __construct(
        \FutureActivities\Products\Helper\Product $helper,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \Magento\Catalog\Api\Data\ProductSearchResultsInterfaceFactory $searchResultsFactory
    ) {
        $this->helper = $helper;
        $this->productRepository = $productRepository;
        $this->searchResultsFactory = $searchResultsFactory;
    }
    
    /**
    * Returns a list of top level products
    * 
    * @api
    * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
    * @return \Magento\Catalog\Api\Data\ProductSearchResultsInterface
    */
    public function getProductList(\Magento\Framework\Api\SearchCriteriaInterface $searchCriteria)
    {
        $time_start = microtime(true); 
        
        $pageSize = $searchCriteria->getPageSize();
        $currPage = $searchCriteria->getCurrentPage();
        $searchCriteria->setPageSize(null);
        $searchCriteria->setCurrentPage(null);
        
        $collection = $this->helper->buildCollection($searchCriteria, $this->_determineParentUse($searchCriteria));
        $collection->setPageSize($pageSize);
        $collection->setCurPage($currPage);
        
        $collection->load();
        $collection->addCategoryIds();
        
        // Temporary fix for Magento not including Extension Attributes here, see: https://github.com/magento/magento2/issues/8700
        $items = [];
        foreach($collection->getItems() AS $item)
            $items[] = $this->productRepository->getById($item->getId());
        
        $searchResult = $this->searchResultsFactory->create();
        $searchResult->setSearchCriteria($searchCriteria);
        //$searchResult->setItems($collection->getItems());
        $searchResult->setItems($items);
        $searchResult->setTotalCount($collection->getSize());

        return $searchResult;
    }
    
    /**
     * Based on the search criteria, determine whether the products are shown
     * as group or ungrouped.
     * 
     * @param $searchCriteria
     * 
     * @return boolean
     */ 
    private function _determineParentUse(\Magento\Framework\Api\SearchCriteriaInterface $searchCriteria)
    {
        foreach ($searchCriteria->getFilterGroups() as $group) {
            $filter = $group->getFilters();
            
            foreach ($filter as $item) {
                if ($item->getField() === 'type_id' && $item->getValue() === 'simple') {
                    return false;
                }
            }
        }
        
        return true;
    }
}
