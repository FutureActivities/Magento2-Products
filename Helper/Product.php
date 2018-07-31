<?php

namespace FutureActivities\Products\Helper;

use \Magento\Framework\App\Helper\AbstractHelper;
use \Magento\Store\Model\ScopeInterface;
 
class Product extends AbstractHelper
{
    protected $collectionFactory;
    protected $extensionAttributesJoinProcessor;
    protected $collectionProcessor;
    
    public function __construct(\Magento\Framework\App\Helper\Context $context,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $collectionFactory,
        \Magento\Framework\Api\ExtensionAttribute\JoinProcessorInterface $extensionAttributesJoinProcessor,
        \Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface $collectionProcessor = null) 
    {
        parent::__construct($context);
        
        $this->collectionFactory = $collectionFactory;
        $this->extensionAttributesJoinProcessor = $extensionAttributesJoinProcessor;
        $this->collectionProcessor = $collectionProcessor ?: $this->getCollectionProcessor();
    }

    /**
     * Builds a product collection from search criteria
     * 
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     * @param boolean $useParent If true, this will return the parent product if available
     */
    public function buildCollection($searchCriteria, $useParent = false)
    {
        $collection = $this->collectionFactory->create();
        $this->extensionAttributesJoinProcessor->process($collection);
        $collection->addAttributeToSelect('*');
        $collection->joinAttribute('status', 'catalog_product/status', 'entity_id', null, 'inner');
        $collection->joinAttribute('visibility', 'catalog_product/visibility', 'entity_id', null, 'inner');
        
        if (!$useParent) {
            $this->collectionProcessor->process($searchCriteria, $collection);
            return $collection;
        }
        
        // Add the parent ID to the collection
        $collection->getSelect()->joinLeft(
            'catalog_product_super_link',
            'e.entity_id = catalog_product_super_link.product_id'
        )->group('e.entity_id');
        
        // Apply the search criteria on the child products
        $this->collectionProcessor->process($searchCriteria, $collection);
        
        // Check for sorting by category index position, must have category_id filter for this to work!
        $position = $this->getSortOrder($searchCriteria, 'cat_index_position');
        $categoryIds = $this->getFiltersByField($searchCriteria, 'category_id');
        if ($position && !empty($categoryIds)) {
            $join = sprintf('e.entity_id = catalog_category_product.product_id AND catalog_category_product.category_id IN (\'%s\')', implode('\',\'', $categoryIds));
            $collection->getSelect()->joinLeft(
                'catalog_category_product',
                $join,
                array('position' => 'position')
            );
            $collection->getSelect()->order('catalog_category_product.position '. $position);
        }
        
        // Get a list of all the product IDs and use the parent if available
        $productIds = [];
        foreach ($collection->getItems() AS $product)
            $productIds[] = $product->getParentId() ?: $product->getId();
        $productIds = $this->uniqueProducts($productIds, $searchCriteria);
        
        if (count($productIds) == 0)
            return $collection;
        
        // Rebuild the collection using the new list of IDs
        $parentCollection = $this->collectionFactory->create();
        $this->extensionAttributesJoinProcessor->process($parentCollection);
        $parentCollection->addAttributeToSelect('*');
        $parentCollection->joinAttribute('status', 'catalog_product/status', 'entity_id', null, 'inner');
        $parentCollection->joinAttribute('visibility', 'catalog_product/visibility', 'entity_id', null, 'inner');    
        $parentCollection->addAttributeToFilter('entity_id', array('in' => $productIds));
        $parentCollection->addAttributeToFilter('status', \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED);
        
        // Maintain the sort order of the child products if sorting by anything other than alphabetical
        if ($nameSort = $this->getSortOrder($searchCriteria, 'name')) $parentCollection->addAttributeToSort('name', $nameSort);
        else $parentCollection->getSelect()->order(new \Zend_Db_Expr(sprintf('FIELD(e.entity_id,%s)', implode(',',$productIds))));
        
        return $parentCollection;
    }
    
    /**
     * Get a specific filter
     */
    public function getFiltersByField($searchCriteria, $field)
    {
        $groups = $searchCriteria->getFilterGroups();
        
        $result = [];
        
        foreach ($groups AS $group) {
            foreach ($group->getFilters() AS $filter) {
                if ($filter->getField() == $field && $filter->getValue())
                    $result[] = $filter->getValue();
            }
        }
        
        return $result;
    }
    
    /**
     * Retrieve collection processor
     *
     * @deprecated 101.1.0
     * @return CollectionProcessorInterface
     */
    private function getCollectionProcessor()
    {
        if (!$this->collectionProcessor) {
            $this->collectionProcessor = \Magento\Framework\App\ObjectManager::getInstance()->get(
                'Magento\Catalog\Model\Api\SearchCriteria\ProductCollectionProcessor'
            );
        }
        return $this->collectionProcessor;
    }
    
    /**
     * Get a specific sort value from the search criteria
     */
    private function getSortOrder($searchCriteria, $field)
    {
        $orders = $searchCriteria->getSortOrders();
        
        if (!$orders)
            return;
            
        foreach ($orders AS $order) {
            if ($order->getField() == $field)
                return $order->getDirection();
        }
        
        return;
    }
    
    /**
     * Return unique product IDs.
     * 
     * If sorting by price descending (High to low) - we need to reverse this list before uniquing, as
     * at this point we have all child products sorted correctly by price, but we want the parent product
     * positioned at the point of its cheapest product, without this it will be positioned at the point
     * of its most expensive product.
     */
    private function uniqueProducts($productIds, $searchCriteria)
    {
        $priceSort = $this->getSortOrder($searchCriteria, 'price');
        
        if ($priceSort && strtoupper($priceSort) == 'DESC')
            return array_reverse(array_unique(array_reverse($productIds)));
            
        return array_unique($productIds);
    }
}