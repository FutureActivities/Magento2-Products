<?php
namespace FutureActivities\Products\Api\Data\Filter;

/**
 * @api
 */
interface AttributeInterface
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
     * Set the field name
     * 
     * @param string $name
     * @return null
     */
    public function setField($name);
    
    /**
     * Set the field type
     * 
     * @param string $type
     * @return null
     */
    public function setType($type);
    
    /**
     * Set the field logical search value
     * 
     * @param boolean $logical
     * @return null
     */
    public function setLogicalAnd($logical);
    
    /**
     * Set the field condition
     * e.g. eq, finset, neq, etc.
     * 
     * @param string $condition
     * @return null
     */
    public function setCondition($condition);
    
    /**
     * Is the attribute currently being filtered?
     * 
     * @param boolean $active
     * @return null
     */
    public function setIsActive($active);
    
    /**
     * Add an attribute value
     * 
     * @param FutureActivities\Products\Api\Data\Filter\AttributeValueInterface $value
     * @return null
     */
    public function addValue($value);
    
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
     * Get the attribute field
     * 
     * @return string
     */
    public function getField();
    
    /**
     * Get the attribute type
     * 
     * @return string
     */
    public function getType();
    
    /**
     * Get if this should be logical and
     * 
     * @return string
     */
    public function getLogicalAnd();
    
    /**
     * Get the attribute condition
     * 
     * @return string
     */
    public function getCondition();
    
    /**
     * Is the attribute currently being filtered?
     * 
     * @return boolean $active
     */
    public function getIsActive();
    
    /**
     * Get the attribute values
     * 
     * @return FutureActivities\Products\Api\Data\Filter\AttributeValueInterface[]
     */
    public function getValues();
}
