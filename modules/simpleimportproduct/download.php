<?php
  include(dirname(__FILE__).'/../../config/config.inc.php');
  include(dirname(__FILE__).'/../../init.php');

  $file = _PS_ROOT_DIR_ . '/modules/simpleimportproduct/upload/settings.txt';
  $file_info  = pathinfo($file);
  $file_type = "application/force-download";
  $file_name  = $file_info['basename'];

  header('Content-type: '.$file_type);
  header('Content-Disposition: attachment; filename=' . $file_name . '');
  readfile('' . $file . '');
  exit();