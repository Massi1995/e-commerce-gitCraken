<?php
	
@ini_set('display_errors', 'off');
error_reporting(0);

if(!class_exists('PHPExcel')){
  include_once(_PS_MODULE_DIR_ . 'simpleimportproduct/libraries/PHPExcel_1.7.9/Classes/PHPExcel.php');
  include_once(_PS_MODULE_DIR_ . 'simpleimportproduct/libraries/PHPExcel_1.7.9/Classes/PHPExcel/IOFactory.php');
}
  include(_PS_MODULE_DIR_ .'simpleimportproduct/classes/myReadFilter.php');

require_once(_PS_MODULE_DIR_ . 'simpleimportproduct/simpleimportproduct.php');

class AdminProductsimportController extends ModuleAdminController
{
  public function __construct()
  {
    parent::__construct();
    if (Tools::getValue('secure_key') !== false) {
      if( Tools::getValue('secure_key') == Configuration::getGlobalValue('GOMAKOIL_IMPORT_TASKS_KEY') ){
        $this->_automaticImport();
        die;
      }
      else{
        die('Invalid secure_key');
      }
    }


    $config_step_one = Tools::unserialize(Configuration::get('GOMAKOIL_CONFIG_IMPORT_PRODUCTS', null, Tools::getValue('id_shop_group'), Tools::getValue('id_shop')));
    if( isset($config_step_one['disable_hooks']) && $config_step_one['disable_hooks'] ){
      define('PS_INSTALLATION_IN_PROGRESS', true);
    }
  }

  private function _automaticImport()
  {
    $this->_sendCallback();
    ob_start();

    $this->_checkStuckImport();
    $this->_runTasks();
    $this->_checkQueue();

    ob_end_clean();
  }

  private function _checkStuckImport()
  {
    if( !Module::getInstanceByName('simpleimportproduct')->checkImportRunning() ){
      $stuckTasks = $this->_getStuckTask();
      foreach( $stuckTasks as $task ){
        if( Module::getInstanceByName('simpleimportproduct')->checkProductsForImport( $task['id_task'] ) ){
          Module::getInstanceByName('simpleimportproduct')->updateImagesImportRunning();
          $automaticLink = Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.basename(_PS_MODULE_DIR_).'/simpleimportproduct/automatic_import.php?settings='.$task['import_settings'].'&id_shop_group='.Tools::getValue('id_shop_group').'&id_shop='.Tools::getValue('id_shop').'&id_lang='.Context::getContext()->language->id.'&secure_key='.Configuration::getGlobalValue('GOMAKOIL_IMPORT_TASKS_KEY').'&id_task='.$task['id_task'].'&run_stuck_import=1&limit=1';
          Module::getInstanceByName('simpleimportproduct')->runImagesCopy( 1, 'link', $automaticLink );
          return true;
        }
      }
    }

    return false;
  }

  private function _getStuckTask()
  {
    $sql = '
        SELECT * 
        FROM ' . _DB_PREFIX_ . 'simpleimport_tasks as t
        WHERE last_finish = ""
    ';

    $res = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
    return $res;
  }

  private function _runTasks()
  {
    $id_shop = (int)Tools::getValue('id_shop');
    $id_shop_group = (int)Tools::getValue('id_shop_group');

    $sql = '
      SELECT * 
      FROM ' . _DB_PREFIX_ . 'simpleimport_tasks as t
      WHERE id_shop = ' . (int)$id_shop . '
      AND id_shop_group = ' . (int)$id_shop_group . '
      AND active = 1
    ';

    $res = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
    if( $res ){
      foreach( $res as $task ){
        if ($this->_shouldBeExecuted($task) == true) {
          Module::getInstanceByName('simpleimportproduct')->addTaskToQueue( $task['id_task'], true );
        }
      }
    }
  }

  private function _getTaskInfo( $idTask )
  {
    $id_shop = (int)Tools::getValue('id_shop');
    $id_shop_group = (int)Tools::getValue('id_shop_group');
    $sql = '
      SELECT * 
      FROM ' . _DB_PREFIX_ . 'simpleimport_tasks as t
      WHERE id_shop = ' . (int)$id_shop . '
      AND id_shop_group = ' . (int)$id_shop_group . '
      AND id_task = '.$idTask.'
      AND active = 1
    ';

    $res = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
    if( isset($res[0]) && $res[0] ){
      return $res[0];
    }
    return false;
  }

  private function _checkQueue()
  {
    if( !Module::getInstanceByName('simpleimportproduct')->checkImportRunning() ){
      $idTaskForRun = Module::getInstanceByName('simpleimportproduct')->getTaskInQueue();
      if( $idTaskForRun ){
        $this->_runTask($idTaskForRun);
      }
    }
  }

  private function _runTask( $idTask )
  {
    $task = $this->_getTaskInfo($idTask);
    if( !$task ){
      return false;
    }

    Module::getInstanceByName('simpleimportproduct')->updateTaskStatus($task['id_task'], $task['one_shot']);
    Module::getInstanceByName('simpleimportproduct')->setTaskStatus($task['id_task'], Module::getInstanceByName('simpleimportproduct')->l('Preparing import'));
    $automaticLink = Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.basename(_PS_MODULE_DIR_).'/simpleimportproduct/automatic_import.php?settings='.$task['import_settings'].'&id_shop_group='.Tools::getValue('id_shop_group').'&id_shop='.Tools::getValue('id_shop').'&id_lang='.Context::getContext()->language->id.'&secure_key='.Configuration::getGlobalValue('GOMAKOIL_IMPORT_TASKS_KEY').'&id_task='.$task['id_task'];
    Module::getInstanceByName('simpleimportproduct')->runImagesCopy( 1, 'link', $automaticLink );

  }

  private function _shouldBeExecuted($task, $time = false)
  {
    include_once(_PS_MODULE_DIR_ . 'simpleimportproduct/libraries/Schedule/csd_parser.php');

    $expression = $task['frequency'];
    $expression = Module::getInstanceByName('simpleimportproduct')->convertSpecialExpression($expression);
    $schedule = new csd_parser($expression);
    $nextRun = $schedule->get();
    $now = time();

    if( ($nextRun - $now) <= 60 ){
      return true;
    }

    return false;
  }

  private function _sendCallback()
  {
    ignore_user_abort(true);
    set_time_limit(0);

    ob_start();
    echo 'Tasks run';
    header('Connection: close');
    header('Content-Length: '.ob_get_length());
    ob_end_flush();
    ob_flush();
    flush();

    if (function_exists('fastcgi_finish_request')) {
      fastcgi_finish_request();
    }
  }

  public function ajaxProcessAddCustomField()
  {
    $customFields = Tools::getValue('customFields');

    Module::getInstanceByName('simpleimportproduct')->addCustomFields( $customFields );
  }

  public function ajaxProcessDownloadSettings()
  {
    try{
      $json = array();
      $write_fd = fopen(_PS_MODULE_DIR_ . 'simpleimportproduct/upload/settings.txt', 'w');
      if (@$write_fd !== false){
        $settings = Tools::getValue('settings');
        $settings = Configuration::get('GOMAKOIL_IMPORT_PRODUCTS_'.$settings, '', Context::getContext()->shop->id_shop_group, Context::getContext()->shop->id);
        fwrite($write_fd, $settings);
      }

      fclose($write_fd);

      $json['download'] = Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.basename(_PS_MODULE_DIR_).'/simpleimportproduct/download.php';
      die(Tools::jsonEncode($json));
    }
    catch(Exception $e){
      $json['error'] = $e->getMessage();
      die(Tools::jsonEncode($json));
    }
  }


  public function ajaxProcessUploadSettings()
  {
    try{
      $json = array();
      if (isset($_FILES['file']) AND !empty($_FILES['file']['tmp_name']))
      {
        $settingsKey = false;
        $file_name = $_FILES['file']['name'];
        $file_type = Tools::substr($file_name, strrpos($file_name, '.')+1);
        $file_type = Tools::strtolower($file_type);
        if( $file_type != 'txt' ){
          throw new Exception($this->l('Settings must have txt format!'));
        }
        $settings = Tools::file_get_contents($_FILES['file']['tmp_name']);
        $settings = unserialize($settings);
        if( !isset($settings['name_save']) || !$settings['name_save'] ){
          throw new Exception($this->l('Settings is not valid!'));
        }

        $count_save = Tools::unserialize(Configuration::get('GOMAKOIL_IMPORT_COUNT_SETTINGS',null,Context::getContext()->shop->id_shop_group,Context::getContext()->shop->id));
        foreach( $count_save as $save ){
          $savedSettings = Configuration::get('GOMAKOIL_IMPORT_PRODUCTS_'.$save,null,Context::getContext()->shop->id_shop_group,Context::getContext()->shop->id);
          $savedSettings = Tools::unserialize($savedSettings);
          if( $savedSettings['name_save'] == $settings['name_save'] ){
            $settingsKey = $save;
            break;
          }
        }
        if( !$settingsKey ){
          if(@!end($count_save) ){
            $settingsKey = 1;
          }
          else{
            $settingsKey = end($count_save)+1;
          }

          $count_save[] = $settingsKey;
        }

        Configuration::updateValue('GOMAKOIL_IMPORT_COUNT_SETTINGS' , serialize($count_save), false, Context::getContext()->shop->id_shop_group,Context::getContext()->shop->id );
        Configuration::updateValue('GOMAKOIL_IMPORT_PRODUCTS_'.$settingsKey , serialize($settings), false, Context::getContext()->shop->id_shop_group,Context::getContext()->shop->id );
        $json['success'] = 'ready';
      }
      else{
        throw new Exception($this->l('Select settings for upload!'));
      }
      die(Tools::jsonEncode($json));
    }
    catch(Exception $e){
      $json['error'] = $e->getMessage();
      die(Tools::jsonEncode($json));
    }
  }

  public function ajaxProcessSubscribe()
  {
    try{
      $json = array();
      $url = 'https://myprestamodules.com/modules/mpm_newsletters/send.php?newsletter=true&ajax=true&email='.pSQL(Tools::getValue('email'));

      $res = Tools::file_get_contents($url);
      die($res);
    }
    catch(Exception $e){
      $json['error'] = $e->getMessage();
      die(Tools::jsonEncode($json));
    }
  }

  public function ajaxProcessCheckVersion()
  {
    try{
      $json = array();
      $url = 'https://myprestamodules.com/modules/mpm_newsletters/send.php?get_module_version=true&ajax=true&module=38';

      $res = Tools::file_get_contents($url);

      if( $res ){
        $version = Tools::jsonDecode($res);
        $version = $version->module_version;
        Configuration::updateGlobalValue('GOMAKOIL_IMPORT_VERSION', $version);
      }

      die($res);
    }
    catch(Exception $e){
      $json['error'] = $e->getMessage();
      die(Tools::jsonEncode($json));
    }
  }

  public function ajaxProcessAddAttribute()
  {
    try{
     $json = array();
      if( Tools::getValue('load_settings') != 'false' && Tools::getValue('key_settings') ){
        $save = Tools::unserialize(Configuration::get('GOMAKOIL_IMPORT_PRODUCTS_'.Tools::getValue('key_settings') ,null,Context::getContext()->shop->id_shop_group,Context::getContext()->shop->id));
        if( isset($save['field_combinations'][Tools::getValue('load_settings')]) ){
          $form = '';

          if(isset($save['field_combinations'][Tools::getValue('load_settings')]['single_attribute']) && $save['field_combinations'][Tools::getValue('load_settings')]['single_attribute']){
            foreach( $save['field_combinations'][Tools::getValue('load_settings')]['single_attribute'] as $key => $attribute ){
              $settings = array(
                'single_attribute'   => $attribute,
                'manually_attribute' => $save['field_combinations'][Tools::getValue('load_settings')]['manually_attribute'][$key],
                'single_type' => $save['field_combinations'][Tools::getValue('load_settings')]['single_type'][$key],
                'single_value' => $save['field_combinations'][Tools::getValue('load_settings')]['single_value'][$key],
                'single_color' => $save['field_combinations'][Tools::getValue('load_settings')]['single_color'][$key],
                'single_delimiter' => $save['field_combinations'][Tools::getValue('load_settings')]['single_delimiter'][$key],
                'existing_attribute' => $save['field_combinations'][Tools::getValue('load_settings')]['existing_attribute'][$key],
              );
              $form .= $this->_getAttributeForm($settings);
            }
          }

          $json['attribute'] = $form;
        }
      }
      else{
        $json['attribute'] = $this->_getAttributeForm();
      }
      die(Tools::jsonEncode($json));
    }
    catch(Exception $e){
      $json['error'] = $e->getMessage();
      die(Tools::jsonEncode($json));
    }
  }

  private function _getAttributeForm( $settings = array() )
  {
    $config = Tools::unserialize(Configuration::get('GOMAKOIL_CONFIG_IMPORT_PRODUCTS',null,Context::getContext()->shop->id_shop_group,Context::getContext()->shop->id));
    $fields = $config['name_fields_upload'];
    $attributes = array(
      'single_attribute' => array(
        'name' => $this->l('Attribute'),
        'hint' => $this->l('Attribute Name'),
      ),
      'existing_attribute' => array(
        'name' => $this->l('Existing Attribute'),
        'hint' => $this->l('Attribute Name'),
        'form_group_class' => 'existing_attribute'
      ),
      'single_type'  => array(
        'name'             => $this->l('Type'),
        'hint'             => $this->l('Attribute type'),
        'form_group_class' => 'attribute_type'
      ),
      'single_color'  => array(
        'name'             => $this->l('Color Hex Code or Texture Image'),
        'hint'             => $this->l('Hexadecimal code for just color or url to texture image for texture'),
        'form_group_class' => 'single-color'
      ),
      'single_value'  => array(
        'name'             => $this->l('Value'),
        'hint'             => $this->l('Attribute value'),
      ),
      'single_delimiter'  => array(
        'name'             => $this->l('Delimiter'),
        'hint'             => $this->l('Attribute value delimiter'),
        'form_group_class' => 'single_delimiter'
      ),
    );

    $attributeTypes = array(
      array(
        'name'  => $this->l('Drop-down list'),
        'value' => 'select'
      ),
      array(
        'name'  => $this->l('Radio buttons'),
        'value' => 'radio'
      ),
      array(
        'name'  => $this->l('Color or texture'),
        'value' => 'color'
      )
    );

    $attributeDelimiter = array(
      array(
        'value' => ';',
        'name' => ';',
      ),
      array(
        'value' => ':',
        'name' => ':',
      ),
      array(
        'value' => ',',
        'name' => ',',
      ),
      array(
        'value' => '.',
        'name' => '.',
      ),
      array(
        'value' => '/',
        'name' => '/',
      ),
      array(
        'value' => '|',
        'name' => '|',
      ),
    );

    $attrNames = $fields;
    $noAttr = array(
      'name'   => $this->l('no'),
    );

    $attrNames[0] = array(
      'name'   => $this->l('Enter manually'),
      'value'  => 'enter_manually'
    );

    $attrNames[1] = array(
      'name'   => $this->l('Select existing attribute'),
      'value'  => 'existing_attribute',

    );

    $attributeGroups = AttributeGroupCore::getAttributesGroups(Configuration::get('PS_LANG_DEFAULT'));
    $existingAttributes = array(
      array(
        'value' => 'no',
        'name' => 'no',
      )
    );
    foreach( $attributeGroups as $attributeGroup ){
      $existingAttributes[] = array(
        'name' => $attributeGroup['name'],
        'value' => $attributeGroup['id_attribute_group']
      );
    }


    array_unshift($attrNames, $noAttr);

    $data = Context::getContext()->smarty->createTemplate(_PS_MODULE_DIR_ . 'simpleimportproduct/views/templates/admin/productsimport/add-attribute.tpl');
    $data->assign(
      array(
        'attributes'          => $attributes,
        'attribute_types'     => $attributeTypes,
        'default_fields'      => $fields,
        'attribute_names'     => $attrNames,
        'saved_settings'      => $settings,
        'attribute_delimiter' => $attributeDelimiter,
        'existing_attributes' => $existingAttributes
      )
    );
    return $data->fetch();
  }

  public function ajaxProcessCheckExpression()
  {
    try{
      $json = array();
      include_once(_PS_MODULE_DIR_ . 'simpleimportproduct/libraries/Schedule/CrontabValidator.php');
      include_once(_PS_MODULE_DIR_ . 'simpleimportproduct/libraries/Schedule/CronSchedule.php');
      include_once(_PS_MODULE_DIR_ . 'simpleimportproduct/libraries/Schedule/csd_parser.php');
      $expression = Tools::getValue('expression');
      $expression = trim($expression);
      $expression = preg_replace('#\s+#', ' ', $expression);
      $expression = Module::getInstanceByName('simpleimportproduct')->convertSpecialExpression($expression);

      $validator = new CrontabValidator();
      if( !$validator->isExpressionValid( $expression ) ){
        throw new Exception('Not valid');
      }

      $parser = new csd_parser( $expression );

      $expressionByPart = explode(' ', $expression);

      $expression = $expression . ' *';
      $schedule = CronSchedule::fromCronString($expression);

      $json['human_description'] = $schedule->asNaturalLanguage();
      $json['next_run'] = date(Context::getContext()->language->date_format_full, $parser->get());
      $json['expression']['min'] = $expressionByPart[0];
      $json['expression']['hour'] = $expressionByPart[1];
      $json['expression']['day_of_month'] = $expressionByPart[2];
      $json['expression']['month'] = $expressionByPart[3];
      $json['expression']['day_of_week'] = $expressionByPart[4];

      

      die(Tools::jsonEncode($json));
    }
    catch(Exception $e){
      $json['error'] = $e->getMessage();
      die(Tools::jsonEncode($json));
    }

  }

  public function ajaxProcessLoadFile()
  {
    try{

      if( Module::getInstanceByName('simpleimportproduct')->checkImportRunning() ){
        throw new Exception(Module::getInstanceByName('simpleimportproduct')->l('Other import is running now. Please wait until it will finish.', 'send'));
      }

      if( !Tools::getValue('import_settings_name') ){
        throw new Exception( Module::getInstanceByName('simpleimportproduct')->l('Please enter Settings Name!') );
      }

      $json = array();
      if( Tools::getValue('feed_source') == 'file_url' ){
        $this->_getHeaders();
      }
      elseif( Tools::getValue('feed_source') == 'ftp' ){
        $this->_checkFtpConnect();
      }
      elseif( Tools::getValue('feed_source') == 'sftp' ){
        $this->_checkSFtpConnect();
      }

      $json['page'] = true;
      die(Tools::jsonEncode($json));
    }
    catch(Exception $e){
      $json['error'] = $e->getMessage();
      die(Tools::jsonEncode($json));
    }
  }

  public function ajaxProcessSend()
  {
    $json = array();
    try{
      include_once (_PS_MODULE_DIR_ . 'simpleimportproduct/send.php');
      die;
    }
    catch(Exception $e){
      $json['error'] = $e->getMessage();
      die(Tools::jsonEncode($json));
    }
  }

  public function ajaxProcessGetNewCategoryLinking()
  {
    $form_template = Context::getContext()->smarty->createTemplate(_PS_MODULE_DIR_ . 'simpleimportproduct/views/templates/hook/new_category_linking.tpl');
    $last_id = Tools::getValue('last_id');
    $categories_tree_id = 'mpm_sip_shop_categories_tree_' . ($last_id + 1);
    $input_name = 'mpm_sip_shop_category_' . ($last_id + 1);

    $form_template->assign(
      array(
        'row_number' => ($last_id + 1),
        'tree' => Simpleimportproduct::getCategoriesTree($categories_tree_id, $input_name, array())
      )
    );

    die(json_encode(array('status' => 'success', 'tpl' => $form_template->fetch())));
  }

  private function _checkSFtpConnect()
  {
    if( !Tools::getValue('file_import_ftp_server') || !Validate::isUrl( Tools::getValue('file_import_ftp_server') ) ){
      throw new Exception($this->l('Please enter valid SFTP Server!'));
    }

    if( !Tools::getValue('file_import_ftp_user') ){
      throw new Exception($this->l('Please enter valid SFTP User Name!'));
    }

    if( !Tools::getValue('file_import_ftp_password') ){
      throw new Exception($this->l('Please enter valid SFTP Password!'));
    }

    if( !Tools::getValue('file_import_ftp_port_ssl') ){
      throw new Exception($this->l('Please enter valid SFTP Port!'));
    }

    if( !Tools::getValue('file_import_ftp_file_path') ){
      throw new Exception($this->l('Please enter valid SFTP File Path!'));
    }

    $port = (int)Tools::getValue('file_import_ftp_port_ssl');
    if( !$port ){
      $port = 22;
    }

    require_once _PS_MODULE_DIR_ . 'simpleimportproduct/libraries/phpseclib/Net/SFTP.php';
    $sftp = new NET_SFTP(Tools::getValue('file_import_ftp_server'), $port);
    if( !$sftp ){
      throw new Exception($this->l('Can not connect to your SFTP Server!'));
    }

    $sftp_login = $sftp->login(Tools::getValue('file_import_ftp_user'), Tools::getValue('file_import_ftp_password'));
    if( !$sftp_login ){
      throw new Exception($this->l('Can not Login to your SFTP Server, please check access!'));
    }

    $format = explode('.',basename(Tools::getValue('file_import_ftp_file_path')));
    $format = end($format);
    $format = Tools::strtolower($format);

    if( $format != Tools::strtolower(Tools::getValue('format_file')) ){
      throw new Exception(sprintf($this->l('File must be in %s format!'),Tools::strtoupper(Tools::getValue('format_file'))));
    }

    $dest = _PS_MODULE_DIR_ . 'simpleimportproduct/data/import_products.'.Tools::getValue('format_file');

    if (!$sftp->get(Tools::getValue('file_import_ftp_file_path'), $dest)) {
      throw new Exception($this->l('Can not download file from SFTP, please check file path!'));
    }

    $mime = mime_content_type($dest);
    $checkFormatFile = false;

    foreach( Simpleimportproduct::$allowedFormats as $allowedFormat ){
      if( strpos($mime, $allowedFormat) !== false ){
        $checkFormatFile= true;
        break;
      }
    }

    if( !$checkFormatFile ){
      unlink($dest);
      throw new Exception(Module::getInstanceByName('simpleimportproduct')->l('File for import is not valid!'));
    }

    $this->_saveFileHeaders($dest);

  }

  private function _checkFtpConnect()
  {
    if( !Tools::getValue('file_import_ftp_server') || !Validate::isUrl( Tools::getValue('file_import_ftp_server') ) ){
      throw new Exception($this->l('Please enter valid FTP Server!'));
    }

    if( !Tools::getValue('file_import_ftp_user') ){
      throw new Exception($this->l('Please enter valid FTP User Name!'));
    }

    if( !Tools::getValue('file_import_ftp_password') ){
      throw new Exception($this->l('Please enter valid FTP Password!'));
    }

    if( !Tools::getValue('file_import_ftp_port') ){
      throw new Exception($this->l('Please enter valid FTP Port!'));
    }

    if( !Tools::getValue('file_import_ftp_file_path') ){
      throw new Exception($this->l('Please enter valid FTP File Path!'));
    }

    $port = (int)Tools::getValue('file_import_ftp_port');
    if( !$port ){
      $port = 21;
    }
    $conn_id = ftp_connect(Tools::getValue('file_import_ftp_server'), $port);
    if( !$conn_id ){
      throw new Exception($this->l('Can not connect to your FTP Server!'));
    }

    $login_result = @ftp_login($conn_id, Tools::getValue('file_import_ftp_user'), Tools::getValue('file_import_ftp_password'));
    if( Tools::getValue('passive_mode') ){
      ftp_pasv($conn_id, true);
    }

    if( !$login_result ){
      throw new Exception($this->l('Can not Login to your FTP Server, please check access!'));
    }

    $format = explode('.',basename(Tools::getValue('file_import_ftp_file_path')));
    $format = end($format);
    $format = Tools::strtolower($format);

    if( $format != Tools::strtolower(Tools::getValue('format_file')) ){
      throw new Exception(sprintf($this->l('File must be in %s format!'),Tools::strtoupper(Tools::getValue('format_file'))));
    }

    $dest = _PS_MODULE_DIR_ . 'simpleimportproduct/data/import_products.'.Tools::getValue('format_file');

    if (!@ftp_get($conn_id, $dest, Tools::getValue('file_import_ftp_file_path'), FTP_BINARY)) {
      throw new Exception($this->l('Can not download file from FTP, please check file path!'));
    }

    $mime = mime_content_type($dest);
    $checkFormatFile = false;

    foreach( Simpleimportproduct::$allowedFormats as $allowedFormat ){
      if( strpos($mime, $allowedFormat) !== false ){
        $checkFormatFile= true;
        break;
      }
    }

    if( !$checkFormatFile ){
      unlink($dest);
      throw new Exception(Module::getInstanceByName('simpleimportproduct')->l('File for import is not valid!'));
    }

    $this->_saveFileHeaders($dest);
  }

  /*private function _checkFormat()
  {
    if( !Validate::isAbsoluteUrl(Tools::getValue('file_url')) || !Tools::getValue('file_url') ){
      throw new Exception($this->l('Please enter valid url for import file!'));
    }

    $format = explode('.',basename(Tools::getValue('file_url')));
    $format = end($format);
    $format = Tools::strtolower($format);

    if( $format != Tools::strtolower(Tools::getValue('format_file')) ){
      throw new Exception(sprintf($this->l('File must be in %s format!'),Tools::strtoupper(Tools::getValue('format_file'))));
    }
  }*/

  private function _saveFileHeaders( $dest )
  {
    if(Tools::getValue('format_file') == 'xlsx' ){
      $format = PHPExcel_IOFactory::identify($dest);
      $reader = PHPExcel_IOFactory::createReader($format);
      $chunkSize = 2;
      $chunkFilter = new myReadFilter();
      $reader->setReadFilter($chunkFilter);
      $chunkFilter->setRows(1,$chunkSize);
      $objPHPExcel = $reader->load($dest);

      $delimiter_val = false;
    }
    elseif(Tools::getValue('format_file') == 'csv'){
      $reader = PHPExcel_IOFactory::createReader("CSV");

      $delimiter_val = Tools::getValue('delimiter_val') == 'tab' ? "\t" :  Tools::getValue('delimiter_val');
      $reader->setDelimiter($delimiter_val);
      $encoding = mb_detect_encoding(Tools::file_get_contents($dest), array('UTF-8','ISO-8859-1','ASCII','GBK'), TRUE);
      if( $encoding ){
        $reader->setInputEncoding($encoding);
      }
      $chunkSize = 2;
      $chunkFilter = new myReadFilter();
      $reader->setReadFilter($chunkFilter);
      $chunkFilter->setRows(1,$chunkSize);
      $objPHPExcel = $reader->load($dest);
      $delimiter_val = Tools::getValue('delimiter_val');
    }
    $name_fields_upload = array(array('name' => 'no'));
    foreach ($objPHPExcel->getWorksheetIterator() as $worksheet) {
      //      $highestRow         = $worksheet->getHighestRow(); // e.g. 10
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
      'combination_identification' => Tools::getValue('combination_identification'),
      'file_import_url'            => Tools::getValue('file_url'),
      'file_import_ftp_server'     => Tools::getValue('file_import_ftp_server'),
      'file_import_ftp_user'       => Tools::getValue('file_import_ftp_user'),
      'file_import_ftp_password'   => Tools::getValue('file_import_ftp_password'),
      'file_import_ftp_port'       => Tools::getValue('file_import_ftp_port'),
      'file_import_ftp_port_ssl'   => Tools::getValue('file_import_ftp_port_ssl'),
      'passive_mode'               => (int)Tools::getValue('passive_mode'),
      'remove_ftp_file'            => Tools::getValue('remove_ftp_file'),
      'file_import_ftp_file_path'  => Tools::getValue('file_import_ftp_file_path'),
      'feed_source'                => Tools::getValue('feed_source'),
      'use_headers'                => ( Tools::getValue('use_headers') ),
      'disable_hooks'              => ( Tools::getValue('disable_hooks') ),
      'search_index'               => ( Tools::getValue('search_index') ),
      'products_range'             => ( Tools::getValue('products_range') ),
      'from_range'                 => ( Tools::getValue('from_range') ),
      'to_range'                   => ( Tools::getValue('to_range') ),
      'force_ids'                  => ( Tools::getValue('force_ids') ),
      'iteration'                  => ( Tools::getValue('iteration') ),
      'import_settings_name'       => Tools::getValue('import_settings_name')
    );


    if( $config['products_range'] == 'range' ){
      if( (int)$config['from_range'] != $config['from_range'] || (int)$config['to_range'] != $config['to_range']
        || (int)$config['from_range'] > (int)$config['to_range']
      ){
        throw new Exception($this->l('Products range is not valid', 'send'));
      }
    }

    $config_save =serialize($config);
    Configuration::updateValue('GOMAKOIL_CONFIG_IMPORT_PRODUCTS', $config_save, false, Tools::getValue('id_shop_group'), Tools::getValue('id_shop'));

    if( Tools::getValue('setting_id') ){
      $settings = Configuration::get('GOMAKOIL_IMPORT_PRODUCTS_'.Tools::getValue('setting_id'), null, Tools::getValue('id_shop_group'), Tools::getValue('id_shop'));
      $settings = Tools::unSerialize($settings);
      $fieldSettings = $settings['field_settings'];
      Module::getInstanceByName('simpleimportproduct')->addCustomFields( $fieldSettings, true );
    }
  }

  private function _getHeaders()
  {
    $dest = _PS_MODULE_DIR_ . 'simpleimportproduct/data/import_products.'.Tools::getValue('format_file');

    $remoteHeaders = @get_headers(Tools::getValue('file_url'));
    $checkFormatFile = false;

    foreach ( $remoteHeaders as $header ){
      foreach( Simpleimportproduct::$allowedFormats as $allowedFormat ){
        if( strpos($header, $allowedFormat) !== false ){
          $checkFormatFile= true;
          break;
        }
      }
    }

    if( !$checkFormatFile ){
      throw new Exception($this->l('File for import is not valid!'));
    }

    if( !@copy(Tools::getValue('file_url'), $dest) ){
      throw new Exception($this->l('Can not copy file for import, please check module folder file permissions or contact us.'));
    }
    $this->_saveFileHeaders($dest);
  }

}
