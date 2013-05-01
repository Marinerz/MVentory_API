<?php

class MVentory_Tm_Helper_Image extends Mage_Catalog_Helper_Image {

  /**
   * Initialize Helper to work with Image
   *
   * @param Mage_Catalog_Model_Product $product
   * @param string $attributeName
   * @param mixed $imageFile
   * @return Mage_Catalog_Helper_Image
  */
  public function init (Mage_Catalog_Model_Product $product, $attributeName,
                        $imageFile = null) {

    $this->_reset();

    $this->_setModel(new Varien_Object());

    $this->_getModel()->setDestinationSubdir($attributeName);
    $this->setProduct($product);

    $path = 'design/watermark/' . $attributeName . '_';

    $this->watermark(
      Mage::getStoreConfig($path . 'image'),
      Mage::getStoreConfig($path . 'position'),
      Mage::getStoreConfig($path . 'size'),
      Mage::getStoreConfig($path . 'imageOpacity')
    );

    if ($imageFile)
      $this->setImageFile($imageFile);

    return $this;
  }

  /**
   * Retrieve original image height
   *
   * @return int|null
   */
  public function getOriginalHeight () {
    return null;
  }

  /**
   * Retrieve original image width
   *
   * @return int|null
   */
  public function getOriginalWidth () {
    return null;
  }

  public function __toString() {
    $model = $this->_getModel();
    $product = $this->getProduct();

    $destSubdir = $model->getDestinationSubdir();

    if (!$imageFileName = $this->getImageFile())
      $imageFileName = $product->getData($destSubdir);

    if ($imageFileName == 'no_selection') {
      if (($bkThumbnail = $product->getData('bk_thumbnail_'))
          && ($destSubdir == 'image' || $destSubdir == 'small_image'))
        return $bkThumbnail . '&zoom=' . ($destSubdir == 'image' ? 1 : 5);

      $placeholder = Mage::getModel('catalog/product_image')
                       ->setDestinationSubdir($destSubdir)
                       ->setBaseFile(null)
                       ->getBaseFile();

      $imageFileName = '/' . basename($placeholder);
    }

    $width = $model->getWidth();
    $height = $model->getHeight();

    //!!!TODO: remove hack for 75x75 images
    if ($width == $height && $width != 75)
      $height = null;

    if (($dimensions = $width . 'x' . $height) == 'x')
      $dimensions = 'full';

    $helper = Mage::helper('mventory_tm/product');
    $website = $helper->getWebsite($product);

    $prefix = $helper
                ->getConfig(MVentory_Tm_Model_Observer::XML_PATH_CDN_PREFIX,
                            $website);

    return $helper->getBaseMediaUrl($website)
           . $prefix
           . '/'
           . $dimensions
           . $imageFileName;
  }
}
