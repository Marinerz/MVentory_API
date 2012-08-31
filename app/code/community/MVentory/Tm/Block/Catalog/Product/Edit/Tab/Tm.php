<?php

/**
 * TM categories
 *
 * @category   MVentory
 * @package    MVentory_Tm
 * @author     MVentory <???@mventory.com>
 */
class MVentory_Tm_Block_Catalog_Product_Edit_Tab_Tm
  extends Mage_Adminhtml_Block_Widget {

  private $_selectedCategories = null;

  private $_tmListingUrl = null;
  private $_productUrl = null;

  public function __construct() {
    parent::__construct();

    $this->setTemplate('catalog/product/tab/tm.phtml');
  }

  public function getProduct () {
    return Mage::registry('current_product');
  }

  public function getCategory () {
    $categories = $this
                    ->getProduct()
                    ->getCategoryIds();

    if (!count($categories))
      return null;

    $category = Mage::getModel('catalog/category')->load($categories[0]);

    if ($category->getId())
      return $category;

    return null;
  }

  public function getCategories () {
    return Mage::getModel('mventory_tm/connector')
             ->getTmCategories();
  }

  public function getSelectedCategories () {
    if ($this->_selectedCategories)
      return $this->_selectedCategories;

    $this->_selectedCategories = array();

    $category = $this->getCategory();

    if ($category) {
      $categories = $category->getTmAssignedCategories();

      if ($categories && is_string($categories))
        $this->_selectedCategories = explode(',', $categories);
    }

    return $this->_selectedCategories;
  }

  public function getColsNumber () {
    $categories = $this->getCategories();

    $cols = 0;

    foreach ($categories as $id => $names)
      if (count($names) > $cols)
        $cols = count($names);

    return $cols;
  }

  public function getTmListingUrl () {
    if ($this->_tmListingUrl)
      return $this->_tmListingUrl;

    $helper = Mage::helper('mventory_tm');
    $product = $this->getProduct();

    $websiteId = $helper->getWebsiteIdFromProduct($product);

    $domain = $helper->isSandboxMode($websiteId)
                ? 'tmsandbox'
                  : 'trademe';

    $id = $product->getTmListingId();

    return $this->_tmListingUrl = 'http://www.'
                                  . $domain
                                  . '.co.nz/Browse/Listing.aspx?id='
                                  . $id;
  }

  public function getProductUrl () {
    if ($this->_productUrl)
      return $this->_productUrl;

    $helper = Mage::helper('mventory_tm');
    $product = $this->getProduct();

    $baseUrl = Mage::app()
                 ->getWebsite($helper->getWebsiteIdFromProduct($product))
                 ->getConfig('web/unsecure/base_url');

    return $this->_productUrl = rtrim($baseUrl, '/')
                                . '/'
                                . $product->getUrlPath($this->getCategory());
  }

  public function getUrlTemplates () {
    $submit = $this->getUrl('mventory_tm/adminhtml_index/submit/id/',
                            array('id' => $this->getProduct()->getId(),
                            'tm_category_id' => '{{tm_category_id}}'));

    return Zend_Json::encode(compact('submit'));
  }

  public function getSubmitButton () {
    $label = $this->__('Submit');
    $class = count($this->getSelectedCategories()) != 1
               ? 'disabled'
                 : '';

    return $this->getButtonHtml($label, null, $class, 'tm_submit_button');
  }

  public function getStatusButton () {
    $label = $this->__('Check status');
    $onclick = 'setLocation(\''
               . $this->getUrl('mventory_tm/adminhtml_index/check/',
                               array('id' => $this->getProduct()->getId()))
               . '\')';

    return $this->getButtonHtml($label, $onclick, '', 'tm_status_button');
  }

  public function getRemoveButton () {
    $label = $this->__('Remove');
    $onclick = 'setLocation(\''
               . $this->getUrl('mventory_tm/adminhtml_index/remove/',
                               array('id' => $this->getProduct()->getId()))
               . '\')';

    return $this->getButtonHtml($label, $onclick, '', 'tm_remove_button');
  }
}

