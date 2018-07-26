<?php
namespace FutureActivities\Products\Model;

use \FutureActivities\Products\Model\Filter\Result;
use \FutureActivities\Products\Model\Filter\Attribute;
use \FutureActivities\Products\Model\Filter\AttributeValue;

class Filter implements \FutureActivities\Products\Api\FilterInterface
{
    protected $config;
    protected $helper;
    protected $store;
    protected $request;
    protected $categoryCollection;
    
    protected $filterableList = [];
    protected $filterBehaviour = 'default';
    
    public function __construct(\FutureActivities\Products\Helper\Config $config,
        \FutureActivities\Products\Helper\Product $helper,
        \Magento\Store\Model\StoreManagerInterface $store, 
        \Magento\Framework\App\Request\Http $request,
        \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory $categoryCollection)
    {
        $this->config = $config;
        $this->helper = $helper;
        $this->store = $store;
        $this->request = $request;
        $this->categoryCollection = $categoryCollection;
        
        $this->filterableList = json_decode($this->config->getGeneralConfig('filterableFields', $this->store->getStore()->getId()));
        $this->filterBehaviour = $this->config->getGeneralConfig('behaviour', $this->store->getStore()->getId()) ?: 'default';
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
        $prevCollection = $this->getPreviousCollection($searchCriteria);
        
        $activeFilters = $this->reformatFilters($searchCriteria);
        $activeFiltersKeys = array_keys($activeFilters);
        $lastFilterAttr = end($activeFiltersKeys);
        
        $collection = $this->helper->buildCollection($searchCriteria);
        
        $collection->load();
        $collection->addCategoryIds()->addMinimalPrice();

        $products = $collection->getItems();
        
        foreach ($this->filterableList AS $filter) 
        {
            switch($filter->type) {
                case 'category':
                    $parent = $this->getParentFilter($filter);
                    $attributeValues = $this->parseCategoryFilter($filter, $products, $parent, $this->helper->getFiltersByField($searchCriteria, 'category_id'));
                    $handle = 'category_id';
                    $type = 'list';
                    $logicalAnd = true;
                    break;
                    
                case 'attribute':
                    $attributeProducts = $this->filterBehaviour == 'multiple' && $filter->id == $lastFilterAttr ? $prevCollection->getItems() : $products;
                    $attributeValues = $this->parseAttributeFilter($filter, $attributeProducts);
                    $handle = $filter->id;
                    $type = 'list';
                    $logicalAnd = $filter->logicalAnd ?? false;
                    break;
                    
                case 'price':
                    $attributeValues = $this->parsePriceFilter($collection);
                    $handle = 'price';
                    $type = 'slider';
                    $logicalAnd = true;
                    break;
                    
                default: 
                    $attributeValues = [];
                    $handle = $filter->id;
                    $type = 'list';
                    $logicalAnd = $filter->logicalAnd ?? false;
            }
            
            // No values? Then lets not show this attribute
            if (count($attributeValues) == 0) continue;
            
            $attribute = new Attribute();
            $attribute->setHandle($filter->handle);
            $attribute->setName($filter->name);
            $attribute->setField($handle);
            $attribute->setType($type);
            $attribute->setLogicalAnd($logicalAnd);
            if (isset($filter->condition)) $attribute->setCondition($filter->condition);
            
            $active = false;
            foreach ($attributeValues AS $valueHandle => $name) {
                $value = new AttributeValue();
                $value->setHandle($valueHandle);
                $value->setName($name);
                $attribute->addValue($value);
                
                // Check if this is an active filter
                if (isset($activeFilters[$handle]) && in_array($valueHandle, $activeFilters[$handle]))
                    $active = true;
            }
            
            $attribute->setIsActive($active);
            
            $this->attributes[$filter->handle] = $attribute;
        }
        
        return $this->attributes;
    }
    
    /**
     * Get the product collection with the last filter group removed
     * 
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     */
    private function getPreviousCollection(\Magento\Framework\Api\SearchCriteriaInterface $searchCriteria)
    {
        $prev = clone $searchCriteria;
        
        $filterGroups = $prev->getFilterGroups();
        array_pop($filterGroups);
        $prev->setFilterGroups($filterGroups);
        
        return $this->helper->buildCollection($prev);
    }
    
    /**
     * Reformat the Magento search criteria into a more usable format
     * 
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     */
    private function reformatFilters(\Magento\Framework\Api\SearchCriteriaInterface $searchCriteria)
    {
        $result = [];
        foreach ($searchCriteria->getFilterGroups() AS $filterGroup) {
            foreach ($filterGroup->getFilters() AS $filter) {
                if (isset($result[$filter->getField()]))
                    $result[$filter->getField()][] = $filter->getValue();
                else
                    $result[$filter->getField()] = [$filter->getValue()];
            }
        }
        
        return $result;
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
    private function getParentFilter($filter, $level = 1, $isActive = false)
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
    private function parseCategoryFilter($filter, $products, $parent = null, $selectedIds = [])
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
    private function parseAttributeFilter($filter, $products)
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
    private function parsePriceFilter($collection)
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