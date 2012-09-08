<?php

/**
 * Image editing helper
 *
 * @author MVentory <???@mventory.com>
 */

class MVentory_Tm_Helper_Imageediting extends Mage_Core_Helper_Abstract {

  const ATTRIBUTE_CODE = 'media_gallery';

  public function rotate ($file, $angle) {
    $media = Mage::getModel('catalog/product_media_config');

    if (!file_exists($media->getMediaPath($file)))
      return;

    $image = Mage::getModel('catalog/product_image');

    $image
      ->setBaseFile($file)
      ->setNewFile($image->getBaseFile())
      ->setQuality(100)
      ->setKeepFrame(false)
      ->rotate($angle)
      ->saveFile();

    return true;
  }

  public function remove ($file, $productId) {
    Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);

    $product = Mage::getModel('catalog/product')->load($productId);

    if (!$product->getId())
      return;

    $attributes = $product
                    ->getTypeInstance(true)
                    ->getSetAttributes($product);

    if (!isset($attributes[self::ATTRIBUTE_CODE]))
      return;

    $gallery = $attributes[self::ATTRIBUTE_CODE];

    if (!$gallery->getBackend()->getImage($product, $file))
      return;

    $gallery
      ->getBackend()
      ->removeImage($product, $file);

    try {
      $product->save();
    } catch (Mage_Core_Exception $e) {
      return;
    }

    return true;
  }
}
