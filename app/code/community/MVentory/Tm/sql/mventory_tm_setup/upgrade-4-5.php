<?php

$entityTypeId = $this->getEntityTypeId('catalog_product');
$setId = $this->getDefaultAttributeSetId($entityTypeId);
$groupId = $this->getDefaultAttributeGroupId($entityTypeId, $setId);

$name = 'tm_account_id';

$attributeData = array(
  //Global settings
  'type' => Varien_Db_Ddl_Table::TYPE_TEXT,
  'input' => 'hidden',
  'label' => 'TM Account ID',
  'required' => false,
  'user_defined' => false,
  'default' => '',
  'global' => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_WEBSITE,

  //Catalogue setting
  'visible' => false,
  'is_configurable' => false
);

$this
  ->addAttribute($entityTypeId, $name, $attributeData)
  ->addAttributeToGroup($entityTypeId, $setId, $groupId, $name);
