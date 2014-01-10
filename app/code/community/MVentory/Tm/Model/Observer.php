<?php

class MVentory_Tm_Model_Observer {

  const XML_PATH_CDN_ACCESS_KEY = 'mventory_tm/cdn/access_key';
  const XML_PATH_CDN_SECRET_KEY = 'mventory_tm/cdn/secret_key';
  const XML_PATH_CDN_BUCKET = 'mventory_tm/cdn/bucket';
  const XML_PATH_CDN_PREFIX = 'mventory_tm/cdn/prefix';
  const XML_PATH_CDN_DIMENSIONS = 'mventory_tm/cdn/resizing_dimensions';

  const XML_PATH_CRON_INTERVAL = 'mventory_tm/settings/cron';
  const XML_PATH_ENABLE_LISTING = 'mventory_tm/settings/enable_listing';

  const XML_PATH_CANCEL_STATES = 'mventory_tm/order/cancel_states';
  const XML_PATH_CANCEL_PERIOD = 'mventory_tm/order/cancel_period';

  const SYNC_START_HOUR = 7;
  const SYNC_END_HOUR = 23;

  private $supportedImageTypes = array(
    IMAGETYPE_GIF => 'gif',
    IMAGETYPE_JPEG => 'jpeg',
    IMAGETYPE_PNG => 'png'
  );

  const TAG_TM_EMAILS = 'tag_tm_emails';
  const TAG_TM_FREE_SLOTS = 'tag_tm_free_slots';

  public function removeListingFromTm ($observer) {
    if (Mage::registry('tm_disable_withdrawal'))
      return;

    $order = $observer
               ->getEvent()
               ->getOrder();

    $items = $order->getAllItems();

   $productHelper = Mage::helper('mventory_tm/product');
   $tmHelper = Mage::helper('mventory_tm/tm');

    foreach ($items as $item) {
      $productId = (int) $item->getProductId();

      $storeId = Mage_Catalog_Model_Abstract::DEFAULT_STORE_ID;

      //We can use default store ID because the attribute is global
      $listingId = $productHelper->getListingId($productId);

      if (!$listingId)
        continue;

      $stockItem = Mage::getModel('cataloginventory/stock_item')
                     ->loadByProduct($productId);

      if (!($stockItem->getManageStock() && $stockItem->getQty() == 0))
        continue;

      $product = Mage::getModel('catalog/product')->load($productId);

      $website = $productHelper->getWebsite($product);
      $accounts = $tmHelper->getAccounts($website);
      $accounts = $productHelper->prepareAccounts($accounts, $product);

      $accountId = $product->getTmCurrentAccountId();

      $account = $accountId && isset($accounts[$accountId])
                   ? $accounts[$accountId]
                     : null;

      //Avoid withdrawal by default
      $avoidWithdrawal = true;

      $tmFields = $productHelper->getTmFields($product, $account);

      $attrs = $product->getAttributes();

      if (isset($attrs['tm_avoid_withdrawal'])) {
        $attr = $attrs['tm_avoid_withdrawal'];

        if ($attr->getDefaultValue() != $tmFields['avoid_withdrawal']) {
          $options = $attr
                      ->getSource()
                      ->getOptionArray();

          if (isset($options[$tmFields['avoid_withdrawal']]))
            $avoidWithdrawal = (bool) $tmFields['avoid_withdrawal'];
        }
      }

      $hasError = false;

      if ($avoidWithdrawal) {
        $price = $product->getPrice() * 5;

        if ($tmFields['add_fees'])
          $price = $tmHelper->addFees($price);

        $result = Mage::getModel('mventory_tm/connector')
                    ->update($product, array('StartPrice' => $price));

        if (!is_int($result))
          $hasError = true;
      } else {
        $result = Mage::getModel('mventory_tm/connector')->remove($product);

        if ($result !== true)
          $hasError = true;
      }

      if ($hasError) {
        //Send email with error message to website's general contact address

        $productUrl = $productHelper->getUrl($product);
        $listingId = $tmHelper->getListingUrl($product);

        $subject = 'TM: error on removing listing';
        $message = 'Error on increasing price or withdrawing listing ('
                   . $listingId
                   . ') linked to product ('
                   . $productUrl
                   . ')'
                   . ' Error: ' . $result;

        $tmHelper->sendEmail($subject, $message);

        continue;
      }

      $productHelper->setListingId(0, $productId);
      $tmHelper->setCurrentAccountId($productId, null);
    }
  }

  public function populateAttributes ($observer) {
    $event = $observer->getEvent();

    //Populate product attributes
    Mage::getSingleton('mventory_tm/product_action')
      ->populateAttributes(array($event->getProduct()), null, false);
  }

  public function saveProductCreateDate ($observer) {
    $product = $observer
                 ->getEvent()
                 ->getProduct();

    if ($product->getId())
      return;

    $product->setData('mv_created_date', time());
  }

  public function saveApiUserId ($observer) {
    if (!$user = Mage::helper('mventory_tm')->getApiUser())
      return;

    $product = $observer
                 ->getEvent()
                 ->getProduct();

    if ($product->getId())
      return;

    $product->setData('mv_created_userid', $user->getId());
  }

  public function sync ($schedule) {
    //Get cron job config
    $jobsRoot = Mage::getConfig()->getNode('default/crontab/jobs');
    $jobConfig = $jobsRoot->{$schedule->getJobCode()};

    //Get website from the job config
    $website = Mage::app()->getWebsite((string) $jobConfig->website);

    //Get website's default store
    $store = $website->getDefaultStore();

    $tmHelper = Mage::helper('mventory_tm/tm');

    //Load TM accounts which are used in specified website
    $accounts = $tmHelper->getAccounts($website);

    //Unset Random pseudo-account
    unset($accounts[null]);

    $helper = Mage::helper('mventory_tm/product');

    //Get time with Magento timezone offset
    $now = localtime(Mage::getModel('core/date')->timestamp(time()), true);

    //Check if we are in allowed hours
    $allowSubmit = $now['tm_hour'] >= self::SYNC_START_HOUR
                   && $now['tm_hour'] < self::SYNC_END_HOUR;

    if ($allowSubmit) {
      $cronInterval
        = (int) $helper->getConfig(self::XML_PATH_CRON_INTERVAL, $website);

      //Calculate number of runnings of the sync script during 1 day
      $runsNumber = $cronInterval
                      ? (self::SYNC_END_HOUR - self::SYNC_START_HOUR) * 60
                          / $cronInterval - 1
                        : 0;
    }

    foreach ($accounts as $accountId => &$accountData) {
      $products = Mage::getModel('catalog/product')
                    ->getCollection()
                    ->addAttributeToSelect('tm_relist')
                    ->addAttributeToSelect('price')
                    ->addFieldToFilter('tm_current_listing_id',
                                       array('neq' => ''))
                    ->addFieldToFilter('tm_current_account_id',
                                       array('eq' => $accountId))
                    ->addStoreFilter($store);

      //!!!Commented to allow loading out of stock products
      //If customer exists and loaded add price data to the product collection
      //filtered by customer's group ID
      //if ($customer->getId())
      //  $products->addPriceData($customer->getGroupId());

      $connector = Mage::getModel('mventory_tm/connector')
                     ->setWebsiteId($website->getId())
                     ->setAccountId($accountId);

      $accountData['listings'] = $connector->massCheck($products);

      foreach ($products as $product) {
        if ($product->getIsSelling())
          continue;

        $result = $connector->check($product);

        if (!$result || $result == 3)
          continue;

        --$accountData['listings'];

        if ($result == 2) {
          $sku = $product->getSku();
          $price = $product->getPrice();
          $qty = 1;

          $shippingType = $helper->getAttributesValue(
            $product->getId(),
            'mv_shipping_',
            $website
          );

          $buyer = $accountData['shipping_types'][$shippingType]['buyer'];

          //API function for creating order requires curren store to be set
          Mage::app()->setCurrentStore($store);

          //Set global flag to prevent removing product from TM during order
          //creating. No need to remove it because it was bought on TM.
          //The flag is used in removeListingFromTm() method
          Mage::register('tm_disable_withdrawal', true, true);

          //Set global flag to enable our dummy shipping method
          Mage::register('tm_allow_dummyshipping', true, true);

          //Set customer ID for API access checks
          Mage::register('tm_api_customer', $buyer, true);

          //Make order for the product
          Mage::getModel('mventory_tm/cart_api')
            ->createOrderForProduct($sku, $price, $qty, $buyer);
        }

        $helper->setListingId(0, $product->getId());
      }

      if ($accountData['listings'] < 0)
        $accountData['listings'] = 0;
    }

    unset($accountId, $accountData);

    if (!($allowSubmit && $runsNumber))
      return;

    foreach ($accounts as $accountId => $accountData) {

      //Remember IDs of all existing accounts for further using
      $allAccountsIDs[$accountId] = true;

      if (($accountData['max_listings'] - $accountData['listings']) < 1)
        unset($accounts[$accountId]);
    }

    unset($accountId, $accountData);

    if (!count($accounts))
      return;

    $enabled = Mage_Catalog_Model_Product_Status::STATUS_ENABLED;

    $listingFilter = array(
      array('null' => true),
      array('in' => array('', 0))
    );

    $imageFilter = array('nin' => array('no_selection', ''));

    $products = Mage::getModel('catalog/product')
                  ->getCollection()
                  ->addAttributeToFilter('tm_relist', '1')
                  ->addAttributeToFilter(
                      'tm_current_listing_id',
                      $listingFilter,
                      'left'
                    )
                  ->addAttributeToFilter('image', $imageFilter)
                  ->addAttributeToFilter('status', $enabled)
                  ->addStoreFilter($store);

    Mage::getSingleton('cataloginventory/stock')
      ->addInStockFilterToCollection($products);

    if (!$poolSize = count($products))
      return;

    //Calculate avaiable slots for current run of the sync script
    foreach ($accounts as $accountId => &$accountData) {
      $cacheId = implode(
        '_',
        array(
          'tm_sync',
          $website->getCode(),
          $accountId,
        )
      );

      try {
        $syncData = unserialize(Mage::app()->loadCache($cacheId));
      } catch (Exception $e) {
        $syncData = null;
      }

      if (!is_array($syncData))
        $syncData = array(
          'free_slots' => 0,
          'duration' => MVentory_Tm_Helper_Tm::LISTING_DURATION_MAX
        );

      $freeSlots = $accountData['max_listings']
                   / ($runsNumber * $syncData['duration'])
                   + $syncData['free_slots'];

      $_freeSlots = (int) floor($freeSlots);

      $syncData['free_slots'] = $freeSlots - $_freeSlots;

      if ($_freeSlots < 1) {
        Mage::app()->saveCache(
          serialize($syncData),
          $cacheId,
          array(self::TAG_TM_FREE_SLOTS),
          null
        );

        unset($accounts[$accountId]);

        continue;
      }

      $accountData['free_slots'] = $_freeSlots;

      $accountData['cache_id'] = $cacheId;
      $accountData['sync_data'] = $syncData;

      $accountData['allowed_shipping_types']
        = array_keys($accountData['shipping_types']);
    }

    if (!count($accounts))
      return;

    unset($accountId, $accountData, $syncData);

    $ids = array_keys($products->getItems());

    shuffle($ids);

    foreach ($ids as $id) {
      $product = Mage::getModel('catalog/product')->load($id);

      if (!$product->getId())
        continue;

      if ($accountId = $product->getTmAccountId())
        if (!isset($allAccountsIDs[$accountId]))
          $product->setTmAccountId($accountId = null);
        else if (!isset($accounts[$accountId]))
          continue;

      $matchResult = Mage::getModel('mventory_tm/rules')
                       ->matchTmCategory($product);

      if (!(isset($matchResult['id']) && $matchResult['id'] > 0))
        continue;

      $accountIds = $accountId
                      ? (array) $accountId
                        : array_keys($accounts);

      shuffle($accountIds);

      $shippingType = $tmHelper->getShippingType($product, true);

      foreach ($accountIds as $accountId) {
        $accountData = $accounts[$accountId];

        if (!in_array($shippingType, $accountData['allowed_shipping_types']))
          continue;

        $minimalPrice = (float) $accountData
                                   ['shipping_types']
                                   [$shippingType]
                                   ['minimal_price'];

        if ($minimalPrice && ($product->getPrice() < $minimalPrice))
          continue;

        $result = Mage::getModel('mventory_tm/connector')
                    ->send($product, $matchResult['id'], $accountId);

        if (trim($result) == 'Insufficient balance') {
          $cacheId = array(
            $website->getCode(),
            $accountData['name'],
            'negative_balance'
          );

          $cacheId = implode('_', $cacheId);

          if (!Mage::app()->loadCache($cacheId)) {
            $helper->sendEmailTmpl(
              'mventory_negative_balance',
              array('account' => $accountData['name']),
              $website
            );

            Mage::app()
              ->saveCache(true, $cacheId, array(self::TAG_TM_EMAILS), 3600);
          }

          if (count($accounts) == 1)
            return;

          unset($accounts[$accountId]);

          continue;
        }

        if (is_int($result)) {
          $product
            ->setTmListingId($result)
            ->setTmCurrentListingId($result)
            ->save();

          if (!--$accounts[$accountId]['free_slots']) {
            $accountData['sync_data']['duration'] = $tmHelper->getDuration(
              $accountData['shipping_types'][$shippingType]
            );

            Mage::app()->saveCache(
              serialize($accountData['sync_data']),
              $accountData['cache_id'],
              array(self::TAG_TM_FREE_SLOTS),
              null
            );

            if (count($accounts) == 1)
              return;

            unset($accounts[$accountId]);
          }

          break;
        }
      }
    }
  }

  public function mediaSaveBefore($observer) {
    if (!function_exists('exif_read_data'))
      return;

    //There's nothing to process because we're using images
    //from original product in duplicate
    if ($observer->getProduct()->getIsDuplicate())
      return;

    $value = $observer->getImages();

    $config = Mage::getSingleton('catalog/product_media_config');

    foreach ($value['images'] as $image) {
      if (isset($image['value_id']))
        continue;

      $imagePath = $config->getMediaPath($image['file']);

      $exif = exif_read_data($imagePath);

      if ($exif === false)
        continue;

      if (isset($exif['FileType']))
        $imageType = $exif['FileType'];
      else
        continue;

      if (!array_key_exists($imageType, $this->supportedImageTypes))
        continue;

      if (isset($exif['Orientation']))
        $orientation = $exif['Orientation'];
      elseif (isset($exif['IFD0']['Orientation']))
        $orientation = $exif['IFD0']['Orientation'];
      else
        continue;

      if (!in_array($orientation, array(3, 6, 8)))
        continue;

      $imageTypeName = $this->supportedImageTypes[$imageType];

      $imageCreate = 'imagecreatefrom' . $imageTypeName;

      $imageData = $imageCreate($imagePath);

      switch($orientation) {
        case 3:
          $imageData = imagerotate($imageData, 180, 0);

          break;
        case 6:
          $imageData = imagerotate($imageData, -90, 0);

          break;
        case 8:
          $imageData = imagerotate($imageData, 90, 0);

          break;
      }

      $imageSave = 'image' . $imageTypeName;

      $imageSave($imageData, $imagePath, 100);
    }
  }

  public function productInit ($observer) {
    $product = $observer->getProduct();

    $categories = $product->getCategoryIds();

    if (!count($categories))
      return;

    $categoryId = $categories[0];

    $lastId = Mage::getSingleton('catalog/session')->getLastVisitedCategoryId();

    $category = $product->getCategory();

    //Return if last visited vategory was not used
    if ($category && $category->getId() != $lastId)
      return;

    //Return if categories are same, nothing to change
    if ($lastId == $categoryId)
      return;

    if (!$product->canBeShowInCategory($categoryId))
      return;

    $category = Mage::getModel('catalog/category')->load($categoryId);

    $product->setCategory($category);

    Mage::unregister('current_category');
    Mage::register('current_category', $category);
  }

  public function addProductNameRebuildMassaction ($observer) {
    $block = $observer->getBlock();

    $route = 'mventory_tm/catalog_product/massNameRebuild';

    $label = Mage::helper('mventory_tm')->__('Rebuild product name');
    $url = $block->getUrl($route, array('_current' => true));

    $block
      ->getMassactionBlock()
      ->addItem('namerebuild', compact('label', 'url'));
  }

  /**
   * Add action "Populate product attributes" to admin product manage grid
   */
  public function addProductAttributesPopulateMassaction ($observer) {
    $block = $observer->getBlock();

    $route = 'mventory_tm/catalog_product/massAttributesPopulate';

    $label = Mage::helper('mventory_tm')->__('Populate product attributes');
    $url = $block->getUrl($route, array('_current' => true));

    $block
      ->getMassactionBlock()
      ->addItem('attributespopulate', compact('label', 'url'));
  }

  public function addProductCategoryMatchMassaction ($observer) {
    $block = $observer->getBlock();

    $route = 'mventory_tm/catalog_product/massCategoryMatch';

    $label = Mage::helper('mventory_tm')->__('Match product category');
    $url = $block->getUrl($route, array('_current' => true));

    $block
      ->getMassactionBlock()
      ->addItem('categorymatch', compact('label', 'url'));
  }

  public function addTabToProduct ($observer) {
    $block =  $observer->getEvent()->getBlock();

    if (!$block instanceof Mage_Adminhtml_Block_Catalog_Product_Edit_Tabs)
      return;

    if (!($block->getProduct()->getAttributeSetId()
          || $block->getRequest()->getParam('set', null)))
      return;

    $label = Mage::helper('mventory_tm')->__('mVentory');
    $content = $block
                 ->getLayout()
                 ->createBlock('mventory_tm/catalog_product_edit_tab_tm')
                 ->toHtml();

    $block->addTab('tm', compact('label', 'content'));
  }

  public function addSiteSwitcher ($observer) {
    $layout = $observer->getEvent()->getLayout();

    $storeId = Mage::app()->getStore()->getId();
    $code = 'site_version_' . $storeId;

    $session = Mage::getSingleton('core/session');

    $cookieValue = Mage::getModel('core/cookie')->get($code);
    $sessionValue = $session->getData($code);

    $identifier = 'desktop_footer_links_';

    //Check current site version
    if ($cookieValue == 'mobile'
        || ($cookieValue === false && $sessionValue == 'mobile')) {
      $session->unsetData($code);

      $identifier = 'mobile_footer_links_';
    }

    $identifier .= $storeId;
    //Append cms block to the footer
    $block = $layout
               ->createBlock('cms/block')
               ->setBlockId($identifier);

    //Check if footer block exists. It doesn't exist in AJAX requests
    if ($footer = $layout->getBlock('footer'))
      $footer->append($block);
  }

  public function uploadImageToCdn ($observer) {
    $product = $observer->getEvent()->getProduct();

    //There's nothing to process because we're using images
    //from original product in duplicate
    if ($product->getIsDuplicate())
      return;

    $images = $observer->getEvent()->getImages();

    $helper = Mage::helper('mventory_tm/product');

    $website = $helper->getWebsite($product);

    //Get settings for S3
    $accessKey = $helper->getConfig(self::XML_PATH_CDN_ACCESS_KEY, $website);
    $secretKey = $helper->getConfig(self::XML_PATH_CDN_SECRET_KEY, $website);
    $bucket = $helper->getConfig(self::XML_PATH_CDN_BUCKET, $website);
    $prefix = $helper->getConfig(self::XML_PATH_CDN_PREFIX, $website);
    $dimensions = $helper->getConfig(self::XML_PATH_CDN_DIMENSIONS, $website);

    //Return if S3 settings are empty
    if (!($accessKey && $secretKey && $bucket && $prefix))
      return;

    //Build prefix for all files on S3
    $cdnPrefix = $bucket . '/' . $prefix . '/';

    //Parse dimension. Split string to pairs of width and height
    $dimensions = str_replace(', ', ',', $dimensions);
    $dimensions = explode(',', $dimensions);

    //Prepare meta data for uploading. All uploaded images are public
    $meta = array(Zend_Service_Amazon_S3::S3_ACL_HEADER
                    => Zend_Service_Amazon_S3::S3_ACL_PUBLIC_READ);

    $config = Mage::getSingleton('catalog/product_media_config');

    $s3 = new Zend_Service_Amazon_S3($accessKey, $secretKey);

    foreach ($images['images'] as &$image) {
      //Process new images only
      if (isset($image['value_id']))
        continue;

      //Get name of the image and create its key on S3
      $fileName = $image['file'];
      $cdnPath = $cdnPrefix . 'full' . $fileName;

      //Full path to uploaded image
      $file = $config->getMediaPath($fileName);

      //Check if object with the key exists
      if ($s3->isObjectAvailable($cdnPath)) {
        $position = strrpos($fileName, '.');

        //Split file name and extension
        $name = substr($fileName, 0, $position);
        $ext = substr($fileName, $position);

        //Search key
        $_key = $prefix .'/full' . $name . '_';

        //Get all objects which is started with the search key
        $keys = $s3->getObjectsByBucket($bucket, array('prefix' => $_key));

        $index = 1;

        //If there're objects which names begin with the search key then...
        if (count($keys)) {
          $extLength = strlen($ext);

          $_keys = array();

          //... store object names without extension as indeces of the array
          //for fast searching
          foreach ($keys as $key)
            $_keys[substr($key, 0, -$extLength)] = true;

          //Find next unused object name
          while(isset($_keys[$_key . $index]))
            ++$index;

          unset($_keys);
        }

        //Build new name and path with selected index
        $fileName = $name . '_' . $index . $ext;
        $cdnPath = $cdnPrefix . 'full' . $fileName;

        //Get new name for uploaded file
        $_file = $config->getMediaPath($fileName);

        //Rename file uploaded to Magento
        rename($file, $_file);

        //Update values of media attribute in the product after renaming
        //uploaded image if the image was marked as 'image', 'small_image'
        //or 'thumbnail' in the product
        foreach ($product->getMediaAttributes() as $mediaAttribute) {
          $code = $mediaAttribute->getAttributeCode();

          if ($product->getData($code) == $image['file'])
            $product->setData($code, $fileName);
        }

        //Save its new name in Magento
        $image['file'] = $fileName;
        $file = $_file;

        unset($_file);
      }

      //Upload original image
      if (!$s3->putFile($file, $cdnPath, $meta)) {
        $msg = 'Can\'t upload original image (' . $file . ') to S3 with '
               . $cdnPath . ' key';

        throw new Mage_Core_Exception($msg);
      }

      //Go to next newly uploaded image if image dimensions for resizing
      //were not set
      if (!count($dimensions))
        continue;

      //For every dimension...
      foreach ($dimensions as $dimension) {
        //... resize original image and get path to resized image
        $newFile = Mage::getModel('catalog/product_image')
                     ->setSize($dimension)
                     ->setBaseFile($fileName)
                     ->setKeepFrame(false)
                     ->setConstrainOnly(true)
                     ->resize()
                     ->saveFile()
                     ->getNewFile();

        //Build S3 path for the resized image
        $newCdnPath = $cdnPrefix . $dimension . $fileName;

        //Upload resized images
        if (!$s3->putFile($newFile, $newCdnPath, $meta)) {
          $msg = 'Can\'t upload resized (' . $dimension . ') image (' . $file
                 . ') to S3 with ' . $cdnPath . ' key';

          throw new Mage_Core_Exception($msg);
        }
      }
    }
  }

  public function syncImages ($observer) {
    $product = $observer->getProduct();

    $attrs = $product->getAttributes();

    if (!isset($attrs['media_gallery']))
      return;

    $galleryAttribute = $attrs['media_gallery'];
    $galleryAttributeId = $galleryAttribute->getAttributeId();

    unset($attrs);

    $helper = Mage::helper('mventory_tm/product_configurable');

    $productId = $product->getId();

    $isConfigurable
      = $product->getTypeId()
          == Mage_Catalog_Model_Product_Type_Configurable::TYPE_CODE;

    $configurableId = $isConfigurable
                        ? $productId
                          : $helper->getIdByChild($product);

    if (!$configurableId)
      return;

    $products = $helper->getChildrenIds($configurableId);
    $products[$configurableId] = $configurableId;

    //Make current product first, because we need to collect removed images
    //on first iteration before processing images from other products
    unset($products[$productId]);
    $products = array($productId => $productId) + $products;

    $storeId = $product->getStoreId();

    $mediaAttributes = $product->getMediaAttributes();

    $noMediaValues = true;

    foreach ($mediaAttributes as $code => $attr) {
      if (($data = $product->getData($code)) != 'no_selection')
        $noMediaValues = false;

      $mediaValues[$attr->getAttributeId()] = $data;
    }

    if ($noMediaValues
        && $data = $product
                     ->getData('mventory_assigned_to_configurable_after')) {

      $product = $data['configurable'];

      foreach ($mediaAttributes as $code => $attr)
        $mediaValues[$attr->getAttributeId()] = $product->getData($code);
    }

    unset($product, $mediaAttributes, $data, $noMediaValues);

    $object = new Varien_Object();
    $object->setAttribute($galleryAttribute);

    $product = new Varien_Object();
    $product->setStoreId($storeId);

    $resourse
      = Mage::getResourceSingleton('catalog/product_attribute_backend_media');

    $_images = $observer->getImages();

    foreach ($products as $id => $images) {

      //Don't load gallery for current product
      $gallery = ($id == $productId)
                   ? $_images['images']
                     : $resourse->loadGallery($product->setId($id), $object);

      $products[$id] = array();

      if ($gallery) foreach ($gallery as $image) {
        $file = $image['file'];

        if (isset($image['removed']) && $image['removed']) {
          $imagesToDelete[$file] = true;

          continue;
        }

        if (isset($imagesToDelete[$file])) {
          $idsToDelete[] = $image['value_id'];

          continue;
        }

        $products[$id][$file] = $image;

        if (!isset($allImages[$file]))
          $allImages[$file] = $image;
      }
    }

    unset($imagesToDelete, $_images);

    if (isset($idsToDelete)) {
      foreach ($idsToDelete as $id)
        $resourse->deleteGalleryValueInStore($id, $storeId);

      $resourse->deleteGallery($idsToDelete);
    }

    unset($idsToDelete);

    if (isset($allImages)) foreach ($products as $id => $images) {
      foreach ($allImages as $file => $image) {
        if (!isset($images[$file]))
          $resourse->insertGalleryValueInStore(
            array(
              'value_id' => $resourse->insertGallery(
                array(
                  'entity_id' => $id,
                  'attribute_id' => $galleryAttributeId,
                  'value' => $file
                )
              ),
              'label'  => $image['label'],
              'position' => (int) $image['position'],
              'disabled' => (int) $image['disabled'],
              'store_id' => $storeId
            )
          );
      }
    }

    Mage::getResourceSingleton('catalog/product_action')
      ->updateAttributes(array_keys($products), $mediaValues, $storeId);
  }

  public function resetExcludeFlag ($observer) {
    $images = $observer->getImages();

    foreach ($images['images'] as &$image)
      $image['disabled'] = 0;
  }

  public function restoreNewAccountInConfig ($observer) {
    $configData = $observer->getObject();

    if ($configData->getSection() != 'mventory_tm')
      return;

    $groups = $configData->getGroups();

    $accounts = array();

    foreach ($groups as $id => $group)
      if (strpos($id, 'account_', 0) === 0)
        if ($group['fields']['name']['value']
            && $group['fields']['key']['value']
            && $group['fields']['secret']['value'])
          $accounts[$id] = $group['fields']['name']['value'];
        else
          unset($groups[$id]);

    $configData->setGroups($groups);

    Mage::register('tm_config_accounts', $accounts);
  }

  public function addAccountsToConfig ($observer) {
    if (Mage::app()->getRequest()->getParam('section') != 'mventory_tm')
      return;

    $settings = $observer
                  ->getConfig()
                  ->getNode('sections')
                  ->mventory_tm
                  ->groups
                  ->settings;

    $template = $settings
                  ->account_template
                  ->asArray();

    if (!$accounts = Mage::registry('tm_config_accounts')) {
      $groups = Mage::getSingleton('adminhtml/config_data')
                  ->getConfigDataValue('mventory_tm');

      $accounts = array();

      foreach ($groups->children() as $id => $account)
        if (strpos($id, 'account_', 0) === 0)
          $accounts[$id] = (string) $account->name;

      unset($id);
      unset($account);

      $accounts['account_' . str_replace('.', '_', microtime(true))]
        = '+ Add account';
    }

    $noAccounts = count($accounts) == 1;

    $position = 0;

    foreach ($accounts as $id => $account) {
      $group = $settings
                 ->fields
                 ->addChild($id);

      $group->addAttribute('type', 'group');
      $group->addChild('frontend_model',
                       'mventory_tm/system_config_form_fieldset_account');
      $group->addChild('label', $account);
      $group->addChild('show_in_default', 0);
      $group->addChild('show_in_website', 1);
      $group->addChild('show_in_store', 0);
      $group->addChild('expanded', (int) $noAccounts);
      $group->addChild('sort_order', $position++);

      $fields = $group->addChild('fields');

      foreach ($template as $name => $field) {
        $node = $fields->addChild($name);

        if (isset($field['@'])) {
          foreach ($field['@'] as $key => $value)
            $node->addAttribute($key, $value);

          unset($field['@']);

          unset($key);
          unset($value);
        }

        foreach ($field as $key => $value)
          $node->addChild($key, $value);

        unset($key);
        unset($value);
      }
    }
  }

  public function checkAccessToWebsite ($observer) {
    $apiUser = $observer->getModel();

    $helper = Mage::helper('mventory_tm');

    $website = $helper->getApiUserWebsite($apiUser);

    if (!$website) {
      $apiUser->setId(null);

      return;
    }

    //Allow access to all websites for customers assigned to Admin website
    if (($websiteId = $website->getId()) == 0)
      return;

    if ($helper->getCurrentWebsite()->getId() != $websiteId)
      $apiUser->setId(null);
  }

  public function hideProductsWithoutSmallImages ($observer) {
    $observer
      ->getCollection()
      ->addAttributeToFilter('small_image',
                             array('nin' => array('no_selection', '')));
  }

  /**
   * Unset is_duplicate flag to prevent coping image files
   * in Mage_Catalog_Model_Product_Attribute_Backend_Media::beforeSave() method
   *
   * @param Varien_Event_Observer $observer Event observer
   */
  public function unsetDuplicateFlagInProduct ($observer) {
    $observer
      ->getNewProduct()
      ->setIsDuplicate(false)
      ->setOrigIsDuplicate(true);
  }

  /**
   * Restore is_duplicate flag to not affect other code, such as in
   * Mage_Catalog_Model_Product_Attribute_Backend_Media::afterSave() method
   *
   * @param Varien_Event_Observer $observer Event observer
   */
  public function restoreDuplicateFlagInProduct ($observer) {
    $product = $observer->getProduct();

    if ($product->getOrigIsDuplicate())
      $product->setIsDuplicate(true);
  }

  public function addMatchingRulesBlock ($observer) {
    Mage::app()
      ->getFrontController()
      ->getAction()
      ->getLayout()
      ->getBlock('content')
      ->sortChildren(true);
  }

  public function matchCategory ($observer) {
    $product = $observer
                 ->getEvent()
                 ->getProduct();

    if ($product->getTmCategoryMatched())
      return;

    $result = Mage::getModel('mventory_tm/rules')->matchCategory($product);

    if ($result)
      $product->setCategoryIds((string) $result);
  }

  public function cancelOrders ($observer) {
    $helper = Mage::helper('mventory_tm');

    $_expr = 'DATE_SUB(\''
             . now()
             . '\', INTERVAL ';

    $websites = Mage::app()->getWebsites();

    foreach ($websites as $website) {
      $states = $helper->getConfig(self::XML_PATH_CANCEL_STATES, $website);
      $period = $helper->getConfig(self::XML_PATH_CANCEL_PERIOD, $website);

      if (!($states && $period))
        continue;

      $states = explode(',', $states);

      $storeId = $website
                   ->getDefaultStore()
                   ->getId();

      $expr = new Zend_Db_Expr($_expr . (int) $period . ' DAY)');

      $orders = Mage::getResourceModel('sales/order_collection')
                  ->addFieldToFilter('status', array('in' => $states))
                  ->addFieldToFilter('store_id', $storeId)
                  ->addFieldToFilter('created_at', array('lt' => $expr));

      foreach($orders->getItems() as $order) {
        $_order = Mage::getModel('sales/order')
                    ->load($order->getId());

        if (!($_order->getId() && $_order->canCancel()))
          continue;

        try {
          $_order
            ->cancel()
            ->save();

          Mage::log('Cancel order: ' . $_order->getIncrementId());
        } catch (Exception $e) {
          Mage::logException($e);
        }
      }
    }
  }

  public function subtractInventory ($observer) {
    $invoice = $observer->getData('invoice');

    foreach ($invoice->getAllItems() as $item)
      $item->setData('total_qty', $item->getData('qty'));

    $args = array('quote' => $invoice);

    $observer = new Varien_Event_Observer();
    $observer
      ->setData('event', new Varien_Event($args))
      ->addData($args);

    Mage::getModel('cataloginventory/observer')
      ->subtractQuoteInventory($observer)
      ->reindexQuoteInventory($observer);

    return $this;
  }

  public function setInventoryProcessed ($observer) {
    $observer
      ->getQuote()
      ->setInventoryProcessed(true);

    return $this;
  }

  public function setListOnTm ($observer) {
    $product = $observer->getProduct();

    if ($product->getId())
      return;

    $helper = Mage::helper('mventory_tm/product');

    $website = $helper->getWebsite($product);

    $relist
      = (bool) $helper->getConfig(self::XML_PATH_ENABLE_LISTING, $website);

    $product->setData('tm_relist', $relist);
  }

  public function updateDuplicate ($observer) {
    $data = $observer
              ->getCurrentProduct()
              ->getData('mventory_update_duplicate');

    if ($data)
      $observer
        ->getNewProduct()
        ->addData($data);
  }

  public function saveAttributesHash ($observer) {
    $product = $observer->getProduct();

    if ($product->getTypeId()
          == Mage_Catalog_Model_Product_Type_Configurable::TYPE_CODE)
      return $this;

    $helper = Mage::helper('mventory_tm');

    $configurable = $helper->getConfigurableAttribute($product->getAttributeSetId());

    if (!($configurable && $configurable->getId()))
      return;

    $storeId = $helper->getCurrentStoreId();

    foreach ($product->getAttributes() as $_attribute) {
      $code = $_attribute->getAttributeCode();

      if (substr($code, -1) == '_'
          && $_attribute
               ->unsetData('store_label')
               ->getStoreLabel($storeId) != '~')

        $data[$code] = is_array($value = $product->getData($code))
                         ? implode(',', $value)
                           : (string) $value;
    }

    if (!isset($data))
      return;

    unset(
      $data[$configurable->getAttributeCode()],
      $data['product_barcode_']
    );

    if ($name = str_replace(' ', '', strtolower($product->getName())))
      $data['name'] = $name;

    if ($data)
      $product->setData(
        'mv_attributes_hash',
        md5(serialize($data))
      );
  }

  public function unassignFromConfigurable ($observer) {
    $product = $observer->getProduct();

    if ($product->getData('mventory_assigned_to_configurable_after') === false
        || !($id = $product->getId())
        || $product->getTypeId()
             == Mage_Catalog_Model_Product_Type_Configurable::TYPE_CODE)
      return;

    if (!$oldHash = $product->getOrigData('mv_attributes_hash'))
      return;

    if (($hash = $product->getData('mv_attributes_hash')) == $oldHash)
      return;

    $helper = Mage::helper('mventory_tm/product_configurable');

    if (!$configurableId = $helper->getIdByChild($product))
      return;

    $configurable = Mage::getModel('catalog/product')->load($configurableId);

    if (!$configurable->getId())
      return;

    $childrenIds = $helper->getSiblingsIds($product);

    if ($childrenIds) {
      $attribute = $helper
                   ->getConfigurableAttribute($product->getAttributeSetId());

      $products = Mage::getResourceModel('catalog/product_collection')
                    ->addAttributeToSelect(array(
                      $attribute->getAttributeCode(),
                      'price'
                    ))
                  ->addIdFilter($childrenIds);

      $helper
        ->removeOption($configurable, $attribute, $product)
        ->unassignProduct($configurable, $product)
        ->recalculatePrices($configurable, $attribute, $products);

      $configurable->save();
    } else
      $configurable->delete();

    $product->setVisibility(4);
  }

  public function assignToConfigurableBefore ($observer) {
    $product = $observer->getProduct();

    if ($product->getData('mventory_assigned_to_configurable_after') === false
        || $product->getTypeId()
             == Mage_Catalog_Model_Product_Type_Configurable::TYPE_CODE)
      return;

    if (!$hash = $product->getData('mv_attributes_hash'))
      return;

    $helper = Mage::helper('mventory_tm/product_configurable');

    if (($id = $product->getId()) && $helper->getIdByChild($product))
      return;

    $attribute = $helper
                   ->getConfigurableAttribute($product->getAttributeSetId());

    $store = $helper
               ->getWebsite($product)
               ->getDefaultStore();

    $products = Mage::getResourceModel('catalog/product_collection')
                  ->addAttributeToSelect('*')
                  ->addFieldToFilter('mv_attributes_hash', $hash)
                  ->addStoreFilter($store);

    if ($id)
      $products->addIdFilter($id, true);

    if (!count($products))
      return;

    foreach ($products as $_product)
      if ($_product->getTypeId()
            == Mage_Catalog_Model_Product_Type_Configurable::TYPE_CODE) {

        $configurable = $_product;

        $products->removeItemByKey($configurable->getId());

        break;
      }

    unset($_product);

    //Create configurable by duplicating similar product.
    //We use similar one because we can't duplicate non-existing product.
    if (!isset($configurable))
      $configurable = $helper->create(
                        $products->getFirstItem(),
                        array($attribute->getAttributeCode() => null)
                      );

    if (!$configurable)
      return;

    $product->addData(array(
      'mventory_assigned_to_configurable_after' => array(
        'configurable' => $configurable,
        'attribute' => $attribute,
        'products' => $products
      ),
      'visibility' => 1,
      'description' => $helper->updateDescription($configurable, $product)
    ));
  }

  public function assignToConfigurableAfter ($observer) {
    $product = $observer->getProduct();

    if (!$data = $product->getData('mventory_assigned_to_configurable_after'))
      return $this;

    $configurable = $data['configurable'];
    $attribute = $data['attribute'];
    $products = $data['products']->addItem($product);

    $helper = Mage::helper('mventory_tm/product_configurable');

    $helper
      ->addAttribute($configurable, $attribute, $products)
      ->recalculatePrices($configurable, $attribute, $products)
      ->assignProducts($configurable, $products);

    $updateAll = false;

    if ($configurable->getData('mventory_update_description')) {
      $helper->shareDescription(
        $configurable,
        $products,
        $product->getDescription()
      );

      $updateAll = true;
    }

    $stockItem = Mage::getModel('cataloginventory/stock_item')
                   ->loadByProduct($configurable);

    $configurable
      ->setStockItem($stockItem)
      ->save();

    $products->removeItemByKey($product->getId());

    //Don't sync images when the product was created by duplicating
    //original one. It already has all images.
    if (!$product->getIsDuplicate())
      $this->syncImages($observer);

    foreach ($products as $product) {
      //Set this field to disable updatePricesInConfigurable()
      //and updateDescriptionInConfigurable() methods.
      //Set false value to disable this method.
      $product->setData('mventory_assigned_to_configurable_after', false);

      if ($product->getVisibility() != 1) {
        $product
          ->setVisibility(1)
          ->save();

        continue;
      }

      if ($updateAll)
        $product->save();
    }
  }

  public function updatePricesInConfigurable ($observer) {
    $product = $observer->getProduct();

    //We don't need to update prices because it's already been done in
    //assignToConfigurableAfter() method or product is new
    if ($product->hasData('mventory_assigned_to_configurable_after')
        || $product->getTypeId()
             == Mage_Catalog_Model_Product_Type_Configurable::TYPE_CODE)
      return;

    $origPrice = $product->getOrigData('price');

    //Ignore product if it's newly created
    if (($origPrice = $product->getOrigData('price')) === null)
      return;

    $origPrice = (float) $origPrice;
    $price = (float) $product->getData('price');

    if ($price == $origPrice)
      return;

    $helper = Mage::helper('mventory_tm/product_configurable');

    if (!$childrenIds = $helper->getSiblingsIds($product))
      return;

    $configurable = Mage::getModel('catalog/product')
                      ->load($helper->getIdByChild($product));

    if (!$configurable->getId())
      return;

    $attribute = $helper->getConfigurableAttribute(
                   $product->getAttributeSetId()
                 );

    $children = Mage::getResourceModel('catalog/product_collection')
                  ->addAttributeToSelect(array(
                      'price',
                      $attribute->getAttributeCode()
                    ))
                  ->addIdFilter($childrenIds);

    Mage::getResourceModel('cataloginventory/stock')
      ->setInStockFilterToCollection($children);

    $helper->recalculatePrices(
      $configurable,
      $attribute,
      $children->addItem($product)
    );

    $configurable->save();
  }

  public function updatePricesInConfigurableOnStockChange ($observer) {
    $item = $observer->getItem();

    if (!$item->getManageStock())
      return;

    $origStatus = $item->getOrigData('is_in_stock');
    $status = $item->getData('is_in_stock');

    if ($origStatus !== null && $origStatus == $status)
      return;

    $product = $item->getProduct();

    if (!$product)
      $product = Mage::getModel('catalog/product')->load($item->getProductId());

    if (!$product->getId())
      return;

    $helper = Mage::helper('mventory_tm/product_configurable');

    if (!$childrenIds = $helper->getSiblingsIds($product))
      return;

    $storeId = Mage::app()
                 ->getStore(true)
                 ->getId();

    if ($storeId != Mage_Core_Model_App::ADMIN_STORE_ID)
      Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);

    $configurable = Mage::getModel('catalog/product')
                      ->load($helper->getIdByChild($product));

    if ($storeId != Mage_Core_Model_App::ADMIN_STORE_ID)
      Mage::app()->setCurrentStore($storeId);

    if (!$configurable->getId())
      return;

    $attribute = $helper->getConfigurableAttribute(
                   $product->getAttributeSetId()
                 );

    $children = Mage::getResourceModel('catalog/product_collection')
                  ->addAttributeToSelect(array(
                      'price',
                      $attribute->getAttributeCode()
                    ))
                  ->addIdFilter($childrenIds);

    Mage::getResourceModel('cataloginventory/stock')
      ->setInStockFilterToCollection($children);

    if ($status)
      $children->addItem($product);

    $helper->recalculatePrices($configurable, $attribute, $children);

    $configurable->save();
  }

  public function updateDescriptionInConfigurable ($observer) {
    $product = $observer->getProduct();

    //We don't need to update prices because it's already been done in
    //assignToConfigurableAfter() method or product is new
    if ($product->hasData('mventory_assigned_to_configurable_after')
        || $product->getTypeId()
             == Mage_Catalog_Model_Product_Type_Configurable::TYPE_CODE)
      return;

    $origDescription = $product->getOrigData('description');
    $description = $product->getDescription();

    if ($origDescription == $description)
      return;

    $helper = Mage::helper('mventory_tm/product_configurable');

    if (!$childrenIds = $helper->getSiblingsIds($product))
      return;

    $configurable = Mage::getModel('catalog/product')
                      ->load($helper->getIdByChild($product));

    if (!$configurable->getId())
      return;

    $attribute = $helper
                   ->getConfigurableAttribute($product->getAttributeSetId());

    //Load all product attributes for correct saving
    $children = Mage::getResourceModel('catalog/product_collection')
                  ->addAttributeToSelect('*')
                  ->addIdFilter($childrenIds);

    $helper->shareDescription(
      $configurable,
      $children->addItem($product),
      $description
    );

    $children
      ->removeItemByKey($product->getId())
      ->setDataToAll('mventory_assigned_to_configurable_after', false)
      ->save();

    $configurable->save();
  }
}
