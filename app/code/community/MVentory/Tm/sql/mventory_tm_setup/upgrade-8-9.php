<?php

$this->startSetup();

$table = $this
           ->getConnection()
           ->newTable($this->getTable('mventory_tm/cart_item'))
           ->addColumn('transaction_id',
                       Varien_Db_Ddl_Table::TYPE_BIGINT,
                       null,
                       array(
                         'unsigned' => true,
                         'nullable' => false,
                         'primary' => true,
                       ),
                       'Transaction ID')
           ->addColumn('product_id',
                       Varien_Db_Ddl_Table::TYPE_INTEGER,
                       null,
                       array(
                         'unsigned' => true,
                         'nullable' => false
                    ),
                       'Product ID')
           ->addColumn('sku',
                       Varien_Db_Ddl_Table::TYPE_TEXT,
                       64,
                       array(
                         'nullable'  => false
                    ),
                       'SKU')
           ->addColumn('customer_id',
                       Varien_Db_Ddl_Table::TYPE_INTEGER,
                       null,
                       array(
                         'unsigned'  => true,
                         'nullable'  => false
                    ),
                       'Customer ID')
           ->addColumn('qty',
                       Varien_Db_Ddl_Table::TYPE_DECIMAL,
                       '12,4',
                       array(
                         'nullable'  => false
                    ),
                       'QTY')
           ->addColumn('price',
                       Varien_Db_Ddl_Table::TYPE_DECIMAL,
                       '12,4',
                       array(
                         'nullable'  => false
                    ),
                       'Price')
           ->addColumn('total',
                       Varien_Db_Ddl_Table::TYPE_DECIMAL,
                       '12,4',
                       array(
                         'nullable'  => false
                    ),
                       'Total')
           ->addColumn('date_time',
                       Varien_Db_Ddl_Table::TYPE_DATETIME,
                       null,
                       array(
                         'nullable'  => false
                    ),
                       'Date Time')
           ->addColumn('store_id',
                       Varien_Db_Ddl_Table::TYPE_SMALLINT,
                       null,
                       array(
                         'unsigned'  => true,
                         'nullable'  => false
                    ),
                       'Store ID')
           ->addColumn('product_name',
                       Varien_Db_Ddl_Table::TYPE_TEXT,
                       255,
                       array(
                         'nullable'  => false
                    ),
                       'Product name')
           ->addColumn('user_name',
                       Varien_Db_Ddl_Table::TYPE_TEXT,
                       255,
                       array(
                         'nullable'  => false
                    ),
                       'User Name')
           ->addIndex($this->getIdxName('mventory_tm/cart_item',
                                        array('transaction_id')),
                      array('store_id', 'date_time'))
           ->setComment('Custom cart table');

$this->getConnection()->createTable($table);

$this->endSetup();


$sql = <<<____SQL
CREATE PROCEDURE GetCart(IN deleteBefore datetime, IN storeId smallint)
BEGIN
  DELETE FROM ##table_name## WHERE date_time < deleteBefore;
  SELECT * FROM ##table_name## WHERE store_id = storeId ORDER BY date_time DESC;
END;
____SQL;

$sql = str_replace('##table_name##', $this->getTable('mventory_tm/cart_item'), $sql);

$write = Mage::getSingleton('core/resource')->getConnection('core_write');
$write->exec($sql);
