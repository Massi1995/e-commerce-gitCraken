<?php
  class Configuration extends ConfigurationCore
  {
    /*
    * module: simpleimportproduct
    * date: 2020-12-02 12:08:12
    * version: 6.3.9
    */
    public static function getGlobalValue($key, $id_lang = null)
    {
      self::loadConfiguration();
      return parent::getGlobalValue($key, $id_lang = null);
    }
  }