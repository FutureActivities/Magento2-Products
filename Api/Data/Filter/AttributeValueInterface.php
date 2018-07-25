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
}
