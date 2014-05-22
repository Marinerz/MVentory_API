<?php

/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Creative Commons License BY-NC-ND.
 * NonCommercial — You may not use the material for commercial purposes.
 * NoDerivatives — If you remix, transform, or build upon the material,
 * you may not distribute the modified material.
 * See the full license at http://creativecommons.org/licenses/by-nc-nd/4.0/
 *
 * See http://mventory.com/legal/licensing/ for other licensing options.
 *
 * @package MVentory/TM
 * @copyright Copyright (c) 2014 mVentory Ltd. (http://mventory.com)
 * @license http://creativecommons.org/licenses/by-nc-nd/4.0/
 */

/**
 * Source model for pickup field
 *
 * @package MVentory/TM
 * @author Anatoly A. Kazantsev <anatoly@mventory.com>
 */
class MVentory_Tm_Model_Entity_Attribute_Source_Pickup
  extends Mage_Eav_Model_Entity_Attribute_Source_Abstract {

  /**
   * Retrieve all options array
   *
   * @return array
   */
  public function getAllOptions () {
    if (is_null($this->_options)) {
      $helper = Mage::helper('mventory_tm');

      $this->_options = array(
        array(
          'label' => $helper->__('Default'),
          'value' => -1
        ),

        array(
          'label' => $helper->__('Buyer can pickup'),
          'value' => MVentory_TradeMe_Model_Config::PICKUP_ALLOW,
        ),

        array(
          'label' => $helper->__('No pickups'),
          'value' => MVentory_TradeMe_Model_Config::PICKUP_FORBID,
        ),
      );
    }

    return $this->_options;
  }

  /**
   * Retrieve option array
   *
   * @return array
   */
  public function getOptionArray () {
    $options = array();

    foreach ($this->getAllOptions() as $option)
      $options[$option['value']] = $option['label'];

    return $options;
  }

  /**
   * Options getter
   *
   * @return array
   */
  public function toOptionArray () {
    $options = $this->getOptionArray();

    unset($options[-1]);

    return $options;
  }
}

?>
