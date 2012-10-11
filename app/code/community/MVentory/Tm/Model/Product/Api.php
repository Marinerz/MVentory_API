<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @category    Mage
 * @package     Mage_Catalog
 * @copyright   Copyright (c) 2012 Magento Inc. (http://www.magentocommerce.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Catalog product api
 *
 * @category   Mage
 * @package    Mage_Catalog
 * @author     Magento Core Team <core@magentocommerce.com>
 */
class MVentory_Tm_Model_Product_Api extends Mage_Catalog_Model_Product_Api {

  const FETCH_LIMIT_PATH = 'mventory_tm/api/products-number-to-fetch';
  const TAX_CLASS_PATH = 'mventory_tm/api/tax_class';

  public function fullInfo ($id = null, $sku = null) {
    $storeId = Mage::helper('mventory_tm')->getCurrentStoreId();

    $product = Mage::getModel('catalog/product');

    if (! $id)
      $id = $product->getResource()->getIdBySku($sku);

    $id = (int) $id;

    $result = $this->info($id, $storeId, null, 'id');

    $stockItem = Mage::getModel('mventory_tm/stock_item_api');

    $_result = $stockItem->items($id);

    if (isset($_result[0]))
      $result = array_merge($result, $_result[0]);

    $productAttribute = Mage::getModel('catalog/product_attribute_api');

    $_result = $productAttribute->items($result['set']);

    $result['set_attributes'] = array();

    foreach ($_result as $_attr) {
      $attr = $productAttribute->info($_attr['attribute_id'], $storeId);

      $attr['options']
        = $productAttribute->options($attr['attribute_id'], $storeId);

      $result['set_attributes'][] = $attr;
    }

    $productAttributeMedia
      = Mage::getModel('catalog/product_attribute_media_api');

    $result['images'] = $productAttributeMedia->items($id, $storeId, 'id');

    $category = Mage::getModel('catalog/category_api');

    foreach ($result['categories'] as $i => $categoryId)
      $result['categories'][$i] = $category->info($categoryId, $storeId);

    return $result;
  }

  public function limitedList ($name = null, $categoryId = null, $page = 1) {
    $storeId = Mage::helper('mventory_tm')->getCurrentStoreId();

    $limit = (int) Mage::getStoreConfig(self::FETCH_LIMIT_PATH, $storeId);

    if ($categoryId) {
      $category = Mage::getModel('catalog/category')
                    ->setStoreId($storeId)
                    ->load($categoryId);

      if (!$category->getId())
        $this->_fault('category_not_exists');

      $collection = $category->getProductCollection();
    } else {
      $collection = Mage::getModel('catalog/product')
                      ->getCollection()
                      ->addStoreFilter($storeId);
    }

    if ($name)
      $collection->addAttributeToFilter(
          array(
              array('attribute'=> 'name','like' => "%{$name}%"),
              array('attribute'=> 'sku','like' => "%{$name}%"))
      );

    $collection
      ->addAttributeToSelect('name')
      ->setPage($page, $limit);

    if (!$name)
      $collection
        ->setOrder('updated_at', Varien_Data_Collection::SORT_ORDER_DESC);

    $result = array('items' => array());

    foreach ($collection as $product)
      $result['items'][] = array('product_id' => $product->getId(),
                                 'sku' => $product->getSku(),
                                 'name' => $product->getName(),
                                 'set' => $product->getAttributeSetId(),
                                 'type' => $product->getTypeId(),
                                 'category_ids' => $product->getCategoryIds() );

    $result['current_page'] = $collection->getCurPage();
    $result['last_page'] = (int) $collection->getLastPageNumber();

    return $result;
  }

  public function createAndReturnInfo ($type, $set, $sku, $productData,
                                   $storeId = null) {

    $id = (int) Mage::getModel('catalog/product')
                  ->getResource()
                  ->getIdBySku($sku);

    if (! $id) {
      $helper = Mage::helper('mventory_tm');

      $storeId = $helper->getCurrentStoreId($storeId);

      $productData['website_ids'] = $helper->getWebsitesForProduct($storeId);

      //Set visibility to "Catalog, Search" value
      $productData['visibility'] = 4;

      //if (!isset($productData['tax_class_id']))
        $productData['tax_class_id']
          = (int) $helper->getConfig(self::TAX_CLASS_PATH,
                                     $helper->getCurrentWebsite());

      //Set storeId as null to save values of attributes in the default scope
      $id = $this->create($type, $set, $sku, $productData, null);
    }

    return $this->fullInfo($id);
  }
  
  /**
   * Get info about new products, sales and stock      
   *      
   * @return array     
   */
  public function statistics () {
    $storeId    = Mage::helper('mventory_tm')->getCurrentStoreId();
    $store      = Mage::app()->getStore($storeId);

    $date       = new Zend_Date();
    
    $dayStart   = $date->toString('yyyy-MM-dd 00:00:00');
    $dayStart   = new Zend_Date($dayStart, 'YYYY-MM-dd 00:00:00');
    
    $weekStart  = new Zend_Date($date->getTimestamp() - 7 * 24 * 3600);
    $weekStart  = $weekStart->toString('yyyy-MM-dd 00:00:00');
    $weekStart  = new Zend_Date($weekStart, 'YYYY-MM-dd');
    
    $monthStart = new Zend_Date($date->getTimestamp() - 30 * 24 * 3600);
    $monthStart = $monthStart->toString('yyyy-MM-dd 00:00:00');
    $monthStart = new Zend_Date($monthStart, 'YYYY-MM-dd');

    // Get Sales info
    $collection = Mage::getModel("sales/order")->getCollection();

    $collection
      ->addFieldToFilter('store_id', $storeId)
      ->setOrder('updated_at', Varien_Data_Collection_Db::SORT_ORDER_DESC)
      ;

    $daySales   = 0;
    $weekSales  = 0;
    $monthSales = 0;
    $totalSales = 0;

    foreach ($collection as $order) {
      $orderDate = new Zend_Date($order->getData('created_at'),
                                                 'YYYY-MM-dd hh:mm:ss');
      $orderGrandTotal  = $order->getData('grand_total');
      
      if($orderDate->isLater($dayStart)) {
        $daySales += $orderGrandTotal; 
        $weekSales += $orderGrandTotal; 
        $monthSales += $orderGrandTotal;
      } elseif($orderDate->isLater($weekStart)) {
        $weekSales += $orderGrandTotal; 
        $monthSales += $orderGrandTotal;  
      } elseif($orderDate->isLater($monthStart)) {
        $monthSales += $orderGrandTotal;  
      }     
      $totalSales += $orderGrandTotal;  
    }
    // End of Sales info
    
    // Get Stock info
    $collection = Mage::getModel('catalog/product')->getCollection();

    if (Mage::helper('catalog')->isModuleEnabled('Mage_CatalogInventory')) {
      $collection
        ->joinField('qty', 
                    'cataloginventory/stock_item', 
                    'qty', 'product_id=entity_id', 
                    '{{table}}.stock_id=1 AND {{table}}.is_in_stock=1', 'left');
    }
    if ($storeId) {
      //$collection->setStoreId($store->getId());
      $collection->addStoreFilter($store);
      
      $collection->joinAttribute(
        'price',
        'catalog_product/price',
        'entity_id',
        null,
        'left',
        $storeId
      );
    } else {
      $collection->addAttributeToSelect('price');
    }
    
    $collection->joinAttribute(
      'status', 
      'catalog_product/status',
      'entity_id', 
      null,
      'inner',
      $storeId);
    $collection->joinAttribute(
      'visibility',
      'catalog_product/visibility',
      'entity_id',
      null,
      'inner',
      $storeId);
      
    $totalStockQty = 0;
    $totalStockValue = 0;
    foreach ($collection as $product) {
      $totalStockQty += $product->getQty();
      $totalStockValue += $product->getQty() * $product->getPrice();
    }
    // End of Stock info
    
    // Get Products info    
    $to   = $date->toString('yyyy-MM-dd 23:59:59');
      
    $from = new Zend_Date($date->getTimestamp() - 30 * 24 * 3600);
    $from = $from->toString('yyyy-MM-dd 00:00:00');
    
    $collection = Mage::getModel('catalog/product')->getCollection();
    $collection
      ->addStoreFilter($store)
      ->addAttributeToFilter('created_at', array('from'  => $from,
                                                 'to'    => $to));

    $dayLoaded   = 0;
    $weekLoaded  = 0;
    $monthLoaded = 0;

    foreach ($collection as $product) {
      $productDate = new Zend_Date($product->getData('created_at'),
                                   'YYYY-MM-dd hh:mm:ss');

      if($productDate->isLater($dayStart)) {
        $dayLoaded ++;
        $weekLoaded ++; 
        $monthLoaded ++; 
      } elseif($productDate->isLater($weekStart)) {
        $weekLoaded ++;  
      } elseif($productDate->isLater($monthStart)) {
        $monthLoaded ++;  
      }  
    }
    // End of Products info

    return array('day_sales' => $daySales, 'week_sales' => $weekSales,
                 'month_sales' => $monthSales, 'total_sales' => $totalSales,
                 'total_stock_qty' => $totalStockQty,
                 'total_stock_value' => $totalStockValue,
                 'day_loaded' => $dayLoaded, 'week_loaded' => $weekLoaded,
                 'month_loaded' => $monthLoaded);
  }
}
