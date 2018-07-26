<?php
namespace FutureActivities\Products\Api\Data\Filter;

/**
 * @api
 */
interface AttributeValueInterface
{
    /**
     * Set the list of attributes
     * 
     * @param string $handle
     * @return null
     */
    public function setHandle($handle);
    
    /**
     * Set the attribute name
     * 
     * @param string $name
     * @return null
     */
    public function setName($name);
    
    /**
     * Set the attribute product count
     * 
     * @param int $count
     * @return null
     */
    public function setCount($count);
    
    /**
     * Get the attribute handle
     * 
     * @return string
     */
    public function getHandle();
    
    /**
     * Get the attribute name
     * 
     * @return string
     */
    public function getName();
    
    /**
     * Get the attribute product count
     * 
     * @return int
     */
    public function getCount();
}
