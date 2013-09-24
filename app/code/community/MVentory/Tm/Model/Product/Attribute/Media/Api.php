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
 * Catalog product media api
 *
 * @category   Mage
 * @package    Mage_Catalog
 * @author     Magento Core Team <core@magentocommerce.com>
 */
class MVentory_Tm_Model_Product_Attribute_Media_Api
  extends Mage_Catalog_Model_Product_Attribute_Media_Api {

  public function createAndReturnInfo ($productId, $data, $storeId = null,
                                       $identifierType = null) {

    if (!isset($data['file']))
      $this->_fault('data_invalid',
                    Mage::helper('catalog')->__('The image is not specified.'));

    $file = $data['file'];

    if (!isset($file['name'], $file['mime'], $file['content']))
      $this->_fault('data_invalid',
                    Mage::helper('catalog')->__('The image is not specified.'));


    if (!isset($this->_mimeTypes[$file['mime']]))
      $this->_fault('data_invalid',
                    Mage::helper('catalog')->__('Invalid image type.'));

    $file['name'] = strtolower(trim($file['name']));

    //$storeId = Mage::helper('mventory_tm')->getCurrentStoreId($storeId);

    //Temp solution, apply image settings globally
    $storeId = null;

    $images = $this->items($productId, $storeId, $identifierType);

    $name = $file['name'] . '.' . $this->_mimeTypes[$file['mime']];

    foreach ($images as $image)
      //Throw of first 5 symbols becau se 'file'
      //has following format '/i/m/image.ext' (dispretion path)
      if (strtolower(substr($image['file'], 5)) == $name)
        return Mage::getModel('mventory_tm/product_api')
                 ->fullInfo($productId, $identifierType);

    $hasMainImage = false;
    $hasSmallImage = false;
    $hasThumbnail = false;

    if (isset($image['types']))
      foreach ($images as $image) {
        if (in_array('image', $image['types']))
          $hasMainImage = true;

        if (in_array('small_image', $image['types']))
          $hasSmallImage = true;

        if (in_array('thumbnail', $image['types']))
          $hasThumbnail = true;
      }

    if (!$hasMainImage)
      $data['types'][] = 'image';

    if (!$hasSmallImage)
      $data['types'][] = 'small_image';

    if (!$hasThumbnail)
      $data['types'][] = 'thumbnail';

    //We don't use exclude feature
    $data['exclude'] = 0;

    $this->create($productId, $data, $storeId, $identifierType);

    $productApi = Mage::getModel('mventory_tm/product_api');

    return $productApi->fullInfo($productId, $identifierType);
  }

  /**
   * Retrieve product
   *
   * The function is redefined to check if api user has access to the product
   *
   * @param int|string $productId
   * @param string|int $store
   * @param  string $identifierType
   * @return Mage_Catalog_Model_Product
   */
  protected function _initProduct($productId, $store = null,
                                  $identifierType = null) {

    $helper = Mage::helper('mventory_tm/product');

    $productId = $helper->getProductId($productId, $identifierType);

    if (!($productId && $helper->hasApiUserAccess($productId, 'id')))
      $this->_fault('product_not_exists');

    $product = Mage::getModel('catalog/product')
                 ->setStoreId(Mage::app()->getStore($store)->getId())
                 ->load($productId);

    if (!$product->getId())
      $this->_fault('product_not_exists');

    return $product;
  }
}
