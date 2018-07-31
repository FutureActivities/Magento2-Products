<?php
namespace FutureActivities\Products\Model;

use \FutureActivities\Products\Model\Filter\Result;
use \FutureActivities\Products\Model\Filter\Attribute;
use \FutureActivities\Products\Model\Filter\AttributeValue;

class Filter implements \FutureActivities\Products\Api\FilterInterface
{
    protected $config;
    protected $productHelper;
    protected $filterHelper;
    protected $store;
    protected $request;
    protected $categoryCollection;
    protected $filterBuilder;
    protected $filterGroupBuilder;
    
    protected $filterableList = [];
    protected $filterBehaviour = 'default';
    protected $parentCount = false;
    
    public function __construct(\FutureActivities\Products\Helper\Config $config,
        \FutureActivities\Products\Helper\Product $productHelper,
        \FutureActivities\Products\Helper\Filter $filterHelper,
        \Magento\Store\Model\StoreManagerInterface $store, 
        \Magento\Framework\App\Request\Http $request,
        \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory $categoryCollection,
        \Magento\Framework\Api\FilterBuilder $filterBuilder,
        \Magento\Framework\Api\Search\FilterGroupBuilder $filterGroupBuilder)
    {
        $this->config = $config;
        $this->productHelper = $productHelper;
        $this->filterHelper = $filterHelper;
        $this->store = $store;
        $this->request = $request;
        $this->categoryCollection = $categoryCollection;
        $this->filterBuilder = $filterBuilder;
        $this->filterGroupBuilder = $filterGroupBuilder;
        
        $this->filterableList = json_decode($this->config->getGeneralConfig('filterableFields', $this->store->getStore()->getId()));
        $this->filterBehaviour = $this->config->getGeneralConfig('behaviour', $this->store->getStore()->getId()) ?: 'default';
        $this->parentCount = (int)$this->config->getGeneralConfig('parentCount', $this->store->getStore()->getId()) ?: false;
    }
    
    /**
    * Return a list of attributes and available filterable values
    *
    * @api
    * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
    * @return \FutureActivities\Products\Api\Data\Filter\AttributeInterface[]
    */
    public function getProductFilter(\Magento\Framework\Api\SearchCriteriaInterface $searchCriteria)
    {
        $result = [];
        
        // Collection/products with last filter group removed
        $prevCollection = $this->getPreviousCollection($searchCriteria);
        $prevProducts = $prevCollection->getItems();
        
        // Get the last filter option
        $lastFilter = $this->filterHelper->getLastFilter($searchCriteria);
        
        // Build product collection with search criteria
        $collection = $this->productHelper->buildCollection($searchCriteria);
        $collection->load();
        $collection->addCategoryIds()->addMinimalPrice();
        $products = $collection->getItems();
        
        // Loop through the list of defined filters
        foreach ($this->filterableList AS $filter) 
        {
            $attribute = new Attribute();
            $attribute->setHandle($filter->handle);
            $attribute->setName($filter->name);
            if (isset($filter->condition)) $attribute->setCondition($filter->condition);
            
            switch($filter->type) {
                case 'category':
                    $parent = $this->getParentFilter($filter);
                    $attribute->setField('category_id');
                    $attribute->setType('list');
                    $attribute->setLogicalAnd(true);
                    $this->setAttributeValues($searchCriteria, $attribute, $this->parseCategoryFilter($filter, $products, $parent, $this->productHelper->getFiltersByField($searchCriteria, 'category_id')));
                    break;
                    
                case 'attribute':
                    $attribute->setField($filter->id);
                    $attribute->setType('list');
                    $attribute->setLogicalAnd($filter->logicalAnd ?? false);
                    $isLast = $this->filterBehaviour == 'multiple' && $filter->handle == $lastFilter->getField();
                    $attributeProducts = $isLast ? $prevProducts : $products;
                    $this->setAttributeValues($searchCriteria, $attribute, $this->parseAttributeFilter($filter, $attributeProducts), $isLast);
                    break;
                    
                case 'price':
                    $attribute->setField('price');
                    $attribute->setType('slider');
                    $attribute->setLogicalAnd(true);
                    $this->setAttributeValues($searchCriteria, $attribute, $this->parsePriceFilter($collection));
                    break;
            }
            
            // No values? Then lets not show this attribute
            if (count($attribute->getValues()) == 0) continue;
            
            $result[$filter->handle] = $attribute;
        }
        
        return $result;
    }
    
    /**
     * Get the product collection with the last filter group removed
     * 
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     */
    protected function getPreviousCollection(\Magento\Framework\Api\SearchCriteriaInterface $searchCriteria)
    {
        $searchCriteria = $this->filterHelper->removeLastFilterGroup(clone $searchCriteria);
        return $this->productHelper->buildCollection($searchCriteria);
    }
    
    /**
     * Reformat attribute values
     * 
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     * @param array $attributeValues
     * @param \FutureActivities\Products\Model\Filter\Attribute $attribute
     * @param boolean $isLast
     */
    protected function setAttributeValues($searchCriteria, &$attribute, $attributeValues, $isLast = false)
    {
        $active = false;
        foreach ($attributeValues AS $valueHandle => $name) {
            
            // Build the product collection to get the product count
            $criteria = clone $searchCriteria;
            if ($isLast) $criteria = $this->filterHelper->removeLastFilterGroup($criteria);

            // Add this attribute value to the search criteria
            $filterGroups = $criteria->getFilterGroups();
            $filter = $this->filterBuilder->setField($attribute->getField())->setValue($valueHandle)->create();
            $filterGroups[] = $this->filterGroupBuilder->addFilter($filter)->create();
            $criteria->setFilterGroups($filterGroups);
           
            $collection = $this->productHelper->buildCollection($criteria, $this->parentCount);
            if ($collection->getSize() == 0) continue;
            
            $value = new AttributeValue();
            $value->setHandle($valueHandle);
            $value->setName($name);
            $value->setCount($collection->getSize());
            $attribute->addValue($value);
            
            if ($this->isAttributeActive($searchCriteria, $attribute->getHandle(), $valueHandle))
                $active = true;
        }
        
        $attribute->setIsActive($active);
    }
    
    /**
     * Check if an attribute value is currently active
     * 
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria 
     * @param string $handle
     * @param string $valueHandle
     */
    protected function isAttributeActive($searchCriteria, $handle, $valueHandle)
    {
        foreach ($searchCriteria->getFilterGroups() AS $filterGroup) {
            foreach ($filterGroup->getFilters() AS $filter) {
                if ($filter->getField() == $handle && $filter->getValue() == $valueHandle)
                    return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get the parent filter
     * Recursive function - it will continue up until it gets to a filter
     * that is not a child
     * 
     * @param Object $filter The current filter object
     * @param int $level How far down the tree are we
     * @parem boolean $isActive Set the filter active value
     */
    protected function getParentFilter($filter, $level = 1, $isActive = false)
    {
        if (!isset($filter->parent))
            return null;
        
        // Find the parent filter definition object
        $parentFilter = array_filter($this->filterableList, function ($e) use ($filter) {
            return isset($filter->parent) && $e->handle == $filter->parent;
        });
        $parentFilter = reset($parentFilter);
        
        // Get the parent attribute
        if (!isset($this->attributes[$parentFilter->handle]))
            return null;
        
        $parentAttribute = $this->attributes[$parentFilter->handle];
        
        // If this is a child then we want to return the parent filter instead
        if (isset($parentFilter->parent))
            return $this->getParentFilter($parentFilter, $level + 1, $parentAttribute->getIsActive());
    
        $result = new \stdClass;
        $result->filter = $parentFilter;
        $result->level = $level;
        $result->active = $level > 1 ? $isActive : $parentAttribute->getIsActive();
        
        return $result;
    }
    
    /**
     * Parses a category filter
     * 
     * @param Object $filter
     * @param array $products
     * @param \stdClass $parent
     * @param int[] $selectedIds
     * @return array
     */
    protected function parseCategoryFilter($filter, $products, $parent = null, $selectedIds = [])
    {
        $categoryIds = [];
        foreach ($products as $product)
            $categoryIds = array_merge($categoryIds, $product->getCategoryIds());
        
        if (isset($filter->parent) && (!$parent || !$parent->active))
            return [];
            
        if ($parent) {
            $path = $parent->filter->path ?? null;
            $result = $this->getCategoryValues($parent->filter->id, $categoryIds, $selectedIds, $parent->level + 2, $path);
            
            return $result;
        }
        
        $path = $filter->path ?? null;
        return $this->getCategoryValues($filter->id, $categoryIds, $selectedIds, 2, $path);
    }
    
    /**
     * Parses an attribute filter
     * 
     * @param Object $filter
     * @param array $products
     */
    protected function parseAttributeFilter($filter, $products)
    {
        $result = [];
        
        foreach ($products as $product) 
        {
            $_value = $product->getData($filter->id);
            if (!$_value) continue;
            
            $text = $_value;
            $_attr = $product->getResource()->getAttribute($filter->id);
            if ($_attr->usesSource()) {
                // An attribute value may be a comma separated list (multiselect)
                $values = explode(',', $_value);
                $options = $_attr->getOptions();
                
                // Loop through each option available for this attribute
                for ($i=0; $i<count($options); $i++) {
                    $option = $options[$i];
                    
                    // If not used by this product, the skip
                    if (!in_array($option->getValue(), $values)) continue;
                    
                    $result[$option->getValue()] = [
                        'label' => $option->getLabel(),
                        'position' => $i
                    ];
                }
            } else {
                $result[$_value] = [
                    'label' => $text,
                    'position' => 0
                ];
            }
        }
        
        // Sort by the attribute position
        uasort($result, function($a, $b) {
            return $a['position'] > $b['position'];
        });
        
        // Return only the attribute label
        array_walk($result, function(&$a, $b) { 
            $a = $a['label'];
        });
        
        return $result;
    }
    
    /**
     * Parses a price filter
     * 
     * @param $collection
     **/
    protected function parsePriceFilter($collection)
    {
        $result = [];
        
        $min = floor($collection->getMinPrice());
        $max = ceil($collection->getMaxPrice());
        
        if (!$min || !$max || ($min == 0 && $max == 0))
            return $result;
            
        $result['min'] = $min;
        $result['max'] = $max;
        
        return $result;
    }
    
    /**
     * Returns available category values for the product collection
     * 
     * @param int $parentCategoryId The parent category ID
     * @param array $productCategoryIds A list of all the categorys the products are visible in
     * @param int $level
     * @param string $path The path to the category level
     * @param int[] $selectedCategoryIds A list of category ids already selected
     */
    private function getCategoryValues($parentCategoryId, $productCategoryIds = [], $selectedCategoryIds = [], $level = 2, $path = null)
    {
        $pathLevels = 0;
        $basePath = '1/'.$parentCategoryId;
        $pathQuery = ['like' => $basePath.'/%'];
        
        // If a category path is set
        if ($path) {
            $path = trim($path, '/');
            $pathLevels = count(explode('/', $path));
            $basePath = '1/'.$path.'/'.$parentCategoryId;
            $pathQuery = ['like' => $basePath.'/%'];
        }
        
        // If not the top level then we should pass in the selected categories from the parent filters.
        if ($level > 3  && count($selectedCategoryIds) > 0) {
            $pathQuery = [];
            
            $selectedCategories = $this->categoryCollection->create()
                ->addAttributeToSelect('path')
                ->addAttributeToFilter('entity_id', ['in' => $selectedCategoryIds])
                ->addAttributeToFilter('level', $level);
            
            foreach ($selectedCategories AS $selectedCategory)
                $pathQuery[] = ['like' => $selectedCategory->getPath().'/%'];
        }
        
        // Build category collection
        $categories = $this->categoryCollection->create();
        $categories->addAttributeToSelect('name');
        $categories->addAttributeToFilter('level', $level + $pathLevels);
        $categories->addAttributeToFilter('path', $pathQuery);
        $categories->addAttributeToFilter('entity_id', array('in' => array_unique($productCategoryIds)));

        $result = [];
        foreach ($categories AS $category)
            $result[$category->getId()] = $category->getName();
            
        return $result;
    }
}