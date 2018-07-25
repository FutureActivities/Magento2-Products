<?php
namespace FutureActivities\Products\Model\Filter;

use FutureActivities\Products\Api\Data\Filter\AttributeInterface;

class Attribute implements AttributeInterface
{
    protected $handle = '';
    protected $name = '';
    protected $field = '';
    protected $type = 'list';
    protected $logicalAnd = false;
    protected $condition = 'eq';
    protected $active = false;
    protected $values = [];

    /**
     * {@inheritdoc}
     */
    public function setHandle($handle)
    {
        $this->handle = $handle;
    }
    
    /**
     * {@inheritdoc}
     */
    public function setName($name)
    {
        $this->name = $name;
    }
    
    /**
     * {@inheritdoc}
     */
    public function setField($name)
    {
        $this->field = $name;
    }
    
   /**
     * {@inheritdoc}
     */
    public function setLogicalAnd($logical)
    {
        $this->logicalAnd = $logical;
    }
    
    /**
     * {@inheritdoc}
     */
    public function setCondition($condition)
    {
        $this->condition = $condition;
    }
    
    /**
     * {@inheritdoc}
     */
    public function setType($type)
    {
        $this->type = $type;
    }
    
    /**
     * {@inheritdoc}
     */
    public function setIsActive($active)
    {
        $this->active = $active;
    }
    
    /**
     * {@inheritdoc}
     */
    public function addValue($value)
    {
        $this->values[] = $value;
    }

    /**
     * {@inheritdoc}
     */
    public function getHandle()
    {
        return $this->handle;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return $this->name;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getField()
    {
        return $this->field;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getType()
    {
        return $this->type;
    }
        
    /**
     * {@inheritdoc}
     */
    public function getLogicalAnd()
    {
        return $this->logicalAnd;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getCondition()
    {
        return $this->condition;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getIsActive()
    {
        return $this->active;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getValues()
    {
        return $this->values;
    }
}