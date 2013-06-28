<?php

class MVentory_Tm_Helper_Product_Configurable
  extends MVentory_Tm_Helper_Product {

  public function getIdByChild ($child) {
    $id = $child instanceof Mage_Catalog_Model_Product
            ? $child->getId()
              : $child;

    if (!$id)
      return $id;

    $configurableType
      = Mage::getResourceSingleton('catalog/product_type_configurable');

    $parentIds = $configurableType->getParentIdsByChild($id);

    //Get first ID because we use only one configurable product
    //per simple product
    return $parentIds ? $parentIds[0] : null;
  }

  public function getChildrenIds ($configurable) {
    $id = $configurable instanceof Mage_Catalog_Model_Product
            ? $configurable->getId()
              : $configurable;

    $ids = Mage::getResourceSingleton('catalog/product_type_configurable')
             ->getChildrenIds($id);

    return $ids[0] ? $ids[0] : null;
  }

  public function getSiblingsIds ($product) {
    $id = $configurable instanceof Mage_Catalog_Model_Product
            ? $configurable->getId()
              : $configurable;

    if (!$configurableId = $this->getIdByChild($id))
      return;

    if (!$ids = $this->getChildrenIds($configurableId))
      return;

    //Unset product'd ID
    unset($ids[$id]);

    return $ids;
  }
}
