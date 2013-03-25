<?php

class MVentory_Tm_Helper_Product extends MVentory_Tm_Helper_Data {

  protected $_tmFields = array(
    'category' => 'tm_category',
    'account_id' => 'tm_account_id',
    'shipping_type' => 'tm_shipping_type',
    'allow_buy_now' => 'tm_allow_buy_now',
    'add_fees' => 'tm_add_fees',
    'relist' => 'tm_relist',
    'avoid_withdrawal' => 'tm_avoid_withdrawal',
    'pickup' => 'tm_pickup'
  );

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
   * Returns TM listing ID linked to the product
   *
   * @param int $productId Product's ID
   *
   * @return string Listing ID
   */
  public function getListingId ($productId) {
    return $this->getAttributesValue($productId, 'tm_listing_id');
  }

  /**
   * Sets value of TM listing ID attribute in the product
   *
   * @param int|string $listingId Listing ID
   * @param int $productId Product's ID
   *
   */
  public function setListingId ($listingId, $productId) {
    $attribute = array('tm_listing_id' => $listingId);

    $this->setAttributesValue($productId, $attribute);
  }

  /**
   * Try to get product's ID
   * Code is taken from Mage_Catalog_Helper_Product::getProduct()
   *
   * @param  int|string $productId (SKU or ID)
   * @param  string $identifierType
   *
   * @return int|null
   */
  public function getProductId ($productId, $identifierType = null) {
    $expectedIdType = false;

    if ($identifierType === null && is_string($productId)
        && !preg_match("/^[+-]?[1-9][0-9]*$|^0$/", $productId))
      $expectedIdType = 'sku';

    if ($identifierType == 'sku' || $expectedIdType == 'sku') {
      $idBySku = Mage::getResourceModel('catalog/product')
                   ->getIdBySku($productId);

      if ($idBySku)
        $productId = $idBySku;
      else if ($identifierType == 'sku')
        return null;
    }

    if ($productId && is_numeric($productId))
      return (int) $productId;

    return null;
  }

  /**
   * Check if api user has access to a product.
   *
   * Return true if current api users assigned to the Admin website
   * or to the same website as the product
   *
   * @param  int|string $productId (SKU or ID)
   * @param  string $identifierType
   *
   * @return boolean
   */
  public function hasApiUserAccess ($productId, $identifierType = null) {
    $userWebsite = $this->getApiUserWebsite();

    if (!$userWebsite)
      return false;

    $userWebsiteId = $userWebsite->getId();

    if ($userWebsiteId == 0)
      return true;

    $id = $this->getProductId($productId, $identifierType);

    $productWebsiteId = $this
                          ->getWebsite($id)
                          ->getId();

    return $productWebsiteId == $userWebsiteId;
  }

  /**
   * Extracts data for TM options from product
   *
   * @param Mage_Catalog_Model_Product|array $product Product's data
   *
   * @return array TM options
   */
  public function getTmFields ($product) {
    if ($product instanceof Mage_Catalog_Model_Product)
      $product = $product->getData();

    $fields = array();

    foreach ($this->_tmFields as $name => $code)
      $fields[$name] = isset($product[$code])
                         ? $product[$code]
                           : null;

    return $fields;
  }

  /**
   * Sets TM options in product
   *
   * @param Mage_Catalog_Model_Product|array $product Product
   * @param array $fields TM options data
   *
   * @return MVentory_Tm_Helper_Product
   */
  public function setTmFields ($product, $fields) {
    foreach ($fields as $name => $value)
      $product->setData($this->_tmFields[$name], $value);

    return $this;
  }
}
