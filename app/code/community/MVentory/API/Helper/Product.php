<?php

/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Creative Commons License BY-NC-ND.
 * NonCommercial — You may not use the material for commercial purposes.
 * NoDerivatives — If you remix, transform, or build upon the material,
 * you may not distribute the modified material.
 * See the full license at http://creativecommons.org/licenses/by-nc-nd/4.0/
 *
 * See http://mventory.com/legal/licensing/ for other licensing options.
 *
 * @package MVentory/API
 * @copyright Copyright (c) 2014 mVentory Ltd. (http://mventory.com)
 * @license http://creativecommons.org/licenses/by-nc-nd/4.0/
 */

/**
 * Product helper
 *
 * @package MVentory/API
 * @author Anatoly A. Kazantsev <anatoly@mventory.com>
 */
class MVentory_API_Helper_Product extends MVentory_API_Helper_Data {

  /**
   * Returns product's category
   *
   * @param Mage_Catalog_Model_Product $product
   *
   * @return Mage_Catalog_Model_Category
   */
  public function getCategory ($product) {
    $categories = $product->getCategoryIds();

    if (!count($categories))
      return null;

    $category = Mage::getModel('catalog/category')->load($categories[0]);

    if ($category->getId())
      return $category;

    return null;
  }

  /**
   * Return website which the product are assigned to
   *
   * @param Mage_Catalog_Model_Product|int $product
   *
   * @return Mage_Core_Model_Website
   */
  public function getWebsite ($product = null) {
    $app = Mage::app();

    if ($product == null) {
      $product = Mage::registry('product');

      if (!$product)
        return $app->getWebsite();
    }

    if ($product instanceof Mage_Catalog_Model_Product)
      $ids = $product->getWebsiteIds();
    else
      $ids = Mage::getResourceModel('catalog/product')
               ->getWebsiteIds($product);

    if (!$n = count($ids))
      return $app->getWebsite();

    if ($n == 1)
      return $app->getWebsite(reset($ids));

    foreach ($ids as $id) {
      $website = $app->getWebsite($id);

      if (!$this->getConfig(MVentory_API_Model_Config::_ROOT_WEBSITE, $website))
        break;
    }

    return $website;
  }

  /**
   * Returns product's URL
   *
   * @param Mage_Catalog_Model_Product $product
   *
   * @return string
   */
  public function getUrl ($product) {
    $website = $this->getWebsite($product);

    $baseUrl = $this->getConfig('web/unsecure/base_url', $website);

    return rtrim($baseUrl, '/')
           . '/'
           . $product->getUrlPath($this->getCategory($product));
  }

  /**
   * Try to get product's ID
   *
   * @param  int|string $productId (SKU, ID or Barcode)
   * @param  string $identifierType
   *
   * @return int|null
   */
  public function getProductId ($productId, $identifierType = null) {
    if ($identifierType == 'barcode') {
      $id = (int) $this->getProductIdByBarcode($productId);

      if ($id < 1)
        $id = (int) Mage::getResourceModel('mventory/sku')
          ->getProductId($productId, $this->getCurrentWebsite());

      return $id > 0 ? $id : null;
    }

    if ($identifierType == 'id' && ((int) $productId > 0))
      return (int) $productId;

    $id = (int) Mage::getResourceModel('catalog/product')
                  ->getIdBySku($productId);

    if ($id > 0)
      return $id;

    $id = (int) $this->getProductIdByBarcode($productId);

    if ($id > 0)
      return $id;

    $id = (int) Mage::getResourceModel('mventory/sku')
      ->getProductId($productId, $this->getCurrentWebsite());

    if ($id > 0)
      return $id;

    $id = (int) $productId;

    return $id > 0 ? $id : null;
  }

  /**
   * Search product ID by value of product_barcode_ attribute
   *
   * !!!TODO: product_barcode_ attribute should be converted to global
   *
   * @param  string $barcode Barcode
   *
   * @return int|null
   */
  public function getProductIdByBarcode ($barcode) {
    $ids = Mage::getResourceModel('catalog/product_collection')
             ->addAttributeToFilter('product_barcode_', $barcode)
             ->addStoreFilter($this->getCurrentStoreId())
             ->getAllIds(1);

    return $ids ? $ids[0] : null;
  }

  public function updateFromSimilar ($product, $similar) {
    if ($similar instanceof Mage_Catalog_Model_Product)
      $similar = array($similar);

    foreach ($similar as $_similar) {
      $item = Mage::getModel('cataloginventory/stock_item')
        ->loadByProduct($_similar);

      if ($item->getId())
        $similarData[] = $item->getData();
    }

    if (!isset($similarData))
      return;

    unset($item, $_similar);

    $this->_loadStockData($product);

    if ($item = $product->getData('stock_item'))
      $item->setData('mventory_ignore_stock_update', true);

    $product->setData(
      'stock_data',
      $this->_updateStockData($product->getData('stock_data'), $similarData)
    );
  }

  private function _loadStockData ($product) {
    $item = $product->getData('stock_item');

    if ($product->getId()) {
      $stock = Mage::getModel('cataloginventory/stock_item')
        ->loadByProduct($product);

      if ($stock->getId())
        $item = $stock;
    }

    $data = $product->getData('stock_data');

    if (!($item instanceof Mage_CatalogInventory_Model_Stock_Item))
      $item = null;

    if (!$data && $item)
      $data = $item->getData();

    $product
      ->setData('stock_item', $item)
      ->setData('stock_data', $data);
  }

  private function _updateStockData ($stock, $similar) {
    foreach ($similar as $_similar) {
      if (!$stock) {
        $stock = $_similar;
        continue;
      }

      if (!isset($stock['manage_stock']) && isset($_similar['manage_stock']))
        $stock['manage_stock'] = $_similar['manage_stock'];

      if (isset($_similar['qty']))
        $stock['qty'] = isset($stock['qty'])
                          ? $stock['qty'] + $_similar['qty']
                            : $_similar['qty'];
    }

    return $stock;
  }

  /**
   * Loads media_gallery attribute. If product is specified it tries to get
   * the attribute from list of loaded attributes in the product
   *
   * @param Mage_Catalog_Model_Product $product Product
   *
   * @return Mage_Eav_Model_Entity_Attribute
   */
  public function getMediaGalleryAttr ($product = null) {
    if ($product && $product instanceof Mage_Catalog_Model_Product) {
      $attrs = $product->getAttributes();

      if (isset($attrs['media_gallery']))
        return $attrs['media_gallery'];
    }

    return Mage::getModel('eav/entity_attribute')
      ->loadByCode(Mage_Catalog_Model_Product::ENTITY, 'media_gallery');
  }

  /**
   * Returns list of media attributes with values. It return values from last
   * product in the list if no one product has all values for all media
   * attributes
   *
   * @param array $products List of products
   *
   * @return array
   */
  public function getMediaAttrs ($products) {
    $attrs = null;

    foreach ($products as $product) {
      $values = array();

      if (!$attrs)
        $attrs = $product->getMediaAttributes();

      foreach ($attrs as $attr) {
        $code = $attr->getAttributeCode();

        if ($value = $product->getData($code))
          $values[$code] = $value;
      }

      if (count($values) == count($attrs))
        return $values;
    }

    return $values;
  }

  /**
   * Returns list of images for the product
   *
   * @param Mage_Catalog_Model_Product $product Product
   * @param Varien_Object $backend
   * @param bool $fileAsKey Return array of images with image filename as a key
   *
   * @return array
   */
  public function getImages ($product, $backend = null, $fileAsKey = true) {
    $gallery = is_array($gallery = $product->getMediaGallery('images'))
                 ? $gallery
                   : null;


    if (!$gallery && $product->getId()) {
      if (!$backend)
        $backend = new Varien_Object(array(
          'attribute' => $this->getMediaGalleryAttr($product)
        ));

      $gallery
        = Mage::getResourceSingleton('catalog/product_attribute_backend_media')
            ->loadGallery($product, $backend);

      unset($backend);
    }

    if (!$gallery)
      return array();

    if (!$fileAsKey)
      return $gallery;

    foreach ($gallery as $image)
      $images[$image['file']] = $image;

    return $images;
  }

  /**
   * Adds images to the product. It ignores existing product images.
   *
   * @param Mage_Catalog_Model_Product $product Product
   * @param array $images
   *
   * @return MVentory_API_Helper_Product
   */
  public function addImages ($product, array $images) {
    $_images = $this->getImages($product);

    $resource
      = Mage::getResourceSingleton('catalog/product_attribute_backend_media');

    $id = $product->getId();
    $storeId = $product->getStoreId();
    $attrId = $this->getMediaGalleryAttr($product)->getAttributeId();

    foreach ($images as $image) {
      if (!isset($_images[$image['file']])) {
        $resource->insertGalleryValueInStore(
            array(
              'value_id' => $resource->insertGallery(
                array(
                  'entity_id' => $id,
                  'attribute_id' => $attrId,
                  'value' => $image['file']
                )
              ),
              'label'  => $image['label'],
              'position' => (int) $image['position'],
              'disabled' => (int) $image['disabled'],
              'store_id' => $storeId
            )
          );
      }
    }

    return $this;
  }

  /**
   * Returns value of mv_shipping_ attribute from specified product
   *
   * @param Mage_Catalog_Model_Product $product
   * @param boolean $rawValue
   * @return mixin Value of the attribute
   */
  public function getShippingType ($product, $rawValue = false) {
    $attributeCode = 'mv_shipping_';

    if ($rawValue)
      return $product->getData($attributeCode);

    $attributes = $product->getAttributes();

    return isset($attributes[$attributeCode])
             ? $attributes[$attributeCode]->getFrontend()->getValue($product)
               : null;
  }
}
