<?php
	

define('_PS_MODE_DEV_', false);
include(dirname(__FILE__).'/../../config/config.inc.php');
//Context::getContext()->controller = 'AdminModules';
//include(dirname(__FILE__).'/../../init.php');

if ( Tools::getValue('phpinfo') ){
  phpinfo();
  die;
}

  if ( Tools::getValue('rows_count') ){
    $sql = '
      SELECT count(`row`) all_rows, count( distinct `row`) as distinct_rows
      FROM '._DB_PREFIX_.'simpleimport_data p
    ';

    $res = Db::getInstance()->executes($sql);
    echo '<pre>';
    var_dump($res[0]);
    die;
  }

if ( !Tools::getValue('ajax') ){
  header('HTTP/1.0 403 Forbidden');
  echo 'You are forbidden!';
  die;
}
  if( !(int)Configuration::get('PS_SHOP_ENABLE') ){
    if (!in_array(Tools::getRemoteAddr(), explode(',', Configuration::get('PS_MAINTENANCE_IP')))) {
      if( !Configuration::get('PS_MAINTENANCE_IP') ){
        Configuration::updateValue('PS_MAINTENANCE_IP', Tools::getRemoteAddr() );
      }
      else{
        Configuration::updateValue('PS_MAINTENANCE_IP', Configuration::get('PS_MAINTENANCE_IP') . ',' . Tools::getRemoteAddr());
      }
    }
  }

  include(dirname(__FILE__).'/../../init.php');

try {
  ini_set("log_errors", 1);
  @error_reporting(E_ALL | E_STRICT);
  ini_set("error_log", _PS_MODULE_DIR_ . "simpleimportproduct/error/error.log");
  include_once(_PS_MODULE_DIR_.'simpleimportproduct/datamodel.php');
  include_once(_PS_MODULE_DIR_.'simpleimportproduct/import.php');
  require_once(_PS_MODULE_DIR_.'simpleimportproduct/simpleimportproduct.php');

  if(!class_exists('PHPExcel')){
    include_once(_PS_MODULE_DIR_ . 'simpleimportproduct/libraries/PHPExcel_1.7.9/Classes/PHPExcel.php');
    include_once(_PS_MODULE_DIR_ . 'simpleimportproduct/libraries/PHPExcel_1.7.9/Classes/PHPExcel/IOFactory.php');
  }
  include(_PS_MODULE_DIR_ .'simpleimportproduct/classes/myReadFilter.php');

  $simpleimportproduct = new Simpleimportproduct();
  $model = new importProductData();

  $json = array();
  $config  = array();

  if ( Tools::getValue('addCategories') == true){
    $json['page'] = Module::getInstanceByName('simpleimportproduct')->addCategories(Tools::getValue('key_settings'));
  }

  if ( Tools::getValue('addFeatures') == true){
    $json['page'] = Module::getInstanceByName('simpleimportproduct')->addFeatures(Tools::getValue('key_settings'));
  }

  if ( Tools::getValue('addCustomization') == true){
    $json['page'] = Module::getInstanceByName('simpleimportproduct')->addCustomization(Tools::getValue('key_settings'));
  }

  if ( Tools::getValue('addImages') == true){
    $json['page'] = Module::getInstanceByName('simpleimportproduct')->addImages(Tools::getValue('key_settings'));
  }

  if ( Tools::getValue('addAttachments') == true){
    $json['page'] = Module::getInstanceByName('simpleimportproduct')->addAttachments(Tools::getValue('key_settings'));
  }

  if ( Tools::getValue('addDiscount') == true){
    $json['page'] = Module::getInstanceByName('simpleimportproduct')->addDiscount(Tools::getValue('key_settings'));
  }

  if ( Tools::getValue('addCombinations') == true){
    $json['page'] = Module::getInstanceByName('simpleimportproduct')->addCombinations(Tools::getValue('key_settings'));
  }

  if ( Tools::getValue('moreImagesCombination') == true){
    $json['page'] = Module::getInstanceByName('simpleimportproduct')->moreImagesCombination(Tools::getValue('hidden_count_images'));
  }
  if ( Tools::getValue('moreCategory') == true){
    $json['page'] = Module::getInstanceByName('simpleimportproduct')->moreCategory(Tools::getValue('hidden_count_category'));
  }
  if ( Tools::getValue('moreSubcategory') == true){
    $json['page'] = Module::getInstanceByName('simpleimportproduct')->moreSubcategory(Tools::getValue('hidden_count_subcategory'));
  }
  if ( Tools::getValue('moreCombination') == true){
    $json['page'] = Module::getInstanceByName('simpleimportproduct')->moreCombination();
  }

  if ( Tools::getValue('moreSuppliers') == true){
    $json['page'] = Module::getInstanceByName('simpleimportproduct')->moreSuppliers(Tools::getValue('key_settings'));
  }

  if ( Tools::getValue('moreSuppliersCombination') == true){
    $json['page'] = Module::getInstanceByName('simpleimportproduct')->moreCombinationSuppliers(0, 0);
  }

  if ( Tools::getValue('addPriceCondition') == true){
    $json['page'] = Module::getInstanceByName('simpleimportproduct')->morePriceSettings();
  }

  if ( Tools::getValue('addFieldCondition') == true){
    $json['page'] = Module::getInstanceByName('simpleimportproduct')->moreFieldSettings();
  }

  if ( Tools::getValue('addQuantityCondition') == true){
    $json['page'] = Module::getInstanceByName('simpleimportproduct')->moreQuantitySettings();
  }

  if ( Tools::getValue('moreImages') == true){
    $json['page'] = Module::getInstanceByName('simpleimportproduct')->moreImages();
  }
  if ( Tools::getValue('moreFeatures') == true){
    $json['page'] = Module::getInstanceByName('simpleimportproduct')->moreFeatures();
  }
  if ( Tools::getValue('moreCustomization') == true){
    $json['page'] = Module::getInstanceByName('simpleimportproduct')->moreCustomization();
  }
  if ( Tools::getValue('moreAttachments') == true){
    $json['page'] = Module::getInstanceByName('simpleimportproduct')->moreAttachments();
  }
  if ( Tools::getValue('moreDiscount') == true){
    $json['page'] = Module::getInstanceByName('simpleimportproduct')->moreDiscount(Tools::getValue('key'));
  }
  if ( Tools::getValue('stepTwo') == true){

    if( !Tools::getValue('import_settings_name') ){
      throw new Exception( $simpleimportproduct->l('Please enter Settings Name!') );
    }

    if (isset($_FILES['file']) AND !empty($_FILES['file']['tmp_name']))
    {
      $file_name = $_FILES['file']['name'];
      $file_type = Tools::substr($file_name, strrpos($file_name, '.')+1);
      $file_type = Tools::strtolower($file_type);

      if( Module::getInstanceByName('simpleimportproduct')->checkImportRunning() ){
        throw new Exception($simpleimportproduct->l('Other import is running now. Please wait until it will finish.', 'send'));
      }

      if(Tools::getValue('format_file') == 'xlsx' && ($file_type == 'xlsx' || $file_type == 'xls')){
        if (!Tools::copy($_FILES['file']['tmp_name'],  _PS_MODULE_DIR_.'simpleimportproduct/data/import_products.xlsx')){
          throw new Exception($simpleimportproduct->l('An error occurred while uploading, file must be XLSX format', 'send'));
        }
        $delimiter_val = false;
      }elseif(Tools::getValue('format_file') == 'csv' && $file_type == 'csv'){
        if (!Tools::copy($_FILES['file']['tmp_name'],   _PS_MODULE_DIR_.'simpleimportproduct/data/import_products.csv')){
          throw new Exception($simpleimportproduct->l('An error occurred while uploading, file must be CSV format ', 'send'));
        }
        $delimiter_val = Tools::getValue('delimiter_val');
      }
      else{
        throw new Exception($simpleimportproduct->l('An error occurred while uploading, file must be ', 'send').Tools::getValue('format_file').' '.$simpleimportproduct->l('format', 'send'));
      }

      $name_fields_upload = array(array('name' => 'no'));
      if($file_type == 'xlsx' || $file_type == 'xls'){
        $format = PHPExcel_IOFactory::identify(_PS_MODULE_DIR_ . "simpleimportproduct/data/import_products.xlsx");
        $reader = PHPExcel_IOFactory::createReader($format);
        $chunkSize = 2;
        $chunkFilter = new myReadFilter();
        $reader->setReadFilter($chunkFilter);
        $chunkFilter->setRows(1,$chunkSize);
        $objPHPExcel = $reader->load(_PS_MODULE_DIR_ . "simpleimportproduct/data/import_products.xlsx");
      }
      elseif($file_type == 'csv'){
        $delimiter_for_reading = (Tools::getValue('delimiter_val') == 'tab') ? "\t" : Tools::getValue('delimiter_val');
        $reader = PHPExcel_IOFactory::createReader("CSV");
        $reader->setDelimiter($delimiter_for_reading);
        $encoding = mb_detect_encoding(Tools::file_get_contents("data/import_products.csv"), array('UTF-8','ISO-8859-1','ASCII','GBK'), TRUE);
        if( $encoding ){
          $reader->setInputEncoding($encoding);
        }

        $chunkSize = 2;
        $chunkFilter = new myReadFilter();
        $reader->setReadFilter($chunkFilter);
        $chunkFilter->setRows(1,$chunkSize);
        $objPHPExcel = $reader->load(_PS_MODULE_DIR_ . "simpleimportproduct/data/import_products.csv");
      }
      foreach ($objPHPExcel->getWorksheetIterator() as $worksheet) {
//        $highestRow         = $worksheet->getHighestRow(); // e.g. 10
        $highestColumn      = $worksheet->getHighestColumn(); // e.g 'F'
        $highestColumnIndex = PHPExcel_Cell::columnIndexFromString($highestColumn);
        for ($row = 1; $row <= 2; ++ $row) {
          for ($col = 0; $col < $highestColumnIndex; ++ $col) {
            $cell = $worksheet->getCellByColumnAndRow($col, $row);
            $val = $cell->getValue();
            if($row == 1){
              if( !Tools::getValue('use_headers') ){
                $val = 'Column ' . ($col + 1);
              }
              else{
                if( !$val ){
                  continue;
                }
              }
              $val = Module::getInstanceByName('simpleimportproduct')->cleanCsvLine($val);
              $name_fields_upload[] = array('name' => htmlentities(strip_tags($val)));
            }
          }
        }
      }

      if(Tools::getValue('import_type_val') == 'Add'){
        $parser_import_val = false;
      }
      else{
        $parser_import_val = Tools::getValue('parser_import_val');
      }
      $config = array(
        'format_file'                => ( Tools::getValue('format_file') ),
        'delimiter_val'              => ( $delimiter_val ),
        'import_type_val'            => ( Tools::getValue('import_type_val') ),
        'id_lang'                    => (int)Tools::getValue('id_lang'),
        'parser_import_val'          => ( $parser_import_val ),
        'name_fields_upload'         => ( $name_fields_upload ),
        'combination_identification' => ( Tools::getValue('combination_identification') ),
        'use_headers'                => ( Tools::getValue('use_headers') ),
        'disable_hooks'              => ( Tools::getValue('disable_hooks') ),
        'search_index'               => ( Tools::getValue('search_index') ),
        'products_range'             => ( Tools::getValue('products_range') ),
        'from_range'                 => ( Tools::getValue('from_range') ),
        'to_range'                   => ( Tools::getValue('to_range') ),
        'force_ids'                  => ( Tools::getValue('force_ids') ),
        'iteration'                  => ( Tools::getValue('iteration') ),
        'feed_source'                => ( Tools::getValue('feed_source') ),
        'import_settings_name'       => Tools::getValue('import_settings_name')
      );
      if( $config['products_range'] == 'range' ){
        if( (int)$config['from_range'] != $config['from_range'] || (int)$config['to_range'] != $config['to_range']
      || (int)$config['from_range'] > (int)$config['to_range']
        ){
          throw new Exception($simpleimportproduct->l('Products range is not valid', 'send'));
        }
      }

      $config_save = serialize($config);
      Configuration::updateValue('GOMAKOIL_CONFIG_IMPORT_PRODUCTS', $config_save, false, Tools::getValue('id_shop_group'), Tools::getValue('id_shop'));
      if( Tools::getValue('setting_id') ){
        $settings = Configuration::get('GOMAKOIL_IMPORT_PRODUCTS_'.Tools::getValue('setting_id'), null, Tools::getValue('id_shop_group'), Tools::getValue('id_shop'));
        $settings = Tools::unSerialize($settings);
        $fieldSettings = $settings['field_settings'];
        Module::getInstanceByName('simpleimportproduct')->addCustomFields( $fieldSettings, true );
      }

      $json['page'] = true;
    }
    else{
      throw new Exception($simpleimportproduct->l('Select file for import', 'send'));
    }
  }

  if ( Tools::getValue('save') == true ){
    $key_settings = Tools::getValue('key_settings');
    $config = array();
    $count_save = array();
    $config = Tools::unserialize(Configuration::get('GOMAKOIL_IMPORT_PRODUCTS_'.$key_settings,'',Tools::getValue('id_shop_group'), Tools::getValue('id_shop')));
    $count_save = Tools::unserialize(Configuration::get('GOMAKOIL_IMPORT_COUNT_SETTINGS',null,Tools::getValue('id_shop_group'),Tools::getValue('id_shop')));
    $base_field = $simpleimportproduct->encodeHeaders(Tools::getValue('field'));
    $field_category = $simpleimportproduct->encodeHeaders(Tools::getValue('field_category'));
    $import_from_categories = $simpleimportproduct->encodeHeaders(Tools::getValue('import_from_categories'));
    $import_from_suppliers = $simpleimportproduct->encodeHeaders(Tools::getValue('import_from_suppliers'));
    $import_from_brands = $simpleimportproduct->encodeHeaders(Tools::getValue('import_from_brands'));
    $price_settings = $simpleimportproduct->encodeHeaders(Tools::getValue('price_settings'));
    $field_settings = $simpleimportproduct->encodeHeaders(Tools::getValue('field_settings'));
    $quantity_settings = $simpleimportproduct->encodeHeaders(Tools::getValue('quantity_settings'));
    $field_combinations = $simpleimportproduct->encodeHeaders(Tools::getValue('field_combinations'));
    $field_discount = $simpleimportproduct->encodeHeaders(Tools::getValue('field_discount'));
    $field_images = $simpleimportproduct->encodeHeaders(Tools::getValue('field_images'));
    $field_featured = $simpleimportproduct->encodeHeaders(Tools::getValue('field_featured'));
    $field_customization = $simpleimportproduct->encodeHeaders(Tools::getValue('field_customization'));
    $field_attachments = $simpleimportproduct->encodeHeaders(Tools::getValue('field_attachments'));
    $field_accessories = $simpleimportproduct->encodeHeaders(Tools::getValue('field_accessories'));
    $field_pack_products = $simpleimportproduct->encodeHeaders(Tools::getValue('field_pack_products'));
    $field_suppliers = $simpleimportproduct->encodeHeaders(Tools::getValue('field_suppliers'));

    $category_linking_active = Tools::getValue('category_linking_active');
    $category_linking = json_decode(Tools::getValue('category_linking'), true);

    if( !Tools::getValue('notification_emails') && Tools::getValue('notification_emails') !== false ){
      //throw new Exception( $simpleimportproduct->l('Please enter at least one email for Automatic Products Import Report!', 'send') );
    }

    $baseSettings = Configuration::get('GOMAKOIL_CONFIG_IMPORT_PRODUCTS', false, Tools::getValue('id_shop_group'), Tools::getValue('id_shop'));
    $baseSettings = unserialize($baseSettings);

    Module::getInstanceByName('simpleimportproduct')->addCustomFields( $field_settings, true );


    if((isset($key_settings) &&  $key_settings)){
      $key = $key_settings;
      $name = $config['name_save'];

      if($name !== Tools::getValue('name_save')){
        $config = array(
          'base_field'          => ($base_field),
          'field_category'      => ($field_category),
          'import_from_categories'      => ($import_from_categories),
          'import_from_suppliers'      => ($import_from_suppliers),
          'import_from_brands'      => ($import_from_brands),
          'price_settings'      => ($price_settings),
          'field_settings'      => ($field_settings),
          'quantity_settings'      => ($quantity_settings),
          'category_linking_active'    => ($category_linking_active),
          'category_linking'    => ($category_linking),
          'field_discount'      => ($field_discount),
          'field_images'        => ($field_images),
          'field_combinations'  => ($field_combinations),
          'field_featured'      => ($field_featured),
          'field_customization' => ($field_customization),
          'field_attachments'   => ($field_attachments),
          'field_accessories'   => ($field_accessories),
          'field_pack_products' => ($field_pack_products),
          'field_suppliers'     => ($field_suppliers),
          'name_save'           => (Tools::getValue('name_save')),
          'notification_emails' => (Tools::getValue('notification_emails')),
          'base_settings'       => $baseSettings,
        );
        $setting =  end($count_save)+1;
        $count_save[] = $setting;
      }
      else {
        $config = array(
          'base_field'          => ($base_field),
          'field_category'      => ($field_category),
          'import_from_categories'      => ($import_from_categories),
          'import_from_suppliers'      => ($import_from_suppliers),
          'import_from_brands'      => ($import_from_brands),
          'price_settings'      => ($price_settings),
          'field_settings'      => ($field_settings),
          'quantity_settings'      => ($quantity_settings),
          'category_linking_active'    => ($category_linking_active),
          'category_linking'    => ($category_linking),
          'field_discount'      => ($field_discount),
          'field_images'        => ($field_images),
          'field_combinations'  => ($field_combinations),
          'field_featured'      => ($field_featured),
          'field_customization' => ($field_customization),
          'field_attachments'   => ($field_attachments),
          'field_suppliers'     => ($field_suppliers),
          'field_accessories'   => ($field_accessories),
          'field_pack_products' => ($field_pack_products),
          'name_save'           => (Tools::getValue('name_save')),
          'notification_emails' => (Tools::getValue('notification_emails')),
          'base_settings'       => $baseSettings,
        );
        $setting = $key;
      }
    }
    else{
      $config = array(
        'base_field'          => ($base_field),
        'field_category'      => ($field_category),
        'import_from_categories'      => ($import_from_categories),
        'import_from_suppliers'      => ($import_from_suppliers),
        'import_from_brands'      => ($import_from_brands),
        'price_settings'      => ($price_settings),
        'field_settings'      => ($field_settings),
        'quantity_settings'      => ($quantity_settings),
        'category_linking_active'    => ($category_linking_active),
        'category_linking'    => ($category_linking),
        'field_discount'      => ($field_discount),
        'field_images'        => ($field_images),
        'field_combinations'  => ($field_combinations),
        'field_featured'      => ($field_featured),
        'field_customization' => ($field_customization),
        'field_suppliers'     => ($field_suppliers),
        'field_attachments'   => ($field_attachments),
        'field_accessories'   => ($field_accessories),
        'field_pack_products' => ($field_pack_products),
        'name_save'           => (Tools::getValue('name_save')),
        'notification_emails' => (Tools::getValue('notification_emails')),
        'base_settings'       => $baseSettings,
      );

      if(@!end($count_save) ){
        $setting = 1;
      }
      else{
        $setting = end($count_save)+1;
      }
      $count_save[] = $setting;
    }


    $config_save =serialize($config);
    Configuration::updateValue('GOMAKOIL_IMPORT_PRODUCTS_'.$setting, $config_save,false, Tools::getValue('id_shop_group'), Tools::getValue('id_shop') );

    Configuration::updateValue('GOMAKOIL_IMPORT_COUNT_SETTINGS' , serialize($count_save), false, Tools::getValue('id_shop_group'), Tools::getValue('id_shop') );



    $json['count_settings'] = $setting;
    $json['success'] = $simpleimportproduct->l('Data successfully saved!', 'send');
  }

  if ( Tools::getValue('import') == true){
    if( !Tools::getValue('limit') ){
      Module::getInstanceByName('simpleimportproduct')->resetImportStatus();
    }
    $id_shop = Tools::getValue('id_shop');
    $base_field = $simpleimportproduct->encodeHeaders(Tools::getValue('field'));
    $field_category = $simpleimportproduct->encodeHeaders(Tools::getValue('field_category'));
    $import_from_categories = $simpleimportproduct->encodeHeaders(Tools::getValue('import_from_categories'));
    $import_from_suppliers = $simpleimportproduct->encodeHeaders(Tools::getValue('import_from_suppliers'));
    $import_from_brands = $simpleimportproduct->encodeHeaders(Tools::getValue('import_from_brands'));
    $price_settings = $simpleimportproduct->encodeHeaders(Tools::getValue('price_settings'));
    $field_settings = $simpleimportproduct->encodeHeaders(Tools::getValue('field_settings'));
    $quantity_settings = $simpleimportproduct->encodeHeaders(Tools::getValue('quantity_settings'));
    $field_combinations = $simpleimportproduct->encodeHeaders(Tools::getValue('field_combinations'));
    $field_discount = $simpleimportproduct->encodeHeaders(Tools::getValue('field_discount'));
    $field_images = $simpleimportproduct->encodeHeaders(Tools::getValue('field_images'));
    $field_featured = $simpleimportproduct->encodeHeaders(Tools::getValue('field_featured'));
    $field_suppliers = $simpleimportproduct->encodeHeaders(Tools::getValue('field_suppliers'));
    $field_customization = $simpleimportproduct->encodeHeaders(Tools::getValue('field_customization'));
    $field_attachments = $simpleimportproduct->encodeHeaders(Tools::getValue('field_attachments'));
    $field_accessories = $simpleimportproduct->encodeHeaders(Tools::getValue('field_accessories'));
    $field_pack_products = $simpleimportproduct->encodeHeaders(Tools::getValue('field_pack_products'));

    $category_linking_active = Tools::getValue('category_linking_active');
    $category_linking = json_decode(Tools::getValue('category_linking'), true);

    $config = array(
      'base_field'          => ($base_field),
      'field_category'      => ($field_category),
      'import_from_categories'      => ($import_from_categories),
      'import_from_suppliers'      => ($import_from_suppliers),
      'import_from_brands'      => ($import_from_brands),
      'price_settings'      => ($price_settings),
      'field_settings'      => ($field_settings),
      'quantity_settings'      => ($quantity_settings),
      'category_linking_active' => ($category_linking_active),
      'category_linking'      => ($category_linking),
      'field_discount'      => ($field_discount),
      'field_images'      => ($field_images),
      'field_combinations'  => ($field_combinations),
      'field_featured'      => ($field_featured),
      'field_suppliers'      => ($field_suppliers),
      'field_customization' => ($field_customization),
      'field_attachments' => ($field_attachments),
      'field_accessories'   => ($field_accessories),
      'field_pack_products' => ($field_pack_products),
    );

    $config_step_one = Tools::unserialize(Configuration::get('GOMAKOIL_CONFIG_IMPORT_PRODUCTS', null, Tools::getValue('id_shop_group'), Tools::getValue('id_shop')));
    if( isset($config_step_one['disable_hooks']) && $config_step_one['disable_hooks'] ){
      define('PS_INSTALLATION_IN_PROGRESS', true);
    }

    if( !Tools::getValue('limit') ){
      Configuration::updateGlobalValue( 'GOMAKOIL_SETTINGS_FOR_IMAGES', serialize($config) );
    }

    $import = new importProducts($config ,$id_shop, Tools::getValue('id_shop_group'), Tools::getValue('limit'));

    $res = $import->import();
    $res['count'] = $simpleimportproduct->l('Imported', 'send'). ' ' . Configuration::get('GOMAKOIL_IMPORT_PRODUCTS_COUNT', null, Tools::getValue('id_shop_group'), $id_shop) . ' '  .$simpleimportproduct->l('products', 'send');
    $json = $res;
  }

  if ( Tools::getValue('remove') == true){
    $key = Tools::getValue('key');
    $key = pSQL($key);
    Db::getInstance()->delete('simpleimport_tasks', "import_settings=$key");

    Configuration::deleteByName('GOMAKOIL_IMPORT_PRODUCTS_'.$key);

    $count_save = Tools::unserialize(Configuration::get('GOMAKOIL_IMPORT_COUNT_SETTINGS',null,Tools::getValue('id_shop_group'),Tools::getValue('id_shop')));

    $key = array_search($key, $count_save);
    unset($count_save[$key]);

    Configuration::updateValue('GOMAKOIL_IMPORT_COUNT_SETTINGS' , serialize($count_save), false, Tools::getValue('id_shop_group'), Tools::getValue('id_shop') );

    $json['success'] = $simpleimportproduct->l('Import settings successfully removed!', 'send');
  }

  if ( Tools::getValue('processImages') == true){

    $config_step_one = Tools::unserialize(Configuration::get('GOMAKOIL_CONFIG_IMPORT_PRODUCTS', null, Tools::getValue('id_shop_group'), Tools::getValue('id_shop')));
    if( !$config_step_one['images_stream'] && !Tools::getValue('id_task') ){
      return true;
    }

//    Module::getInstanceByName('simpleimportproduct')->runImagesCopy( 6, 'generate' );

    $config = unserialize(Configuration::getGlobalValue('GOMAKOIL_SETTINGS_FOR_IMAGES'));
    if( is_array($config) ){
      $automatic = false;
      if( isset($config['base_settings']) && isset($config['base_settings']['automatic']) && $config['base_settings']['automatic'] ){
        $automatic = true;
      }
      $import = new importProducts($config ,Tools::getValue('id_shop'), Tools::getValue('id_shop_group'), 0, $automatic);
      $import->searchImages();
    }
    Configuration::updateGlobalValue( 'GOMAKOIL_SETTINGS_FOR_IMAGES', false );

    Module::getInstanceByName('simpleimportproduct')->runImagesCopy(1);
  }

  if ( Tools::getValue('returnCount') == true){

    if( Tools::getValue('start_import_running') ){
      checkNewVersion();
    }

    $progress = getProgressTemplate();
    $json['progress'] = $progress['template'];
    $json['import_running'] = 0;
    $json['import_finished'] = $progress['import_finished'];
    $json['need_copy_image'] = $progress['need_copy_image'];

    if( Module::getInstanceByName('simpleimportproduct')->checkImportRunning() || Configuration::getGlobalValue('GOMAKOIL_SETTINGS_FOR_IMAGES') ){
      $json['import_running'] = 1;
    }
    else{
//      Module::getInstanceByName('simpleimportproduct')->truncateImageTable();
    }
  }

  if ( Tools::getValue('copyImages') == true){
    $needCopy = (int)Module::getInstanceByName('simpleimportproduct')->copyImages();
    if( !$needCopy && Configuration::getGlobalValue('GOMAKOIL_SETTINGS_FOR_IMAGES') ){
      $needCopy = 1;
    }
    if( !Module::getInstanceByName('simpleimportproduct')->checkImportRunning() ){
      $needCopy = 0;
    }

    if( $needCopy ){
      Module::getInstanceByName('simpleimportproduct')->runImagesCopy();
    }
    else{
      Module::getInstanceByName('simpleimportproduct')->runImagesCopy( 1, 'generate' );
    }
  }

  if ( Tools::getValue('generateThumbnails') == true){
    $needGenerate = Module::getInstanceByName('simpleimportproduct')->checkThumbnails();

    if( !(int)$needGenerate && !Module::getInstanceByName('simpleimportproduct')->checkImportRunning( true ) ){
      $needGenerate = 0;
      Module::getInstanceByName('simpleimportproduct')->updateImagesImportRunning(true);
    }
    else{
      if( !Module::getInstanceByName('simpleimportproduct')->getImageListsCount() ){
        $needGenerate = 0;
        Module::getInstanceByName('simpleimportproduct')->updateImagesImportRunning(true);
      }
      elseif( !Module::getInstanceByName('simpleimportproduct')->checkImportRunning() ){
        $needGenerate = 0;
      }
      else{
        $needGenerate = 1;
      }
    }

    if( $needGenerate ){
      Module::getInstanceByName('simpleimportproduct')->runImagesCopy( 1, 'generate' );
    }

    if(!Module::getInstanceByName('simpleimportproduct')->checkImportRunning()){
      Module::getInstanceByName('simpleimportproduct')->sendEmail();
    }
  }

  if( Tools::getValue('runGenerateThumbnails') ){
    Module::getInstanceByName('simpleimportproduct')->generateThumbnails();
  }

  echo Tools::jsonEncode($json);
}
catch( Exception $e ){
  $json['error'] = $e->getMessage();
  echo Tools::jsonEncode($json);
}

function checkNewVersion(){

  $versionDateId = Configuration::getIdByName('GOMAKOIL_IMPORT_VERSION', 0 ,0);
  $needUpdate = false;
  if( $versionDateId ){
    $versionConf = new Configuration($versionDateId);
    if(( time()-strtotime($versionConf->date_upd) ) > ( 10*24*3600 ) ){
      $needUpdate = true;
    }
  }

  if( !$versionDateId || $needUpdate ){
    $url = 'https://myprestamodules.com/modules/mpm_newsletters/send.php?get_module_version=true&ajax=true&module=38';

    $res = Tools::file_get_contents($url);

    if( $res ){
      $version = Tools::jsonDecode($res);
      $version = $version->module_version;
    }

    if( $versionDateId ){
	  $versionConf->value = $version;
      $versionConf->date_upd = date('Y-m-d H:i:s');
      $versionConf->update();
    }
    else{
      ConfigurationCore::updateGlobalValue('GOMAKOIL_IMPORT_VERSION', $version);
    }
  }

}

function getProgressTemplate()
{
  $data = Context::getContext()->smarty->createTemplate(_PS_MODULE_DIR_ . 'simpleimportproduct/views/templates/hook/import-progress.tpl');
  $current = (int)Configuration::getGlobalValue('GOMAKOIL_CURRENTLY_IMPORTED');
  $total = (int)Configuration::getGlobalValue('GOMAKOIL_PRODUCTS_FOR_IMPORT');
  $error_products = Configuration::getGlobalValue('GOMAKOIL_PRODUCTS_WITH_ERRORS');
  $error_message = false;
  $importFinished = false;

  $progress = 0;
  if( $total ){
    $progress = ($current/$total) * 100;
    $progress = round($progress);
  }

  $duration = Module::getInstanceByName('simpleimportproduct')->getImportTimeDuration();

  $finished = Tools::getValue('finish');

  if( Tools::getValue('error') ){
    if( !(int)Tools::getValue('error') ){
      $error_message = Tools::getValue('error');
    }
    else{
      $error_message = 'fatal';
    }

    $importFinished = true;
  }
  elseif( $finished == 2 ){
    $status = 'Stopped';
    Configuration::updateGlobalValue('GOMAKOIL_IMPORT_STATUS', $status);
    $importFinished = true;
  }
  elseif( $finished == 3 ){
    Configuration::updateGlobalValue('GOMAKOIL_IMPORT_STATUS', 'Stopping');
    $importFinished = true;
    if( !Module::getInstanceByName('simpleimportproduct')->checkImportRunning( true ) ){
      $status = 'Stopped';
      Configuration::updateGlobalValue('GOMAKOIL_IMPORT_STATUS', $status);
    }
  }
  elseif( !Module::getInstanceByName('simpleimportproduct')->checkImportRunning( true ) && Module::getInstanceByName('simpleimportproduct')->checkImportRunning() ){
    $status = 'Images importing';
    Configuration::updateGlobalValue('GOMAKOIL_IMPORT_STATUS', $status);
  }
  elseif( !Module::getInstanceByName('simpleimportproduct')->checkImportRunning() && !Tools::getValue('start_import_running') && !Module::getInstanceByName('simpleimportproduct')->getNeedThumbnailsCount(0) ){
    Configuration::updateGlobalValue('GOMAKOIL_IMPORT_STATUS', 'Completed');
    $importFinished = true;
  }

  $images_data = array();
  $images_data['copied'] = Module::getInstanceByName('simpleimportproduct')->getImageListsCount(1);
  $images_data['need_copy'] = Module::getInstanceByName('simpleimportproduct')->getImageListsCount();
  $images_data['skipped'] = Module::getInstanceByName('simpleimportproduct')->getImageListsCount(2);

  $images_data['thumbnails_generated'] = Module::getInstanceByName('simpleimportproduct')->getNeedThumbnailsCount(1);
  $images_data['thumbnails_total'] = Module::getInstanceByName('simpleimportproduct')->getNeedThumbnailsCount();
  $images_data['duration'] = Module::getInstanceByName('simpleimportproduct')->getImportTimeDuration(true);

  if( Configuration::getGlobalValue('GOMAKOIL_GENERATE_THUMBNAILS') ){
    $typesCount = ImageType::getImagesTypes('products');
    $typesCount = (int)count($typesCount);

    $images_data['thumbnails_generated'] = $images_data['thumbnails_generated']* $typesCount;
    $images_data['thumbnails_total'] = $images_data['thumbnails_total']* $typesCount;
  }

  $imagesImported = ( $images_data['copied'] + $images_data['skipped'] + $images_data['thumbnails_generated'] );
  $imagesForImport = ( $images_data['need_copy'] + $images_data['thumbnails_total']);

  if( !$imagesForImport ){
    $images_data['progress'] = 0;
  }
  else{
    $images_data['progress'] = $imagesImported / $imagesForImport *100;
  }

  $images_data['progress'] = round($images_data['progress']);

  $status = Configuration::getGlobalValue('GOMAKOIL_IMPORT_STATUS');
  if( $importFinished ){
    Module::getInstanceByName('simpleimportproduct')->resetImportStatus();
  }

  $data->assign(
    array(
      'status'         => $status,
      'current'        => $current,
      'total'          => $total,
      'progress'       => $progress,
      'duration'       => $duration,
      'finished'       => $importFinished,
      'error_products' => $error_products,
      'log_folder'     => _PS_BASE_URL_.__PS_BASE_URI__.'modules/simpleimportproduct/error/',
      'error_message'  => $error_message,
      'images_data'    => $images_data
    )
  );
  return array(
    'template'        => $data->fetch(),
    'import_finished' => $importFinished,
    'need_copy_image' => (int)$images_data['thumbnails_total']
  );
}