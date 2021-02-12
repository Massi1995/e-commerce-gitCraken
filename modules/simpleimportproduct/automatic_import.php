<?php
  /**
   * Created by PhpStorm.
   * User: root
   * Date: 20.01.16
   * Time: 15:38
   */

  define('_PS_MODE_DEV_', false);
  include(dirname(__FILE__).'/../../config/config.inc.php');

  if( !(int)Configuration::get('PS_SHOP_ENABLE', null, Tools::getValue('id_shop_group'), Tools::getValue('id_shop') ) ){
    if (!in_array(Tools::getRemoteAddr(), explode(',', Configuration::get('PS_MAINTENANCE_IP')))) {
      if( !Configuration::get('PS_MAINTENANCE_IP', null, Tools::getValue('id_shop_group'), Tools::getValue('id_shop')) ){
        Configuration::updateValue('PS_MAINTENANCE_IP', Tools::getRemoteAddr(), false, Tools::getValue('id_shop_group'), Tools::getValue('id_shop') );
      }
      else{
        Configuration::updateValue('PS_MAINTENANCE_IP', Configuration::get('PS_MAINTENANCE_IP', null, Tools::getValue('id_shop_group'), Tools::getValue('id_shop')) . ',' . Tools::getRemoteAddr(), false, Tools::getValue('id_shop_group'), Tools::getValue('id_shop'));
      }
    }
  }

  include(dirname(__FILE__).'/../../init.php');

  function fatalErrorHandler()
  {
    # Getting last error
    $error = error_get_last();
    if(($error['type'] === E_ERROR) || ($error['type'] === E_USER_ERROR) )
    {
      if( Tools::getValue('limit') ){
        $error = (int)Configuration::getGlobalValue('GOMAKOIL_AUTOMATIC_ERRORS');
        $error++;
        Configuration::updateGlobalValue('GOMAKOIL_AUTOMATIC_ERRORS', $error);
        if( $error > 10 ){
          Module::getInstanceByName('simpleimportproduct')->sendEmail(Module::getInstanceByName('simpleimportproduct')->l('Some error occurred, please contact us!'));
          die;
        }
        $url = Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.basename(_PS_MODULE_DIR_).'/simpleimportproduct/automatic_import.php?settings='.Tools::getValue('settings').'&id_shop_group='.Tools::getValue('id_shop_group').'&id_shop='.Tools::getValue('id_shop').'&id_lang='.Tools::getValue('id_lang').'&secure_key='.Tools::getValue('secure_key').'&limit='.Tools::getValue('limit') . '&id_task=' . Tools::getValue('id_task');
        Module::getInstanceByName('simpleimportproduct')->runImagesCopy( 1, 'link', $url );
        die;
      }
    }
  }

  register_shutdown_function('fatalErrorHandler');
  include_once(dirname(__FILE__).'/import.php');

  try{
    ini_set("log_errors", 1);
    @error_reporting(E_ALL | E_STRICT);
    ini_set("error_log", _PS_MODULE_DIR_ . "simpleimportproduct/error/".Tools::getValue('settings')."_error.log");
    checkConfig();
    if (Tools::getValue('secure_key')) {
      $secureKey = md5(_COOKIE_KEY_.Configuration::get('PS_SHOP_NAME', null, Tools::getValue('id_shop_group'), Tools::getValue('id_shop')));
      if ( $secureKey === Tools::getValue('secure_key') || Tools::getValue('secure_key') == Configuration::getGlobalValue('GOMAKOIL_IMPORT_TASKS_KEY') ) {
        if( !Tools::getValue('id_lang') ){
          throw new Exception('id_lang is Empty');
        }
        if( !Tools::getValue('id_shop') ){
          throw new Exception('id_shop is Empty');
        }

        if( !Tools::getValue('id_shop_group') ){
          throw new Exception('id_shop_group is Empty');
        }

        $config = Configuration::get('GOMAKOIL_IMPORT_PRODUCTS_' . Tools::getValue('settings'), null, Tools::getValue('id_shop_group'), Tools::getValue('id_shop'));

        $config = Tools::unserialize($config);
        $baseConfig = $config['base_settings'];
        $config['base_settings']['automatic'] = true;
        if( $baseConfig['disable_hooks'] ){
          define('PS_INSTALLATION_IN_PROGRESS', true);
        }
        if( !Tools::getValue('limit') ){
          if( Module::getInstanceByName('simpleimportproduct')->checkImportRunning() ){
            throw new Exception(Module::getInstanceByName('simpleimportproduct')->l('Other import is running now. Please wait until it will finish.', 'send'), 333);
          }
          Configuration::updateGlobalValue('GOMAKOIL_IMPORT_RUNNING', (time()+60));
          Configuration::updateGlobalValue('GOMAKOIL_AUTOMATIC_SETTINGS', Tools::getValue('settings'));
          Configuration::updateGlobalValue('GOMAKOIL_AUTOMATIC_ERRORS', 0);
          Configuration::updateGlobalValue( 'GOMAKOIL_SETTINGS_FOR_IMAGES', serialize($config) );
          Configuration::updateGlobalValue('GOMAKOIL_IMPORT_STOPPED', 0);
          copyFile($baseConfig);
        }
        $import = new importProducts( $config, Tools::getValue('id_shop'), Tools::getValue('id_shop_group'), Tools::getValue('limit'), true );
        $res = $import->import();

        if( !Tools::getValue('limit') ){
          echo Module::getInstanceByName('simpleimportproduct')->l('Import started, you will get email notification about finish (if you set up it in settings)!','automatic_import');
          if( $config['field_images'][0]['images_stream'] ){
            $url = Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.basename(_PS_MODULE_DIR_).'/simpleimportproduct/send.php?ajax=1&processImages=1&id_task='.Tools::getValue('id_task').'&id_shop_group='.Tools::getValue('id_shop_group').'&id_shop='.Tools::getValue('id_shop');
            Module::getInstanceByName('simpleimportproduct')->runImagesCopy( 1, 'link', $url );
          }
        }

        if( Tools::getValue('run_stuck_import') && $config['field_images'][0]['images_stream'] )
        {
          Module::getInstanceByName('simpleimportproduct')->runImagesCopy(1);
        }

        if( $res['limit'] ){
          $url = Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.basename(_PS_MODULE_DIR_).'/simpleimportproduct/automatic_import.php?settings='.Tools::getValue('settings').'&id_shop_group='.Tools::getValue('id_shop_group').'&id_shop='.Tools::getValue('id_shop').'&id_lang='.Tools::getValue('id_lang').'&secure_key='.Tools::getValue('secure_key').'&limit='.$res['limit'] . '&id_task=' . Tools::getValue('id_task');
          Module::getInstanceByName('simpleimportproduct')->runImagesCopy( 1, 'link', $url );
          die;
        }

        if( !Module::getInstanceByName('simpleimportproduct')->checkImportRunning() ){
          Module::getInstanceByName('simpleimportproduct')->sendEmail();
        }
      }
      else{
        throw new Exception(Module::getInstanceByName('simpleimportproduct')->l('Secure key is wrong','automatic_import'));
      }
    }
    else{
      throw new Exception(Module::getInstanceByName('simpleimportproduct')->l('Secure key is wrong','automatic_import'));
    }
  }
  catch( Exception $e ){


    if( $e->getCode() != 333 ){
      Module::getInstanceByName('simpleimportproduct')->sendEmail($e->getMessage(), $e->getCode());
      Module::getInstanceByName('simpleimportproduct')->resetImportStatus();
    }

    echo '<strong>Error: </strong>' . $e->getMessage();
  }

  function copyFromUrl($config)
  {
    $dest = _PS_MODULE_DIR_ . 'simpleimportproduct/data/'.Tools::getValue('settings').'_import.'.$config['format_file'];
    $remoteHeaders = @get_headers($config['file_import_url']);
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
      throw new Exception(Module::getInstanceByName('simpleimportproduct')->l('File for import is not valid!'));
    }

    if( !@copy($config['file_import_url'], $dest) ){
      throw new Exception(Module::getInstanceByName('simpleimportproduct')->l('Can not copy file for import, please check module folder file permissions or contact us.'));
    }
  }

  function copyFromSFtp($config)
  {
    require_once _PS_MODULE_DIR_ . 'simpleimportproduct/libraries/phpseclib/Net/SFTP.php';

    $dest = _PS_MODULE_DIR_ . 'simpleimportproduct/data/'.Tools::getValue('settings').'_import.'.$config['format_file'];

    $port = (int)$config['file_import_ftp_port_ssl'];
    if( !$port ){
      $port = 22;
    }

    $sftp = new NET_SFTP($config['file_import_ftp_server'], $port);
    
    if( !$sftp ){
      throw new Exception(Module::getInstanceByName('simpleimportproduct')->l('Can not connect to your SFTP Server!'));
    }

    $sftp_login = $sftp->login($config['file_import_ftp_user'], $config['file_import_ftp_password']);
    if( !$sftp_login ){
      throw new Exception(Module::getInstanceByName('simpleimportproduct')->l('Can not Login to your SFTP Server, please check access!'));
    }
    if (!$sftp->get($config['file_import_ftp_file_path'], $dest)) {
      throw new Exception(Module::getInstanceByName('simpleimportproduct')->l('Can not download file from SFTP, please check file path!'));
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

  }

  function copyFromFtp($config)
  {
    $dest = _PS_MODULE_DIR_ . 'simpleimportproduct/data/'.Tools::getValue('settings').'_import.'.$config['format_file'];

    $port = (int)$config['file_import_ftp_port'];
    if( !$port ){
      $port = 21;
    }

    $conn_id = ftp_connect($config['file_import_ftp_server'], $port);
    if( !$conn_id ){
      throw new Exception(Module::getInstanceByName('simpleimportproduct')->l('Can not connect to your FTP Server!'));
    }

    $login_result = @ftp_login($conn_id, $config['file_import_ftp_user'], $config['file_import_ftp_password']);
    if( $config['passive_mode'] ){
      ftp_pasv($conn_id, true);
    }
    if( !$login_result ){
      throw new Exception(Module::getInstanceByName('simpleimportproduct')->l('Can not Login to your FTP Server, please check access!'));
    }

    if (!ftp_get($conn_id, $dest, $config['file_import_ftp_file_path'], FTP_BINARY)) {
      throw new Exception(Module::getInstanceByName('simpleimportproduct')->l('Can not download file from FTP, please check file path!'));
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
  }

  function copyFile( $config )
  {
    if( $config['feed_source'] == 'file_url' ){
      copyFromUrl($config);
    }

    if( $config['feed_source'] == 'ftp' ){
      copyFromFtp($config);
    }

    if( $config['feed_source'] == 'sftp' ){
      copyFromSFtp($config);
    }
  }

  function checkConfig()
  {
    if( !Configuration::get('GOMAKOIL_IMPORT_PRODUCTS_' . Tools::getValue('settings'), null, Tools::getValue('id_shop_group'), Tools::getValue('id_shop')) ){
      throw new Exception(Module::getInstanceByName('simpleimportproduct')->l('import Settings does not exists!', 'automatic_import'));
    }
  }