<?php

/**
 * Product description block
 *
 * @category   Mage
 * @package    Mage_Catalog
 * @author     Magento Core Team <core@magentocommerce.com>
 */
class MVentory_Tm_Block_Product_View_Attributes
 extends Mage_Catalog_Block_Product_View_Attributes {
    protected $_product = null;

  /**
   * $excludeAttr is optional array of attribute codes to
   * exclude them from additional data array
   *
   * @param array $excludeAttr
   * @return array
   */
  public function getAdditionalData (array $excludeAttr = array()) {
    $data = parent::getAdditionalData($excludeAttr);

    $productIdData= array(
      'label' => 'Product ID',
      'value' => $this->getProduct()->getId(),
      'code' => null
    );

    array_unshift($data, $productIdData);

    return $data;
  }
}
