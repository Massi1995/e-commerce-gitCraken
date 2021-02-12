<?php

class importProducts
{
  private $_context;
  private $_idShop;
  private $defaultIdShop;
  private $_idShopGroup;
  private $_idLang;
  private $_format;
  private $_cover = 'no';
  private $_model;
  private $_delimiter;
  private $_parser;
  private $ids_images = array();
  private $_importFieldsBase;
  private $_importFieldsCategories;
  private $_importFieldsCombinations;
  private $_importFieldsDiscount;
  private $_importFieldImages;
  private $_importFieldsFeatures;
  private $_importFieldsCustomization;
  private $_importFieldsAttachments;
  private $_importFieldsAccessories;
  private $_importFieldsSuppliers;
  private $_importFieldsPackProducts;
  private $_importProducts = 0;
  private $_importedProducts = 0;
  private $_productsForImport = 0;
  private $_PHPExcelFactory;
  private $selected_countries = array();
  private $selected_states = array();
  private $_catProducts = array();
  private $errors = array();
  private $_limit = false;
  private $_preConfigs = false;
  private $_automatic = false;
  private $_importSettings = '';
  private $_baseConfig = false;
  private $_iteration = false;
  private $_delete_associated_warehouses = false;
  private $_productError = false;
  private $_importedCombinations = array();
  private $_importedCombinationsData = array();
  private $_insertValues = '';
  private $_uniqueFields = false;
  private $_calculate = false;

  public function __construct($fields, $id_shop, $idShopGroup, $limit, $automatic = false){
   	@ini_set('display_errors', 'off');
    ini_set("log_errors", 1);
	  @error_reporting(E_ALL | E_STRICT);

    $cookie = new Cookie('psAdmin', '', (int)Configuration::get('PS_COOKIE_LIFETIME_BO'));
    $employee = new Employee((int)$cookie->id_employee);
    Context::getContext()->employee = $employee;

    $this->_limit = (int)$limit;
    $this->_automatic = $automatic;
    if( $this->_automatic ){
      $this->_importSettings = Configuration::getGlobalValue('GOMAKOIL_AUTOMATIC_SETTINGS') . '_';
    }
    ini_set("error_log", _PS_MODULE_DIR_ . "simpleimportproduct/error/".$this->_importSettings."error.log");
    $this->_importedProducts = Configuration::get('GOMAKOIL_IMPORT_PRODUCTS_COUNT', null, $idShopGroup, $id_shop);
    $this->_importProducts = $limit;

    if (!class_exists('PHPExcel')) {
      include_once(_PS_MODULE_DIR_ . 'simpleimportproduct/libraries/PHPExcel_1.7.9/Classes/PHPExcel.php');
      include_once(_PS_MODULE_DIR_ . 'simpleimportproduct/libraries/PHPExcel_1.7.9/Classes/PHPExcel/IOFactory.php');
    }

    include_once(_PS_MODULE_DIR_ . 'simpleimportproduct/datamodel.php');
    require_once(dirname(__FILE__).'/simpleimportproduct.php');
    require_once(dirname(__FILE__).'/classes/calculateString.php');

    $this->_calculate = new calculateString();
    ini_set('memory_limit', '-1');
    ini_set('max_execution_time', "0");
    if( $this->_automatic ){
      $config_step_one = $fields['base_settings'];
    }
    else{
      $config_step_one = Tools::unserialize(Configuration::get('GOMAKOIL_CONFIG_IMPORT_PRODUCTS', null, $idShopGroup, $id_shop));
    }
    $this->_simpleimportproduct = new Simpleimportproduct();
    $this->_model = new importProductData();
    $this->_context = Context::getContext();
    $this->_idShop = $id_shop;
    $this->defaultIdShop = $id_shop;
    $this->_idShopGroup = $idShopGroup;
    $this->_baseConfig = $config_step_one;

    if( $this->_baseConfig['iteration'] ){
      $this->_iteration = (int)$this->_baseConfig['iteration'];
    }
    else{
      $this->_iteration = 100;
    }
    $this->_idLang = $config_step_one['id_lang'];
    $this->_format = $config_step_one['format_file'];
    $this->_delimiter = ($config_step_one['delimiter_val'] == 'tab') ? "\t" : $config_step_one['delimiter_val'];

    $this->_parser = $config_step_one['parser_import_val'];

    $this->_category_linking = array();
    if (!empty($fields['category_linking_active']) && $fields['category_linking_active'] == 1 && !empty($fields['category_linking'])) {
      $this->_category_linking = $fields['category_linking'];
    }

    $this->_importFieldsBase = $fields['base_field'];
    $this->_importFieldsCategories = $fields['field_category'];
    $this->_importFromCategories = $fields['import_from_categories'];
    $this->_importFromSuppliers = $fields['import_from_suppliers'];
    $this->_importFromBrands = $fields['import_from_brands'];
    $this->_importFieldsCombinations = $fields['field_combinations'];
    $this->_importFieldsDiscount = $fields['field_discount'];
    $this->_importFieldImages = $fields['field_images'];
    $this->_importFieldsFeatures = $fields['field_featured'];
    $this->_importFieldsCustomization = $fields['field_customization'];
    $this->_importFieldsAttachments = $fields['field_attachments'];
    $this->_importFieldsAccessories = $fields['field_accessories'];
    $this->_importFieldsSuppliers = $fields['field_suppliers'];
    $this->_importFieldsPackProducts = $fields['field_pack_products'];
    $this->_priceConditions = $fields['price_settings'];
    $this->_quantityConditions = $fields['quantity_settings'];
    $this->_fieldConditions = $fields['field_settings'];

    $_GET['checkBoxShopAsso_category'] = array();//Must be set for preventing associating categories to all shops every time.
  }

  private function _cleanTmpImportFiles()
  {
    foreach (scandir(dirname(__FILE__).'/data/') as $d) {
      if ( strpos($d, 'tmp_import_') !== false ) {
        unlink(dirname(__FILE__).'/data/'.$d);
      }
    }
  }

  public function import()
  {
    $res = array();
    $this->_updateImportRunning();
    if( !$this->_limit ){
      Configuration::updateGlobalValue('GOMAKOIL_IMPORT_STATUS', Module::getInstanceByName('simpleimportproduct')->l('Preparing import'));
      Configuration::updateGlobalValue('GOMAKOIL_PRODUCTS_FOR_IMPORT', (int)0);
      Configuration::updateGlobalValue('GOMAKOIL_PRODUCTS_WITH_ERRORS', (int)0);
      Configuration::updateGlobalValue('GOMAKOIL_IMPORT_START_TIME', time());

      $this->_clearErrorFile();
      Module::getInstanceByName('simpleimportproduct')->truncateImageTable();
      Module::getInstanceByName('simpleimportproduct')->cleanImages();
      $this->_cleanTmpImportFiles();
      $this->_importedProducts = 0;
      Configuration::updateValue('GOMAKOIL_IMPORT_PRODUCTS_COUNT', (int)0, false, $this->_idShopGroup, $this->defaultIdShop);
      Configuration::updateValue('GOMAKOIL_IMPORT_START', time(), false, $this->_idShopGroup, $this->defaultIdShop);
      Configuration::updateValue('GOMAKOIL_IMPORT_IMAGES_ERROR_LOG', (int)0, false, $this->_idShopGroup, $this->defaultIdShop);

      if( $this->_format == 'csv' ){
        Configuration::updateValue('GOMAKOIL_IMPORT_FILE_ENCODING', '', false, $this->_idShopGroup, $this->defaultIdShop);
        if( $this->_automatic ){
          $encoding = mb_detect_encoding(Tools::file_get_contents(_PS_MODULE_DIR_."simpleimportproduct/data/".Tools::getValue('settings')."_import.csv"), array('UTF-8','ISO-8859-1','ASCII','GBK'), TRUE);
        }
        else{
          $encoding = mb_detect_encoding(Tools::file_get_contents(_PS_MODULE_DIR_."simpleimportproduct/data/import_products.csv"), array('UTF-8','ISO-8859-1','ASCII','GBK'), TRUE);
        }
        if( $encoding ){
          Configuration::updateValue('GOMAKOIL_IMPORT_FILE_ENCODING', $encoding, false, $this->_idShopGroup, $this->defaultIdShop);
        }
      }
    }

    if( !$this->_limit ){
      Configuration::updateGlobalValue('GOMAKOIL_CURRENTLY_IMPORTED', (int)0);
      $this->_copyFile();
      $this->_createFilesParts();
      Configuration::updateGlobalValue('GOMAKOIL_PRODUCTS_FOR_IMPORT', (int)$this->_getProductsForImportCount());
      Configuration::updateGlobalValue('GOMAKOIL_IMPORT_STATUS', Module::getInstanceByName('simpleimportproduct')->l('Importing'));
    }
    else{
      $this->_productsForImport = Configuration::getGlobalValue('GOMAKOIL_PRODUCTS_FOR_IMPORT');
    }
    $this->runImport();
    $res['limit'] = 0;

    if( !$this->_checkProductsForImport() ){
      $this->_processFileProducts();
      $this->_processFileStoreProducts();
      $this->_disableZeroProducts();
      Category::regenerateEntireNtree();
      $this->_cleanTmpImportFiles();
      $this->_updateImportRunning(true );
      $this->_removeFtpFile();
      //Configuration::updateGlobalValue('GOMAKOIL_IMPORT_STATUS', Module::getInstanceByName('simpleimportproduct')->l('Completed'));
      if( $this->_automatic ){
//        Module::getInstanceByName('simpleimportproduct')->cleanImages();
      }

      if( $this->_baseConfig['products_range'] == 'range' ){
        if( (int)$this->_baseConfig['to_range'] > $this->_productsForImport ){
          $this->_productsForImport = (int)$this->_productsForImport - (int)$this->_baseConfig['from_range'];
          $this->_productsForImport++;
        }
        else{
          $this->_productsForImport = (int)$this->_baseConfig['to_range'] - (int)$this->_baseConfig['from_range'];
          $this->_productsForImport++;
        }
      }
      if( $this->_importedProducts < $this->_productsForImport ){
        $res['success'] = array(
          'message'     =>  sprintf(Module::getInstanceByName('simpleimportproduct')->l('Successfully imported %1s products from: %2s'), $this->_importedProducts, $this->_productsForImport),
          'error_logs'  => _PS_BASE_URL_.__PS_BASE_URI__.'modules/simpleimportproduct/error/'.$this->_importSettings.'error_logs.csv',
        );

        return $res;
      }

      $res['success'] = array(
        'message'     =>  sprintf(Module::getInstanceByName('simpleimportproduct')->l('Successfully imported %s products!'), $this->_importedProducts),
        'error_logs'  => false
      );

      if( Configuration::get('GOMAKOIL_IMPORT_IMAGES_ERROR_LOG', null, $this->_idShopGroup, $this->defaultIdShop) ){
        $res['success']['error_logs'] = _PS_BASE_URL_.__PS_BASE_URI__.'modules/simpleimportproduct/error/'.$this->_importSettings.'error_logs.csv';
      }

      if( _PS_CACHE_ENABLED_ && _PS_CACHING_SYSTEM_ == 'CacheMemcached' ){
// 	    Cache::getInstance()->flush();
      }

    }
    else{
      $res['limit'] = ($this->_limit+$this->_iteration);
    }

    return $res;
  }

  private function _disableZeroProducts()
  {
    if( $this->_baseConfig['disable_zero_products'] ){
      $sql = '
      SELECT sp.id_product
      FROM '._DB_PREFIX_.'simpleimport_products sp
    ';

      $products = Db::getInstance()->executes($sql);
      foreach( $products as $product ){
        $this->_updateImportRunning();
        $quantity = StockAvailable::getQuantityAvailableByProduct($product['id_product']);
        if( !$quantity ){
          $product = new Product($product['id_product'], false);
          if( Shop::getContext() == Shop::CONTEXT_ALL || $product->isAssociatedToShop(Context::getContext()->shop->id) ){
            $product->active = false;
            if( $this->_idShop == null && $product->id ){
              $product->setFieldsToUpdate($product->getFieldsShop());
            }
            $objectError = false;
            if( ( $error = $product->validateFields(false, true) ) !== true ){
              $this->_createErrorsFile($error,'Product ID - ' . $product->id);
              $objectError = true;
            }

            if( ( $error = $product->validateFieldsLang(false, true) ) !== true ){
              $this->_createErrorsFile($error,'Product ID - ' . $product->id);
              $objectError = true;
            }
            if( !$objectError ){
              $product->update();
            }
          }
        }
      }
    }
  }

  private function _processFileStoreProduct( $product )
  {
    if( $this->_baseConfig['file_store_products'] == 'enable' ){
      $product->active = true;
    }
    if( $this->_baseConfig['file_store_products'] == 'disable' ){
      $product->active = false;
    }
  }

  private function _processFileStoreProducts()
  {
    if( $this->_baseConfig['file_store_products'] == 'zero_quantity' ){
      Configuration::updateGlobalValue('GOMAKOIL_IMPORT_STATUS', Module::getInstanceByName('simpleimportproduct')->l('Set zero quantity for products that are in store and in file'));

      $sql = '
      SELECT sp.id_product
      FROM '._DB_PREFIX_.'simpleimport_products sp
    ';

      $products = Db::getInstance()->executes($sql);
      foreach( $products as $product ){
        $this->_updateImportRunning();
        if( $this->_baseConfig['file_store_products'] == 'zero_quantity' ){
          if( !StockAvailable::getQuantityAvailableByProduct($product['id_product']) ){
            continue;
          }

          $attributesAll = Product::getProductAttributesIds($product['id_product'], true);
          foreach ($attributesAll as $attribute){
            StockAvailable::setQuantity($product['id_product'], $attribute['id_product_attribute'],  (int)0);
          }
          StockAvailable::setQuantity($product['id_product'], null, (int)0);
        }
      }
    }
  }

  private function _processFileProducts()
  {
    if( $this->_baseConfig['file_products'] == 'ignore' ){
      return false;
    }
    if( $this->_baseConfig['file_products'] == 'disable' ){
      Configuration::updateGlobalValue('GOMAKOIL_IMPORT_STATUS', Module::getInstanceByName('simpleimportproduct')->l('Disabling products that are in store but not in file'));
    }
    if( $this->_baseConfig['file_products'] == 'zero_quantity' ){
      Configuration::updateGlobalValue('GOMAKOIL_IMPORT_STATUS', Module::getInstanceByName('simpleimportproduct')->l('Set zero quantity for products that are in store but not in file'));
    }

    $sql = '
      SELECT p.id_product
      FROM '._DB_PREFIX_.'product p
      LEFT JOIN '._DB_PREFIX_.'simpleimport_products sp
      ON sp.id_product = p.id_product
      WHERE sp.id_product IS NULL
    ';

    $products = Db::getInstance()->executes($sql);
    foreach( $products as $product ){
      $this->_updateImportRunning();
      if( !$this->_checkImportProductFrom( array(), $product['id_product'] ) ){
        continue;
      }
        if( $this->_baseConfig['file_products'] == 'disable' ){
          $product = new Product($product['id_product'], false);
          if( Shop::getContext() == Shop::CONTEXT_ALL || $product->isAssociatedToShop(Context::getContext()->shop->id) ){
            if( !$product->active ){
              continue;
            }
            $product->active = false;
            if( $this->_idShop == null && $product->id ){
              $product->setFieldsToUpdate($product->getFieldsShop());
            }
            $objectError = false;
            if( ( $error = $product->validateFields(false, true) ) !== true ){
              $this->_createErrorsFile($error,'Product ID - ' . $product->id);
              $objectError = true;
            }

            if( ( $error = $product->validateFieldsLang(false, true) ) !== true ){
              $this->_createErrorsFile($error,'Product ID - ' . $product->id);
              $objectError = true;
            }
            if( !$objectError ){
              $product->update();
            }
          }
        }
        if( $this->_baseConfig['file_products'] == 'zero_quantity' ){
          if( !StockAvailable::getQuantityAvailableByProduct($product['id_product']) ){
            continue;
          }
          $attributesAll = Product::getProductAttributesIds($product['id_product'], true);

          foreach ($attributesAll as $attribute){
            StockAvailable::setQuantity($product['id_product'], $attribute['id_product_attribute'],  (int)0);
          }
          StockAvailable::setQuantity($product['id_product'], null, (int)0);
        }
    }
  }

  private function _clearErrorFile()
  {
    $write_fd = fopen(_PS_MODULE_DIR_ . 'simpleimportproduct/error/'.$this->_importSettings.'error_logs.csv', 'w');

    fwrite($write_fd, 'product_name,error'."\r\n");

    fclose($write_fd);

    $write_fd = fopen(_PS_MODULE_DIR_ . 'simpleimportproduct/error/'.$this->_importSettings.'image_logs.csv', 'w');

    fwrite($write_fd, 'error,image_url'."\r\n");

    fclose($write_fd);

    $write_fd = fopen(_PS_MODULE_DIR_ . 'simpleimportproduct/error/'.$this->_importSettings.'error.log', 'w');

    fwrite($write_fd, " ");

    fclose($write_fd);
  }

  private function _copyFile()
  {
    if($this->_format == 'xlsx'){
      if( $this->_automatic ){
        $settings = Configuration::getGlobalValue('GOMAKOIL_AUTOMATIC_SETTINGS');
        $this->_PHPExcelFactory = PHPExcel_IOFactory::load(_PS_MODULE_DIR_ . "simpleimportproduct/data/".$settings."_import.xlsx");
      }
      else{
        $this->_PHPExcelFactory = PHPExcel_IOFactory::load(_PS_MODULE_DIR_ . "simpleimportproduct/data/import_products.xlsx");
      }
    }
    elseif($this->_format == 'csv'){
      $reader = PHPExcel_IOFactory::createReader("CSV");
      $reader->setDelimiter($this->_delimiter);

      $encoding = Configuration::get('GOMAKOIL_IMPORT_FILE_ENCODING', null, $this->_idShopGroup, $this->defaultIdShop);
      if( $encoding ){
        $reader->setInputEncoding($encoding);
      }

      if( $this->_automatic ){
        $settings = Configuration::getGlobalValue('GOMAKOIL_AUTOMATIC_SETTINGS');
        $this->_PHPExcelFactory = $reader->load(_PS_MODULE_DIR_ . "simpleimportproduct/data/".$settings."_import.csv");
      }
      else{
        $this->_PHPExcelFactory = $reader->load(_PS_MODULE_DIR_ . "simpleimportproduct/data/import_products.csv");
      }
    }
  }

  public function searchImages()
  {

    $rows = $this->_getRowsNumber();
    foreach( $rows as $row ){
      $product = $this->_getDataForImport($row['row']);
      $this->_runImport($product, true);
    }

  }

  private function _removeFtpFile()
  {
    if($this->_baseConfig['remove_ftp_file'] ){
      if( $this->_baseConfig['feed_source'] == 'ftp' ){
        $port = (int)$this->_baseConfig['file_import_ftp_port'];
        if( !$port ){
          $port = 21;
        }
        $conn_id = ftp_connect($this->_baseConfig['file_import_ftp_server'], $port);
        if( !$conn_id ){
          $this->_createErrorsFile(Module::getInstanceByName('simpleimportproduct')->l('Can not connect to your FTP Server!'),Module::getInstanceByName('simpleimportproduct')->l('Remove File From FTP feature'));
          return false;
        }

        $login_result = @ftp_login($conn_id, $this->_baseConfig['file_import_ftp_user'], $this->_baseConfig['file_import_ftp_password']);
        if( $this->_baseConfig['passive_mode'] ){
          ftp_pasv($conn_id, true);
        }

        if( !$login_result ){
          $this->_createErrorsFile(Module::getInstanceByName('simpleimportproduct')->l('Can not Login to your FTP Server, please check access!'),Module::getInstanceByName('simpleimportproduct')->l('Remove File From FTP feature'));
          return false;
        }

        if (!ftp_delete($conn_id, $this->_baseConfig['file_import_ftp_file_path'])) {
          $this->_createErrorsFile(Module::getInstanceByName('simpleimportproduct')->l('Can not remove file from FTP, please check file path!'),Module::getInstanceByName('simpleimportproduct')->l('Remove File From FTP feature'));
          return false;
        }
      }

      if( $this->_baseConfig['feed_source'] == 'sftp' ){
        require_once _PS_MODULE_DIR_ . 'simpleimportproduct/libraries/phpseclib/Net/SFTP.php';
        $port = (int)$this->_baseConfig['file_import_ftp_port_ssl'];
        if( !$port ){
          $port = 22;
        }
        $sftp = new NET_SFTP($this->_baseConfig['file_import_ftp_server'], $port);
        if( !$sftp ){
          $this->_createErrorsFile(Module::getInstanceByName('simpleimportproduct')->l('Can not connect to your SFTP Server!'),Module::getInstanceByName('simpleimportproduct')->l('Remove File From SFTP feature'));
          return false;
        }

        $sftp_login = $sftp->login($this->_baseConfig['file_import_ftp_user'], $this->_baseConfig['file_import_ftp_password']);
        if( !$sftp_login ){
          $this->_createErrorsFile(Module::getInstanceByName('simpleimportproduct')->l('Can not Login to your SFTP Server, please check access!'),Module::getInstanceByName('simpleimportproduct')->l('Remove File From SFTP feature'));
          return false;
        }


        if (!$sftp->delete($this->_baseConfig['file_import_ftp_file_path'])) {
          $this->_createErrorsFile(Module::getInstanceByName('simpleimportproduct')->l('Can not remove file from SFTP, please check file path!'),Module::getInstanceByName('simpleimportproduct')->l('Remove File From SFTP feature'));
          return false;
        }
      }
    }
  }

  private function _getProductsForImportCount()
  {
    $sql = "
      SELECT COUNT( DISTINCT `row`) as count
      FROM " . _DB_PREFIX_ . "simpleimport_data
    ";

    $res = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);

    return $res[0]['count'];
  }

  private function _getRowsNumber( $limit = false )
  {
    if( $limit ){
      $limit = " LIMIT $limit ";
    }
    $sql = "
      SELECT `row`
      FROM " . _DB_PREFIX_ . "simpleimport_data
      GROUP BY `row`
      $limit
    ";

    $res = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
    return $res;
  }

  private function _createFilesParts()
  {
    foreach ($this->_PHPExcelFactory->getWorksheetIterator() as $worksheet) {
      $highestRow         = $worksheet->getHighestRow(); // e.g. 10
      $highestColumn      = $worksheet->getHighestColumn(); // e.g 'F'
      $highestColumnIndex = PHPExcel_Cell::columnIndexFromString($highestColumn);
      $fileFields = array();
      $continueLimit = 0;
      $currentIteration = 0;
      $processLimit = $this->_limit;

      $this->_productsForImport = ($highestRow-1);
      if( $this->_baseConfig['products_range'] == 'range' ){
        $from = (int)$this->_baseConfig['from_range'];
        $to = (int)$this->_baseConfig['to_range'];

        if( $from && $to ){
          if( $from > $processLimit ){
            $processLimit = ($from-1);
            if( !$this->_baseConfig['use_headers']){
              $continueLimit = $processLimit;
            }
            else{
              $continueLimit = $processLimit+1;
            }
          }
          if( $to < $this->_productsForImport ){
            if( !$this->_baseConfig['use_headers']){
              $this->_productsForImport = ($to-1);
            }
            else{
              $this->_productsForImport = $to;
            }
          }
        }
      }

      if( $this->_baseConfig['products_range'] == 'range' && !$this->_baseConfig['use_headers'] ){
        if( $this->_productsForImport < $processLimit ){
          return false;
        }
      }
      else{
        if( !$this->_baseConfig['use_headers'] ){
          if( ($this->_productsForImport+1) <= $processLimit ){
            return false;
          }
        }
        else{
          if( $this->_productsForImport <= $processLimit ){
            return false;
          }
        }
      }

      for ($row = 1; $row <= ($this->_productsForImport+1); ++ $row) {
        if( $row != 1 && ($continueLimit) >= $row ){
          continue;
        }
        $product = array();
        for ($col = 0; $col < $highestColumnIndex; ++ $col) {
          $cell = $worksheet->getCellByColumnAndRow($col, $row);
          if($cell->getOldCalculatedValue() !== null){
            $val = $cell->getOldCalculatedValue();
          }
          else{
            $val = $cell->getValue();
          }
          if( $this->_baseConfig['use_headers'] ){
            if($row == 1){
              if( !$val ){
                continue;
              }
              $fileFields[$col] = $val;
            }
            else{
              if(!isset($fileFields[$col])){
                continue;
              }
              $val = trim($val);
              $product[$fileFields[$col]] = $val;
            }
          }
          else{
            if( $row == 1 ){
              $valHeader = 'Column ' . ($col + 1);
              $fileFields[$col] = $valHeader;
            }
            if(!isset($fileFields[$col])){
              continue;
            }
            $val = trim($val);
            if( $row == 1 && $this->_baseConfig['products_range'] == 'range' && ( (int)$this->_baseConfig['from_range'] != 1 || $processLimit != 0) ){
              continue;
            }
            $product[$fileFields[$col]] = $val;
          }
        }
        if( ($row - $continueLimit) > ($currentIteration + $this->_iteration) ){
          $currentIteration = $currentIteration + $this->_iteration;
        }
        $this->_createPartFile($fileFields, $product, $currentIteration, $row);
      }
      $this->_addDataToDbQuery();
      return true;
    }
  }

  private function _createPartFile($fileFields, $product, $currentIteration, $row)
  {
	if( !$this->_uniqueFields ){
      $uniqueFields = array_count_values($fileFields);
      foreach( $uniqueFields as $key=>$value ){
        if( $value > 1 ){
          throw new Exception( Module::getInstanceByName('simpleimportproduct')->l('You have duplicated field names in your file: ') . '<strong>' . $key . '</strong><br>' . Module::getInstanceByName('simpleimportproduct')->l(' All field names must have unique value.') );
          $this->_uniqueFields = true;
          break;
        }
      }
    }

    if( $product && !$this->_checkEmptyProduct( $product ) ){
      $this->_updateImportRunning();
      $this->_addDataToDb($product, $row);
    }
  }

  private function _checkEmptyProduct( $product )
  {
    foreach( $product as $value ){
      if( $value ){
        return false;
      }
    }

    return true;
  }

  private function _checkProductsForImport()
  {
    $sql = "
      SELECT COUNT(*) as count
      FROM " . _DB_PREFIX_ . "simpleimport_data
    ";

    $res = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);

    return $res[0]['count'];
  }

  private function _getDataForImport( $row, $remove = false )
  {
    $product = array();

    $sql = "
      SELECT * 
      FROM " . _DB_PREFIX_ . "simpleimport_data
      WHERE `row` = $row
    ";

    $res = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);

    if( $res ){
      foreach( $res as $data ){
        $product[$data['field']] = $data['value'];
      }

      if( $remove ){
        Db::getInstance(_PS_USE_SQL_SLAVE_)->delete('simpleimport_data', '`row`='.$row);
      }

      return $product;
    }

    return false;
  }

  private function _processFieldConditions( $product )
  {
    foreach( $this->_fieldConditions as $fieldCondition )
    {
      if( ( isset( $product[$fieldCondition['field']] ) || $fieldCondition['condition'] == 'any' ) && $fieldCondition['new_field'] ){
        $value = $this->_processFieldCondition( $fieldCondition, isset($product[$fieldCondition['field']]) ? $product[$fieldCondition['field']] : '', $product );
        if( $value !== false ){
          $product[$fieldCondition['new_field']] = $value;
        }
      }
    }

    return $product;
  }

  private function _processFieldCondition($fieldCondition, $value, $product)
  {
    if( $this->_checkFieldCondition( $fieldCondition, $value ) ){
      return $this->_processFieldFormula( $fieldCondition['field_formula'], $product, $fieldCondition['formula_type'] );
    }

    return false;
  }

  private function _processFieldFormula( $formula, $product, $formulaType )
  {
    if( !$formula ){
      return $formula;
    }

    $formula = html_entity_decode($formula);

    preg_match_all( '/\[(.*?)\]/', $formula, $matches );
    if( $matches && $matches[0] && $matches[1] ){
      $replaceField = array();
      foreach( $matches[0] as $key => $match ){
        if( isset( $product[$matches[1][$key]] ) ){
          $replaceField['search'][] = $match;
          $replaceField['replace'][] = $product[$matches[1][$key]];
        }
      }

      if( $replaceField ){
        $formula = str_replace( $replaceField['search'], $replaceField['replace'], $formula );
      }
    }


    if( $formulaType == 'string' ){
      return $formula;
    }

    if( $formulaType == 'replace' ){
      $data = explode('=>', $formula);
      $text  = trim($data[0]);
      $search = $data[1] != ' ' ? trim($data[1]) : $data[1];
      $replace = $data[2] != ' ' ? trim($data[2]) : $data[2];

      $formula = str_replace($search, $replace, $text);

      return $formula;
    }

    if( $formulaType == 'round' ){
      return $this->_roundValue($formula);
    }

    try{
      $formula = str_replace(',','.', $formula);
      $value = $this->_calculate->execute($formula);
    }
    catch(Exception $e){
      $value = $formula;
    }

    return $value;
  }

  private function _roundValue($formula)
  {
    if( strpos($formula, 'ROUND_TO_99') !== false ){
      $value = str_replace('ROUND_TO_99', '', $formula);
      $value = $this->_preparePrice($value);
      $value = ceil($value) - 0.01;

      return $value;
    }

    if( strpos($formula, 'ROUND_TO_00') !== false ){
      $value = str_replace('ROUND_TO_00', '', $formula);
      $value = $this->_preparePrice($value);
      $value = ceil($value);

      return $value;
    }

    return $formula;
  }

  private function _checkFieldCondition( $fieldCondition, $value )
  {
    $condition = html_entity_decode($fieldCondition['condition']);
    $value = trim($value);
    $fieldCondition['condition_value'] = html_entity_decode($fieldCondition['condition_value']);

    if( $condition == '<' ){
      $value = str_replace(',', '.', $value);
      $fieldCondition['condition_value'] = str_replace(',', '.', $fieldCondition['condition_value']);
      if( is_numeric( $fieldCondition['condition_value'] ) && is_numeric($value) && $value < $fieldCondition['condition_value'] ){
        return true;
      }
    }

    if( $condition == '>' ){
      $value = str_replace(',', '.', $value);
      $fieldCondition['condition_value'] = str_replace(',', '.', $fieldCondition['condition_value']);
      if( is_numeric( $fieldCondition['condition_value'] ) && is_numeric($value) && $value > $fieldCondition['condition_value'] ){
        return true;
      }
    }

    if( $condition == '==' && $value == $fieldCondition['condition_value'] ){
      return true;
    }

    if( $condition == '!=' && $value != $fieldCondition['condition_value'] ){
      return true;
    }

    if( $condition == 'list' ){
      $conditionValues = explode(',', $fieldCondition['condition_value']);
      if( in_array( $value, $conditionValues ) ){
        return true;
      }
    }


    if( $condition == 'not_list' ){
      $conditionValues = explode(',', $fieldCondition['condition_value']);
      if( !in_array( $value, $conditionValues ) ){
        return true;
      }
    }

    if( $condition == 'empty' && $value == '' ){
      return true;
    }

    if( $condition == 'not_empty' && $value != '' ){
      return true;
    }

    if( $condition == 'regex' ){
      @preg_match($fieldCondition['condition_value'], $value, $matches);
      if( $matches ){
        return true;
      }
    }

    if( $condition == 'any' ){
      return true;
    }

    return false;
  }

  private function _addDataToDb( $product, $row )
  {

    foreach( $product as $field => $value ){

	  $field = Module::getInstanceByName('simpleimportproduct')->cleanCsvLine($field);
    $field = htmlentities(strip_tags($field));
      
      $data = array(
        'row'   => $row,
        'field' => $field,
        'value' => pSQL($value, true)
      );

      if( Tools::getValue('id_task') ){
        $data['id_task'] = Tools::getValue('id_task');
      }
      else{
        $data['id_task'] = 0;
      }

      $this->_insertValues .= '("'.$data['row'].'","'.$data['field'].'","'.$data['value'].'","'.$data['id_task'].'"),';
//      Db::getInstance(_PS_USE_SQL_SLAVE_)->insert('simpleimport_data', $data);
    }

    $limit = 50;
    if( $row % $limit == 0 ){
      $this->_addDataToDbQuery();
    }
  }

  private function _addDataToDbQuery()
  {
    if( $this->_insertValues ){
      $this->_insertValues = rtrim($this->_insertValues, ',');

      $sql = "
      INSERT INTO "._DB_PREFIX_."simpleimport_data
        (`row`,`field`,`value`,`id_task`)
      VALUES
      $this->_insertValues
      ;
    ";

      Db::getInstance()->execute($sql);
      $this->_insertValues = '';
    }

  }

  private function runImport()
  {
    $rows = $this->_getRowsNumber( $this->_iteration );
    foreach( $rows as $row ){
      $product = $this->_getDataForImport($row['row'], true);
      if( $product ){
        $product = $this->_processFieldConditions( $product );
        $this->_runImport($product);
      }
    }
  }

  private function _runImport( $product, $processImages = false )
  {
    if( $product ){

      $emptyProduct = true;
      foreach ( $product as $prod ){
        if( $prod ){
          $emptyProduct = false;
          break;
        }
      }

      if( $emptyProduct ){
        return false;
      }

      $productData = array();
      foreach( $this->_importFieldsBase as $key => $value){
        if( $key == 'tax_method'
          || $key == 'category_method'
          || $key == 'delimiter_categories'
          || $key == 'supplier_method'
          || $key == 'manufacturer_method'
        ){
          continue;
        }

        if($value !== 'no'){
          if ( ($key === 'shop_id' && preg_match('/^ImportToShop_.+_\([\d,]+\)$/', $value) ) ) {
            $productData['main'][$key] = $value;
          }
          elseif( strpos($value, '{pre_saved}_') !== false ){
            $value = str_replace('{pre_saved}_', '', $value);
            $productData['main'][$key] = $value;
          }
          else{
            if( isset($product[$value]) ){
              $productData['main'][$key] = $product[$value];
            }
            else{
              $productData['main'][$key] = $value;
            }
          }
        }
      }

      if($this->_importFieldsCategories){
        foreach( $this->_importFieldsCategories as $key => $value){
          $categories = array();
          foreach( $value as $k => $v ){
            if($v !== 'no' && $v !== 'undefined'){
              $categories[] = $product[$v];
            }
          }

          $productData['categories'][$key] = $categories;
        }
      }

      if($this->_importFieldsCombinations){
        foreach( $this->_importFieldsCombinations as $key => $value){
          $combinations = array();

          foreach( $value as $k => $v ){


            if($v !== 'no' && $k !== 'undefined' && !is_array($v)){
              if( $k == 'combinations_import_type' || $k == 'remove_combinations' || $k == 'supplier_method_combination' || $k == 'quantity_combination_method' || $k == 'combination_key' ){
                $combinations[$k] = $v;
              }
              elseif( strpos($v, '{pre_saved}_') !== false ){
                $v = str_replace('{pre_saved}_', '', $v);
                $combinations[$k] = $v;
              }
              else{
                $combinations[$k] = $product[$v];
              }
            }
            elseif($k == 'suppliers'){
              $suppliers = array();
              foreach( $v as $val_sup ){
                $supplier = array();
                if($val_sup && $val_sup !== 'no' && $val_sup !== 'undefined'){
                  foreach( $val_sup as $j => $sup ){
                    $supplier[$j] = isset( $product[$sup] ) ? $product[$sup] : '';
                    if( strpos($sup, '{pre_saved}_') !== false ){
                      $supplier[$j] = str_replace('{pre_saved}_', '', $sup);
                    }
                  }
                  $suppliers[] = $supplier;
                }
              }
            }

            if( is_array($v) && $k != 'suppliers' ){
              foreach( $v as $sKey => $singleAttr ){
                $combinations[$k][$sKey] = isset( $product[$singleAttr] ) ? $product[$singleAttr] : '';

                if( $singleAttr == 'no' ){
                  $combinations[$k][$sKey] = '';
                }

                if( $singleAttr == 'enter_manually' ){
                  $combinations[$k][$sKey] = $singleAttr;
                }

                if( $singleAttr == 'existing_attribute' ){
                  $combinations[$k][$sKey] = $singleAttr;
                }

                if( $k == 'existing_attribute' ){
                  $combinations[$k][$sKey] = $singleAttr;
                }

                if( $k == 'manually_attribute' ){
                  $combinations[$k][$sKey] = $singleAttr;
                }

                if( $k == 'single_type' ){
                  $combinations[$k][$sKey] = $singleAttr;
                }

                if( $k == 'single_delimiter' ){
                  $combinations[$k][$sKey] = $singleAttr;
                }
              }
            }

            if($k == 'suppliers'){
              $combinations[$k] = $suppliers;
            }

          }

          $productData['combinations'][$key] = $combinations;
        }
      }
      else{
        $productData['combinations'] = false;
      }

      if($this->_importFieldsDiscount){
        foreach( $this->_importFieldsDiscount as $key => $value){
          $discount = array();
          foreach( $value as $k => $v ){
            if( $k == 'remove_specific_prices' || $k == 'specific_prices_for' ){
              $discount[$k] = $v;
            }
            elseif( strpos($v, '{pre_saved}_') !== false ){
              $discount[$k] = str_replace('{pre_saved}_', '', $v);
            }
            elseif($v !== 'no' && $k !== 'undefined'){
              $discount[$k] = $product[$v];
            }
          }

          $productData['discount'][$key] = $discount;
        }
      }
      else{
        $productData['discount'] = false;
      }


      if($this->_importFieldImages){
        foreach( $this->_importFieldImages as $key => $value){
          $images = array();
          foreach( $value as $k => $v ){
            if($v !== 'no' && $k !== 'undefined'){

              if( isset($product[$v]) ){
                $images[$k] = $product[$v];
              }
              else{
                $images[$k] = $v;
              }


            }
          }
          $productData['images'][$key] = $images;
        }
      }
      else{
        $productData['images'] = false;
      }

      if($this->_importFieldsFeatures){
        foreach( $this->_importFieldsFeatures as $key => $value){
          $features = array();
          foreach( $value as $k => $v ){
            if($v !== 'no' && $k !== 'undefined'){
              if( $k == 'features_type' || $k == 'remove_features' || $k == 'features_name_manually' || $v == 'enter_manually' || $k == 'features_name_import_method' || $k == 'features_value_import_method' || $k == 'multiple_feature_values' || strpos( $v, '{pre_saved}' ) !== false ){
                $v = str_replace('{pre_saved}_', '', $v);
                $features[$k] = $v;
              }
              else{
                $features[$k] = isset($product[$v]) ? $product[$v] : '';
              }
            }
          }
          $productData['features'][$key] = $features;
        }
      }
      else{
        $productData['features'] = false;
      }

      if($this->_importFieldsCustomization){
        if ($this->_importFieldsCustomization[0]['customization_one_column'] && !empty($product[$this->_importFieldsCustomization[0]['customization_name']])) {
            $customization_name = explode(',', $product[$this->_importFieldsCustomization[0]['customization_name']]);
            $customization_type = array();
            $customization_required = array();

            $customization_fields = array();

            if (!empty($product[$this->_importFieldsCustomization[0]['customization_type']])) {
                $customization_type = explode(',', $product[$this->_importFieldsCustomization[0]['customization_type']]);
            }

            if (!empty($product[$this->_importFieldsCustomization[0]['customization_required']])) {
                $customization_required = explode(',', $product[$this->_importFieldsCustomization[0]['customization_required']]);
            }

            foreach ($customization_name as $count => $customization_name_field) {
                $customization_field = array(
                  'remove_customization' => $this->_importFieldsCustomization[0]['remove_customization'],
                  'customization_name' => trim($customization_name_field)
                );

                if (isset($customization_type[$count])) {
                    $customization_field['customization_type'] = trim($customization_type[$count]);
                }

                if (isset($customization_required[$count])) {
                    $customization_field['customization_required'] = trim($customization_required[$count]);
                }

                array_push($customization_fields, $customization_field);
            }

            $productData['customization'] = $customization_fields;
        } else {
            foreach( $this->_importFieldsCustomization as $key => $value){
                $customization = array();
                foreach( $value as $k => $v ){
                    if($v !== 'no' && $k !== 'undefined'){
                        if ($k == 'remove_customization' || $k == 'customization_one_column') {
                            $customization[$k] = $v;
                        } else if( $k == 'customization_type' || $k == 'customization_required' ){
                            if( isset( $product[$v] ) ){
                                $customization[$k] = $product[$v];
                            }
                            else {
                                $customization[$k] = $v;
                            }
                        }
                        else{
                            $customization[$k] = $product[$v];
                        }
                    }
                }
                $productData['customization'][$key] = $customization;
            }
        }
      }
      else{
        $productData['customization'] = false;
      }



      if($this->_importFieldsAttachments){
        foreach( $this->_importFieldsAttachments as $key => $value){
          $attachments = array();
          foreach( $value as $k => $v ){
            if($v !== 'no' && $k !== 'undefined'){
              if( $k == 'remove_attachments' ||  $k == 'import_attachments_from_single_column'){
                $attachments[$k] = $v;
              } else{
                $attachments[$k] = $product[$v];
              }
            }
          }

          $productData['attachments'][$key] = $attachments;
        }
      }
      else{
        $productData['attachments'] = false;
      }

      if($this->_importFieldsAccessories){
        $accessories = array();
        foreach( $this->_importFieldsAccessories as $k => $v){
          if($v !== 'no' && $k !== 'undefined'){
            if( $k == 'identifier_type' || $k == 'remove_accessories' || $k == 'identifier_delimiter' ){
              $accessories[$k] = $v;
            }
            else{
              $accessories[$k] = $product[$v];
            }
          }
        }
        $productData['accessories'] = $accessories;
      }
      else{
        $productData['accessories'] = false;
      }

      if($this->_importFieldsSuppliers){
        foreach( $this->_importFieldsSuppliers as $key => $value){
          $suppliers = array();
          foreach( $value as $k => $v ){
            if($v !== 'no' && $k !== 'undefined'){
              if( $k == 'supplier_method' ){
                $suppliers[$k] = $v;
              }
              else{
                if( isset( $product[$v] ) ){
                  $suppliers[$k] = $product[$v];
                }
                else{
                  $v = str_replace('{pre_saved}_', '', $v);
                  $suppliers[$k] = $v;
                }
              }
            }
          }
          $productData['suppliers'][$key] = $suppliers;
        }
      }
      else{
        $productData['suppliers'] = false;
      }

      if($this->_importFieldsPackProducts){
        $packProducts = array();
        foreach( $this->_importFieldsPackProducts as $k => $v){
          if($v !== 'no' && $k !== 'undefined'){
            if( $k == 'pack_identifier_type' || $k == 'remove_pack_products' || $k == 'pack_identifier_delimiter' ){
              $packProducts[$k] = $v;
            }
            else{
              $packProducts[$k] = $product[$v];
            }
          }
        }
        $productData['pack_products'] = $packProducts;
      }
      else{
        $productData['pack_products'] = false;
      }

      if( !$processImages ){
        $this->_updateImportRunning();
      }
      if( $processImages ){
        if( !$productData['combinations'][0]['images'][0] && !isset($productData['images'][0]['images_url']) ){
          return false;
        }
      }
      if(!$this->_importProduct( $productData, $processImages )){
        return false;
      }
    }
  }

  private function _updateImportRunning( $stop = false )
  {
    if( $stop ){
      if( !$this->_baseConfig['images_stream'] ){
        Module::getInstanceByName('simpleimportproduct')->updateImagesImportRunning(true);
      }
      Configuration::updateGlobalValue('GOMAKOIL_IMPORT_RUNNING', false);
    }
    else{
      Configuration::updateGlobalValue('GOMAKOIL_IMPORT_RUNNING', time());
    }

    Configuration::updateGlobalValue('GOMAKOIL_IMPORT_RUNNING_TIME', time());
  }

  private function _importProduct( $productData, $processImages = false )
  {
    $value = '';

    if($this->_parser == 'name'){

      if( $this->_importFieldsBase['name'] == 'no' ){
        throw new Exception(Module::getInstanceByName('simpleimportproduct')->l('You selected Product name as product identifier key, but does not set it!'));
      }
      $value = $productData['main']['name'];
    }
    elseif($this->_parser == 'reference'){
      if( $this->_importFieldsBase['reference'] == 'no' && ( !$this->_baseConfig['combination_identification'] || ($this->_baseConfig['combination_identification'] && !isset($productData['combinations'][0]['reference_combination'])) ) ){
        throw new Exception(Module::getInstanceByName('simpleimportproduct')->l('You selected Reference code as product identifier key, but does not set it!'));
      }
      $value = $productData['main']['reference'];
    }
    elseif($this->_parser == 'ean13'){
      if( $this->_importFieldsBase['ean13'] == 'no' && ( !$this->_baseConfig['combination_identification'] || ($this->_baseConfig['combination_identification'] && !isset($productData['combinations'][0]['ean13_combination'])) ) ){
        throw new Exception(Module::getInstanceByName('simpleimportproduct')->l('You selected EAN-13 or JAN barcode as product identifier key, but does not set it!'));
      }
      $value = $productData['main']['ean13'];
    }
    elseif($this->_parser == 'upc'){
      if( $this->_importFieldsBase['upc'] == 'no' && ( !$this->_baseConfig['combination_identification'] || ($this->_baseConfig['combination_identification'] && !isset($productData['combinations'][0]['upc_combination'])) ) ){
        throw new Exception(Module::getInstanceByName('simpleimportproduct')->l('You selected UPC barcode as product identifier key, but does not set it!'));
      }
      $value = $productData['main']['upc'];
    }
    elseif($this->_parser == 'isbn'){
      if( $this->_importFieldsBase['isbn'] == 'no' && ( !$this->_baseConfig['combination_identification'] || ($this->_baseConfig['combination_identification'] && !isset($productData['combinations'][0]['isbn_combination'])) ) ){
        throw new Exception(Module::getInstanceByName('simpleimportproduct')->l('You selected UPC barcode as product identifier key, but does not set it!'));
      }
      $value = $productData['main']['isbn'];
    }
    elseif($this->_parser == 'product_id'){
      if( $this->_importFieldsBase['product_id'] == 'no' ){
        throw new Exception(Module::getInstanceByName('simpleimportproduct')->l('You selected Product ID as product identifier key, but does not set it!'));
      }
      $value = $productData['main']['product_id'];
    }

    if (!empty($productData['main']['shop_id'])) {
      if (preg_match('/^ImportToShop_.+_\([\d,]+\)$/', $productData['main']['shop_id'])) {
        if ($productData['main']['shop_id'] === 'ImportToShop_All Shops_(0)') {
          Shop::setContext(Shop::CONTEXT_ALL);
          $this->_idShop = null;
        } else {
          $explode = explode('_', $productData['main']['shop_id']);
          $id_shop = trim(end($explode), '()');
          Shop::setContext(Shop::CONTEXT_SHOP, (int)$id_shop);
          Context::getContext()->shop = new Shop((int)$id_shop);
          $this->_idShop = Shop::getContextShopID();
        }
      }
    }

    $id_product = false;
    if( $value ){
      $id_product = $this->_model->getProductId($this->_parser, $value, $this->_idShop, $this->_idLang);
    }

    if( !$id_product && $this->_baseConfig['combination_identification'] ){
      $id_product = $this->_model->detectProductByCombination($this->_parser, $this->_idShop, $productData['combinations'][0]);
    }

    if( !$id_product ){
      $id_product['id_product'] = false;
    }

    if( $processImages ){
      return $this->_createImagesList($id_product['id_product'], $productData);
    }

    return $this->_saveNewProduct($id_product['id_product'], $productData);
  }

  private function _createImagesList($id_product, $productData)
  {
	if( isset($productData['main']['remove_product']) && $productData['main']['remove_product'] == 1 ){
      return false;
    }
    
    if($this->_importFieldImages && isset($productData['images']) && $productData['images']){
      $images = $productData['images'];

      $urls = '';
      $remove_images = false;

      if(isset($images[0]['remove_images']) && $images[0]['remove_images']){
        $remove_images = true;
      }

      foreach ($productData['combinations'] as $combination){
        foreach( $combination['images'] as $image ){
          if($image){
            $urls .= $image.',';
          }
          else{
            $urls .= ' ,';
          }
        }
      }

      foreach ($images as $img){
        if($img){
          if(isset($img['images_url']) && $img['images_url']){
            $urls .= $img['images_url'].',';
          }
          else{
            $urls .= ' ,';
          }
        }
      }

      $urls = rtrim($urls, ' ,');
      if( $urls ){
        if( !$remove_images && $id_product && Image::getImagesTotal($id_product) ){
          return true;
        }
        else{
         $imagesLinks = explode(',', $urls );
         foreach( $imagesLinks as $link ){
           $this->_addImageToList($link);
         }
       }
      }
    }
    return true;
  }

  private function _addImageToList( $link )
  {
    $link = trim($link);
    $link = str_replace(' ','%20', $link);

    if( !$link ){
      return false;
    }

    if( !$this->_checkImageInList($link) ){
      $data = array(
        'image_url' => pSQL($link),
        'processed' => 0
      );

      Module::getInstanceByName('simpleimportproduct')->updateImagesImportRunning();
      Db::getInstance()->insert('simpleimport_images_path', $data);
    }
  }

  private function _checkImageInList( $link )
  {
    $sql = 'SELECT count(*) as count
     FROM '._DB_PREFIX_.'simpleimport_images_path
     WHERE image_url = "'.pSQL($link).'"
     ';

    $res = Db::getInstance()->executeS($sql);
    return (bool)$res[0]['count'];
  }

  private function _checkImportProductFrom( $productData = array(), $idProduct )
  {

    $importFromCategories = true;
    $importFromBrands = true;
    $importFromSuppliers = true;

    if( $this->_importFromCategories ){
      $importFromCategories = false;
      $associatedCategories = array();

      if( $productData ){
        $main = $productData['main'];
        $categories = $productData['categories'];

        if( $this->_importFieldsBase['category_method'] == 'category_ids_method' ){
           if( ( isset($main['associated_categories_ids']) && $main['associated_categories_ids'] ) || ( isset($main['default_category_id']) && $main['default_category_id'] ) ){
            $id_category = explode(',', $main['associated_categories_ids']);
            if(isset($main['default_category_id']) && $main['default_category_id']){
              $id_category[] = (int)$main['default_category_id'];
            }
            $associatedCategories = array_unique($id_category);
          }
        }
        else {
          $new_categories = array();
          if ($this->_importFieldsBase['category_method'] == 'category_tree_method') {

            if ($this->_importFieldsBase['delimiter_categories'] && isset($categories[0][0]) ) {
              $this->_importFieldsBase['delimiter_categories'] = html_entity_decode($this->_importFieldsBase['delimiter_categories']);
              foreach ($categories as $key => $value) {
                if( !$value[0] ){
                  continue;
                }
                $new_categories[$key] = explode($this->_importFieldsBase['delimiter_categories'], $value[0]);
              }
              $new_categories = array_values($new_categories);
            }

          } else {
            if (isset($categories[0][0]) && $categories[0][0]) {
              $new_categories = $categories;
            }
          }

          if ( isset( $new_categories[0] ) && $new_categories[0] ) {
            $id_category = $this->_productCategories($new_categories);
            $associatedCategories = array_unique($id_category);
          }
        }
      }

      if( $idProduct && ( ( $categories[0] && isset($main['remove_categories']) && !$main['remove_categories'] ) || !$categories[0] )){
        $currentCategories = Product::getProductCategories($idProduct);
        $merge = array_merge($currentCategories, $associatedCategories);
        $associatedCategories = array_unique($merge);
      }

      foreach( $associatedCategories as $associatedCategory ){
        if( in_array($associatedCategory, $this->_importFromCategories) ){
          $importFromCategories = true;
          break;
        }
      }
    }

    if( $this->_importFromBrands ){
      $importFromBrands = false;
      $associatedBrand = false;
      if( $productData ){
        $main = $productData['main'];
        if( $this->_importFieldsBase['manufacturer_method'] == 'manufacturer_name_method' ){
          if(isset($main['manufacturer']) && $main['manufacturer']){
            $id_manufacturer = $this->_productManufacturer($main['manufacturer'], $main['name']);
            $associatedBrand = $id_manufacturer;
          }
        }
        elseif( $this->_importFieldsBase['manufacturer_method'] == 'manufacturer_ids_method' ){
          if(isset($main['manufacturer_id']) && $main['manufacturer_id']){
            $associatedBrand = (int)$main['manufacturer_id'];
          }
        }
        else{
          if(isset($main['existing_manufacturer']) && $main['existing_manufacturer']){
            $associatedBrand = (int)$main['existing_manufacturer'];
          }
        }
      }

      if( !$associatedBrand && $idProduct ){
        $tmpProduct = new Product($idProduct, false, null, $this->_idShop);
        $associatedBrand = $tmpProduct->id_manufacturer;
      }

      if( $associatedBrand && in_array($associatedBrand, $this->_importFromBrands) ){
        $importFromBrands = true;
      }
    }

    if( $this->_importFromSuppliers ){
      $importFromSuppliers = false;

      if( $productData ){

        $suppliers = $productData['suppliers'];
        $combinations = $productData['combinations'];

        foreach( $combinations as $combination ){
          $type = $combination['supplier_method_combination'];
          foreach( $combination['suppliers'] as $supplier ){

            if($type == 'supplier_name_method'){
              if(isset($supplier['supplier']) && $supplier['supplier']){
                $supplierId = $this->_model->getSupplier(trim($supplier['supplier']));
                if( $supplierId ){
                  $supplierId = $supplierId['id_supplier'];
                  if( in_array( $supplierId,  $this->_importFromSuppliers) ){
                    $importFromSuppliers = true;
                    break;
                  }
                }
              }
            }
            else{
              if( $type == 'existing_supplier_method' ){
                $supplier['supplier_ids'] = $supplier['existing_supplier'];
              }
              if(isset($supplier['supplier_ids']) && $supplier['supplier_ids']){
                if( in_array( $supplier['supplier_ids'],  $this->_importFromSuppliers) ){
                  $importFromSuppliers = true;
                  break;
                }
              }
            }

          }
        }

        foreach( $suppliers as $supplier ){
          $type = $suppliers[0]['supplier_method'];
          if($type == 'supplier_name_method'){
            if(isset($supplier['supplier']) && $supplier['supplier']){
              $supplierId = $this->_model->getSupplier(trim($supplier['supplier']));
              if( $supplierId ){
                $supplierId = $supplierId['id_supplier'];
                if( in_array( $supplierId,  $this->_importFromSuppliers) ){
                  $importFromSuppliers = true;
                  break;
                }
              }
            }
          }
          else{
            if( $type == 'existing_supplier_method' ){
              $supplier['supplier_ids'] = $supplier['existing_supplier'];
            }
            if(isset($supplier['supplier_ids']) && $supplier['supplier_ids']){
              if( in_array( $supplier['supplier_ids'], $this->_importFromSuppliers) ){
                $importFromSuppliers = true;
                break;
              }
            }
          }
        }
      }



      if( !$importFromSuppliers && $idProduct && ( !$productData || ( isset($productData['main']) && !$productData['main']['remove_suppliers']) ) ){
       $productSuppliers = $this->_model->getProductSuppliersID( $idProduct );
       foreach( $productSuppliers as $supplierId ){
         if( in_array( $supplierId['id_supplier'],  $this->_importFromSuppliers) ){
           $importFromSuppliers = true;
           break;
         }
       }
      }
    }

    if( $importFromCategories && $importFromBrands && $importFromSuppliers ){
      return true;
    }
    else{
      return false;
    }
  }

  private function _preparePreConfigs($productData){
    if( !$this->_preConfigs ){
      $this->_preConfigs = true;
      $this->_baseConfig['generate_thumbnails'] = $productData['images'][0]['generate_thumbnails'];
      $this->_baseConfig['no_product_images'] = $productData['images'][0]['no_product_images'];
      $this->_baseConfig['disable_zero_products'] = $productData['main']['disable_zero_products'];
      $this->_baseConfig['new_products'] = $productData['main']['new_products'];
      $this->_baseConfig['existing_products'] = $productData['main']['existing_products'];
      $this->_baseConfig['file_products'] = $productData['main']['file_products'];
      $this->_baseConfig['file_store_products'] = $productData['main']['file_store_products'];
      Configuration::updateGlobalValue('GOMAKOIL_GENERATE_THUMBNAILS', $productData['images'][0]['generate_thumbnails']);

      if( $this->_automatic ){
        $config_step_one = Tools::unserialize(Configuration::get('GOMAKOIL_IMPORT_PRODUCTS_'.rtrim($this->_importSettings,'_'), null, $this->_idShopGroup, $this->defaultIdShop));
        $config_step_one['base_settings']['images_stream'] = $productData['images'][0]['images_stream'];
        $this->_baseConfig['images_stream'] = $productData['images'][0]['images_stream'];
        Configuration::updateValue('GOMAKOIL_IMPORT_PRODUCTS_'.rtrim($this->_importSettings,'_'), serialize($config_step_one), false, $this->_idShopGroup, $this->defaultIdShop);
      }
      else{
        $config_step_one = Tools::unserialize(Configuration::get('GOMAKOIL_CONFIG_IMPORT_PRODUCTS', null, $this->_idShopGroup, $this->defaultIdShop));
        $config_step_one['images_stream'] = $productData['images'][0]['images_stream'];
        $this->_baseConfig['images_stream'] = $config_step_one['images_stream'];
        Configuration::updateValue('GOMAKOIL_CONFIG_IMPORT_PRODUCTS', serialize($config_step_one), false, $this->_idShopGroup, $this->defaultIdShop);
      }
    }
  }

  private function _saveNewProduct( $id_product, $productData ){
    $this->_productError = false;
    $remove_images = false;
    $this->ids_images = array();
    $this->_cover = 'no';
    $main = $productData['main'];
    $categories = $productData['categories'];
    $combinations = $productData['combinations'];
    $discount = $productData['discount'];
    $features = $productData['features'];
    $customization = $productData['customization'];
    $attachments = $productData['attachments'];

    $accessories = $productData['accessories'];
    $suppliers = $productData['suppliers'];
    $packProducts = $productData['pack_products'];
    $reallyProductId = $id_product;


    $this->_preparePreConfigs($productData);

    if(isset($combinations) && $combinations){
      foreach ($combinations as $k => $combination){
        if(isset($combination['images']) && $combination['images']){
          $combinations[$k]['images_combination'] = implode(",", $combination['images']);
        }
      }
    }

    if($this->_importFieldImages && isset($productData['images']) && $productData['images']){
      $images = $productData['images'];

      $urls = '';
      $alt = '';

      if(isset($images[0]['remove_images']) && $images[0]['remove_images']){
        $remove_images = true;
      }

      foreach ($images as $img){
        if($img){
          if(isset($img['images_url']) && $img['images_url']){
            $urls .= $img['images_url'].',';
          }
          else{
            $urls .= ' ,';
          }
          if(isset($img['images_alt']) && $img['images_alt']){
            $alt .= $img['images_alt'].',';
          }
          else{
            $alt .= ' ,';
          }
        }

      }
      $urls = rtrim($urls, ' ,');
      $alt = rtrim($alt, ' ,');
      $main['images_url'] = $urls;
      $main['images_alt'] = $alt;
    }


    Configuration::updateGlobalValue('GOMAKOIL_CURRENTLY_IMPORTED', ((int)Configuration::getGlobalValue('GOMAKOIL_CURRENTLY_IMPORTED')+1));
    Module::getInstanceByName('simpleimportproduct')->updateProgress();

    if( $this->_baseConfig['new_products'] == 'skip' && !$id_product ){
      return false;
    }

    if( !$this->_checkImportProductFrom( $productData, $id_product ) ){
      return false;
    }

    if( $this->_baseConfig['existing_products'] == 'skip' && $id_product && !$this->_checkImportedProduct($id_product) ){
	    if( $this->_baseConfig['file_products'] != 'ignore' ){
	      $this->_addImportedProduct($id_product);
      }
      return false;
    }



    if(isset($main['skip_product']) && $main['skip_product'] == 1){
      return false;
    }

    if( $this->_baseConfig['force_ids'] && $main['product_id'] && $this->_parser == 'product_id' ){
      $id_product = (int)$main['product_id'];
	  if( Tools::strlen($id_product) > 9 ){
      	$this->_createErrorsFile('Product ID Can not have more 9 numbers ','Product ID: ' . $main['product_id']);
      	return false;
  	  }
    }

    $object  = new Product($id_product, false);

    if(isset($main['remove_product']) && $main['remove_product'] == 1 ){
      if( $object->id ){
        $object->delete();
      }
      return false;
    }

    if( $this->_baseConfig['force_ids'] && $main['product_id'] && $this->_parser == 'product_id' ){
      $object->force_id = true;
      $object->id = $id_product;
    }

    if(isset($main['name']) && $main['name']){
      $object->name = $this->_createMultiLangField( $main['name'], $object->name );
    }

    if(isset( $main['reference']) &&  $main['reference']){
      $object->reference = $main['reference'];
    }
    if(isset( $main['available_for_order']) &&  $main['available_for_order'] !== ''){
      $object->available_for_order = (string)$main['available_for_order'] == '0' ? 0 : 1;
    }
    if(isset( $main['active']) &&  $main['active'] !== ''){
      $object->active = (string)$main['active'] == '0' ? 0 : 1;
    }
    else{
      if( !$reallyProductId ){
        $object->active = 1;
      }
    }
    if(isset( $main['condition']) &&  $main['condition']){
      $object->condition = $main['condition'];
    }
    if(isset( $main['visibility']) &&  $main['visibility'] !== ''){
      $object->visibility = $main['visibility'];
    }
    if(isset( $main['link_rewrite']) &&  $main['link_rewrite']){
      $object->link_rewrite = $this->_createMultiLangField(  Tools::link_rewrite($main['link_rewrite']), $object->link_rewrite );
    }
    else{
      if( isset( $main['name']) &&  $main['name'] ){
        if( !isset($object->link_rewrite[$this->_idLang]) || !$object->link_rewrite[$this->_idLang] ){
	          $object->link_rewrite = $this->_createMultiLangField( Tools::link_rewrite($main['name']), $object->link_rewrite );
	        }
      }
    }
    if(isset( $main['meta_description']) &&  $main['meta_description']){
      $object->meta_description = $this->_createMultiLangField( $main['meta_description'], $object->meta_description );
    }
    if(isset( $main['meta_keywords']) &&  $main['meta_keywords']){
      $object->meta_keywords = $this->_createMultiLangField( $main['meta_keywords'], $object->meta_keywords );
    }
    if(isset( $main['meta_title']) &&  $main['meta_title']){
      $object->meta_title = $this->_createMultiLangField( $main['meta_title'], $object->meta_title );
    }
    if(isset( $main['on_sale']) &&  $main['on_sale'] !== ''){
      $object->on_sale = $main['on_sale'];
    }
    if(isset( $main['show_price']) &&  $main['show_price'] !== ''){
      $object->show_price = (string)$main['show_price'] == '0' ? 0 : 1;
    }
    if(isset( $main['online_only']) &&  $main['online_only'] !== ''){
      $object->online_only = (string)$main['online_only'] == '0' ? 0 : 1;
    }
    if(isset( $main['show_condition']) &&  $main['show_condition'] !== ''){
      $object->show_condition = (string)$main['show_condition'] == '0' ? 0 : 1;
    }
    if(isset( $main['unity']) &&  $main['unity'] !== ''){
      $object->unity = (string)$main['unity'];
    }
    if(isset( $main['price']) &&  $main['price'] !== ''){
      $main['price'] = $this->_preparePrice($main['price']);
      $object->price = $main['price'];
    }
    if( $object->price == null ){
      $object->price = 0;
    }
    if(isset( $main['unit_price']) &&  $main['unit_price']){
      $main['unit_price'] = $this->_preparePrice($main['unit_price']);
      $object->unit_price = $main['unit_price'];
      $object->unit_price_ratio = $object->price / $object->unit_price;
    }
    else{
      if($object->unit_price_ratio != 0){
        $object->unit_price = $object->price / $object->unit_price_ratio;
      }
    }
    if(isset( $main['additional_shipping_cost']) &&  $main['additional_shipping_cost'] !== ''){
      $main['additional_shipping_cost'] = $this->_preparePrice($main['additional_shipping_cost']);
      $object->additional_shipping_cost = $main['additional_shipping_cost'];
    }

    if(isset( $main['wholesale_price']) &&  $main['wholesale_price'] !== ''){
      $main['wholesale_price'] = $this->_preparePrice($main['wholesale_price']);
      $object->wholesale_price = $main['wholesale_price'];
    }
    if(isset( $main['ean13']) &&  $main['ean13']){
      $object->ean13 = $main['ean13'];
    }
    if(isset( $main['ecotax']) && $main['ecotax'] != ''){
      $object->ecotax = (float)$main['ecotax'];
    }
    if(isset( $main['upc']) && $main['upc']){
      $object->upc = $main['upc'];
    }
    if(isset( $main['isbn']) && $main['isbn']){
      $object->isbn = $main['isbn'];
    }
    if(isset( $main['date_add']) && $main['date_add']){
      $main['date_add'] = trim($main['date_add']);
      $main['date_add'] = strtotime($main['date_add']);
      if( $main['date_add'] ){
        $main['date_add'] = date('Y-m-d H:i:s', $main['date_add']);
      }
      if (!Validate::isDateFormat($main['date_add'])) {
        $this->_createErrorsFile('Date add - date format is not valid','Product ID: ' . $object->id);
      }
      else{
        $object->date_add = $main['date_add'];
      }
    }
    if( $this->_importFieldsBase['manufacturer_method'] == 'manufacturer_name_method' ){
      if(isset($main['manufacturer']) && $main['manufacturer']){
        $id_manufacturer = $this->_productManufacturer($main['manufacturer'], $main['name']);
        $object->id_manufacturer = $id_manufacturer;
      }
    }
    elseif( $this->_importFieldsBase['manufacturer_method'] == 'manufacturer_ids_method' ){
      if(isset($main['manufacturer_id']) && $main['manufacturer_id']){
        $object->id_manufacturer = (int)$main['manufacturer_id'];
      }
    }
    else{
      if(isset($main['existing_manufacturer']) && $main['existing_manufacturer']){
        $object->id_manufacturer = (int)$main['existing_manufacturer'];
      }
    }
  if(isset($main['width']) && $main['width'] != '' ){
      $object->width = $this->_preparePrice($main['width']);
    }
    if(isset($main['height']) && $main['height'] != ''){
      $object->height = $this->_preparePrice($main['height']);
    }
    if(isset($main['depth']) && $main['depth'] != '' ){
      $object->depth = $this->_preparePrice($main['depth']);
    }
    if(isset($main['weight']) && $main['weight'] != '' ){
      $object->weight = $this->_preparePrice($main['weight']);
    }

    if(isset($main['additional_delivery_times']) && $main['additional_delivery_times'] != '' && property_exists($object, 'additional_delivery_times')){
      $object->additional_delivery_times = $main['additional_delivery_times'];
    }

    if(isset($main['delivery_in_stock']) && $main['delivery_in_stock'] != '' && property_exists($object, 'delivery_in_stock')){
      $object->delivery_in_stock = $this->_createMultiLangField($main['delivery_in_stock'], $object->delivery_in_stock);
    }

    if(isset($main['delivery_out_stock']) && $main['delivery_out_stock'] != '' && property_exists($object, 'delivery_out_stock')){
      $object->delivery_out_stock = $this->_createMultiLangField($main['delivery_out_stock'], $object->delivery_out_stock);
    }

    if(isset($main['short_description']) && $main['short_description']){
      $object->description_short = $this->_createMultiLangField( $main['short_description'], $object->description_short );
    }
    if(isset($main['description']) && $main['description']){
      $object->description = $this->_createMultiLangField( $main['description'], $object->description );
    }
    if(isset($main['available_now']) && $main['available_now']){
      $object->available_now = $this->_createMultiLangField( $main['available_now'], $object->available_now );
    }
    if(isset($main['available_later']) && $main['available_later']){
      $object->available_later = $this->_createMultiLangField( $main['available_later'], $object->available_later );
    }

    if( $this->_importFieldsBase['tax_method'] == 'tax_rate_method' ){
      if(isset( $main['tax']) &&  $main['tax']){
        $name_tax = 'Import module tax ('. $main['tax'] .'%)';
        $tax = new Tax();
        $id_tax = $tax->getTaxIdByName($name_tax);
        if($id_tax){
          $tax_rule_group_id = TaxRulesGroup::getIdByName($name_tax);
          if( Configuration::get('PS_MULTISHOP_FEATURE_ACTIVE') ){
            $tax_rule_group = new TaxRulesGroup($tax_rule_group_id);
            $id_shop_list = Shop::getContextListShopID();
            $tax_rule_group->id_shop_list = $id_shop_list;
            if( $this->_idShop == null && $tax_rule_group->id ){
              $tax_rule_group->setFieldsToUpdate($tax_rule_group->getFieldsShop());
            }
            $tax_rule_group->update();
          }
          $object->id_tax_rules_group = $tax_rule_group_id;
        }
        else{
          if((float)$main['tax']>0 && (float)$main['tax']<100){
            $tax->name =  $this->_createMultiLangField($name_tax, $tax->name);
            $tax->rate = (float)$main['tax'];
            $tax->active = 1;
            if( $this->_idShop == null && $tax->id ){
              $tax->setFieldsToUpdate($tax->getFieldsShop());
            }
            $tax->save();
            $tax_rule_group = new TaxRulesGroup();
            $tax_rule_group->name =  $name_tax;
            $tax_rule_group->active = 1;
            if( $this->_idShop == null && $tax_rule_group->id ){
              $tax_rule_group->setFieldsToUpdate($tax_rule_group->getFieldsShop());
            }
            $tax_rule_group->save();
            $this->_createRule($tax->id, $tax_rule_group->id);
            $object->id_tax_rules_group = $tax_rule_group->id;
          }
        }
      }
    }
    else{
      if(  $this->_importFieldsBase['tax_method'] == 'existing_tax_method' ){
        $main['tax_rule_id'] = $main['existing_tax'];
      }
      if(isset( $main['tax_rule_id'] )){
        $object->id_tax_rules_group = (int)$main['tax_rule_id'];
      }
    }

    if( ( $error = $object->validateFields(false, true) ) !== true ){
        $this->_createErrorsFile($error,$main['name']);
        return false;
    }

    if( ( $error = $object->validateFieldsLang(false, true) ) !== true ){
      $this->_createErrorsFile($error,$main['name']);
      return false;
    }

    if( !$reallyProductId){
      $object->add();
    }

    $productId = $object->id;

    if(isset( $main['tax_price']) &&  $main['tax_price'] !== ''){
	  $main['tax_price'] = str_replace(',','.', $main['tax_price']);
	  $main['tax_price'] = (float)$main['tax_price'];
      if( isset($main['tax']) && (float)$main['tax']>0 && (float)$main['tax']<100 && $this->_importFieldsBase['tax_method'] == 'tax_rate_method' ){
	      $main['tax'] = (float)$main['tax'];
        $main['tax_price'] = $main['tax_price'] / (($main['tax']/100)+1);
      }
      else{
        $address = null;
        if (is_object(Context::getContext()->cart) && Context::getContext()->cart->{Configuration::get('PS_TAX_ADDRESS_TYPE')} != null) {
          $address = Context::getContext()->cart->{Configuration::get('PS_TAX_ADDRESS_TYPE')};
        }
        $taxRate = $object->getTaxesRate(new Address($address));
        $main['tax_price'] = $main['tax_price'] - $object->ecotax;
        $main['tax_price'] = $main['tax_price'] / (($taxRate/100)+1);
      }
      
      $main['tax_price'] = $this->_preparePrice($main['tax_price'], 6);
      $object->price = $main['tax_price'];
    }




    if( $this->_importFieldsBase['category_method'] == 'category_ids_method' ){
      if( ( isset($main['associated_categories_ids']) && $main['associated_categories_ids'] ) || ( isset($main['default_category_id']) && $main['default_category_id'] ) ){
        if( $main['remove_categories'] ){
          $object->deleteCategories();
        }

        $id_category = explode(',', $main['associated_categories_ids']);
        if(isset($main['default_category_id']) && $main['default_category_id']){
          $id_category[] = (int)$main['default_category_id'];
        }
        Cache::clean('Product::getProductCategories_'.(int)$id_product);
        $id_category = array_unique($id_category);
        $object->addToCategories($id_category);
      }
      if(isset($main['default_category_id']) && $main['default_category_id']){
        $object->id_category_default = (int)$main['default_category_id'];
        $id_category[] = (int)$main['default_category_id'];
      }
      else{
        if( isset( $id_category ) && $id_category ){
          $object->id_category_default = end( $id_category );
        }
      }
    }
    else {
      $new_categories = array();
      if ($this->_importFieldsBase['category_method'] == 'category_tree_method') {

        if ($this->_importFieldsBase['delimiter_categories'] && isset($categories[0][0]) ) {
	      $this->_importFieldsBase['delimiter_categories'] = html_entity_decode($this->_importFieldsBase['delimiter_categories']);
          foreach ($categories as $key => $value) {
	        if( !$value[0] ){
		        continue;
	        }
            $new_categories[$key] = explode($this->_importFieldsBase['delimiter_categories'], $value[0]);
          }
          $new_categories = array_values($new_categories);
        }

      } else {
        if (isset($categories[0][0]) && $categories[0][0]) {
          $new_categories = $categories;
        }
      }



      if ( isset( $new_categories[0] ) && $new_categories[0] ) {

        $id_category = $this->_productCategories($new_categories);

        if ($id_category) {
          if (!$this->_checkImportedProduct($productId)) {
            if( $main['remove_categories'] ){
              $object->deleteCategories();
            }
          }

          $id_category = array_unique($id_category);
          Cache::clean('Product::getProductCategories_' . (int)$id_product);
          $object->addToCategories($id_category);
          $this->_catProducts = $id_category;
        }

        if (isset($main['default_category']) && $main['default_category']) {
          if (!empty($this->_category_linking) && !empty($this->_category_linking[trim($main['default_category'])])) {
            $category_def = array(
              array(
                'id_category' => $this->_category_linking[trim($main['default_category'])]['id']
              )
            );
          } else {
            $category_def = $this->_model->getCategoryByNameImport(trim($main['default_category']), $this->_idLang, false);
          }


          if (isset($category_def) && $category_def) {
            foreach ($category_def as $value) {
              if (in_array($value['id_category'], $this->_catProducts)) {
                $cat_def = $value['id_category'];
                break;
              } else {
                $cat_def = end($this->_catProducts);
              }
            }
          }
          if ($category_def) {
            $object->id_category_default = $cat_def;
          }
        } else {
          $cat_def = end($this->_catProducts);
          $object->id_category_default = $cat_def;
        }
      }


    }


    if(isset($main['tags']) && $main['tags']){
      $this->_deleteTagsForProduct($productId);
      Tag::addTags($this->_idLang, $productId, $main['tags']);
    }


    if(isset( $main['depends_on_stock'] ) &&  $main['depends_on_stock'] !== ''){
      if( $main['depends_on_stock'] == 1 ){
        StockAvailable::setProductDependsOnStock($productId, (int)1);
      }
      else{
        StockAvailable::setProductDependsOnStock($productId, (int)0);
      }
    }

    if(isset( $main['out_of_stock'] ) &&  $main['out_of_stock'] !== ''){
      if( $main['out_of_stock'] == 1 ){
        StockAvailable::setProductOutOfStock($productId, (int)1);
      }
      elseif( $main['out_of_stock'] == 2 ){
        StockAvailable::setProductOutOfStock($productId, (int)2);
      }
      else{
        StockAvailable::setProductOutOfStock($productId, (int)0);
      }
    }

    if(isset($main['minimal_quantity']) && $main['minimal_quantity'] !== ''){
      $object->minimal_quantity = $main['minimal_quantity'];
    }
    if(isset($main['available_date']) && $main['available_date'] !== ''){
      $main['available_date'] = date('Y-m-d', strtotime(trim($main['available_date'])));
      if (Validate::isDateFormat($main['available_date'])) {
        if( $object->available_date != $main['available_date'] ){
	       $object->available_date = $main['available_date'];
        }
      }
      else{
        $this->_createErrorsFile('Available Date - date format is not valid','Product ID: ' . $productId);
        return false;
      }
    }

    $img_attr = false;
    if(isset($combinations) && $combinations){
      foreach($combinations as $combination){
        if(isset($combination['images_combination']) && $combination['images_combination']){
          $img_attr = true;
        }
      }
    }

     if(!($main['images_url']) && isset( $main['images_alt'] ) && $main['images_alt'] ){
      $img_alt = explode(",", $main['images_alt']);
      foreach($object->getImages($this->_idLang) as $key=>$imgTmp){
        if($imgTmp){
          if( isset( $img_alt[$key] ) && $img_alt[$key] ){
            $imgTmp = new Image($imgTmp['id_image'], $this->_idLang);
            $imgTmp->legend = $img_alt[$key];
            if( $this->_idShop == null && $imgTmp->id ){
              $imgTmp->setFieldsToUpdate($imgTmp->getFieldsShop());
            }
            $imgTmp->update();
          }
        }
      }
    }


    if((isset($main['images_url']) && $main['images_url']) || $img_attr){

//      $this->_checkNotImportedImages($object);

      if( ( (isset($main['images_url']) && $main['images_url']) || $img_attr ) && $remove_images && !$this->_checkCombinationImage($productId) ){
        foreach($object->getImages($this->_idLang) as $img_del){
          if($img_del){
            $image_del = new Image($img_del['id_image']);
            $image_del->delete();
          }
        }
      }

      if( $this->_cover == 'no' ){
        if($object->getImages( $this->_idLang )){
          $this->_cover = 'yes';
        }
      }

      if(isset($main['images_url']) && $main['images_url']){

        $img_products = explode(",", $main['images_url']);
        if(isset($main['images_alt']) && $main['images_alt']){
          $img_alt = explode(",", $main['images_alt']);
        }
        else{
          $img_alt = false;
        }

        $justNewImages = false;
        if( $this->_baseConfig['no_product_images'] && !$this->_checkCombinationImage($productId) ){
          if( $object->getImages($this->_idLang) ){
            $justNewImages = true;
          }
        }
        foreach($img_products as $kay => $url_img){
          if(!isset($img_alt[$kay])){
            $img_alt[$kay] = $main['name'];
          }
          $ids_images = $this->ids_images;
          if(!isset($ids_images[$url_img]) || !$ids_images[$url_img]){
            if( $justNewImages ){
              continue;
            }
            $this->_productImages($productId, $url_img, $img_alt[$kay]);
          }
        }
      }

      if($img_attr){
        if(isset($combinations) && $combinations){

          $justNewImages = false;
          if( $this->_baseConfig['no_product_images'] && !$this->_checkCombinationImage($productId) ){
            if( $object->getImages($this->_idLang) ){
              $justNewImages = true;
            }
          }

          foreach($combinations as $combination){
            if(isset($combination['images_combination']) && $combination['images_combination']){
              $img_products = explode(",", $combination['images_combination']);
              foreach($img_products as $kay => $url_img){
                if(!isset($img_alt[$kay])){
                  $img_alt[$kay] = $main['name'];
                }
                $ids_images = $this->ids_images;

                if(!isset($ids_images[$url_img]) || !$ids_images[$url_img]){
                  if( $justNewImages ){
                    continue;
                  }
                  $this->_productImages($productId, $url_img, $img_alt[$kay]);
                }
              }
            }
          }
        }
      }
    }

    $attributes = array();

    if(isset($combinations) && $combinations
//      && ( isset($combinations[0]['attribute']) || ( isset($combinations[0]['single_attribute'][0]) && $combinations[0]['single_attribute'][0] ) )
    ){
      if( $combinations[0]['remove_combinations'] && !$this->_checkImportedProduct($productId) ){
        $object->deleteProductAttributes();
        $object->cache_default_attribute = 0;
      }

      $rez = $this->_productCombinations($combinations, $productId, $object);

      $attributes = $rez['attributes'];
      $values = $rez['values'];
      $id_images = $rez['id_images'];
      $directValues = $rez['direct_values'];

     if( $values && !$this->_checkImportedProduct($productId) ){
       if( (isset($combinations[0]['suppliers'][0]['supplier']) && $combinations[0]['suppliers'][0]['supplier']) || (isset($combinations[0]['suppliers'][0]['supplier_ids']) && $combinations[0]['suppliers'][0]['supplier_ids']) ){
         if( $main['remove_suppliers'] ){
          $object->deleteFromSupplier();
         }
       }
     }
     if( $values ){
        if( $combinations[0]['combination_key'] == 'all_combinations' ){
          $combinationsIds = $this->_getProductCombinationIds($productId);
          foreach( $combinationsIds as $combinationsId ){
            foreach( $values as $key=>$value ){
              $values[$key]['id'] = (int)$combinationsId['id_product_attribute'];
            }
            $this->_generateCombinations($values, $attributes, $id_images, $directValues, $combinations[0]['combination_key']);
          }
        }
        else{
          $this->_generateCombinations($values, $attributes, $id_images, $directValues, $combinations[0]['combination_key']);
        }
        $object->checkDefaultAttributes();
        $object->cache_default_attribute = Product::getDefaultAttribute($productId);
        $this->_setAvailableDate($object);
      }
    }

    if($this->_importFieldsSuppliers && ($combinations[0]['suppliers'][0]['supplier'] ||
      $combinations[0]['suppliers'][0]['supplier_ids'] || $combinations[0]['suppliers'][0]['supplier_reference']
      )){
      if( isset( $this->_importFieldsSuppliers[0]['supplier_method'] ) &&  $this->_importFieldsSuppliers[0]['supplier_method'] ){
        $type = $this->_importFieldsSuppliers[0]['supplier_method'];
      }

      if($type){
        if($type == 'supplier_name_method'){
          foreach ($suppliers as $key => $supplier){
            if(isset($supplier['supplier_default']) && $supplier['supplier_default']){
              $isset_supplier_dafault = $this->_model->getSupplier(trim($supplier['supplier_default']));
              if($isset_supplier_dafault){
                $object->id_supplier = $isset_supplier_dafault['id_supplier'];
              }
            }
          }
        }
        else{
          foreach($suppliers as $key => $supplier){
            if( $type == 'existing_supplier_method' ){
              $supplier['supplier_default_id'] = $supplier['existing_supplier_default'];
            }
            if(isset($supplier['supplier_default_id']) && $supplier['supplier_default_id']){
              $object->id_supplier = $supplier['supplier_default_id'];
            }
          }
        }
      }
    }

    if($this->_importFieldsSuppliers && !$object->getDefaultAttribute($productId)
      ){
      if(isset( $this->_importFieldsSuppliers[0]['supplier_method']) &&  $this->_importFieldsSuppliers[0]['supplier_method']){
        $type = $this->_importFieldsSuppliers[0]['supplier_method'];
      }

      if($type){
        if($type == 'supplier_name_method'){
          foreach ($suppliers as $key => $supplier){
            if(isset($supplier['supplier']) && $supplier['supplier']){
              if($key == 0){
                if( $main['remove_suppliers'] ){
                  $object->deleteFromSupplier();
                }
              }
              $this->_productSuppliers($supplier, $productId, $type);
              if(isset($supplier['supplier_default']) && $supplier['supplier_default']){
                $isset_supplier_dafault = $this->_model->getSupplier(trim($supplier['supplier_default']));
                if($isset_supplier_dafault){
                  $object->id_supplier = $isset_supplier_dafault['id_supplier'];
                }
              }
            }
          }
        }
        else{
          foreach($suppliers as $key => $supplier){
            if( $type == 'existing_supplier_method' ){
              $supplier['supplier_ids'] = $supplier['existing_supplier'];
              $supplier['supplier_default_id'] = $supplier['existing_supplier_default'];
            }
            if(isset($supplier['supplier_ids']) && $supplier['supplier_ids']){
              if($key == 0){
                if( $main['remove_suppliers'] ){
                  $object->deleteFromSupplier();
                }
              }
              $reference = trim($supplier['supplier_reference']);
              $price = $supplier['supplier_price'];
              $price = $this->_preparePrice($price);
              $currency = $supplier['supplier_currency'];
              Db::getInstance()->insert('product_supplier', array('id_product' => (int)$productId, 'id_supplier' => (int)$supplier['supplier_ids'], 'product_supplier_reference' => pSQL($reference), 'product_supplier_price_te' => pSQL($price), 'id_currency' => (int)$currency), false, true, DB::ON_DUPLICATE_KEY);
            }
            if(isset($supplier['supplier_default_id']) && $supplier['supplier_default_id']){
              $object->id_supplier = $supplier['supplier_default_id'];
            }
          }
        }
      }
    }

    if( isset( $main['id_warehouse'] ) &&  $main['id_warehouse'] !== '' && !$object->hasAttributes() ){
      if (Warehouse::exists($main['id_warehouse'])) {
        $warehouse_location_entity = new WarehouseProductLocation();
        $warehouse_location_entity->id_product = $object->id;
        $warehouse_location_entity->id_product_attribute = 0;
        $warehouse_location_entity->id_warehouse = $main['id_warehouse'];

        if (WarehouseProductLocation::getProductLocation($object->id, 0, $main['id_warehouse']) !== false) {
          if( $this->_idShop == null && $warehouse_location_entity->id ){
            $warehouse_location_entity->setFieldsToUpdate($warehouse_location_entity->getFieldsShop());
          }
          $warehouse_location_entity->update();
        } else {
          if( $this->_idShop == null && $warehouse_location_entity->id ){
            $warehouse_location_entity->setFieldsToUpdate($warehouse_location_entity->getFieldsShop());
          }
          $warehouse_location_entity->save();
        }

        if (isset($main['warehouse_location']) && $main['warehouse_location']) {
          Warehouse::setProductLocation($object->id, 0, $main['id_warehouse'], $main['warehouse_location']);
        }

        StockAvailable::synchronize($object->id);
      }


      if(isset($main['quantity']) && $main['quantity'] !== ''){

        if (isset($main['depends_on_stock']) && $main['depends_on_stock'] == 1) {

          $stock_manager = StockManagerFactory::getManager();
          $price = (float)str_replace(',', '.', $object->wholesale_price);

          if (!$price) {
            $price = (float)0.001;
          }

          $price = round((float)$price, 6);
          $warehouse = new Warehouse( $main['id_warehouse'] );

          if( (int)$main['quantity'] > 0 ){

            if ($stock_manager->addProduct((int)$object->id, 0, $warehouse, (int)$main['quantity'], 1, $price, true)) {
              StockAvailable::synchronize((int)$object->id);
            }
          }
          if( (int)$main['quantity'] < 0 ){
            $main['quantity'] = (int)$main['quantity'] * (-1);
            if ($stock_manager->removeProduct((int)$object->id, 0, $warehouse, (int)$main['quantity'], 1)) {
              StockAvailable::synchronize((int)$object->id);
            }
          }
        }
        else{
          if($main['quantity_method'] == 'add'){
            $currentQuantity = (int)StockAvailable::getQuantityAvailableByProduct($productId, null, $this->_idShop);
            $quantity = $currentQuantity+(int)$main['quantity'];

            StockAvailable::setQuantity($productId, null, $quantity, $this->_idShop);
          }
          elseif( $main['quantity_method'] == 'deduct' ){
            $currentQuantity = (int)StockAvailable::getQuantityAvailableByProduct($productId, null, $this->_idShop);
            $quantity = $currentQuantity-(int)$main['quantity'];

            StockAvailable::setQuantity($productId, null, $quantity, $this->_idShop);
          }
          else{
            StockAvailable::setQuantity($productId, null, (int)$main['quantity'], $this->_idShop);
          }
        }
      }
    }
     else{
      if(isset($main['quantity']) && $main['quantity'] !== '' && !$object->hasAttributes()){
        if($main['quantity_method'] == 'add'){
          $currentQuantity = (int)StockAvailable::getQuantityAvailableByProduct($productId, null, $this->_idShop);
          $quantity = $currentQuantity+(int)$main['quantity'];

          StockAvailable::setQuantity($productId, null, $quantity, $this->_idShop);
        }
        elseif( $main['quantity_method'] == 'deduct' ){
          $currentQuantity = (int)StockAvailable::getQuantityAvailableByProduct($productId, null, $this->_idShop);
          $quantity = $currentQuantity-(int)$main['quantity'];

          StockAvailable::setQuantity($productId, null, $quantity, $this->_idShop);
        }
        else{
          StockAvailable::setQuantity($productId, null, (int)$main['quantity'], $this->_idShop);
        }
      }
    }

    if( isset($main['location']) && method_exists(new StockAvailable(), 'setLocation') ){
      StockAvailable::setLocation($productId, $main['location'], $this->_idShop, 0);
    }

    if(isset($main['low_stock_threshold']) && $main['low_stock_threshold'] != '' ){
      $object->low_stock_threshold = $main['low_stock_threshold'];
    }

    if(isset($main['low_stock_alert']) && $main['low_stock_alert'] != ''){
      $object->low_stock_alert = (string)$main['low_stock_alert'] == '0' ? 0 : 1;
    }

    if( isset( $main['virtual_product_url'] ) && $main['virtual_product_url'] ){
      $this->_addVirtualProduct($main, $productId, $object);
    }

    if($discount){

      $rez = $this->_productDiscount($discount, $productId);
      if( !$rez ){
        return false;
      }
    }
    if($features){
      if( $features[0]['remove_features'] ){
        $object->deleteProductFeatures();
      }
      foreach($features as $feature){
        $rez = $this->_productFeatures($feature, $productId);
        if(!empty($rez)){
          foreach ($rez as $feature) {
            $object->addFeatureProductImport($productId, $feature['id_feature'], $feature['id_feature_val']);
          }
        }
      }
    }

    if( $customization ){
      if( $customization[0]['remove_customization'] ){
        $object->deleteCustomization();
      }

      foreach( $customization as $customField ){
        if( isset($customField['customization_name']) && $customField['customization_name'] ){
          $this->_addCustomization($customField, $productId, $object);
        }
      }
    }

    if( $attachments ){
      if( $attachments[0]['remove_attachments'] ){
        $object->deleteAttachments();
      }

      if ($attachments[0]['import_attachments_from_single_column']) {
        $original_attachments = $attachments[0];
        $new_attachments = array();

        $attachment_names = explode(',', $original_attachments['attachment_name']);
        $attachment_descriptions = explode(',', $original_attachments['attachment_description']);
        $attachment_urls = explode(',', $original_attachments['attachment_url']);

        $num_of_attachments_to_import = count($attachment_names);

        if ($num_of_attachments_to_import > 0) {
          for ($i = 0; $i < $num_of_attachments_to_import; $i++) {
            if (!empty($attachment_names[$i]) && !empty($attachment_urls[$i])) {
              $new_attachments[$i]['attachment_name'] = trim($attachment_names[$i]);
              $new_attachments[$i]['attachment_description'] = trim($attachment_descriptions[$i]);
              $new_attachments[$i]['attachment_url'] = trim($attachment_urls[$i]);
            }
          }
        }

        $attachments = $new_attachments;
      }

      foreach( $attachments as $attachment ){
        if( isset( $attachment['attachment_name'] ) ){
          $this->_addAttachment($attachment, $productId, $object);
        }
      }
    }

    if( $accessories ){
      if( $accessories['remove_accessories'] ){
        $object->deleteAccessories();
      }
      if( isset($accessories['accessories_identifier']) && $accessories['accessories_identifier'] ){
        $accessoriesDelimiter = $accessories['identifier_delimiter'];
        //$accessories['accessories_identifier'] = str_replace(' ','',$accessories['accessories_identifier'] );
        $accessoriesValues = explode($accessoriesDelimiter, $accessories['accessories_identifier']);
        $accessoriesIds = $this->_getAccessoriesIds( $accessories['identifier_type'], $accessoriesValues, $object->id );
        if( $accessoriesIds ){
          $object->changeAccessories($accessoriesIds);
        }
      }
    }

    if( $packProducts ){
      if( $packProducts['remove_pack_products'] ){
        $object->deletePack();
      }

      if( isset($packProducts['pack_products_identifier']) && $packProducts['pack_products_identifier'] ){
        $packProductsDelimiter = $packProducts['pack_identifier_delimiter'];
        //$packProducts['pack_products_identifier'] = str_replace(' ','',$packProducts['pack_products_identifier'] );
        $packProductsValues = explode($packProductsDelimiter, $packProducts['pack_products_identifier']);
        $packProductsQuantity = array();
        if( isset( $packProducts['pack_products_quantity'] ) && $packProducts['pack_products_quantity'] ){
          $packProductsQuantity = explode($packProductsDelimiter, $packProducts['pack_products_quantity']);
        }

        $packProductsSuccess = $this->_setPackProducts($packProductsValues, $packProductsQuantity, $packProducts['pack_identifier_type'], $object->id);
        if( $packProductsSuccess ){
          $object->cache_is_pack = 1;
        }
      }
    }

	if(isset($main['carriers_id']) && $main['carriers_id'] ){
      $carriers_id = str_replace('.',',', $main['carriers_id']);

      $carriers = explode(',', $carriers_id);
      if( isset($carriers[0]) && $carriers[0] ){
        $carrierReferences = array();
        foreach( $carriers as $carrier ){
          $carrierObject = new Carrier($carrier);
          $carrierReferences[] = $carrierObject->id_reference;
        }
        $object->setCarriers($carrierReferences);
      }
    }

    if( $this->_idShop == null && $object->id ){
      $object->setFieldsToUpdate($this->_getProductFields($object));
    }

    $this->_checkPriceConditions( $object, $productData );
    $this->_checkQuantityConditions( $object, $productData );

    $this->_processFileStoreProduct($object);

    if( ( $error = $object->validateFields(false, true) ) !== true ){
      $this->_createErrorsFile($error,'Product ID: ' . $object->id);
      return false;
    }

    if( ( $error = $object->validateFieldsLang(false, true) ) !== true ){
      $this->_createErrorsFile($error,'Product ID: ' . $object->id);
      return false;
    }

    $object->update();

    if ( $this->_baseConfig['search_index'] ) {
      Search::indexation(false, $object->id);
    }

    if(isset( $main['advanced_stock_management'] ) &&  $main['advanced_stock_management'] !== ''){
      if( $main['advanced_stock_management'] == 1 ){
        $object->setAdvancedStockManagement((int)1);
      }
      else{
        $object->setAdvancedStockManagement((int)0);
      }
    }

    $this->_importProducts++;
    $this->_importedProducts++;

    $this->_addImportedProduct($object->id);

    Configuration::updateValue('GOMAKOIL_IMPORT_PRODUCTS_COUNT', (int)$this->_importedProducts, false, $this->_idShopGroup, $this->defaultIdShop);

    return true;
  }

  private function _preparePrice( $price, $decimal = 4 )
  {
    $price = str_replace(',','.', $price);

    preg_match_all('/\./',$price, $matches);
    $pointNumber = count($matches[0]);
    if( $pointNumber > 1 ){
      $priceArray = str_split($price);
      $newPrice = '';
      $i = 1;
      foreach( $priceArray as $number ){
        if( $number == '.' ){
          if( $i == $pointNumber ){
            $newPrice .= $number;
          }
          $i++;
        }
        else{
          $newPrice .= $number;
        }
      }
      $price = $newPrice;
    }

    $price = number_format($price, $decimal, '.', '');

    return $price;
  }

  private function _setAvailableDate($product, $available_date = '0000-00-00')
  {
    if (Validate::isDateFormat($available_date) && $product->available_date != $available_date) {
      $product->available_date = $available_date;
    }

    return false;
  }

  private function _getProductCombinationIds( $productId )
  {
    $product = new Product($productId, false);
    return $product->getAttributeCombinations(Context::getContext()->language->id, false);
  }

  private function _checkQuantityConditions( $object, $productData )
  {
    foreach( $this->_quantityConditions as $quantityCondition ){
      if( $quantityCondition['quantity_formula'] != '' ){
        $this->_processQuantityCondition( $quantityCondition, $object, $productData );
      }
    }
  }

  private function _processQuantityCondition( $quantityCondition, $object, $productData )
  {
    $quantityCondition['condition'] = html_entity_decode($quantityCondition['condition']);
    if( $quantityCondition['quantity_field'] == 'product_quantity'){
      if( $object->hasCombinations() ){
        return false;
      }

      if( $quantityCondition['quantity_source'] == 'store' ){
        $quantityCondition = $this->_processQuantity($quantityCondition, null, 'get', $object);
      }
      else{
        if( isset($productData['main']['quantity']) ){
          $quantityCondition['quantity'] = $productData['main']['quantity'];
        }
        else{
          return false;
        }
      }

      if(
        ( version_compare( $quantityCondition['quantity'], $quantityCondition['condition_value'], $quantityCondition['condition'] ) && is_numeric($quantityCondition['quantity']) )
        || $quantityCondition['condition'] == 'any'
        || ( $quantityCondition['condition'] == 'zero' && $quantityCondition['quantity'] == 0 )
        || ( $quantityCondition['condition'] == '==' && $quantityCondition['condition_value'] == $quantityCondition['quantity'] )
      ){
        $this->_processQuantity($quantityCondition, null, 'save', $object);
      }
    }

    if( $quantityCondition['quantity_field'] == 'combination_quantity' ){
      $combinations = $object->getWsCombinations();
      foreach( $combinations as $combination ){
        if( $quantityCondition['price_source'] == 'file' ){
          if( in_array($combination['id'], $this->_importedCombinations) ){
            $quantityCondition['quantity'] = $this->_getFileCombination( $combination['id'], 'quantity' );
          }
          else{
            continue;
          }
          if( $quantityCondition['quantity'] === false ){
            continue;
          }
        }
        else{
          $quantityCondition = $this->_processQuantity($quantityCondition, $combination['id'], 'get', $object);
        }

        if(
          ( version_compare( $quantityCondition['quantity'], $quantityCondition['condition_value'], $quantityCondition['condition'] ) && is_numeric($quantityCondition['quantity']) )
          || $quantityCondition['condition'] == 'any'
          || ( $quantityCondition['condition'] == 'zero' && $quantityCondition['quantity'] == 0 )
          || ( $quantityCondition['condition'] == '==' && $quantityCondition['condition_value'] == $quantityCondition['quantity'] )
        ){
          $this->_processQuantity($quantityCondition, $combination['id'], 'save', $object);
        }
      }
    }

  }

  private function _processQuantity( $quantityCondition, $combinationId = null, $type = 'get', $product = false )
  {
    if( $type == 'get' ){
      $quantityCondition['quantity'] = (int)StockAvailable::getQuantityAvailableByProduct($product->id, $combinationId, $this->_idShop);
      return $quantityCondition;
    }

    if( $type == 'save' ){
      if( $quantityCondition['quantity_formula'] == '0' ){
        StockAvailable::setQuantity($product->id, $combinationId, 0, $this->_idShop);
      }
      else{
        $val = $this->_getMathOperator( $quantityCondition['quantity'], $quantityCondition['quantity_formula'], 'quantity' );
        if( $val ){
          StockAvailable::setQuantity($product->id, $combinationId, $val, $this->_idShop);
        }
        else{
          $this->_createErrorsFile('Quantity formula: ' . $quantityCondition['quantity_formula'] . ' is not valid', 'Product ID: ' . $product->id);
        }
      }
    }
  }

  private function _checkPriceConditions( $object, $productData )
  {
    foreach( $this->_priceConditions as $priceCondition ){
      if( $priceCondition['price_formula'] != '' ){
        $this->_processPriceCondition( $priceCondition, $object, $productData);
      }
    }
  }

  private function _processPriceCondition( $priceCondition, $object, $productData )
  {
    $priceCondition['condition'] = html_entity_decode($priceCondition['condition']);
    $priceCondition['condition_value'] = str_replace(',', '.', $priceCondition['condition_value']);
    $priceCondition['condition_value'] = Tools::ps_round($priceCondition['condition_value'], 6);

    if( $priceCondition['price_field'] == 'wholesale_price' || $priceCondition['price_field'] == 'price' || $priceCondition['price_field'] == 'unit_price' || $priceCondition['price_field'] == 'tax_price' ){

      if( $priceCondition['price_source'] == 'store' ){
        if( $priceCondition['price_field'] == 'tax_price' ){
          $taxRate = $object->getTaxesRate();
          $price = $object->price;
          $price = $price*($taxRate/100+1);
        }
        else{
          $price = (float)$object->{$priceCondition['price_field']};
        }
      }
      else{
       if( isset($productData['main'][$priceCondition['price_field']]) ){
         $price = $productData['main'][$priceCondition['price_field']];
         $price = str_replace(',', '.', $price);
       }
       else{
         return false;
       }
      }


      if( ( version_compare( $price, $priceCondition['condition_value'], $priceCondition['condition'] ) && is_numeric($price) )
        || $priceCondition['condition'] == 'any'
        || ( $priceCondition['condition'] == 'zero' && $price == 0 )
        || ( $priceCondition['condition'] == '==' && $priceCondition['condition_value'] == $price )
      ){
        if( $priceCondition['price_formula'] == '0' ){
          if( $priceCondition['price_field'] == 'tax_price' ){
            $object->price = 0;
          }
          else{
            $object->{$priceCondition['price_field']} = 0;
          }
        }
        else{
          $price = Tools::ps_round($price, 6);
          $val = $this->_getMathOperator( $price, $priceCondition['price_formula']);

          if( $val ){
            if( $priceCondition['price_field'] == 'tax_price' ){
              $taxPrice = (float)$val;

              $taxPrice = $taxPrice - $object->ecotax;
              $taxPrice = $taxPrice / (($taxRate/100)+1);
              $taxPrice = Tools::ps_round($taxPrice, 6);
              $object->price = $taxPrice;

            }
            else{
              $object->{$priceCondition['price_field']} = $val;
              if( $priceCondition['price_field'] == 'unit_price' ){
                $object->unit_price_ratio = $object->price / $object->unit_price;
              }
            }
          }
          else{
            $this->_createErrorsFile('Price formula: ' . $priceCondition['price_formula'] . ' is not valid', 'Product ID: ' . $object->id);
          }
        }
      }
    }

    if( $priceCondition['price_field'] == 'wholesale_price_combination' || $priceCondition['price_field'] == 'final_price'
      || $priceCondition['price_field'] == 'final_price_with_tax' || $priceCondition['price_field'] == 'impact_price'
      || $priceCondition['price_field'] == 'impact_price_with_tax'
    ){
      $combinations = $object->getWsCombinations();
      foreach( $combinations as $combination ){
        $priceCondition = $this->_processCombination( $priceCondition, $combination['id'], 'get', $object );
        if( $priceCondition['price_source'] == 'file' ){
          if( in_array($combination['id'], $this->_importedCombinations) ){
            $priceCondition['price'] = $this->_getFileCombination( $combination['id'], isset($priceCondition['original_price_field']) ? $priceCondition['original_price_field'] : $priceCondition['price_field'] );
          }
          else{
            continue;
          }
          if( $priceCondition['price'] === false ){
            continue;
          }
        }
        if( ( version_compare( $priceCondition['price'], $priceCondition['condition_value'], $priceCondition['condition'] ) && is_numeric($priceCondition['price']) )
          || $priceCondition['condition'] == 'any'
          || ( $priceCondition['condition'] == 'zero' && $priceCondition['price'] == 0 )
          || ( $priceCondition['condition'] == '==' && $priceCondition['condition_value'] == $priceCondition['price'] )
        ){
          $this->_processCombination($priceCondition, $combination['id'], 'save', $object);
        }
      }
    }
  }

  private function _getFileCombination( $combinationId, $field )
  {
    foreach( $this->_importedCombinationsData as $combination ){
      if( $combination['combination_id'] == $combinationId ){
        return $combination[$field];
      }
    }
  }

  private function _processCombination( $priceCondition, $combinationId, $type = 'get', $product = false )
  {
    $combination = new Combination($combinationId);
    if( $type == 'get' ){
      if( $priceCondition['price_field'] == 'wholesale_price_combination' ){
        $priceCondition['price'] = Tools::ps_round($combination->wholesale_price, 6);
        $priceCondition['price_field'] = 'wholesale_price';
      }

      if( $priceCondition['price_field'] == 'final_price' ){
        $productPrice = (float)$product->price;
        $combinationPrice = (float)$combination->price;
        $priceCondition['price'] = Tools::ps_round($productPrice+$combinationPrice, 6);
        $priceCondition['price_field'] = 'price';
        $priceCondition['original_price_field'] = 'final_price';
      }

      if( $priceCondition['price_field'] == 'final_price_with_tax' ){
        $productPrice = (float)$product->price;
        $combinationPrice = (float)$combination->price;
        $taxRate = $product->getTaxesRate();

        $price = ($productPrice+$combinationPrice)*($taxRate/100+1);

        $priceCondition['price'] = Tools::ps_round($price, 6);
        $priceCondition['price_field'] = 'price';
        $priceCondition['original_price_field'] = 'final_price_with_tax';
      }

      if( $priceCondition['price_field'] == 'impact_price' ){
        $priceCondition['price'] = Tools::ps_round($combination->price, 6);
        $priceCondition['price_field'] = 'price';
      }

      if( $priceCondition['price_field'] == 'impact_price_with_tax' ){
        $price = $combination->price;
        $taxRate = $product->getTaxesRate();
        $price = $price*($taxRate/100+1);

        $priceCondition['price'] = Tools::ps_round($price, 6);
        $priceCondition['price_field'] = 'price';
        $priceCondition['original_price_field'] = 'impact_price_with_tax';
      }

      return $priceCondition;
    }

    if( $type == 'save' ){
      if( $priceCondition['price_formula'] == '0' ){
        $val = 0;
        if( isset($priceCondition['original_price_field']) && ( $priceCondition['original_price_field'] == 'final_price' || $priceCondition['original_price_field'] == 'final_price_with_tax' ) ){
          $val = $val - $product->price;
          $val = Tools::ps_round($val, 6);
        }
        $combination->{$priceCondition['price_field']} = $val;
        $combination->update();
      }
      else{
        $val = $this->_getMathOperator( $priceCondition['price'], $priceCondition['price_formula'] );
        if( $val ){
          if( isset($priceCondition['original_price_field']) && $priceCondition['original_price_field'] == 'final_price' ){
            $val = $val - $product->price;
            $val = Tools::ps_round($val, 6);
          }

          if( isset($priceCondition['original_price_field']) && $priceCondition['original_price_field'] == 'final_price_with_tax' ){
            $taxRate = $product->getTaxesRate();
            $val = $val/(($taxRate/100)+1);
            $val = $val - $product->price;
            $val = Tools::ps_round($val, 6);
          }

          if( isset($priceCondition['original_price_field']) && $priceCondition['original_price_field'] == 'impact_price_with_tax' ){
            $taxRate = $product->getTaxesRate();
            $val = $val/(($taxRate/100)+1);
            $val = Tools::ps_round($val, 6);
          }

          $combination->{$priceCondition['price_field']} = $val;
          $combination->update();
        }
        else{
          $this->_createErrorsFile('Price formula: ' . $priceCondition['price_formula'] . ' is not valid', 'Product ID: ' . $combination->id_product);
        }
      }
    }
  }

  private function _getMathOperator( $price, $formula, $type = 'price' )
  {
    str_replace(',', '.', $formula);
    $res = $price . $formula;

    if( $type == 'quantity' ){
      $pattern = '/([0-9.]+)(?:\s*)([\+\-])(?:\s*)([0-9.]+)/';
      $precision = 0;
    }
    else{
      $pattern = '/([0-9.]+)(?:\s*)([\+\-\*\/])(?:\s*)([0-9.]+)/';
      $precision = 6;
    }

    if( preg_match($pattern, $res, $matches) ){
      $operator = $matches[2];

      switch($operator){
        case '+':
          $p = $matches[1] + $matches[3];
          break;
        case '-':
          $p = $matches[1] - $matches[3];
          break;
        case '*':
          $p = $matches[1] * $matches[3];
          break;
        case '/':
          $p = $matches[1] / $matches[3];
          break;
      }

      if( is_numeric($p) ){
        return Tools::ps_round($p, $precision);
      }
    }


    if( is_numeric($formula) ){
      return Tools::ps_round($formula, $precision);
    }



    return false;
  }

  private function _getProductFields($object)
  {
    $data = array();
    $fields = Product::$definition;
    foreach( $fields['fields'] as $key => $field ){
      if( isset($field['lang']) && $field['lang'] ){
        $data[$key] = array(
          $this->_idLang => 1
        );
      }
    }

    $data = array_merge($object->getFieldsShop(), $data);
    return $data;
  }

  private function _getFileSize($url){
    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_HEADER, TRUE);
    curl_setopt($ch, CURLOPT_NOBODY, TRUE);

    curl_exec($ch);
    $size = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);

    curl_close($ch);

    if ($size == -1) {
      return filesize($url);
    }

    return $size;
  }

  private function _checkNotImportedImages( $object )
  {
    $images = $object->getImages( $this->_idLang );
    foreach( $images as $image ){
      $image = new Image($image['id_image']);
      $path = _PS_PROD_IMG_DIR_ . $image->getImgPath() . '.' . $image->image_format;
      if( !file_exists($path) ){
        $image->delete();
      }
    }
  }

  private function getAttachmentFileName($path, $generated_file_id = null, $hardcoded_file_name_without_ext = null)
  {
    $filename = false;

    if (Validate::isAbsoluteUrl($path)) {
      $filename = $this->getFileNameFromHttpHeaders($path);
    }

    if (empty($filename) || !$this->isValidFileNameWithExt($filename)) {
      $filename = basename($path);
    }

    if ((empty($filename) || !$this->isValidFileNameWithExt($filename)) && ($generated_file_id && $hardcoded_file_name_without_ext)) {
      $mime_type = mime_content_type(_PS_DOWNLOAD_DIR_ . $generated_file_id);
      $hardcoded_file_name_without_ext = Tools::truncate($hardcoded_file_name_without_ext, 128, '');

      if ($mime_type) {
        $filename = $hardcoded_file_name_without_ext . '.' . $this->mime2ext($mime_type);
      }
    }

    return $filename;
  }

  private function getFileNameFromHttpHeaders($url)
  {
    $file_headers = @get_headers($url, 1);
    $content_desposition = $file_headers['Content-Disposition'];
    $filename = false;

    if (!empty($content_desposition)) {
      preg_match('/filename="(.+\.[a-z_\-0-9]{1,10})"/', $content_desposition, $filename);
    }

    if (empty($filename[1]) || !$this->isValidFileNameWithExt($filename[1])) {
      return false;
    }

    return $filename[1];
  }

  private function isValidFileNameWithExt($file_name)
  {
    return preg_match('/^[a-zA-Z0-9\s_.-]+\.[a-z_\-0-9]{1,10}$/', $file_name);
  }

  private function mime2ext($mime)
  {
    $all_mimes = '{"png":["image\/png","image\/x-png"],"bmp":["image\/bmp","image\/x-bmp","image\/x-bitmap","image\/x-xbitmap","image\/x-win-bitmap","image\/x-windows-bmp","image\/ms-bmp","image\/x-ms-bmp","application\/bmp","application\/x-bmp","application\/x-win-bitmap"],"gif":["image\/gif"],"jpeg":["image\/jpeg","image\/pjpeg"],"xspf":["application\/xspf+xml"],"vlc":["application\/videolan"],"wmv":["video\/x-ms-wmv","video\/x-ms-asf"],"au":["audio\/x-au"],"ac3":["audio\/ac3"],"flac":["audio\/x-flac"],"ogg":["audio\/ogg","video\/ogg","application\/ogg"],"kmz":["application\/vnd.google-earth.kmz"],"kml":["application\/vnd.google-earth.kml+xml"],"rtx":["text\/richtext"],"rtf":["text\/rtf"],"jar":["application\/java-archive","application\/x-java-application","application\/x-jar"],"zip":["application\/x-zip","application\/zip","application\/x-zip-compressed","application\/s-compressed","multipart\/x-zip"],"7zip":["application\/x-compressed"],"xml":["application\/xml","text\/xml"],"svg":["image\/svg+xml"],"3g2":["video\/3gpp2"],"3gp":["video\/3gp","video\/3gpp"],"mp4":["video\/mp4"],"m4a":["audio\/x-m4a"],"f4v":["video\/x-f4v"],"flv":["video\/x-flv"],"webm":["video\/webm"],"aac":["audio\/x-acc"],"m4u":["application\/vnd.mpegurl"],"pdf":["application\/pdf","application\/octet-stream"],"pptx":["application\/vnd.openxmlformats-officedocument.presentationml.presentation"],"ppt":["application\/powerpoint","application\/vnd.ms-powerpoint","application\/vnd.ms-office","application\/msword"],"docx":["application\/vnd.openxmlformats-officedocument.wordprocessingml.document"],"xlsx":["application\/vnd.openxmlformats-officedocument.spreadsheetml.sheet","application\/vnd.ms-excel"],"xl":["application\/excel"],"xls":["application\/msexcel","application\/x-msexcel","application\/x-ms-excel","application\/x-excel","application\/x-dos_ms_excel","application\/xls","application\/x-xls"],"xsl":["text\/xsl"],"mpeg":["video\/mpeg"],"mov":["video\/quicktime"],"avi":["video\/x-msvideo","video\/msvideo","video\/avi","application\/x-troff-msvideo"],"movie":["video\/x-sgi-movie"],"log":["text\/x-log"],"txt":["text\/plain"],"css":["text\/css"],"html":["text\/html"],"wav":["audio\/x-wav","audio\/wave","audio\/wav"],"xhtml":["application\/xhtml+xml"],"tar":["application\/x-tar"],"tgz":["application\/x-gzip-compressed"],"psd":["application\/x-photoshop","image\/vnd.adobe.photoshop"],"exe":["application\/x-msdownload"],"js":["application\/x-javascript"],"mp3":["audio\/mpeg","audio\/mpg","audio\/mpeg3","audio\/mp3"],"rar":["application\/x-rar","application\/rar","application\/x-rar-compressed"],"gzip":["application\/x-gzip"],"hqx":["application\/mac-binhex40","application\/mac-binhex","application\/x-binhex40","application\/x-mac-binhex40"],"cpt":["application\/mac-compactpro"],"bin":["application\/macbinary","application\/mac-binary","application\/x-binary","application\/x-macbinary"],"oda":["application\/oda"],"ai":["application\/postscript"],"smil":["application\/smil"],"mif":["application\/vnd.mif"],"wbxml":["application\/wbxml"],"wmlc":["application\/wmlc"],"dcr":["application\/x-director"],"dvi":["application\/x-dvi"],"gtar":["application\/x-gtar"],"php":["application\/x-httpd-php","application\/php","application\/x-php","text\/php","text\/x-php","application\/x-httpd-php-source"],"swf":["application\/x-shockwave-flash"],"sit":["application\/x-stuffit"],"z":["application\/x-compress"],"mid":["audio\/midi"],"aif":["audio\/x-aiff","audio\/aiff"],"ram":["audio\/x-pn-realaudio"],"rpm":["audio\/x-pn-realaudio-plugin"],"ra":["audio\/x-realaudio"],"rv":["video\/vnd.rn-realvideo"],"jp2":["image\/jp2","video\/mj2","image\/jpx","image\/jpm"],"tiff":["image\/tiff"],"eml":["message\/rfc822"],"pem":["application\/x-x509-user-cert","application\/x-pem-file"],"p10":["application\/x-pkcs10","application\/pkcs10"],"p12":["application\/x-pkcs12"],"p7a":["application\/x-pkcs7-signature"],"p7c":["application\/pkcs7-mime","application\/x-pkcs7-mime"],"p7r":["application\/x-pkcs7-certreqresp"],"p7s":["application\/pkcs7-signature"],"crt":["application\/x-x509-ca-cert","application\/pkix-cert"],"crl":["application\/pkix-crl","application\/pkcs-crl"],"pgp":["application\/pgp"],"gpg":["application\/gpg-keys"],"rsa":["application\/x-pkcs7"],"ics":["text\/calendar"],"zsh":["text\/x-scriptzsh"],"cdr":["application\/cdr","application\/coreldraw","application\/x-cdr","application\/x-coreldraw","image\/cdr","image\/x-cdr","zz-application\/zz-winassoc-cdr"],"wma":["audio\/x-ms-wma"],"vcf":["text\/x-vcard"],"srt":["text\/srt"],"vtt":["text\/vtt"],"ico":["image\/x-icon","image\/x-ico","image\/vnd.microsoft.icon"],"csv":["text\/x-comma-separated-values","text\/comma-separated-values","application\/vnd.msexcel"],"json":["application\/json","text\/json"]}';
    $all_mimes = json_decode($all_mimes,true);

    foreach ($all_mimes as $key => $value) {
      if (array_search($mime,$value) !== false) {
        return $key;
      }
    }

    return false;
  }

  private function attachExistingFileToProduct($attachment_id, $product_id, Product $product_object)
  {
    if (!is_int($attachment_id)) {
      return false;
    }

    $attachmentObject = new Attachment($attachment_id);
    $attachmentObject->attachProduct($product_id);
    $product_object->cache_has_attachments = true;

    return true;
  }

  private function _addAttachment( $attachment, $productId, $object )
  {
    if (empty($attachment['attachment_url'])) {
      $attachId = $this->_getAttachFileId($attachment['attachment_name'], $productId);

      if($attachId === false){
        $this->_createErrorsFile( 'File for attachments not found: ' . $attachment['attachment_name'] , 'Product ID: ' . $productId );
        return false;
      }

      $this->attachExistingFileToProduct($attachId, $productId, $object);
    } else {
      $fileSize = $this->_getFileSize($attachment['attachment_url']);

      if( !$fileSize || $fileSize == -1 ){
        $this->_createErrorsFile( 'File for attachments not found: ' . $attachment['attachment_url'] , 'Product ID: ' . $productId );
        return false;
      } else {
        if( $fileSize > (Configuration::get('PS_ATTACHMENT_MAXIMUM_SIZE') * 1024 * 1024) ){
          $error = sprintf(
            Module::getInstanceByName('simpleimportproduct')->l('The file is too large. Maximum size allowed is: %1$d kB. The file you are trying to upload is %2$d kB.'),
            (Configuration::get('PS_ATTACHMENT_MAXIMUM_SIZE') * 1024),
            number_format(($fileSize / 1024), 2, '.', '')
          );

          $this->_createErrorsFile( $error, 'Product ID: ' . $productId );
          return false;
        }

        if (!$this->_copyAttachedFile($attachment, $productId, $object)) {
          $this->_createErrorsFile( 'Can not upload attachment file: ' . $attachment['attachment_url'] , 'Product ID: ' . $productId );
        }
      }
    }

    return true;
  }

  private function _copyAttachedFile($attachmentInfo, $productId, $object)
  {
    $uniqid = sha1(microtime());
    if (!copy($attachmentInfo['attachment_url'], _PS_DOWNLOAD_DIR_.$uniqid)) {
      return false;
    }

    $attachment_url = $attachmentInfo['attachment_url'];
    $attachment_name = $attachmentInfo['attachment_name'];
    $attachment_description = $attachmentInfo['attachment_description'];

    $file_name = $this->getAttachmentFileName($attachment_url, $uniqid, $attachment_name);
    $file_size = $this->_getFileSize($attachment_url);
    $attachment_id = $this->_getAttachFileId($attachment_name, $productId, $file_size, $file_name);

    if (is_int($attachment_id)) {
      $this->attachExistingFileToProduct($attachment_id, $productId, $object);
      unlink(_PS_DOWNLOAD_DIR_.$uniqid);
      return true;
    }

    $attachment = new Attachment();
    $attachment->name = $this->_createMultiLangField($attachment_name, false);
    $attachment->description = !empty($attachment_description) ? $this->_createMultiLangField($attachment_description, false) : '';
    $attachment->file = $uniqid;
    $attachment->file_name = $file_name;
    $attachment->mime = mime_content_type(_PS_DOWNLOAD_DIR_.$uniqid);
    $res = $attachment->add();

    if( $res ){
      $attachment->attachProduct($productId);
      $object->cache_has_attachments = true;
    } else{
      $this->_createErrorsFile( 'Can not add attachment file' , 'Product ID: ' . $productId );
    }

    return true;
  }

  private function _deleteTagsForProduct($id_product)
  {
    $tags_removed = Db::getInstance()->executeS('SELECT id_tag FROM '._DB_PREFIX_.'product_tag WHERE id_product='.(int)$id_product . ' AND id_lang = ' . (int)$this->_idLang );
    $result = Db::getInstance()->delete('product_tag', 'id_product = '.(int)$id_product  . ' AND id_lang = ' . (int)$this->_idLang );
    Db::getInstance()->delete('tag', 'NOT EXISTS (SELECT 1 FROM '._DB_PREFIX_.'product_tag
        												WHERE '._DB_PREFIX_.'product_tag.id_tag = '._DB_PREFIX_.'tag.id_tag)');
    $tag_list = array();
    foreach($tags_removed as $tag_removed) {
      $tag_list[] = $tag_removed['id_tag'];
    }
    if ($tag_list != array()) {
      Tag::updateTagCount($tag_list);
    }
    return $result;
  }

  private function _getAttachFileId( $attachName, $productId, $fileSize = false, $fileName = false )
  {
    $attachedFiles = Attachment::getAttachments($this->_idLang, $productId, true);
    foreach( $attachedFiles as $file ){
      if( !$fileSize && !$fileName ){
        if( $file['name'] == $attachName ){
          return (int)$file['id_attachment'];
        }
      }
      else{
        if( $file['name'] == $attachName && $file['file_size'] == $fileSize && $file['file_name'] == $fileName ){
          return (int)$file['id_attachment'];
        }
      }
    }

    $notAttachedFiles = Attachment::getAttachments($this->_idLang, $productId, false);
    foreach( $notAttachedFiles as $file ){
      if( !$fileSize && !$fileName ){
        if( $file['name'] == $attachName ){
          return (int)$file['id_attachment'];
        }
      }
      else{
        if( $file['name'] == $attachName && $file['file_size'] == $fileSize && $file['file_name'] == $fileName ){
          return (int)$file['id_attachment'];
        }
      }
    }

    return false;
  }


  private function _addCustomization( $customization, $productId, $object )
  {
    $type = 1;
    $required = 0;

    if (isset($customization['customization_type']) &&
      (Tools::strtolower($customization['customization_type']) == '0' ||
          Tools::strtolower($customization['customization_type']) == 'file')
    ) {
      $type = 0;
    }

    if (isset($customization['customization_required']) &&
        (Tools::strtolower($customization['customization_required']) == '1' ||
        Tools::strtolower($customization['customization_required']) == 'yes')
    ) {
      $required = 1;
    }

    $data = array(
      'id_product' => (int)$productId,
      'type'       => (int)$type,
      'required'   => (int)$required,
    );

    Db::getInstance()->insert('customization_field', $data);
    $id_customization_field = Db::getInstance()->Insert_ID();

    $languages = Language::getLanguages(true, $this->_idShop );
    foreach( $languages as $lang ){
      $data = array(
        'id_customization_field' => (int)$id_customization_field,
        'id_lang' => (int)$lang['id_lang'],
        'id_shop' => (int)$this->_idShop,
        'name'    => pSQL($customization['customization_name'])
      );

      Db::getInstance()->insert('customization_field_lang', $data);
    }

    $this->_updateCustomizableCount( $productId, $object );

  }

  private function _updateCustomizableCount( $productId, $product )
  {
    $sql = '
      SELECT count( c.type ) as count, c.type
      FROM '._DB_PREFIX_.'customization_field c
      WHERE id_product = '.(int)$productId.'
      GROUP BY c.type 
    ';

    $res = Db::getInstance()->executeS($sql);
    if( $res ){
      $product->text_fields = 0;
      $product->uploadable_files = 0;
       foreach($res as $fieldsCount){
         if( $fieldsCount['type'] == 1 ){
           $product->text_fields = (int)$fieldsCount['count'];
         }
         else{
           $product->uploadable_files = (int)$fieldsCount['count'];
         }
       }
      $product->customizable = 1;
      if( $this->_idShop == null && $product->id ){
        $product->setFieldsToUpdate($product->getFieldsShop());
      }
      $product->update();
    }
    else{
      $product = new Product($productId);
      $product->text_fields = 0;
      $product->uploadable_files = 0;
      $product->customizable = 0;
      if( $this->_idShop == null && $product->id ){
        $product->setFieldsToUpdate($product->getFieldsShop());
      }
      $product->update();
    }


  }

  private function _setPackProducts( $productList, $productQuantity, $type, $productId )
  {
    if( !$productList ){
      return false;
    }

    $makePack = false;
    foreach ( $productList as $key=>$identifier ){
	  $identifier = trim($identifier);
      $product = $this->_getPackIdByIdentifier($type, $identifier);
      if( !$product ){
        $this->_createErrorsFile( 'Product for Pack not found' , 'Product ' . $type . ': ' . $identifier );
        continue;
      }
      if( $product['is_virtual'] || $product['cache_is_pack'] ){
        $this->_createErrorsFile( 'Products for Pack must have Standard product type' , 'Product ' . $type . ': ' . $identifier );
        continue;
      }
      if( isset($product['id_product']) && $product['id_product'] ){
        if( $this->_model->checkPackItem($productId, $product['id_product'], $product['id_product_attribute']) ){
          continue;
        }
        $makePack = true;
        $quantity = 1;
        if( isset($productQuantity[$key]) && $productQuantity[$key] ){
          $quantity = $productQuantity[$key];
        }
        Pack::addItem($productId, $product['id_product'], $quantity, (int)$product['id_product_attribute']);
      }
    }

    return $makePack;
  }

  private function _getAccessoriesIds( $type, $values = array(), $idProduct ){
    if( !$values ){
      return false;
    }

    $res = array();
    foreach ( $values as $identifier ){
	  $identifier = trim($identifier);
      $productId = $this->_getAccessoriesIdByIdentifier($type, $identifier);
      if( !$productId ){
        $this->_createErrorsFile( 'Product for accessories not found' , 'Product ' . $type . ': ' . $identifier );
      }
      if( isset($productId['id_product']) && $productId['id_product'] ){
        if( $this->_model->checkAccessory($idProduct, $productId['id_product']) ){
          continue;
        }
        $res[] = $productId['id_product'];
      }
    }

    return $res;
  }

  private function _getPackIdByIdentifier( $type, $value ){

    $id_product = $this->_model->getProductForPack($type, $value, $this->_idShop, $this->_idLang);

    return $id_product;
  }

  private function _getAccessoriesIdByIdentifier( $type, $value ){

    $id_product = $this->_model->getProductId($type, $value, $this->_idShop, $this->_idLang);

    return $id_product;
  }

  private function _detectCombinationId( $attributes, $combination, $combinationKey )
  {
    $combinationId = null;
    if( $combinationKey == 'attributes' ){
      $product = new Product($combination['id_product'], false);
      $combinationId = (int)$product->productAttributeExists($attributes, false, null, false, true);
    }
    else{
      $combinationId = $this->_model->detectCombinationId($combination['id_product'], $combinationKey, $combination[$combinationKey], $this->_idShop);
    }

    return $combinationId;
  }


  private function _generateCombinations($combinations, $attributes, $id_images, $directValues, $combinationKey){
    $res = true;
    $this->_delete_associated_warehouses = false;
    $this->_importedCombinations = array();
    $product = new Product($combinations[0]['id_product'], false);
    foreach ($combinations as $key => $combination) {
      if( $combinationKey == 'all_combinations' ){
        if( isset($combination['id']) && $combination['id'] ){
          $id_combination = $combination['id'];
        }
        else{
          $id_combination = false;
        }
      }
      else{
        $id_combination = $this->_detectCombinationId( $attributes[$key], $combination, $combinationKey );
      }
      if( !$id_combination && !$attributes[$key] ){
        continue;
      }

      $obj = new Combination($id_combination);

      if ($id_combination) {
        $obj->minimal_quantity = 1;
        $obj->available_date = '0000-00-00';
      }

      foreach ($combination as $field => $value) {
        if( $field == 'default_on' && $value ){
          $product->deleteDefaultAttributes();
        }

        if( $id_combination && $directValues[$key][$field] == 'miss' ){
          continue;
        }

        if( $field == 'low_stock_alert' ){
          if( $value != '' ){
            $obj->low_stock_alert = (string)$value == '0' ? 0 : 1;
          }
          continue;
        }

        if( $field == 'low_stock_threshold' ){
          if( $value != '' ){
            $obj->low_stock_threshold = (int)$value;
          }
          continue;
        }

        $obj->$field = $value;
      }

      if( ( $error = $obj->validateFields(false, true) ) !== true ){
        $this->_createErrorsFile($error,'Product ID - ' . $combination['id_product']);
        continue;
      }
      if( ( $error = $obj->validateFieldsLang(false, true) ) !== true ){
        $this->_createErrorsFile($error,'Product ID - ' . $combination['id_product']);
        continue;
      }

      if( $this->_idShop == null && $obj->id ){
        $obj->setFieldsToUpdate($obj->getFieldsShop());
      }
      $obj->save();

      $this->_importedCombinationsData[$key]['combination_id'] = $obj->id;

      $this->_importedCombinations[] = $obj->id;

      if(isset( $combination['id_warehouse'] ) &&  $combination['id_warehouse'] ){
        if (Warehouse::exists($combination['id_warehouse'])) {

          $id_warehouse_product_location = WarehouseProductLocation::getIdByProductAndWarehouse($combination['id_product'], $obj->id, $combination['id_warehouse']);
          $warehouse_location_entity = new WarehouseProductLocation( $id_warehouse_product_location );
          $warehouse_location_entity->id_product = $combination['id_product'];
          $warehouse_location_entity->id_product_attribute = $obj->id;
          $warehouse_location_entity->id_warehouse = $combination['id_warehouse'];

          if ($id_warehouse_product_location) {
            if( $this->_idShop == null && $warehouse_location_entity->id ){
              $warehouse_location_entity->setFieldsToUpdate($warehouse_location_entity->getFieldsShop());
            }
            $warehouse_location_entity->update();
          } else {
            if( $this->_idShop == null && $warehouse_location_entity->id ){
              $warehouse_location_entity->setFieldsToUpdate($warehouse_location_entity->getFieldsShop());
            }
            $warehouse_location_entity->save();
          }

          if (isset($combination['warehouse_location']) && $combination['warehouse_location']) {
            Warehouse::setProductLocation($combination['id_product'], $obj->id, $combination['id_warehouse'], $combination['warehouse_location']);
          }

          StockAvailable::synchronize($combination['id_product']);
        }
      }

      if( isset($combination['location']) && method_exists(new StockAvailable(), 'setLocation') ){
        StockAvailable::setLocation((int)$combination['id_product'], $combination['location'], $this->_idShop, (int)$obj->id);
      }

      if(isset($combination['quantity']) && $combination['quantity'] !== ''){
        if(isset( $combination['id_warehouse'] ) &&  $combination['id_warehouse'] && StockAvailable::dependsOnStock($combination['id_product'], $this->_idShop) ){
          $stock_manager = StockManagerFactory::getManager();
          $price = (float)$obj->wholesale_price;

          if( !$price ){
            $productTmp = new Product((int)$combination['id_product'], false);
            $price = (float)$productTmp->wholesale_price;
          }

          if( !$price ){
            $price = (float)0.001;
          }

          $warehouse = new Warehouse( $combination['id_warehouse'] );

          if( (int)$combination['quantity'] > 0 ){
            if ($stock_manager->addProduct((int)$combination['id_product'], $obj->id, $warehouse, (int)$combination['quantity'], 1, $price, true)) {
              StockAvailable::synchronize((int)$combination['id_product']);
            }
          }

          if( (int)$combination['quantity'] < 0 ){
            $combination['quantity'] = (int)$combination['quantity'] * (-1);
            if ($stock_manager->removeProduct((int)$combination['id_product'], $obj->id, $warehouse, (int)$combination['quantity'], 1)) {
              StockAvailable::synchronize((int)$combination['id_product']);
            }
          }

        }
        else{
          if($combination['quantity_method'] == 'add'){
            $currentQuantity = (int)StockAvailable::getQuantityAvailableByProduct($combination['id_product'], (int)$obj->id, $this->_idShop);
            $quantity = $currentQuantity+(int)$combination['quantity'];

            StockAvailable::setQuantity($combination['id_product'], (int)$obj->id, $quantity, $this->_idShop);
          }
          elseif( $combination['quantity_method'] == 'deduct' ){
            $currentQuantity = (int)StockAvailable::getQuantityAvailableByProduct($combination['id_product'], (int)$obj->id, $this->_idShop);
            $quantity = $currentQuantity-(int)$combination['quantity'];

            StockAvailable::setQuantity($combination['id_product'], (int)$obj->id, $quantity, $this->_idShop);
          }
          else{
            StockAvailable::setQuantity($combination['id_product'], (int)$obj->id,  (int)$combination['quantity']);
          }

        }
      }

      if (!empty($id_images[$key])) {

        $old_images = $obj->getWsImages();

        $ids = $id_images[$key];

        foreach ($old_images as $img){
          $ids[] = $img['id'];
        }

        $ids = array_unique($ids);
        $obj->setImages($ids);
      }

      if (!$id_combination) {
        $attribute_list = array();
        foreach ($attributes[$key] as $id_attribute) {
          $attribute_list[] = array(
            'id_product_attribute' => (int)$obj->id,
            'id_attribute' => (int)$id_attribute
          );
        }
        $res &= Db::getInstance()->insert('product_attribute_combination', $attribute_list);
      }
      $suppliers = $combination['suppliers'];
      $suppliers_metod = $combination['supplier_method_combination'];



      if($suppliers_metod){
        if($suppliers_metod == 'supplier_name_method'){
          foreach ($suppliers as $key => $supplier){
            if(isset($supplier['supplier']) && $supplier['supplier']){
              $this->_productSuppliersCombination($supplier, $combination['id_product'], $obj->id);
            }
          }
        }
        else{
          foreach($suppliers as $key => $supplier){
            if( $suppliers_metod == 'existing_supplier_method' ){
              $supplier['supplier_ids'] = $supplier['existing_supplier'];
            }
            if(isset($supplier['supplier_ids']) && $supplier['supplier_ids']){

              $reference = trim($supplier['supplier_reference']);
              $price = $supplier['supplier_price'];
              $price = $this->_preparePrice($price);
              $currency = $supplier['supplier_currency'];

              if(!$this->_checkImportedProduct($combination['id_product'])){
                Db::getInstance()->insert('product_supplier', array('id_product' => (int)$combination['id_product'], 'id_product_attribute' => 0, 'id_supplier' => (int)$supplier['supplier_ids'], 'product_supplier_reference' => pSQL($reference), 'product_supplier_price_te' => pSQL($price), 'id_currency' => (int)$currency), false, true, DB::ON_DUPLICATE_KEY);
              }

              Db::getInstance()->insert('product_supplier', array('id_product' => (int)$combination['id_product'], 'id_product_attribute' => (int)$obj->id, 'id_supplier' => (int)$supplier['supplier_ids'], 'product_supplier_reference' => pSQL($reference), 'product_supplier_price_te' => pSQL($price), 'id_currency' => (int)$currency), false, true, DB::ON_DUPLICATE_KEY);
            }
          }
        }
      }


    }


    return $res;
  }

  private function _productSuppliersCombination($supplier, $productId, $id_product_attribute){
    $sup = trim($supplier['supplier']);

    if($sup){
      $reference = trim($supplier['supplier_reference']);
      $price = $supplier['supplier_price'];
      $price = $this->_preparePrice($price);
      $currency = $supplier['supplier_currency'];

      $id_shop_list = Shop::getContextListShopID();
      $isset_supplier = $this->_model->getSupplier($sup);



      if(!$isset_supplier){
        $supplier_obj = new Supplier();
        $supplier_obj->name = $sup;
        $supplier_obj->id_shop_list = $id_shop_list;
        $supplier_obj->active = 1;
        if( $this->_idShop == null && $supplier_obj->id ){
          $supplier_obj->setFieldsToUpdate($supplier_obj->getFieldsShop());
        }
        $supplier_obj->save();

        Db::getInstance()->insert('product_supplier', array('id_product' => (int)$productId, 'id_product_attribute' => 0,  'id_supplier' => (int)$supplier_obj->id, 'product_supplier_reference' => pSQL($reference), 'product_supplier_price_te' => pSQL($price), 'id_currency' => (int)$currency ), false, true, DB::ON_DUPLICATE_KEY);
        Db::getInstance()->insert('product_supplier', array('id_product' => (int)$productId, 'id_product_attribute' => (int)$id_product_attribute,  'id_supplier' => (int)$supplier_obj->id, 'product_supplier_reference' => pSQL($reference), 'product_supplier_price_te' => pSQL($price), 'id_currency' => (int)$currency ), false, true, DB::ON_DUPLICATE_KEY);
      }
      else{

        //$id = $this->_model->getProductSupplier($productId);

        if( Configuration::get('PS_MULTISHOP_FEATURE_ACTIVE') ){
          $supplier_obj = new Supplier((int)$isset_supplier['id_supplier']);
          $id_shop_list = Shop::getContextListShopID();
          $supplier_obj->id_shop_list = $id_shop_list;
          if( $this->_idShop == null && $supplier_obj->id ){
            $supplier_obj->setFieldsToUpdate($supplier_obj->getFieldsShop());
          }
          $supplier_obj->update();
        }


        if(!$this->_checkImportedProduct($productId)){
          Db::getInstance()->insert('product_supplier', array('id_product' => (int)$productId, 'id_product_attribute' => 0,  'id_supplier' => (int)$isset_supplier['id_supplier'], 'product_supplier_reference' => pSQL($reference), 'product_supplier_price_te' => pSQL($price), 'id_currency' => (int)$currency ), false, true, DB::ON_DUPLICATE_KEY);
        }


        Db::getInstance()->insert('product_supplier', array('id_product' => (int)$productId, 'id_product_attribute' => (int)$id_product_attribute,  'id_supplier' => (int)$isset_supplier['id_supplier'], 'product_supplier_reference' => pSQL($reference), 'product_supplier_price_te' => pSQL($price), 'id_currency' => (int)$currency ), false, true, DB::ON_DUPLICATE_KEY);

      }
    }

  }


  private function _createRule($id_tax, $id_tax_rules_group)
  {
    $zip_code = 0;
    $id_rule = (int)0;
    $behavior = (int)0;
    $description = "";

    $countries = Country::getCountries(Context::getContext()->language->id);
    $this->selected_countries = array();
    foreach ($countries as $country) {
      $this->selected_countries[] = (int)$country['id_country'];
    }

    if (empty($this->selected_states) || count($this->selected_states) == 0) {
      $this->selected_states = array(0);
    }
    $tax_rules_group = new TaxRulesGroup((int)$id_tax_rules_group);
    foreach ($this->selected_countries as $id_country) {
      $first = true;
      foreach ($this->selected_states as $id_state) {
	    $tax_rules_group->id = $id_tax_rules_group;
        if ($tax_rules_group->hasUniqueTaxRuleForCountry($id_country, $id_state, $id_rule)) {
          $this->errors[] = Tools::displayError('A tax rule already exists for this country/state with tax only behavior.');
          continue;
        }
        $tr = new TaxRule();

        // update or creation?
        if (isset($id_rule) && $first) {
          $tr->id = $id_rule;
          $first = false;
        }

        $tr->id_tax = $id_tax;
        $tax_rules_group = new TaxRulesGroup((int)$id_tax_rules_group);
        $tr->id_tax_rules_group = (int)$tax_rules_group->id;
        $tr->id_country = (int)$id_country;
        $tr->id_state = (int)$id_state;
        list($tr->zipcode_from, $tr->zipcode_to) = $tr->breakDownZipCode($zip_code);

        // Construct Object Country
        $country = new Country((int)$id_country, (int)Context::getContext()->language->id);

        if ($zip_code && $country->need_zip_code) {
          if ($country->zip_code_format) {
            foreach (array($tr->zipcode_from, $tr->zipcode_to) as $zip_code) {
              if ($zip_code) {
                if (!$country->checkZipCode($zip_code)) {
                  $this->errors[] = sprintf(
                    Tools::displayError('The Zip/postal code is invalid. It must be typed as follows: %s for %s.'),
                    str_replace('C', $country->iso_code, str_replace('N', '0', str_replace('L', 'A', $country->zip_code_format))), $country->name
                  );
                }
              }
            }
          }
        }

        $tr->behavior = (int)$behavior;
        $tr->description = $description;
        $this->tax_rule = $tr;

        if (count($this->errors) == 0) {
          $tax_rules_group = $this->updateTaxRulesGroup($tax_rules_group);
//          $tr->id = (int)$tax_rules_group->getIdTaxRuleGroupFromHistorizedId((int)$tr->id);
//          $tr->id_tax_rules_group = (int)$tax_rules_group->id;

          if( $this->_idShop == null && $tr->id ){
            $tr->setFieldsToUpdate($tr->getFieldsShop());
          }
          if (!$tr->save()) {
            $this->errors[] = Tools::displayError('An error has occurred: Cannot save the current tax rule.');
          }
        }
      }
    }
  }

  protected function updateTaxRulesGroup($object)
  {
    static $tax_rules_group = null;
    if ($tax_rules_group === null) {
      if( $this->_idShop == null && $object->id ){
        $object->setFieldsToUpdate($object->getFieldsShop());
      }
      $object->update();
      $tax_rules_group = $object;
    }

    return $tax_rules_group;
  }

  private function _checkImportedProduct( $idProduct )
  {
    $sql = 'SELECT count(*) as count
     FROM '._DB_PREFIX_.'simpleimport_products
     WHERE id_product = "'.(int)$idProduct.'"
     AND id_shop = "'.(int)$this->_idShop.'"
     ';

    $res = Db::getInstance()->executeS($sql);
    return (bool)$res[0]['count'];
  }

  private function _addImportedProduct( $idProduct )
  {
    $data = array(
      'id_product' => (int)$idProduct,
      'id_shop'    => (int)$this->_idShop,
    );

    Db::getInstance()->insert('simpleimport_products', $data);
  }

  private function _checkCombinationImage($idProduct)
  {
    $sql = 'SELECT count(*) as count
     FROM '._DB_PREFIX_.'simpleimport_images
     WHERE id_product = "'.(int)$idProduct.'"
     AND id_shop = "'.(int)$this->_idShop.'"
     ';

    $res = Db::getInstance()->executeS($sql);
    return (bool)$res[0]['count'];
  }


  private function _getExistsImageId( $imgUrl, $idProduct )
  {
    $sql = 'SELECT id_image
     FROM '._DB_PREFIX_.'simpleimport_images
     WHERE id_product = "'.(int)$idProduct.'"
     AND image_url = "'.pSQL($imgUrl).'"
     AND id_shop = "'.(int)$this->_idShop.'"
     ';

    $res = Db::getInstance()->executeS($sql);
    if( isset( $res[0] ) && $res[0] ){
      return $res[0]['id_image'];
    }

    return false;
  }

  private function _addCombinationImage( $imgUrl, $idProduct, $idImage, $processed = false )
  {
    $data = array(
      'image_url'  => pSQL($imgUrl),
      'id_product' => (int)$idProduct,
      'id_image'   => (int)$idImage,
      'id_shop'    => (int)$this->_idShop,
    );

    if( $processed ){
      $data['processed'] = $processed;
    }

    if( Tools::getValue('id_task') ){
      $data['id_task'] = Tools::getValue('id_task');
    }

    Module::getInstanceByName('simpleimportproduct')->updateImagesImportRunning();
    Db::getInstance()->insert('simpleimport_images', $data);
  }

  private function _getImagePath( $link )
  {
    $sql = 'SELECT *
     FROM '._DB_PREFIX_.'simpleimport_images_path
     WHERE image_url = "'.pSQL($link).'"
     ';

    $res = Db::getInstance()->executeS($sql);
    if( isset($res[0]) ){
      return $res[0];
    }

    return false;
  }

 private function _productImages($productId, $url_img, $img_alt)
 {

    $url_img = trim($url_img);
    $img_alt = trim($img_alt);
    $url_img = str_replace(' ','%20', $url_img);
    if( !$url_img ){
	    return false;
    }

    if( ($idImage = $this->_getExistsImageId($url_img, $productId)) ){
      $this->ids_images[$url_img] = $idImage;
      return true;
    }

   $imageData = $this->_getImagePath($url_img);
   if( !$imageData ){
     $this->_addImageToList($url_img);
   }

   if( $this->_baseConfig['images_stream'] ){
     $imagePath = true;
   }
   else{
     $imagePath = Module::getInstanceByName('simpleimportproduct')->copyImageForResize($url_img);
   }

    if($imagePath){
      $image = new Image();
      $image->id_product = $productId;
      $image->legend = @trim($img_alt);
      if( $this->_cover == 'no'){
      Image::deleteCover((int)$productId);
        $image->cover = 1;
        $image->position = 1;
        $this->_cover = 'yes';
      }
      if( ( $error = $image->validateFields(false, true) ) !== true ){
        $this->_createErrorsFile($error,'Product ID - ' . $productId);
        return false;
      }
      if( ( $error = $image->validateFieldsLang(false, true) ) !== true ){
        $this->_createErrorsFile($error,'Product ID - ' . $productId);
        return false;
      }
      $image->add();

      $all_img = $this->ids_images;
      $all_img[$url_img] = $image->id;
      $this->ids_images = $all_img;

      $new_path = $image->getPathForCreation();

      if( !$this->_baseConfig['images_stream'] ){
        if( !ImageManager::resize($imagePath, $new_path.'.'.$image->image_format, null, null, 'jpg', false) ){
          $this->_addCombinationImage($url_img, $productId, $image->id, 2);
          return false;
        }

        if( $this->_baseConfig['generate_thumbnails'] ){
          $imagesTypes = ImageType::getImagesTypes('products');
          foreach ($imagesTypes as $imageType)
          {
            ImageManager::resize($imagePath, $new_path.'-'. Tools::stripslashes($imageType['name']).'.'.$image->image_format, $imageType['width'], $imageType['height'], $image->image_format);
          }
        }
        $this->_addCombinationImage($url_img, $productId, $image->id, 1);
      }
      else{
        $this->_addCombinationImage($url_img, $productId, $image->id);
      }

      if( $this->_idShop == null && $image->id ){
        $image->setFieldsToUpdate($image->getFieldsShop());
      }
      $image->update();

    }
   else{
     if( $url_img ){
       $this->_createErrorsFile('"Image is not available for uploading, Image Url: ' . $url_img . '"' ,'Product ID - ' . $productId);
     }
   }
  }

  private function _productSuppliers($supplier, $productId){
    $sup = trim($supplier['supplier']);

    if($sup){

      $reference = '';
      if(isset($supplier['supplier_reference']) && $supplier['supplier_reference']){
        $reference = trim($supplier['supplier_reference']);
      }

      $price = '';
      if(isset($supplier['supplier_price']) && $supplier['supplier_price']){
        $price = $supplier['supplier_price'];
        $price = $this->_preparePrice($price);
      }

      $currency = '';
      if(isset($supplier['supplier_currency']) && $supplier['supplier_currency']){
        $currency = $supplier['supplier_currency'];
      }

      $id_shop_list = Shop::getContextListShopID();
      $isset_supplier = $this->_model->getSupplier($sup);

      if(!$isset_supplier){
        $supplier_obj = new Supplier();
        $supplier_obj->name = $sup;
        $supplier_obj->id_shop_list = $id_shop_list;
        $supplier_obj->active = 1;
        if( $this->_idShop == null && $supplier_obj->id ){
          $supplier_obj->setFieldsToUpdate($supplier_obj->getFieldsShop());
        }
        $supplier_obj->save();

        Db::getInstance()->insert('product_supplier', array('id_product' => (int)$productId, 'id_supplier' => (int)$supplier_obj->id, 'product_supplier_reference' => pSQL($reference), 'product_supplier_price_te' => pSQL($price), 'id_currency' => (int)$currency ), false, true, DB::ON_DUPLICATE_KEY);
      }
      else{
        if( Configuration::get('PS_MULTISHOP_FEATURE_ACTIVE') ){
          $supplier_obj = new Supplier((int)$isset_supplier['id_supplier']);
          $id_shop_list = Shop::getContextListShopID();
          $supplier_obj->id_shop_list = $id_shop_list;
          if( $this->_idShop == null && $supplier_obj->id ){
            $supplier_obj->setFieldsToUpdate($supplier_obj->getFieldsShop());
          }
          $supplier_obj->update();
        }
        Db::getInstance()->insert('product_supplier', array('id_product' => (int)$productId, 'id_supplier' => (int)$isset_supplier['id_supplier'], 'product_supplier_reference' => pSQL($reference), 'product_supplier_price_te' => pSQL($price), 'id_currency' => (int)$currency ), false, true, DB::ON_DUPLICATE_KEY);
      }
    }
  }

  private function _productFeatures($features, $productId){
    $rez = array();
    $id_shop_list = Shop::getContextListShopID();

    if( $features['features_name_import_method'] == 'feature_name' ){
      if( !isset($features['features_name']) ){
        return false;
      }
      if( $features['features_name'] == 'enter_manually' ){
        $feature_name = html_entity_decode( $features['features_name_manually'] );
      }
      else{
        $feature_name = trim($features['features_name']);
      }
      if( !$feature_name ){
        return false;
      }

      $isset_feature = $this->_model->getFeatures($feature_name, $this->_idLang, $this->_idShop);
    }

    if( $features['features_name_import_method'] == 'feature_id' ){
      if( $features['features_id'] == 'enter_manually' ){
        $isset_feature = (int)$features['features_name_manually'];
      }
      else{
        $isset_feature = (int)$features['features_id'];
      }

      if( !Feature::existsInDatabase($isset_feature, 'feature')){
        return false;
      }
    }

    if( $features['features_name_import_method'] == 'existing_feature' ){
      $isset_feature = (int)$features['existing_feature'];
      if( !Feature::existsInDatabase($isset_feature, 'feature')){
        return false;
      }
    }


    if(!$isset_feature){
      $feature = new Feature();
      $feature->name = $this->_createMultiLangField( $feature_name, $feature->name );
      $feature->id_shop_list = $id_shop_list;
      if( ( $error = $feature->validateFields(false, true) ) !== true ){
        $this->_createErrorsFile($error,'Product ID - ' . $productId);
        return false;
      }
      if( ( $error = $feature->validateFieldsLang(false, true) ) !== true ){
        $this->_createErrorsFile($error,'Product ID - ' . $productId);
        return false;
      }
      if( $this->_idShop == null && $feature->id ){
        $feature->setFieldsToUpdate($feature->getFieldsShop());
      }
      $feature->save();
      $id_feature = $feature->id;
    }
    else{
      if( Configuration::get('PS_MULTISHOP_FEATURE_ACTIVE') ){
        $feature= new Feature($isset_feature);
        $feature->id_shop_list = $id_shop_list;
        if( $this->_idShop == null && $feature->id ){
          $feature->setFieldsToUpdate($feature->getFieldsShop());
        }
        $feature->update();
      }
      $id_feature = $isset_feature;
    }

    $feature_value = trim($features['features_value']);

    if( !$feature_value && $feature_value != '0' ){
      return false;
    }
    if( $features['multiple_feature_values'] ){
       $feature_values = explode(',', $feature_value);
    }
    else{
      $feature_values = array($feature_value);
    }

    foreach ($feature_values as $key => $feature_value) {
      if( $features['features_value_import_method'] == 'feature_value' ){
        $feature_value = trim($feature_value);
        $isset_feature_val = $this->_model->getFeaturesValue($feature_value, $id_feature, $this->_idLang, $features['features_type']);
      }
      else{
        $isset_feature_val = $feature_value;
        if( !FeatureValue::existsInDatabase($isset_feature_val, 'feature_value') ){
          continue;
        }
      }
      if(!$isset_feature_val){
        $feature_val= new FeatureValue();
        $feature_val->id_feature = $id_feature;
        $feature_val->value = $this->_createMultiLangField( $feature_value, $feature_val->value );
        if( $features['features_type'] == 'feature_customized' ){
          $feature_val->custom = true;
        }
        if( ( $error = $feature_val->validateFields(false, true) ) !== true ){
          $this->_createErrorsFile($error,'Product ID - ' . $productId);
          return false;
        }
        if( ( $error = $feature_val->validateFieldsLang(false, true) ) !== true ){
          $this->_createErrorsFile($error,'Product ID - ' . $productId);
          return false;
        }
        if( $this->_idShop == null && $feature_val->id ){
          $feature_val->setFieldsToUpdate($feature_val->getFieldsShop());
        }
        $feature_val->save();
        $id_feature_val = $feature_val->id;
      }
      else{
        $id_feature_val = $isset_feature_val;
      }

      $rez[$key]['id_feature'] = $id_feature;
      $rez[$key]['id_feature_val'] = $id_feature_val;
    }

    return $rez;
  }

  private function _productDiscount($discounts, $productId){
    if( $discounts[0]['remove_specific_prices'] && !$this->_checkImportedProduct($productId)){
      $forDelete = SpecificPrice::getByProductId($productId);
      foreach( $forDelete as $sPrice ){
        if( $sPrice['id_shop'] == '0' || $sPrice['id_shop'] == $this->_idShop ){
          $sPriceDelete = new SpecificPrice($sPrice['id_specific_price']);
          $sPriceDelete->delete();
        }
      }
    }

    foreach ($discounts as $discount)
    {
      $reduction_tax = 1;

      if (isset($discount['reduction_tax_excl']) && $discount['reduction_tax_excl']) {
        $discount['reduction'] = $discount['reduction_tax_excl'];
        $reduction_tax = 0;
      } elseif(isset($discount['reduction_tax_incl']) && $discount['reduction_tax_incl']) {
        $discount['reduction'] = $discount['reduction_tax_incl'];
        $reduction_tax = 1;
      }

      if( isset( $discount['reduction'] ) && $discount['reduction'] ){
        $discount['reduction'] = $this->_preparePrice($discount['reduction']);
      }
      else{
        $discount['reduction'] = 0;
      }

      if( isset( $discount['fixed_price'] ) && $discount['fixed_price'] ){
        $discount['fixed_price'] = $this->_preparePrice($discount['fixed_price']);
      }
      else{
        $discount['fixed_price'] = 0;
      }

      if( !(float)$discount['reduction'] && !(float)$discount['fixed_price'] ){
        continue;
      }

      if( isset($discount['reduction_type']) && $discount['reduction_type'] ){
	      $reduction_type = trim($discount['reduction_type']);
      }
      else{
	      $reduction_type = false;
      }

      if($reduction_type == 'amount'){
        $reduction = $discount['reduction'];
      }
      else if($reduction_type == 'percentage'){
        $reduction = $discount['reduction']/100;
      }
      else{
        $reduction_type = 'amount';
        $reduction = $discount['reduction'];
      }

      if( isset($discount['reduction_from']) && $discount['reduction_from'] && $discount['reduction_from'] != '0000-00-00 00:00:00' ){
	    $reduction_from = trim($discount['reduction_from']);
		  $reduction_from = strtotime($reduction_from);
      }
      else{
	      $reduction_from = false;
      }

      if( $reduction_from ){
        $reduction_from = date('Y-m-d H:i:s', $reduction_from);
      }
      else{
        $reduction_from = '0000-00-00 00:00:00';
      }

      if( isset($discount['reduction_to']) && $discount['reduction_to'] && $discount['reduction_to'] != '0000-00-00 00:00:00' ){
	    $reduction_to = trim($discount['reduction_to']);
		  $reduction_to = strtotime($reduction_to);
      }
      else{
	      $reduction_to = false;
      }

      if( $reduction_to ){
        $reduction_to = date('Y-m-d H:i:s', $reduction_to);
      }
      else{
        $reduction_to = '0000-00-00 00:00:00';
      }

	if( isset($discount['fixed_price']) && $discount['fixed_price'] ){
		$price = trim($discount['fixed_price']);
	}
	else{
		$price = -1;
	}

	if( isset($discount['from_quantity']) && $discount['from_quantity'] ){
		$from_quantity = trim($discount['from_quantity']);
	}
	else{
		$from_quantity = 1;
	}

      $specific_price = new SpecificPrice();

      if( (float)$price > 0 ){
        $price = $this->_preparePrice($price);
      }

      if( (float)$reduction > 0 ){
        $reduction = $this->_preparePrice($reduction);
      }

      if( isset($discount['customer_id']) && $discount['customer_id'] ){
        $specificCuctomer = (int)$discount['customer_id'];
      }
      else{
        $specificCuctomer = 0;
      }

      if( isset($discount['customer_group_id']) && $discount['customer_group_id'] ){
        $specificCuctomerGroup = (int)$discount['customer_group_id'];
      }
      else{
        $specificCuctomerGroup = 0;
      }

      if( isset($discount['reduction_country_id']) && $discount['reduction_country_id'] ){
        $specificCountryId = (int)$discount['reduction_country_id'];
      }
      else{
        $specificCountryId = 0;
      }

      if( isset($discount['specific_prices_for']) && $discount['specific_prices_for'] ){
        $key = ((int)$discount['specific_prices_for'] - 1);
        if( isset( $this->_importedCombinations[$key] ) &&  $this->_importedCombinations[$key] ){
          $specific_price->id_product_attribute =  $this->_importedCombinations[$key];
        }
      }

      $specific_price->id_product = (int)$productId;
      $specific_price->id_specific_price_rule = 0;
      $specific_price->id_shop = (int)$this->_idShop;
      $specific_price->id_currency = !empty($discount['currency_id']) ? $discount['currency_id'] : 0;
      $specific_price->id_country = $specificCountryId;
      $specific_price->id_group = 0;
      $specific_price->price = $price;
      $specific_price->id_customer = $specificCuctomer;
      $specific_price->id_group = $specificCuctomerGroup;
      $specific_price->from_quantity = (int)$from_quantity;
      $specific_price->reduction = $reduction;
      $specific_price->reduction_tax = $reduction_tax;
      $specific_price->reduction_type = $reduction_type;
      $specific_price->from = $reduction_from;
      $specific_price->to = $reduction_to;
      if( ( $error = $specific_price->validateFields(false, true) ) !== true ){
        $this->_createErrorsFile($error,'Product ID - ' . $productId);
        return false;
      }
      if( $this->_idShop == null && $specific_price->id ){
        $specific_price->setFieldsToUpdate($specific_price->getFieldsShop());
      }
      $specific_price->save();
    }

    return true;
  }

  private function _generateAttributes( &$arr, $idx = 0 )
  {
    static $line = array();
    static $keys;
    static $max;
    static $results;
    if ($idx == 0) {
      $keys = array_keys($arr);
      $max = count($arr);
      $results = array();
    }
    if ($idx < $max) {
      $values = $arr[$keys[$idx]];
      foreach ($values as $value) {
        array_push($line, $value);
        $this->_generateAttributes($arr, $idx+1);
        array_pop($line);
      }
    } else {
      $results[] = $line;
    }
    if ($idx == 0) return $results;
  }

  private function _addVirtualProduct( $main, $productId, $object )
  {
    if( $object->getWsCombinations() ){
      $this->_createErrorsFile( 'A virtual product cannot have combinations.' , 'Product ID: ' . $productId );
      return false;
    }

    $fileSize = $this->_getFileSize( $main['virtual_product_url'] );
    $post_max_size = Tools::getMaxUploadSize(Configuration::get('PS_LIMIT_UPLOAD_FILE_VALUE') * 1024 * 1024);

    if( $fileSize > $post_max_size ){
      $error = sprintf(Tools::displayError('The uploaded file exceeds the "Maximum size for a downloadable product" set in preferences (%1$dMB) or the post_max_size/ directive in php.ini (%2$dMB).'), number_format((Configuration::get('PS_LIMIT_UPLOAD_FILE_VALUE'))), ($post_max_size / 1024 / 1024));
      $this->_createErrorsFile( $error, 'Product ID: ' . $productId );
      return false;
    }

    if( !$fileSize || $fileSize == -1 ){
      $this->_createErrorsFile( 'File for virtual product not found: ' . $main['virtual_product_url'] , 'Product ID: ' . $productId );
      return false;
    }
    else{
      $virtual_product_filename = ProductDownload::getNewFilename();
      if (!copy($main['virtual_product_url'], _PS_DOWNLOAD_DIR_.$virtual_product_filename)) {
        $this->_createErrorsFile( 'Can not upload virtual product file: ' . $main['virtual_product_url'] , 'Product ID: ' . $productId );
        return false;
      }

      $id_product_download = (int)ProductDownload::getIdFromIdProduct((int)$productId, false);
      $virtual_product_name = basename($main['virtual_product_url']);
      $virtual_product_nb_days = isset( $main['virtual_product_nb_days'] ) ? (int)$main['virtual_product_nb_days'] : 0;
      $virtual_product_nb_downloable = isset( $main['virtual_product_nb_downloable'] ) ? (int)$main['virtual_product_nb_downloable'] : 0;
      $virtual_product_expiration_date = isset( $main['virtual_product_expiration_date'] ) ? date('Y-m-d', strtotime($main['virtual_product_expiration_date'])) : '';

      $download = new ProductDownload((int)$id_product_download);
      $download->id_product = (int)$productId;
      $download->display_filename = $virtual_product_name;
      $download->filename = $virtual_product_filename;
      $download->date_add = date('Y-m-d H:i:s');
      $download->date_expiration = $virtual_product_expiration_date;
      $download->nb_days_accessible = (int)$virtual_product_nb_days;
      $download->nb_downloadable = (int)$virtual_product_nb_downloable;
      $download->active = 1;
      $download->is_shareable = (int)0;
      if( $this->_idShop == null && $download->id ){
        $download->setFieldsToUpdate($download->getFieldsShop());
      }
      if ($download->save()) {
        $object->is_virtual = true;
        return true;
      }
      else{
        $this->_createErrorsFile( 'Can not save virtual product' , 'Product ID: ' . $productId );
        return false;
      }
    }
  }

  private function _productCombinations($combinations_all, $productId, $productObject){
    $attributes = array();
    $values = array();
    $directValues = array();
    $img_attr = array();
    $id_attributes = array();
    $this->_importedCombinationsData = array();
    $combinationsKey = $combinations_all[0]['combination_key'];

    if( $combinations_all[0]['combinations_import_type'] == 'separated_field_value' && $combinationsKey == 'attributes' ){
      $newCombinations = array();
      $attrName = array();
      $attrType = array();
      $attrValues = array();
      $attrColor = array();

      foreach( $combinations_all[0]['single_attribute'] as $sKey => $singleAttribute ){
        if( $singleAttribute == 'enter_manually' ){
          $attrName[] = html_entity_decode($combinations_all[0]['manually_attribute'][$sKey]);
        }
        elseif( $singleAttribute == 'existing_attribute' ){
          $attrName[] = 'group_id_' . $combinations_all[0]['existing_attribute'][$sKey];
        }
        else{
          $attrName[] = $singleAttribute;
        }
        $attrType[] = $combinations_all[0]['single_type'][$sKey];
      }

      foreach( array_values($combinations_all[0]['single_value']) as $cKey => $sVal ){
        $attrValues[] = explode($combinations_all[0]['single_delimiter'][$cKey],$sVal);
      }

      foreach( array_values($combinations_all[0]['single_color']) as $cKey => $sVal ){
        $attrColor[] = explode($combinations_all[0]['single_delimiter'][$cKey],trim($sVal, $combinations_all[0]['single_delimiter'][$cKey]));
      }

      $colorAssociationArray = array();
      foreach( array_values($combinations_all[0]['single_type']) as $cKey => $sVal ){
        if ($sVal == 'color') {
          foreach ($attrValues[$cKey] as $key => $attrColVal) {
            $colorAssociationArray[$cKey][$attrColVal] = $attrColor[$cKey][$key];
          }
        }
      }

      $attrValues = $this->_generateAttributes($attrValues);

      foreach( $attrValues as $aKey => $attrValue ){
        $newCombinations[$aKey]['combinations_import_type'] = 'single_field_value';
        foreach( $attrValue as $gKey => $genValue ){
          $newCombinations[$aKey]['single_attribute'][$gKey] = $attrName[$gKey];
          $newCombinations[$aKey]['single_type'][$gKey] = $attrType[$gKey];
          $newCombinations[$aKey]['single_value'][$gKey] = $genValue;
          if( isset($colorAssociationArray[$gKey][$genValue]) ){
            $newCombinations[$aKey]['single_color'][$gKey] = $colorAssociationArray[$gKey][$genValue];
          }
          else{
            $newCombinations[$aKey]['single_color'][$gKey] = '';
          }
        }
      }

      $combinations_all = $newCombinations;
    }
    foreach($combinations_all as $k => $combinations){

      if( $combinationsKey == 'attributes' ){
        if( $combinations['combinations_import_type'] == 'one_field_combinations' ){
          if( $combinationsKey == 'attributes' && ( !isset($combinations['attribute']) || !$combinations['attribute'] || !isset($combinations['value']) || !$combinations['value'] ) ){
            continue;
          }
        }
        $attribut = array();
        $attribut_val = array();
        $attrType = array();

        if( $combinations['combinations_import_type'] == 'one_field_combinations' ){
          $attribut = explode(",", $combinations['attribute']);
          $attribut_val = explode(",", $combinations['value']);
        }

        if( $combinations['combinations_import_type'] == 'single_field_value' || $combinations['combinations_import_type'] == 'separate_combination_row' ){
          foreach( $combinations['single_attribute'] as $sKey => $singleAttribute ){

            if( $singleAttribute == 'enter_manually' ){
              $attribut[] = html_entity_decode($combinations['manually_attribute'][ $sKey ]);
            } elseif( $singleAttribute == 'existing_attribute' ){
              $attribut[] = 'group_id_' . $combinations['existing_attribute'][ $sKey ];
            } else{
              $attribut[] = $singleAttribute;
            }

            $attribut_val[] = $combinations['single_value'][ $sKey ];
            $attrType[] = $combinations['single_type'][ $sKey ];
          }
        }
        $id_attributes = array();
        foreach( $attribut as $key => $a ){
          $val_name = array();
          if( $combinations['combinations_import_type'] == 'one_field_combinations' ){
            $val_name = explode(":", $a);
          }
          if( $combinations['combinations_import_type'] == 'single_field_value' || $combinations['combinations_import_type'] == 'separate_combination_row' ){
            $val_name[0] = $a;
            $val_name[1] = $attrType[ $key ];
          }
          $val_name[0] = trim($val_name[0]);
          $val_name[1] = trim($val_name[1]);
          if( !$val_name[0] || ( !trim($attribut_val[ $key ]) && trim($attribut_val[ $key ]) != '0' ) ){
            continue;
          }

          if( strpos($val_name[0], 'group_id_') !== false ){
            $isset_group = str_replace('group_id_', '', $val_name[0]);
            if( !AttributeGroup::existsInDatabase($isset_group, 'attribute_group') ){
              continue;
            }
          } else{
            $isset_group = $this->_model->getGroupAttribute($val_name[0], $val_name[1], $this->_idLang);
          }

          if( !$isset_group ){
            if( $val_name[1] !== 'select' && $val_name[1] !== 'radio' && $val_name[1] !== 'color' ){
              $type = 'select';
            } else{
              $type = $val_name[1];
            }
            $obj = new AttributeGroup();
            $obj->name = $this->_createMultiLangField($val_name[0], $obj->name);
            $obj->public_name = $this->_createMultiLangField($val_name[0], $obj->public_name);
            $obj->group_type = $type;
            if( $type == 'color' ){
              $obj->is_color_group = 1;
            } else{
              $obj->is_color_group = 0;
            }

            if( ( $error = $obj->validateFields(false, true) ) !== true ){
              $this->_createErrorsFile($error, 'Product ID - ' . $productId);
              continue;
            }
            if( ( $error = $obj->validateFieldsLang(false, true) ) !== true ){
              $this->_createErrorsFile($error, 'Product ID - ' . $productId);
              continue;
            }

            if( $this->_idShop == null && $obj->id ){
              $obj->setFieldsToUpdate($obj->getFieldsShop());
            }
            $obj->save();
            $id_attribute_group = $obj->id;
          } else{
            if( Configuration::get('PS_MULTISHOP_FEATURE_ACTIVE') ){
              $attr = new AttributeGroup($isset_group);
              $id_shop_list = Shop::getContextListShopID();
              $attr->id_shop_list = $id_shop_list;
              if( $this->_idShop == null && $attr->id ){
                $attr->setFieldsToUpdate($attr->getFieldsShop());
              }
              $attr->update();
            }
            $id_attribute_group = $isset_group;
          }

          $attribut_val[ $key ] = explode(':', $attribut_val[ $key ]);
          $attribut_val[ $key ] = $attribut_val[ $key ][0];

          $isset_attribute = $this->_model->getAttribute(trim($attribut_val[ $key ]), $id_attribute_group, $this->_idLang);

          if( !$isset_attribute ){
            $attribute = new Attribute();
            $attribute->id_attribute_group = $id_attribute_group;
            $attribute->name = $this->_createMultiLangField(trim($attribut_val[ $key ]), $attribute->name);

            if( $this->isColorAttributeGroup($id_attribute_group) && $combinations['single_color'] && $this->getAttributeColorImportType($combinations['single_color'][ $key ]) == 'color_hex' ){
              $attribute->color = $combinations['single_color'][ $key ];
              unlink(_PS_IMG_DIR_ . 'co/' . $attribute->id . '.jpg');
            }

            if( ( $error = $attribute->validateFields(false, true) ) !== true ){
              $this->_createErrorsFile($error, 'Product ID - ' . $productId);
              continue;
            }
            if( ( $error = $attribute->validateFieldsLang(false, true) ) !== true ){
              $this->_createErrorsFile($error, 'Product ID - ' . $productId);
              continue;
            }
            if( $this->_idShop == null && $attribute->id ){
              $attribute->setFieldsToUpdate($attribute->getFieldsShop());
            }
            $attribute->save();
            $id_attributes[] = $attribute->id;

            if( $this->isColorAttributeGroup($id_attribute_group) && $combinations['single_color'][ $key ] && $this->getAttributeColorImportType($combinations['single_color'][ $key ]) == 'texture_image' ){
              if( getimagesize($combinations['single_color'][ $key ]) ){
                $this->setAttributeColorTexture($combinations['single_color'][ $key ], $attribute->id);
              }
            }
          } else{
            if( ( Configuration::get('PS_MULTISHOP_FEATURE_ACTIVE') ) || ( $this->isColorAttributeGroup($id_attribute_group) && $combinations['single_color'] ) ){
              $attr = new Attribute($isset_attribute);

              if( $this->isColorAttributeGroup($id_attribute_group) && $combinations['single_color'][ $key ] ){
                if( $this->getAttributeColorImportType($combinations['single_color'][ $key ]) == 'color_hex' ){
                  $attr->color = $combinations['single_color'][ $key ];
                  if( file_exists(_PS_IMG_DIR_ . 'co/' . $attr->id . '.jpg') ){
                    unlink(_PS_IMG_DIR_ . 'co/' . $attr->id . '.jpg');
                  }
                } elseif( $this->getAttributeColorImportType($combinations['single_color'][ $key ]) == 'texture_image' ){
                  if( getimagesize($combinations['single_color'][ $key ]) ){
                    $this->setAttributeColorTexture($combinations['single_color'][ $key ], $attr->id);
                  }
                }
              }

              if( Configuration::get('PS_MULTISHOP_FEATURE_ACTIVE') ){
                $id_shop_list = Shop::getContextListShopID();
                $attr->id_shop_list = $id_shop_list;
              }

              if( ( $error = $attr->validateFields(false, true) ) !== true ){
                $this->_createErrorsFile($error, 'Product ID - ' . $productId);
                continue;
              }
              if( ( $error = $attr->validateFieldsLang(false, true) ) !== true ){
                $this->_createErrorsFile($error, 'Product ID - ' . $productId);
                continue;
              }
              if( $this->_idShop == null && $attr->id ){
                $attr->setFieldsToUpdate($attr->getFieldsShop());
              }
              $attr->update();
            }

            $id_attributes[] = $isset_attribute;
          }
        }
    }

	if( $id_attributes || $combinationsKey != 'attributes' ){
		$attributes[] = $id_attributes;

		$this->_importedCombinationsData[] = array(
      'wholesale_price_combination' => isset($combinations['wholesale_price_combination']) ? $combinations['wholesale_price_combination'] : false,
      'final_price'                 => isset($combinations['final_price']) ? $combinations['final_price'] : false,
      'final_price_with_tax'        => isset($combinations['final_price_with_tax']) ? $combinations['final_price_with_tax'] : false,
      'impact_price'                => isset($combinations['impact_price']) ? $combinations['impact_price'] : false,
      'impact_price_with_tax'       => isset($combinations['impact_price_with_tax']) ? $combinations['impact_price_with_tax'] : false,
      'quantity'                    => isset($combinations['quantity']) ? $combinations['quantity'] : false,
    );

    if(isset( $combinations['final_price'] ) && $combinations['final_price'] != ''){

      if( ( $id_attributes && $combinationsKey != 'all_combinations') || ($combinationsKey == 'all_combinations' && $productObject->hasCombinations()) )
      {
        $tmpProd = new Product($productId, false);
        if( $tmpProd->price != $productObject->price ){
          $productObject->price = $tmpProd->price;
        }
      }

      $prod_price = (float)$productObject->price;
      $final_price = (float)$combinations['final_price'];

      $comb_price = $final_price - $prod_price;
      $combinations['impact_price'] = $this->_preparePrice($comb_price);
    }

    if(isset( $combinations['final_price_with_tax'] ) && $combinations['final_price_with_tax'] != ''){

      if( ( $id_attributes && $combinationsKey != 'all_combinations') || ($combinationsKey == 'all_combinations' && $productObject->hasCombinations()) )
      {
        $tmpProd = new Product($productId, false);
        if( $tmpProd->price != $productObject->price ){
          $productObject->price = $tmpProd->price;
        }
      }
	
	  $combinations['final_price_with_tax'] = str_replace(',','.', $combinations['final_price_with_tax']);
      $final_price_with_tax = (float)$combinations['final_price_with_tax'];

      $address = null;
      if (is_object(Context::getContext()->cart) && Context::getContext()->cart->{Configuration::get('PS_TAX_ADDRESS_TYPE')} != null) {
        $address = Context::getContext()->cart->{Configuration::get('PS_TAX_ADDRESS_TYPE')};
      }
      $taxRate = $productObject->getTaxesRate(new Address($address));
      $prod_price = (float)$productObject->price;
      $prod_price = $prod_price*(($taxRate/100)+1);

      $price = $final_price_with_tax - $prod_price;
      $price = $price/(($taxRate/100)+1);
      $combinations['impact_price'] = $price;
      $combinations['impact_price'] = $this->_preparePrice($combinations['impact_price']);
    }

    if(isset( $combinations['impact_price'] ) && $combinations['impact_price'] != ''){
      $combinations['impact_price'] = $this->_preparePrice($combinations['impact_price']);
    }

    if(isset( $combinations['impact_unit_price'] ) && $combinations['impact_unit_price'] != ''){
      $combinations['impact_unit_price'] = $this->_preparePrice($combinations['impact_unit_price']);
    }

    if(isset( $combinations['impact_price_with_tax'] ) && $combinations['impact_price_with_tax'] != ''){
      $combinations['impact_price_with_tax'] = $this->_preparePrice($combinations['impact_price_with_tax']);

      $address = null;
      if (is_object(Context::getContext()->cart) && Context::getContext()->cart->{Configuration::get('PS_TAX_ADDRESS_TYPE')} != null) {
        $address = Context::getContext()->cart->{Configuration::get('PS_TAX_ADDRESS_TYPE')};
      }
      $taxRate = $productObject->getTaxesRate(new Address($address));

      $combinations['impact_price'] = $combinations['impact_price_with_tax'] / (($taxRate/100)+1);
      $combinations['impact_price'] = $this->_preparePrice($combinations['impact_price']);
    }

    if( isset( $combinations['wholesale_price_combination'] ) && $combinations['wholesale_price_combination'] != '' ){
      $combinations['wholesale_price_combination'] = $this->_preparePrice($combinations['wholesale_price_combination']);
    }

    if( isset( $combinations['impact_weight'] ) && $combinations['impact_weight'] != '' ){
      $combinations['impact_weight'] = $this->_preparePrice($combinations['impact_weight']);
    }

    if( isset( $combinations['ecotax_combination'] ) && $combinations['ecotax_combination'] != '' ){
      $combinations['ecotax_combination'] = $this->_preparePrice($combinations['ecotax_combination']);
    }



    if(isset($combinations['available_date_combination']) && $combinations['available_date_combination'] !== ''){
      $combinations['available_date_combination'] = trim($combinations['available_date_combination']);
      $combinations['available_date_combination'] = strtotime($combinations['available_date_combination']);
      $combinations['available_date_combination'] = date('Y-m-d', $combinations['available_date_combination']);
      if (!Validate::isDateFormat($combinations['available_date_combination'])) {
        $this->_createErrorsFile('Available Date - date format is not valid for combination','Product ID: ' . $productId);
        $combinations['available_date_combination'] = '';
      }
    }

      if(isset($combinations['images_combination']) && $combinations['images_combination']){
        $img_products = explode(",", $combinations['images_combination']);
        foreach($img_products as $url_img){
	         $url_img = trim($url_img);
	         $url_img = str_replace(' ','%20', $url_img);
          $ids_images = $this->ids_images;
          if(isset($ids_images[$url_img]) && $ids_images[$url_img]){
            $img_attr[$k][] = $ids_images[$url_img];
          }
        }
      }


    $values[] = array(
        'id_product' => $productId,
        'price' => isset( $combinations['impact_price'] ) ? $combinations['impact_price'] : 0,
        'unit_price_impact' => isset( $combinations['impact_unit_price'] ) ? $combinations['impact_unit_price'] : 0,
        'weight' => isset( $combinations['impact_weight'] ) ? $combinations['impact_weight'] : 0,
        'id_warehouse' => isset( $combinations['id_warehouse_combination'] ) ? $combinations['id_warehouse_combination'] : 0,
        'warehouse_location' => isset( $combinations['warehouse_location_combination'] ) ? $combinations['warehouse_location_combination'] : '',
        'ecotax' => isset( $combinations['ecotax_combination'] ) ? $combinations['ecotax_combination'] : 0,
        'ean13' => isset( $combinations['ean13_combination'] ) ? $combinations['ean13_combination'] : '',
        'upc' => isset( $combinations['upc_combination'] ) ? $combinations['upc_combination'] : '',
        'isbn' => isset( $combinations['isbn_combination'] ) ? $combinations['isbn_combination'] : '',
        'wholesale_price' => isset( $combinations['wholesale_price_combination'] ) ? $combinations['wholesale_price_combination'] : 0,
        'minimal_quantity' => isset( $combinations['min_quantity_combination'] ) ? $combinations['min_quantity_combination'] : 0,
        'quantity' => isset( $combinations['quantity_combination'] ) ? (int)$combinations['quantity_combination'] : '',
        'quantity_method' => isset( $combinations['quantity_combination_method'] ) ? $combinations['quantity_combination_method'] : 'override',
        'location' => isset( $combinations['location_combination'] ) ? $combinations['location_combination'] : '',
        'low_stock_threshold' => isset( $combinations['low_stock_threshold_combination'] ) ? $combinations['low_stock_threshold_combination'] : '',
        'low_stock_alert' => isset( $combinations['low_stock_alert_combination'] ) ? $combinations['low_stock_alert_combination'] : '',
        'reference' => isset( $combinations['reference_combination'] ) ? pSQL($combinations['reference_combination']) : '',
        'available_date' => isset( $combinations['available_date_combination'] ) ? pSQL($combinations['available_date_combination']) : '',
        'default_on' => isset( $combinations['default'] ) ? (int)$combinations['default'] : 0,
        'suppliers' => isset( $combinations['suppliers'] ) ? $combinations['suppliers'] : '',
        'supplier_method_combination' => isset( $combinations['supplier_method_combination'] ) ? $combinations['supplier_method_combination'] : '',
    );

    $directValues[] = array(
      'id_product' => $productId,
      'price' => isset( $combinations['impact_price'] ) ? $combinations['impact_price'] : 'miss',
      'unit_price_impact' => isset( $combinations['impact_unit_price'] ) ? $combinations['impact_unit_price'] : 'miss',
      'weight' => isset( $combinations['impact_weight'] ) ? $combinations['impact_weight'] : 'miss',
      'id_warehouse' => isset( $combinations['id_warehouse_combination'] ) ? $combinations['id_warehouse_combination'] : 'miss',
      'warehouse_location' => isset( $combinations['warehouse_location_combination'] ) ? $combinations['warehouse_location_combination'] : 'miss',
      'ecotax' => isset( $combinations['ecotax_combination'] ) ? $combinations['ecotax_combination'] : 'miss',
      'ean13' => isset( $combinations['ean13_combination'] ) ? $combinations['ean13_combination'] : 'miss',
      'upc' => isset( $combinations['upc_combination'] ) ? $combinations['upc_combination'] : 'miss',
      'isbn' => isset( $combinations['isbn_combination'] ) ? $combinations['isbn_combination'] : 'miss',
      'wholesale_price' => isset( $combinations['wholesale_price_combination'] ) ? $combinations['wholesale_price_combination'] : 'miss',
      'minimal_quantity' => isset( $combinations['min_quantity_combination'] ) ? $combinations['min_quantity_combination'] : 'miss',
      'quantity' => isset( $combinations['quantity_combination'] ) ? (int)$combinations['quantity_combination'] : 'miss',
      'quantity_method' => isset( $combinations['quantity_combination_method'] ) ? $combinations['quantity_combination_method'] : 'miss',
      'location' => isset( $combinations['location_combination'] ) ? $combinations['location_combination'] : 'miss',
      'low_stock_threshold' => isset( $combinations['low_stock_threshold_combination'] ) ? $combinations['low_stock_threshold_combination'] : 'miss',
      'low_stock_alert' => isset( $combinations['low_stock_alert_combination'] ) ? $combinations['low_stock_alert_combination'] : 'miss',
      'reference' => isset( $combinations['reference_combination'] ) ? pSQL($combinations['reference_combination']) : 'miss',
      'available_date' => isset( $combinations['available_date_combination'] ) ? pSQL($combinations['available_date_combination']) : 'miss',
      'default_on' => isset( $combinations['default'] ) ? (int)$combinations['default'] : 'miss',
      'suppliers' => isset( $combinations['suppliers'] ) ? $combinations['suppliers'] : 'miss',
      'supplier_method_combination' => isset( $combinations['supplier_method_combination'] ) ? $combinations['supplier_method_combination'] : 'miss',
    );
  }
    }

    $rez = array();
    $rez['attributes'] = $attributes;
    $rez['values'] = $values;
    $rez['direct_values'] = $directValues;
    $rez['id_images'] = $img_attr;
    return $rez;
  }

  private function _productCategories($categories){
    $id_category = array();
    foreach($categories as $category){
      $count_cat = count($category);
      $parent_cat = Context::getContext()->shop->id_category;
      if( !$parent_cat ){
        $parent_cat = Configuration::get('PS_HOME_CATEGORY');
      }
      for($i = 0; $i < $count_cat; $i++){
        $cat = trim($category[$i]);

        if($cat){
          if($i == 0){
            if (!empty($this->_category_linking) && !empty($this->_category_linking[$cat])) {
              $isset_cat = $this->_category_linking[$cat]['id'];
            } else {
              $isset_cat = $this->_model->getCategoryByName($cat, $this->_idLang, $this->_idShop, $parent_cat);
            }

            if(!$isset_cat){
              $obj_cat = new Category(null, null, $this->_idShop);
              $obj_cat->name = $this->_createMultiLangField( $cat, $obj_cat->name );
              $obj_cat->link_rewrite = $this->_createMultiLangField( Tools::link_rewrite( $cat ), $obj_cat->link_rewrite);
              $obj_cat->id_parent = $parent_cat;
              if( ( $error = $obj_cat->validateFields(false, true) ) !== true ){
                $this->_createErrorsFile($error,'Category name - ' . $cat);
                continue;
              }
              if( ( $error = $obj_cat->validateFieldsLang(false, true) ) !== true ){
                $this->_createErrorsFile($error,'Category name - ' . $cat);
                continue;
              }
              if( $this->_idShop == null && $obj_cat->id ){
                $obj_cat->setFieldsToUpdate($obj_cat->getFieldsShop());
              }
              $obj_cat->save();
              $parent_cat = $obj_cat->id;
            }
            else{
              $obj_cat = new Category((int)$isset_cat, null, $this->_idShop);
              $obj_cat->groupBox = $obj_cat->getGroups();
              $obj_cat->update();

              $parent_cat = $isset_cat;
            }
          }
          else{
            if (!empty($this->_category_linking) && !empty($this->_category_linking[$cat])) {
              $isset_cat = $this->_category_linking[$cat]['id'];
            } else {
              $isset_cat = $this->_model->getCategoryByName($cat, $this->_idLang, $this->_idShop, $parent_cat );
            }

            if(!$isset_cat){
              $obj_cat = new Category(null, null, $this->_idShop);
              $obj_cat->name = $this->_createMultiLangField( $cat, $obj_cat->name );
              $obj_cat->link_rewrite = $this->_createMultiLangField( Tools::link_rewrite( $cat ), $obj_cat->link_rewrite );
              $obj_cat->id_parent = $parent_cat;
              if( ( $error = $obj_cat->validateFields(false, true) ) !== true ){
                $this->_createErrorsFile($error,'Category name - ' . $cat);
                continue;
              }
              if( ( $error = $obj_cat->validateFieldsLang(false, true) ) !== true ){
                $this->_createErrorsFile($error,'Category name - ' . $cat);
                continue;
              }
              if( $this->_idShop == null && $obj_cat->id ){
                $obj_cat->setFieldsToUpdate($obj_cat->getFieldsShop());
              }

              $obj_cat->save();
              $parent_cat = $obj_cat->id;
            }
            else{
              $obj_cat = new Category((int)$isset_cat, null, $this->_idShop);
              $obj_cat->groupBox = $obj_cat->getGroups();
              $obj_cat->update();

              $parent_cat = $isset_cat;
            }
          }
          $id_category[] = $parent_cat;
        }
      }
    }

    return $id_category;
  }

  private function _productManufacturer($manufacturer, $nameProduct)
  {
    $manufacturer = trim($manufacturer);
    $isset_manufacturer = $this->_model->getManufacturer($manufacturer);
    if(!$isset_manufacturer && $manufacturer){
      $manufacturer_obj = new Manufacturer();
      $manufacturer_obj->name = $manufacturer;
      $manufacturer_obj->active = 1;

      if( ( $error = $manufacturer_obj->validateFields(false, true) ) !== true ){
        $this->_createErrorsFile($error,$nameProduct);
        return false;
      }
      if( $this->_idShop == null && $manufacturer_obj->id ){
        $manufacturer_obj->setFieldsToUpdate($manufacturer_obj->getFieldsShop());
      }
      $manufacturer_obj->save();

      return $manufacturer_obj->id;
    }
    else{
      return $isset_manufacturer['id_manufacturer'];
    }
  }

  private function _createErrorsFile($error, $nameProduct)
  {
    $write_fd = fopen(_PS_MODULE_DIR_ . 'simpleimportproduct/error/'.$this->_importSettings.'error_logs.csv', 'a+');
    if (@$write_fd !== false){
      fwrite($write_fd, '"' . $nameProduct . '","' . $error . '"' . "\r\n");
    }
    fclose($write_fd);
    if( !Configuration::get('GOMAKOIL_IMPORT_IMAGES_ERROR_LOG', null, $this->_idShopGroup, $this->defaultIdShop) ){
      Configuration::updateValue('GOMAKOIL_IMPORT_IMAGES_ERROR_LOG', (int)1, false, $this->_idShopGroup, $this->defaultIdShop);
    }

    if( !$this->_productError ){
      Configuration::updateGlobalValue('GOMAKOIL_PRODUCTS_WITH_ERRORS', ((int)Configuration::getGlobalValue('GOMAKOIL_PRODUCTS_WITH_ERRORS')+1));
      $this->_productError = true;
    }
  }

  private function _createMultiLangField($field, $currentValue)
  {
    $languages = Language::getLanguages(false);
    $res = array();
    foreach ($languages as $lang){
      if( isset($currentValue[$lang['id_lang']]) && $currentValue[$lang['id_lang']] ){
        $res[$lang['id_lang']] = $currentValue[$lang['id_lang']];
      }
      else{
        $res[$lang['id_lang']] = $field;
      }

      if( $lang['id_lang'] == $this->_idLang ){
        $res[$lang['id_lang']] = $field;
      }
    }

    return $res;
  }

  public function isColorAttributeGroup($attribute_group_id)
  {
    $attr_group = Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'attribute_group` 
      WHERE `id_attribute_group` = ' . (int)$attribute_group_id);

    if ($attr_group[0]['is_color_group'] != 1 || $attr_group[0]['group_type'] != 'color') {
      return false;
    }

    return true;
  }

  /**
   *
   * @param $attribute_color_value
   * @return string
   *
   * Atribute color hexadecimal code or texture image
   */
  private function getAttributeColorImportType($attribute_color_value) 
  {
    $hex_check_regex = '/#([a-f]|[A-F]|[0-9]){3}(([a-f]|[A-F]|[0-9]){3})?\b/';

    if (!preg_match($hex_check_regex, $attribute_color_value)) {
      return 'texture_image';
    }

    return 'color_hex';
  }

  /**
   * @param $image_path
   * @param $attribute_id
   * @return bool
   */
  private function setAttributeColorTexture($image_path, $attribute_id)
  {
    $texture_img_path = _PS_IMG_DIR_ . 'co/' . $attribute_id . '.jpg';

    if (!copy($image_path, $texture_img_path)) {
      return false;
    }

    return true;
  }
}
