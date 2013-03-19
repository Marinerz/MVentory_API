<?php

$entityTypeId = $this->getEntityTypeId('catalog_product');
$setId = $this->getDefaultAttributeSetId($entityTypeId);
$groupId = $this->getDefaultAttributeGroupId($entityTypeId, $setId);

$name = 'tm_pickup';

$attributeData = array(
  //Global settings
  'type' => 'int',
  'input' => 'hidden',
  'label' => 'TM Pickup',
  'required' => false,
  'user_defined' => false,
  'default' => -1,
  'global' => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,

  //Catalogue setting
  'visible' => false,
  'is_configurable' => false
);

$this
  ->addAttribute($entityTypeId, $name, $attributeData)
  ->addAttributeToGroup($entityTypeId, $setId, $groupId, $name);
