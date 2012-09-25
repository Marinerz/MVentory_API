<?php

class MVentory_Tm_Helper_Tm extends Mage_Core_Helper_Abstract {

  //TM fees description.
  //Available fields:
  // * from - Min product price for the fee (value is included in comparision)
  // * to - Max product price for the fee (value is included in comparision)
  // * rate - Percents
  // * fixed - Fixed part of fee
  // * min - Min fee value
  // * max - Max fee value
  private $_fees = array(
    //Up to $200 : 7.5% of sale price (50c minimum)
    array(
      'from' => 0,
      'to' => 199,
      'rate' => 0.075,
      'fixed' => 0,
      'min' => 0.5,
    ),

    //$200 - $1500 : $15.00 + 4.5% of sale price over $200
    array(
      'from' => 200,
      'to' => 1500,
      'rate' => 0.045,
      'fixed' => 15,
    ),

    //Over $1500 : $73.50 + 1.9% of sale price over $1500 (max fee = $149)
    array(
      'from' => 1501,
      'rate' => 0.019,
      'fixed' => 73.5,
      'max' => 149
    ),
  );

  public function getAttributes ($categoryId) {
    $model = Mage::getModel('mventory_tm/connector');

    $attrs = $model
               ->getTmCategoryAttrs($categoryId);

    if (!(is_array($attrs) && count($attrs)))
      return null;

    foreach ($attrs as &$attr) {
      $attr['Type'] = $model->getAttrTypeName($attr['Type']);
    }

    return $attrs;
  }

  /**
   * Add TM fees to the product
   *
   * @param float $price
   * @return float Product's price with calculated fees
   */
  public function addFees ($price) {
    foreach ($this->_fees as $_fee) {
      //Check if price of the product is in the range of the fee
      $from = isset($_fee['from'])
                ? $price >= $_fee['from']
                  : true;

      $to = isset($_fee['to'])
              ? $price <= $_fee['to']
                : true;

      //Price of the product is not the range of the fee
      if (!($from && $to))
        continue;

      //Calculate percents
      $fee = isset($_fee['rate'])
               ? $price * $_fee['rate']
                 : 0;

      //Add fixed part of the fee
      $fee = isset($_fee['fixed'])
               ? $fee + $_fee['fixed']
                 : $fee;

      //Check for min and max values of the calculated fee
      $fee = isset($_fee['min']) && $fee < $_fee['min']
               ? $_fee['min']
                 : $fee;

      $fee = isset($_fee['max']) && $fee > $_fee['max']
               ? $_fee['max']
                 : $fee;

      return round($price + $fee, 2);
    }

    return $price;
  }
}
