<?php

$this->startSetup();

$tableName = $this->getTable('mventory_tm/additional_skus');

$c = $this->getConnection();

$c->dropIndex(
  $tableName,
  $this->getIdxName(
    $tableName,
    array('sku'),
    Varien_Db_Adapter_Interface::INDEX_TYPE_UNIQUE
  )
);

$c->addColumn(
  $tableName,
  'website_id',
  array(
    'type' => Varien_Db_Ddl_Table::TYPE_SMALLINT,
    'unsigned' => true,
    'nullable' => false,
    'comment' => 'Website ID'
  )
);

$c->addIndex(
  $tableName,
  $this->getIdxName(
    $tableName,
    array('sku', 'website_id')
  ),
  array('sku', 'website_id')
);

$websiteTable = $this->getTable('core/website');

$c->addForeignKey(
  $this->getFkName(
    $tableName,
    'website_id',
    $websiteTable,
    'website_id'
  ),
  $tableName,
  'website_id',
  $websiteTable,
  'website_id'
);

$this->endSetup();