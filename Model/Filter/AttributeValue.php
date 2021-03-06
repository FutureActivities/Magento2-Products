<?php
namespace FutureActivities\Products\Model\Filter;

use FutureActivities\Products\Api\Data\Filter\AttributeValueInterface;

class AttributeValue implements AttributeValueInterface
{
    protected $handle = '';
    protected $name = '';
    protected $count = 0;
    protected $condition = 'eq';

    /**
     * Set the list of attributes
     * 
     * @param string $handle
     * @return null
     */
    public function setHandle($handle)
    {
        $this->handle = $handle;
    }
    
    /**
     * Set the attribute name
     * 
     * @param string $name
     * @return null
     */
    public function setName($name)
    {
        $this->name = $name;
    }
    
        
    /**
     * Set the attribute product count
     * 
     * @param int $count
     * @return null
     */
    public function setCount($count)
    {
        $this->count = $count;
    }
    
    /**
     * Get the attribute handle
     * 
     * @return string
     */
    public function getHandle()
    {
        return $this->handle;
    }
    
    /**
     * Get the attribute name
     * 
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }
    
    /**
     * Get the attribute product count
     * 
     * @return int
     */
    public function getCount()
    {
        return $this->count;
    }
}