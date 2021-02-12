<?php

class importProductData
{
  private $_context;
  private $_combinationDetectFields = array(
    'reference',
    'ean13',
    'upc',
    'isbn',
  );
  public function __construct(){
    include_once(dirname(__FILE__).'/../../config/config.inc.php');
    include_once(dirname(__FILE__).'/../../init.php');
    $this->_context = Context::getContext();
  }

  public function getProductId($type = false, $value = false, $id_shop = false, $id_lang = false)
  {
    if($id_shop === false || $id_shop == null ){
      $id_shop =  $this->_context->shop->id;
    }
    if($id_lang === false){
      $id_lang =  $this->_context->language->id;
    }

    $where = '';
    $join = '';

    if( Configuration::get('PS_MULTISHOP_FEATURE_ACTIVE') ){
      $join  .= "
        INNER JOIN " . _DB_PREFIX_ . "product_shop as ps
        ON p.id_product = ps.id_product
      ";

      $where .= " AND ps.id_shop = ".(int)$id_shop." ";
    }

    if($type == 'product_name' || $type == 'name' ){
      $where .= " AND pl.name = '".pSQL($value)."' AND pl.id_lang = ".(int)$id_lang." ";
      $join  .= " 
        INNER JOIN " . _DB_PREFIX_ . "product_lang as pl
        ON p.id_product = pl.id_product
       ";
    }
    elseif($type == 'reference'){
      $where .= " AND p.reference = '".pSQL($value)."'";
    }
    elseif($type == 'ean13'){
      $where .= " AND p.ean13 = '".pSQL($value)."'";
    }
    elseif($type == 'upc'){
      $where .= " AND p.upc = '".pSQL($value)."'";
    }
    elseif ($type == 'product_id'){
      $where .= " AND p.id_product = '".(int)$value."'";
    }
    elseif ($type == 'isbn'){
      $where .= " AND p.isbn = '".(int)$value."'";
    }

    $sql = "
        SELECT p.id_product
      FROM " . _DB_PREFIX_ . "product as p
      $join
      WHERE 1
      " . $where . "
			";
    
    $rez = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
    if($rez){
      $rez = $rez[0];
    }
    else{
      $rez = false;
    }
    return $rez;
  }

  private function _getCombinationDetectValue($type, $combination)
  {
    if( $type == 'reference' && isset($combination['reference_combination']) && $combination['reference_combination'] ){
      return $combination['reference_combination'];
    }

    if( $type == 'ean13' && isset($combination['ean13_combination']) && $combination['ean13_combination'] ){
      return $combination['ean13_combination'];
    }

    if( $type == 'upc' && isset($combination['upc_combination']) && $combination['upc_combination'] ){
      return $combination['upc_combination'];
    }

    if( $type == 'isbn' && isset($combination['isbn_combination']) && $combination['isbn_combination'] ){
      return $combination['isbn_combination'];
    }

    return false;
  }

  public function detectProductByCombination( $type = false, $id_shop = false, $combination )
  {
    $join = '';
    $where = '';

    if( !in_array($type, $this->_combinationDetectFields) ){
      return false;
    }

    $value = $this->_getCombinationDetectValue($type, $combination);
    if( !$value ){
      return false;
    }

    if( Configuration::get('PS_MULTISHOP_FEATURE_ACTIVE') && $id_shop ){
      $join  .= "
        INNER JOIN " . _DB_PREFIX_ . "product_attribute_shop as pas
        ON pa.id_product_attribute = pas.id_product_attribute
      ";

      $where .= " AND pas.id_shop = ".(int)$id_shop." ";
    }

    $sql = "
        SELECT pa.id_product
        FROM " . _DB_PREFIX_ . "product_attribute as pa
        $join
        WHERE pa.".$type." = '".pSQL($value)."'
        " . $where . "
        LIMIT 1
			";

    $res = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);

    if( $res ){
      return $res[0];
    }

    return false;
  }

  public function detectCombinationId( $productId, $type, $value, $idShop )
  {
    $join = '';
    $where = '';

    if( !$value ){
      return null;
    }

    if( Configuration::get('PS_MULTISHOP_FEATURE_ACTIVE') && $idShop ){
      $join  .= "
        INNER JOIN " . _DB_PREFIX_ . "product_attribute_shop as pas
        ON pa.id_product_attribute = pas.id_product_attribute
      ";

      $where .= " AND pas.id_shop = ".(int)$idShop." ";
    }

    $sql = "
        SELECT pa.id_product_attribute
      FROM " . _DB_PREFIX_ . "product_attribute as pa
      $join
      WHERE pa.id_product = '".(int)$productId."'
      AND pa.".$type." = '".pSQL($value)."'
      AND pa.id_product != 0
      " . $where . "
			";

    $res = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);

    if($res){
      return $res[0]['id_product_attribute'];
    }

    return null;

  }

  public function getProductForPack($type = false, $value = false, $id_shop = false, $id_lang = false){
    if($id_shop === false  || $id_shop == null ){
      $id_shop =  $this->_context->shop->id ;
    }
    if($id_lang === false){
      $id_lang =  $this->_context->language->id;
    }

    $where = '';

    if($type == 'product_name'){
      $where .= " AND pl.name = '".pSQL($value)."'";
    }
    elseif($type == 'reference'){
      $where .= " AND ( p.reference = '".pSQL($value)."' OR pa.reference = '".pSQL($value)."' ) ";
    }
    elseif($type == 'ean13'){
      $where .= " AND ( p.ean13 = '".pSQL($value)."' OR pa.ean13 = '".pSQL($value)."' ) ";
    }
    elseif($type == 'upc'){
      $where .= " AND ( p.upc = '".pSQL($value)."' OR pa.upc = '".pSQL($value)."' ) ";
    }
    elseif ($type == 'product_id'){
      $where .= " AND p.id_product = '".pSQL($value)."'";
    }
    elseif ($type == 'id_product_attribute'){
      $where .= " AND pa.id_product_attribute = '".pSQL($value)."'";
    }

    $sql = "
        SELECT p.id_product, p.is_virtual, p.cache_is_pack, pa.id_product_attribute
      FROM " . _DB_PREFIX_ . "product as p
      INNER JOIN " . _DB_PREFIX_ . "product_lang as pl
      ON p.id_product = pl.id_product
      LEFT JOIN " . _DB_PREFIX_ . "product_attribute as pa
      ON p.id_product = pa.id_product
      WHERE pl.id_shop = ".(int)$id_shop."
      AND pl.id_lang = ".(int)$id_lang."
      " . $where . "
      ORDER BY pa.default_on DESC
      LIMIT 1
			";
    $rez = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);

    if($rez){
      $rez = $rez[0];
    }
    else{
      $rez = false;
    }
    return $rez;
  }

  public function getManufacturer($manufacturer = ''){
    $sql = "
        SELECT m.id_manufacturer
    FROM " . _DB_PREFIX_ . "manufacturer as m
    WHERE m.name = '".pSQL(trim(Tools::strtolower($manufacturer)))."'
			";
    $rez = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
    if($rez){
      $rez = $rez[0];
    }
    else{
      $rez = false;
    }
    return $rez;
  }

  public function getSupplier($supplier = ''){
    $sql = "
          SELECT s.id_supplier
      FROM " . _DB_PREFIX_ . "supplier as s
      WHERE s.name = '".pSQL(trim(Tools::strtolower($supplier)))."'
        ";
    $rez = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
    if($rez){
      $rez = $rez[0];
    }
    else{
      $rez = false;
    }
    return $rez;
  }

  public function getGroupAttribute($name = '', $group_type = '', $id_lang = false){
    if($id_lang === false){
      $id_lang =  $this->_context->language->id ;
    }
    if( !$group_type ){
      $group_type = 'select';
    }
    $sql = "
          SELECT ag.id_attribute_group
      FROM " . _DB_PREFIX_ . "attribute_group as ag
      LEFT JOIN "._DB_PREFIX_."attribute_group_lang agl
		  ON ag.id_attribute_group = agl.id_attribute_group AND agl.id_lang = ".(int)$id_lang."
      WHERE agl.name = '".pSQL(trim(Tools::strtolower($name)))."'
      AND ag.group_type = '".pSQL(trim(Tools::strtolower($group_type)))."'
        ";
    $rez = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
    if($rez){
      $rez = $rez[0]['id_attribute_group'];
    }
    else{
      $rez = false;
    }
    return $rez;
  }

  public function getAttribute($name = '', $id_attribute_group = 0, $id_lang = false){
    if($id_lang === false){
      $id_lang =  $this->_context->language->id ;
    }
    $sql = "
          SELECT a.id_attribute
      FROM " . _DB_PREFIX_ . "attribute as a
      LEFT JOIN "._DB_PREFIX_."attribute_lang al
		  ON a.id_attribute = al.id_attribute AND al.id_lang = ".(int)$id_lang."
      WHERE al.name = '".pSQL(trim(Tools::strtolower($name)))."'
      AND a.id_attribute_group = ".(int)$id_attribute_group."
        ";
    $rez = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
    if($rez){
      $rez = $rez[0]['id_attribute'];
    }
    else{
      $rez = false;
    }
    return $rez;
  }

  public function getProductSuppliersID( $productId = false )
  {
    $sql = '
			SELECT ps.id_supplier
      FROM ' . _DB_PREFIX_ . 'product_supplier as ps
      WHERE  ps.id_product = '.(int)$productId.'
      AND ps.id_product_attribute = 0
			';
    
    return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
  }

  public function checkAccessory( $productId, $accessoryId ){
    $sql = '
      SELECT count(*) as count
      FROM ' . _DB_PREFIX_ . 'accessory
      WHERE id_product_1 = '.(int)$productId.'
      AND id_product_2 = '.(int)$accessoryId.'
    ';

    $res = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
    return $res[0]['count'];
  }

  public function checkPackItem( $productId, $itemId, $attributeId ){
    $sql = '
      SELECT count(*) as count
      FROM ' . _DB_PREFIX_ . 'pack
      WHERE id_product_pack = '.(int)$productId.'
      AND id_product_item = '.(int)$itemId.'
      AND id_product_attribute_item = '.(int)$attributeId.'
    ';

    $res = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
    return $res[0]['count'];
  }

  public function getFeatures($name = '', $id_lang = false, $id_shop = false){
    if($id_lang === false){
      $id_lang =  $this->_context->language->id ;
    }
    if($id_shop === false  || $id_shop == null ){
      $id_shop =  $this->_context->shop->id ;
    }
    $sql = "
          SELECT fp.id_feature
      FROM " . _DB_PREFIX_ . "feature as fp
      LEFT JOIN "._DB_PREFIX_."feature_shop fs
      ON fp.id_feature = fs.id_feature AND fs.id_shop = ".(int)$id_shop."
      LEFT JOIN "._DB_PREFIX_."feature_lang fl
		  ON fl.id_feature = fp.id_feature AND fl.id_lang = ".(int)$id_lang."
      WHERE fl.name = '".pSQL(trim(Tools::strtolower($name)))."'
        ";

    $rez = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
    if($rez){
      $rez = $rez[0]['id_feature'];
    }
    else{
      $rez = false;
    }
    return $rez;
  }

  public function getFeaturesValue($value = '', $id_feature = 0, $id_lang = false, $valueType = false){
    if($id_lang === false){
      $id_lang =  $this->_context->language->id ;
    }
    $custom = 0;
    if( $valueType == 'feature_customized'){
      $custom = 1;
      return false;
    }
    $sql = "
          SELECT fv.id_feature_value
      FROM " . _DB_PREFIX_ . "feature as fp
      LEFT JOIN "._DB_PREFIX_."feature_value fv
		  ON fp.id_feature = fv.id_feature
      LEFT JOIN "._DB_PREFIX_."feature_value_lang fvl
		  ON fvl.id_feature_value = fv.id_feature_value AND fvl.id_lang = ".(int)$id_lang."
      WHERE fvl.value = '".pSQL(trim(Tools::strtolower($value)))."'
      AND  fp.id_feature = ".(int)$id_feature."
      AND fv.custom = ".(int)$custom."
        ";
    $rez = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
    if($rez){
      $rez = $rez[0]['id_feature_value'];
    }
    else{
      $rez = false;
    }
    return $rez;
  }

  public function getCategoryByName($name = '', $id_lang = false, $id_shop = false, $parent = 0){

    if($id_lang === false){
      $id_lang =  $this->_context->language->id ;
    }
    if($id_shop === false  || $id_shop == null){
      $id_shop =  $this->_context->shop->id ;
    }
    if( Tools::strtolower($name) == 'home' ){
      return  Configuration::get('PS_HOME_CATEGORY');
    }
    $where = '';
    if($parent){
      $where .= ' AND c.id_parent = '.(int)$parent;
    }
    $sql = "
          SELECT c.id_category
      FROM " . _DB_PREFIX_ . "category as c
      LEFT JOIN "._DB_PREFIX_."category_lang cl
		  ON cl.id_category = c.id_category AND cl.id_lang = ".(int)$id_lang." AND cl.id_shop = ".(int)$id_shop."
      WHERE cl.name = '".pSQL(trim(Tools::strtolower($name)))."'
        $where
       ";
    $rez = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
    if($rez){
      $rez = $rez[0]['id_category'];
    }
    else{
      $rez = false;
    }
    return $rez;
  }

  public function getSpecificPrice($product_id = 0, $id_shop = false){
    if($id_shop === false  || $id_shop == null ){
      $id_shop =  $this->_context->shop->id ;
    }
    $sql = "
          SELECT sp.id_specific_price
      FROM " . _DB_PREFIX_ . "specific_price as sp
      WHERE sp.id_product = ".(int)$product_id."
      AND sp.id_shop = ".(int)$id_shop."
       ";
    $rez = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
    if($rez){
      $rez = $rez[0]['id_specific_price'];
    }
    else{
      $rez = false;
    }
    return $rez;
  }

  public function getProductSupplier($id){
    $sql = "
          SELECT ps.id_product_supplier
      FROM " . _DB_PREFIX_ . "product_supplier as ps
      WHERE ps.id_product = ".(int)$id."
      AND ps.id_product_attribute = 0
        ";


    return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
  }



  public function getCategoryByNameImport($name = '', $id_lang = false, $id_shop = false, $parent = 0){

    if($id_lang === false){
      $id_lang =  $this->_context->language->id ;
    }
    if($id_shop === false || $id_shop == null){
      $id_shop =  $this->_context->shop->id ;
    }
    if( Tools::strtolower($name) == 'home' ){
      $parent = 1;
    }
    $where = '';
    if($parent){
      $where .= ' AND c.id_parent = '.(int)$parent;
    }
    $sql = "
          SELECT c.id_category
      FROM " . _DB_PREFIX_ . "category as c
      LEFT JOIN "._DB_PREFIX_."category_lang cl
		  ON cl.id_category = c.id_category AND cl.id_lang = ".(int)$id_lang." AND cl.id_shop = ".(int)$id_shop."
      WHERE cl.name = '".pSQL(trim(Tools::strtolower($name)))."'
        $where
       ";
    $rez = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);

    return $rez;
  }


}
