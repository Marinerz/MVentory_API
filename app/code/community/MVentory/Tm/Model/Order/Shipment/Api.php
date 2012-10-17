<?php

/**
 * Sales order Shipment API
 *
 * @category   MVentory
 * @package    MVentory_Tm
 * @author     MVentory <???@mventory.com>
 */
class MVentory_Tm_Model_Order_Shipment_Api extends Mage_Sales_Model_Order_Shipment_Api {
	
  public function createShipmentWithTracking($orderIncrementId, $carrier,
  $title, $trackNumber, $params = null) {
  	
  	$itemsQty = array();
  	$comment = null;
  	$email = false;
  	$includeComment = false;
  	
    if(is_array($params)) {
      if(array_key_exists('itemsQty'))
        $itemsQty = $params['itemsQty'];
      if(array_key_exists('comment'))
        $itemsQty = $params['comment'];
      if(array_key_exists('email'))
        $itemsQty = $params['email'];
      if(array_key_exists('includeComment'))
        $itemsQty = $params['includeComment'];
    }
  	
    $shipmentId = $this->create($orderIncrementId,$itemsQty,$comment,$email,
      $includeComment);
  	
  	$this->addTrack($shipmentId,$carrier,$title,$trackNumber);
  	
  	$orderApi = Mage::getModel('mventory_tm/order_api');
  	
  	return $orderApi->fullInfo($orderIncrementId);
  }
}