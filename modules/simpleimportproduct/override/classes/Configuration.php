<?php

  class Configuration extends ConfigurationCore
  {
    public static function getGlobalValue($key, $id_lang = null)
    {
      self::loadConfiguration();
      return parent::getGlobalValue($key, $id_lang = null);
    }
  }