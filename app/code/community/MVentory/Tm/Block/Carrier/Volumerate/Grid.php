<?php

class MVentory_Tm_Block_Carrier_Volumerate_Grid
  extends Mage_Adminhtml_Block_Widget_Grid {

  /**
   * Define grid properties
   *
   * @return void
   */
  public function __construct () {
    parent::__construct();

    $this->setId('shippingVolumerateGrid');

    $this->_exportPageSize = 10000;
  }

  /**
   * Prepare shipping table rate collection
   *
   * @return Mage_Adminhtml_Block_Shipping_Carrier_Tablerate_Grid
   */
  protected function _prepareCollection () {
    $collection
      = Mage::getResourceModel('mventory_tm/carrier_volumerate_collection')
          ->setWebsiteFilter($this->getWebsiteId());

    $this->setCollection($collection);

    foreach ($collection as $rate) {
      $name = $rate->getConditionName();

      if ($name == 'weight')
        $rate->setWeight($rate->getConditionValue());
      else if ($name == 'volume')
        $rate->setVolume($rate->getConditionValue());
    }

    return parent::_prepareCollection();
  }

  /**
   * Prepare table columns
   *
   * @return Mage_Adminhtml_Block_Widget_Grid
   */
  protected function _prepareColumns () {
    $helper = Mage::helper('adminhtml');
    $tmHelper = Mage::helper('mventory_tm');

    $columns = array(
      'dest_country' => array(
        'header' => $helper->__('Country'),
        'index' => 'dest_country',
        'default' => '*',
      ),
      'dest_region' => array(
        'header' => $helper->__('Region/State'),
        'index' => 'dest_region',
        'default' => '*',
      ),
      'dest_zip' => array(
        'header' => $helper->__('Zip/Postal Code'),
        'index' => 'dest_zip',
        'default' => '*',
      ),
      'weight' => array(
        'header' => $tmHelper->__('Weight'),
        'index' => 'weight',
      ),
      'volume' => array(
        'header' => $tmHelper->__('Volume'),
        'index' => 'volume',
      ),
      'price' => array(
        'header' => $helper->__('Shipping Price'),
        'index' => 'price',
      )
    );

    foreach ($columns as $id => $data)
      $this->addColumn($id, $data);

    return parent::_prepareColumns();
  }
}
