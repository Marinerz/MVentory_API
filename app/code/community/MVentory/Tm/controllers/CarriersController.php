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
 * Volumerate carriers export controller
 *
 * @package MVentory/TM
 * @author Anatoly A. Kazantsev <anatoly@mventory.com>
 */
class MVentory_Tm_CarriersController
  extends Mage_Adminhtml_Controller_Action {

  protected function _construct() {
    $this->setUsedModuleName('MVentory_Tm');
  }

  /**
   * Export shipping rates in csv format
   */
  public function exportAction () {
    $websiteId = Mage::app()
                   ->getWebsite($this->getRequest()->getParam('website'))
                   ->getId();

    $content = $this
                 ->getLayout()
                 ->createBlock('mventory_tm/carrier_volumerate_grid')
                 ->setWebsiteId($websiteId)
                 ->getCsvFile();

    $this->_prepareDownloadResponse('shippingrates.csv', $content);
  }
}
