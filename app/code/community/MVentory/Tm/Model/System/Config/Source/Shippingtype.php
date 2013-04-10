<?php

class MVentory_Tm_Model_System_Config_Source_Shippingtype {

  //TM shipping type mapping
  protected $_options = array(
    //MVentory_Tm_Model_Connector::NONE => 'None',
    //MVentory_Tm_Model_Connector::UNKNOWN => 'Unknown',

    //The option was renamed to Use free shipping with YES/NO values
    MVentory_Tm_Model_Connector::UNDECIDED => 'No',

    //MVentory_Tm_Model_Connector::PICKUP => 'Pickup',

    //The option was renamed to Use free shipping with YES/NO values
    MVentory_Tm_Model_Connector::FREE => 'Yes',

    //MVentory_Tm_Model_Connector::CUSTOM => 'Custom'
  );

  /**
   * Options getter
   *
   * @return array
   */
  public function toOptionArray () {
    $helper = Mage::helper('mventory_tm');

    $options = array();

    foreach ($this->_options as $value => $code)
      $options[] = array('value' => $value, 'label' => $helper->__($code));

    return $options;
  }
  
  /**
   * Get options in "key-value" format
   *
   * @return array
   */
  public function toArray () {
    return $this->_options;
  }
}

?>
