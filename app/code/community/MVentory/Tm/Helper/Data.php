<?php

class MVentory_Tm_Helper_Data extends Mage_Core_Helper_Abstract {

  public function getCurrentWebsite () {
    $params =  Mage::registry('application_params');

    $hasScopeCode = isset($params)
                      && is_array($params)
                      && isset($params['scope_code'])
                      && $params['scope_code'];

    return $hasScopeCode
             ? Mage::app()->getWebsite($params['scope_code'])
               : Mage::app()->getStore(true)->getWebsite();
  }

  public function getCurrentStoreId ($storeId = null) {
    if ($storeId)
      return $storeId;

    $website = $this->getCurrentWebsite();

    return $website && $website->getId()
             ? $website->getDefaultStore()->getId() : true;
  }

  public function getWebsitesForProduct ($storeId) {
    $data = Mage::getStoreConfig("mventory_tm/api/add_to_websites", $storeId);

    $websites = explode(',', $data);

    $currentWebsiteId = $this->getCurrentWebsite()->getId();

    if (!in_array($currentWebsiteId, $websites))
      $websites[] = $currentWebsiteId;

    return $websites;
  }
}
