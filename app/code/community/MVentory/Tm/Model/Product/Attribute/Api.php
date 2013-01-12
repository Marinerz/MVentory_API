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
 * Catalog product attribute api
 *
 * @category   Mage
 * @package    Mage_Catalog
 * @author     Magento Core Team <core@magentocommerce.com>
 */
class MVentory_Tm_Model_Product_Attribute_Api
  extends Mage_Catalog_Model_Product_Attribute_Api {

  public function fullInfoList ($setId) {
    $storeId = Mage::helper('mventory_tm')->getCurrentStoreId(null);

    $_attributes = $this->items($setId);

    $attributes = array();

    foreach ($_attributes as $_attribute) {
      $attribute = $this->info($_attribute['attribute_id']);

      $attribute['options']
        = $this->optionsPerStoreView($attribute['attribute_id'], $storeId);

      $attributes[] = $attribute;
    }

    return $attributes;
  }

  public function addOptionAndReturnInfo ($attribute, $value) {
    $storeId = Mage::helper('mventory_tm')->getCurrentStoreId(null);

    $data = array(
              'label' => array(
                           array(
                             'store_id' => array(0, $storeId),
                             'value' => $value
                           )
                         ),

              'order' => 0
            );

    try {
      $this->addOption($attribute, $data);
    } catch (Exception $e) {}

    $attributeRet = $this->info($attribute);

    $attributeRet['options']
      = $this->optionsPerStoreView($attribute, $storeId);

    return $attributeRet;
  }

  private function optionsPerStoreView($attribute, $storeId)
  {
    $attributeModel = Mage::getResourceModel('catalog/eav_attribute')
      ->setEntityTypeId(Mage::getModel('eav/entity')->setType('catalog_product')->getTypeId());

    if (is_numeric($attribute)) {
      $attributeModel->load(intval($attribute));
    } else {
      $attributeModel->load($attribute, 'attribute_code');
    }

    $attributeId = $attributeModel->getId();

    $optionCollection = Mage::getResourceModel('eav/entity_attribute_option_collection')
      ->setAttributeFilter($attributeId)
      ->setStoreFilter($storeId, false)
      ->load();

    $values = array();

    foreach ($optionCollection as $option) {
      $value = array();

      $value['value'] = $option->getId();
      $value['label'] = $option->getValue();
      $values[] = $value;
    }

    return $values;
  }
}
