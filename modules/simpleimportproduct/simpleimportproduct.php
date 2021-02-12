<?php

if (!defined('_PS_VERSION_')){
  exit;
}

class Simpleimportproduct extends Module {
  private $_html;
  private $_has_hint_combinations;
  private $_has_hint_discount;
  private $_has_hint_featured;
  private $_has_hint_accessories;
  private $_has_hint_pack;
  private $_has_hint_additional_settings;
  private $_has_hint_customization;
  private $_has_hint_attachments;
  private $_automatic = false;
  private $_has_hint_images = array();

  private $_has_hint_suppliers;

  public static $allowedFormats = array('csv', 'officedocument', 'text', 'octet-stream', 'vnd.openxmlformats', 'vnd.ms-excel');

  public function __construct(){

    $this->name = 'simpleimportproduct';
    $this->tab = 'quick_bulk_update';
    $this->version = '6.3.9';
    $this->author = 'MyPrestaModules';
    $this->need_instance = 0;
    $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
    $this->bootstrap = true;
    $this->module_key = "659ea36715aa55ad9ce03afc739e4ade";
//     $this->author_address = '0x289929BB6B765f9668Dc1BC709E5949fEB83455e';

    parent::__construct();

    $this->displayName = $this->l('Product Catalog (CSV, Excel) Import');
    $this->description = $this->l('Product Catalog (CSV, Excel) Import module is a convenient module especially designed to perform import operations with the PrestaShop products.');

    $this->_defineFields();
  }

  private function _defineFields()
  {

    $config = Tools::unserialize(Configuration::get('GOMAKOIL_CONFIG_IMPORT_PRODUCTS',null,Context::getContext()->shop->id_shop_group,Context::getContext()->shop->id));

    $fields = $config['name_fields_upload'];

    $this->_has_hint_images = array(
      'images_url'                => array(
        'name' => $this->l('Images urls'),
        'hint' => $this->l('Each image url must be separated by a comma.'),
        'form_group_class' => 'images',
      ),
      'images_alt' => array(
        'name' => $this->l('Images caption'),
        'hint' => $this->l('Each image caption must be separated by a comma. Invalid characters <>;=#{}'),
        'form_group_class' => 'images',
      ),
    );

    $this->_has_hint_suppliers = array(

      'remove_suppliers' => array(
        'name'   => $this->l('Remove supplier associations'),
        'form_group_class' => 'suppliers'
      ),
      'supplier_method'     => array(
        'name'   => $this->l('Suppliers import method'),
        'hint'             => $this->l('Suppliers import method'),
        'fields' => array(
          array(
            'id' => 'supplier_name_method',
            'value' => 'supplier_name_method',
            'name' => $this->l('Supplier name')
          ),
          array(
            'id' => 'supplier_ids_method',
            'value' => 'supplier_ids_method',
            'name' => $this->l('Supplier ID')
          ),
          array(
            'id' => 'existing_supplier_method',
            'value' => 'existing_supplier_method',
            'name' => $this->l('Existing Supplier')
          ),
        ),
        'form_group_class' => 'suppliers',
      ),
      'supplier_default'    => array(
        'name'             => $this->l('Default supplier name'),
        'hint'             => $this->l('The default supplier name. Invalid characters &lt;&gt;;=#{}'),
        'form_group_class' => 'supplier_name_method suppliers block_after_line',
      ),
      'supplier_default_id' => array(
        'name'             => $this->l('Default supplier ID'),
        'form_group_class' => 'supplier_id_method suppliers block_after_line',
      ),
      'existing_supplier_default' => array(
        'name'             => $this->l('Default supplier'),
        'form_group_class' => 'existing_supplier_method suppliers block_after_line',
        'fields'           => $this->_getPreSavedFields( $fields, 'supplier' ),
      ),

      'supplier'            => array(
        'name'             => $this->l('Supplier name'),
        'hint'             => $this->l('Supplier name'),
        'form_group_class' => 'supplier_name_method suppliers',
      ),
      'supplier_ids'        => array(
        'name'             => $this->l('Supplier Id'),
        'hint'             => $this->l('Supplier Id'),
        'form_group_class' => 'supplier_id_method suppliers',
      ),
      'existing_supplier'            => array(
        'name'             => $this->l('Supplier'),
        'hint'             => $this->l('Existing Supplier name'),
        'form_group_class' => 'existing_supplier_method suppliers',
        'fields'           => $this->_getPreSavedFields( $fields, 'supplier' ),
      ),
      'supplier_reference'        => array(
        'name'             => $this->l('Supplier reference'),
        'hint'             => $this->l('Supplier reference.'),
        'form_group_class' => 'supplier_reference_method suppliers',
      ),
      'supplier_price'        => array(
        'name'             => $this->l('Supplier price'),
        'hint'             => $this->l('Supplier price (TAX EXCL.)'),
        'form_group_class' => 'supplier_price_method suppliers',
      ),
      'supplier_currency'        => array(
        'name'             => $this->l('Supplier currency'),
        'hint'             => $this->l('Supplier currency'),
        'form_group_class' => 'supplier_currency_method suppliers',
      ),
    );

    $this->_has_hint_combinations = array(
      'combinations_import_type' => array(
        'name' => $this->l('Combinations import method'),
        'hint' => null,
        'form_group_class' => 'combinations_import_type',
        'fields' => array(
          array(
            'name'  => 'Combination in one field',
            'value' => 'one_field_combinations'
          ),
          array(
            'name'  => 'Each attribute and value in separate field',
            'value' => 'single_field_value'
          ),
          array(
            'name'  => 'Each combination in a separate row in the file',
            'value' => 'separate_combination_row'
          ),
          array(
            'name'  => 'Generate combinations from attribute values',
            'value' => 'separated_field_value'
          )
        )
      ),
      'attribute'                   => array(
        'name' => $this->l('Attribute'),
        'hint' => $this->l('Attribute (Name:Type, Name:Type)'),
        'form_group_class' => 'old_type'
      ),
      'value'                       => array(
        'name'             => $this->l('Value'),
        'hint'             => $this->l('Attribute value (Value,Value)'),
        'form_group_class' => 'old_type'
      ),
      'reference_combination'       => array(
        'name' => $this->l('Reference code'),
        'hint' => $this->l('Your internal reference code for this combination. Allowed special characters .-_#'),
        'form_group_class' => 'full_combination',
      ),
      'ean13_combination'           => array(
        'name' => $this->l('EAN-13 or JAN barcode'),
        'hint' => null,
        'form_group_class' => 'full_combination',
      ),
      'upc_combination'             => array(
        'name' => $this->l('UPC barcode'),
        'hint' => null,
        'form_group_class' => 'full_combination',
      ),
      'isbn_combination'             => array(
        'name' => $this->l('ISBN'),
        'hint' => null,
        'form_group_class' => 'full_combination',
      ),
      'wholesale_price_combination' => array(
        'name' => $this->l('Wholesale price'),
        'hint' => $this->l('Overrides the wholesale price from the "Prices" tab.'),
        'form_group_class' => 'full_combination',
      ),
      'final_price'                => array(
        'name' => $this->l('Final on price (tax exlc.)'),
        'hint' => null,
        'form_group_class' => 'full_combination',
      ),
      'final_price_with_tax'                => array(
        'name' => $this->l('Final on price (tax incl.)'),
        'hint' => null,
        'form_group_class' => 'full_combination',
      ),
      'impact_price'                => array(
        'name' => $this->l('Impact on price (tax exlc.)'),
        'hint' => null,
        'form_group_class' => 'full_combination',
      ),
      'impact_price_with_tax'                => array(
        'name' => $this->l('Impact on price (tax incl.)'),
        'hint' => null,
        'form_group_class' => 'full_combination',
      ),
      'impact_unit_price'                => array(
        'name' => $this->l('Impact on price per unit (tax excl.)'),
        'hint' => null,
        'form_group_class' => 'full_combination',
      ),
      'impact_weight'               => array(
        'name' => $this->l('Impact on weight (amount)'),
        'hint' => null,
        'form_group_class' => 'full_combination',
      ),
      'ecotax_combination'          => array(
        'name' => $this->l('Ecotax (tax excl.)'),
        'hint' => $this->l('Overrides the ecotax from the "Prices" tab.'),
        'form_group_class' => 'full_combination',
      ),
      'min_quantity_combination'    => array(
        'name' => $this->l('Minimum quantity'),
        'hint' => $this->l('The minimum quantity to buy this product (set to 1 to disable this feature).'),
        'form_group_class' => 'full_combination',
      ),
      'available_date_combination'                  => array(
        'name' => $this->l('Availability date'),
        'hint' => $this->l('If this product is out of stock, you can indicate when the product will be available again.'),
        'form_group_class' => 'full_combination',
      ),
      'id_warehouse_combination'                  => array(
        'name' => $this->l('Warehouse'),
        'hint' => $this->l('ID Warehouse.'),
        'form_group_class' => 'full_combination',
      ),
      'warehouse_location_combination' => array(
        'name'             => $this->l('Warehouse Location'),
        'hint'             => $this->l('Warehouse Location'),
        'form_group_class' => 'full_combination'
      ),
      'quantity_combination_method'                  => array(
        'name' => $this->l('Quantity Import Method'),
        'hint' => $this->l('You can override add or deduct to existing quantity values.'),
        'form_group_class' => 'full_combination',
      ),
      'quantity_combination'        => array(
        'name' => $this->l('Quantity'),
        'hint' => $this->l('Available quantities for sale.'),
        'form_group_class' => 'full_combination',
      ),
      'location_combination'                  => array(
        'name' => $this->l('Stock location'),
        'hint' => null,
        'form_group_class' => 'full_combination',
      ),
      'low_stock_threshold_combination'     => array(
        'name' => $this->l('Low stock level'),
        'hint' => null,
        'form_group_class' => 'full_combination',
      ),
      'low_stock_alert_combination'     => array(
        'name' => $this->l('Email alert'),
        'hint' => $this->l('Send me an email when the quantity is below or equals this level'),
        'form_group_class' => 'full_combination pre_defined',
        'fields'           => $this->_getPreSavedFields( $fields, 'available' ),
      ),
      'default'  => array(
        'name' => $this->l('Default combination'),
        'hint' => $this->l('Make this combination the default combination for this product. Value 0 or 1.'),
        'form_group_class' => 'full_combination',
      ),
      'images_combination'  => array(
        'name' => $this->l('Combination images'),
        'hint' => $this->l('Combination images'),
        'form_group_class' => 'full_combination',
      ),
      'supplier_method_combination'     => array(
        'name'   => $this->l('Suppliers import method'),
        'hint'             => $this->l('Suppliers import method'),
        'fields' => array(
          array(
            'id' => 'supplier_name_method',
            'value' => 'supplier_name_method',
            'name' => $this->l('Supplier name')
          ),
          array(
            'id' => 'supplier_ids_method',
            'value' => 'supplier_ids_method',
            'name' => $this->l('Supplier ID')
          ),
          array(
            'id' => 'existing_supplier_method',
            'value' => 'existing_supplier_method',
            'name' => $this->l('Existing Supplier')
          ),
        ),
        'form_group_class' => 'full_combination full_combination_top_line block_combination_background',
      ),

    );


    $this->_has_hint_discount = array(
      'reduction_type' => array(
        'name'   => $this->l('Reduction type'),
        'hint'   => $this->l('Reduction type (amount or percentage)'),
        'fields' => $this->_getPreSavedFields( $fields, 'reduction_type' ),
      ),
      'reduction_tax_incl'      => array(
        'name' => $this->l('Reduction (tax incl.)'),
        'hint' => $this->l('Amount of reduction with tax'),
      ),
      'reduction_tax_excl'      => array(
        'name' => $this->l('Reduction (tax excl.)'),
        'hint' => $this->l('Amount of reduction without tax'),
      ),
      'reduction_from' => array(
        'name' => $this->l('Available from (date)'),
        'hint' => null,
      ),
      'reduction_to'   => array(
        'name' => $this->l('Available to (date)'),
        'hint' => null,
      ),
      'fixed_price'    => array(
        'name' => $this->l('Fixed price (tax excl.)'),
        'hint' => null,
      ),
      'from_quantity'  => array(
        'name' => $this->l('Starting at (quantity)'),
        'hint' => null,
      ),
      'customer_id'  => array(
        'name' => $this->l('Customer ID'),
        'hint' => $this->l('Add Specific Price for particular Customer'),
      ),
      'customer_group_id'  => array(
        'name' => $this->l('Customer Group ID'),
        'hint' => $this->l('Add Specific Price for particular Customer Group'),
        'fields' => $this->_getPreSavedFields( $fields, 'customer_group' ),
      ),
      'reduction_country_id'  => array(
        'name' => $this->l('Country ID'),
        'hint' => $this->l('Add Specific Price for particular Country'),
      ),
      'currency_id'  => array(
        'name' => $this->l('Currency ID'),
        'hint' => $this->l('Add Specific Price for particular currency'),
      ),
    );

    $this->_has_hint_featured = array(
      'features_name_import_method'  => array(
        'name' => $this->l('Features Name Import Method'),
        'fields' => array(
          array(
            'name'  => 'Feature Name',
            'value' => 'feature_name'
          ),
          array(
            'name'  => 'Feature Name ID',
            'value' => 'feature_id'
          ),
          array(
            'name'  => 'Existing Feature Name',
            'value' => 'existing_feature'
          ),
        )
      ),
      'features_name'  => array(
        'name' => $this->l('Feature name'),
        'hint' => $this->l('Invalid characters <>;=#{}'),
        'form_group_class' => 'features_name'
      ),
      'features_id'  => array(
        'name' => $this->l('Feature ID'),
        'hint' => $this->l('Invalid characters <>;=#{}'),
        'form_group_class' => 'features_id'
      ),
      'existing_feature'  => array(
        'name' => $this->l('Existing Feature Name'),
        'hint' => $this->l('Invalid characters <>;=#{}'),
        'form_group_class' => 'existing_feature',
        'fields'           => $this->_getPreSavedFields( $fields, 'feature_names' ),
      ),
      'features_name_manually'  => array(
        'name' => $this->l('Enter features name'),
        'hint' => $this->l('Invalid characters <>;=#{}'),
      ),
      'features_value_import_method'  => array(
        'name' => $this->l('Features Value Import Method'),
        'fields' => array(
          array(
            'name'  => 'Feature Value',
            'value' => 'feature_value'
          ),
          array(
            'name'  => 'Feature Value ID',
            'value' => 'feature_id'
          ),
        )
      ),
      'features_value' => array(
        'name' => $this->l('Features value'),
        'hint' => $this->l('Product features value'),
      ),
      'multiple_feature_values' => array(
        'name' => $this->l('Multiple Feature Values'),
        'hint' => $this->l('Multiple feature values separated by a comma'),
        'fields' => array(
          array(
            'name'  => 'Yes',
            'value' => '1'
          ),
          array(
            'name'  => 'No',
            'value' => '0'
          ),
        )
      ),
      'features_type' => array(
        'name' => $this->l('Value type'),
        'hint' => $this->l('Features value type'),
        'fields' => array(
          array(
            'name'  => 'Pre-defined value',
            'value' => 'feature_pre_defined'
          ),
          array(
            'name'  => 'Customized value',
            'value' => 'feature_customized'
          ),
        )
      ),
    );


    $customizationTypes = Tools::unserialize(Configuration::get('GOMAKOIL_CONFIG_IMPORT_PRODUCTS',null,Context::getContext()->shop->id_shop_group,Context::getContext()->shop->id));
    $customizationTypes = $customizationTypes['name_fields_upload'];
    if( !$customizationTypes ){
      $customizationTypes = array();
      $customizationRequired  = array();
    }
    else{
      foreach( $customizationTypes as $key=>$type ){
        $customizationTypes[$key]['value'] = $type['name'];
      }

      $noField = $customizationTypes[0];
      $customizationRequired = $customizationTypes;

      $customizationTypes[0] =     array(
        'name'  => $this->l('File'),
        'value' => 'file'
      );

      array_unshift($customizationTypes, array(
        'name'  => $this->l('Text'),
        'value' => 'text'
      ) );

      array_unshift( $customizationTypes, $noField );

      $customizationRequired[0] = array(
        'name'  => $this->l('No'),
        'value' => '0'
      );

      array_unshift($customizationRequired, array(
        'name'  => $this->l('Yes'),
        'value' => '1'
      ) );

      array_unshift( $customizationRequired, $noField );
    }


    $this->_has_hint_customization = array(
      'customization_type'  => array(
        'name' => $this->l('Field type'),
        'hint' => $this->l('Values (text, file)'),
        'fields' => $customizationTypes
      ),
      'customization_name' => array(
        'name' => $this->l('Field label'),
      ),
      'customization_required' => array(
        'name' => $this->l('Required'),
        'hint' => $this->l('Values (0 - No, 1 - Yes)'),
        'fields' => $customizationRequired
      ),
    );

    $this->_has_hint_attachments = array(
      'attachment_name'  => array(
        'name' => $this->l('Filename'),
      ),
      'attachment_description' => array(
        'name' => $this->l('Description'),
      ),
      'attachment_url' => array(
        'name' => $this->l('File URL'),
        'hint' => $this->l('Link on file'),
      ),
    );

    $this->_has_hint_accessories = array(
      'accessories_identifier'  => array(
        'name' => $this->l('Related product identifier'),
        'hint' => $this->l('Key for product identification'),
      ),
      'identifier_type' => array(
        'name' => $this->l('Identifier type'),
        'hint' => null,
        'fields' => array(
          array(
            'name'  => $this->l('Reference code'),
            'value' => 'reference'
          ),
          array(
            'name'  => $this->l('EAN-13 or JAN barcode'),
            'value' => 'ean13'
          ),
          array(
            'name'  => $this->l('UPC barcode'),
            'value' => 'upc'
          ),
          array(
            'name'  => $this->l('Product ID'),
            'value' => 'product_id'
          ),
          array(
            'name'  => $this->l('Product Name'),
            'value' => 'product_name'
          ),
        )
      ),
      'identifier_delimiter' => array(
        'name' => $this->l('Delimiter of identifier'),
        'hint' => $this->l('Accessories identifier delimiter'),
        'fields' => array(
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
        )
      ),
    );

    $this->_has_hint_pack = array(
      'pack_products_identifier'  => array(
        'name' => $this->l('List of products of Pack'),
        'hint' => $this->l('Key for product identification'),
      ),
      'pack_products_quantity'  => array(
        'name' => $this->l('Quantity of products of Pack'),
        'hint' => null,
      ),
      'pack_identifier_type' => array(
        'name' => $this->l('Identifier type'),
        'hint' => null,
        'fields' => array(
          array(
            'name'  => $this->l('Reference code'),
            'value' => 'reference'
          ),
          array(
            'name'  => $this->l('EAN-13 or JAN barcode'),
            'value' => 'ean13'
          ),
          array(
            'name'  => $this->l('UPC barcode'),
            'value' => 'upc'
          ),
          array(
            'name'  => $this->l('Product ID'),
            'value' => 'product_id'
          ),
          array(
            'name'  => $this->l('Product Attribute ID'),
            'value' => 'id_product_attribute'
          ),
          array(
            'name'  => $this->l('Product Name'),
            'value' => 'product_name'
          ),
        )
      ),
      'pack_identifier_delimiter' => array(
        'name' => $this->l('Delimiter of identifier'),
        'hint' => $this->l('Pack products delimiter'),
        'fields' => array(
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
        )
      ),
    );
  }

  public function install()
  {
    if (!parent::install()|| !$this->registerHook('actionAdminControllerSetMedia')) {
      return false;
    }
    $this->_createTab();
    if( !$this->installDb() ){
      return false;
    }
    Configuration::updateGlobalValue('GOMAKOIL_IMPORT_TASKS_KEY', md5(_COOKIE_KEY_.Configuration::get('PS_SHOP_NAME')));

    Configuration::updateValue('GOMAKOIL_IMPORT_COUNT_SETTINGS', 'a:5:{i:0;i:1;i:1;i:2;i:2;i:3;i:3;i:4;i:4;i:5;}', false, Context::getContext()->shop->id_shop_group, Context::getContext()->shop->id);
    Configuration::updateValue('GOMAKOIL_IMPORT_PRODUCTS_1', 'a:22:{s:10:"base_field";a:77:{s:10:"product_id";s:2:"no";s:4:"name";s:12:"Product name";s:9:"reference";s:2:"no";s:5:"ean13";s:2:"no";s:3:"upc";s:2:"no";s:4:"isbn";s:2:"no";s:8:"date_add";s:2:"no";s:6:"active";s:2:"no";s:10:"visibility";s:2:"no";s:19:"available_for_order";s:2:"no";s:10:"show_price";s:2:"no";s:11:"online_only";s:2:"no";s:14:"show_condition";s:2:"no";s:9:"condition";s:2:"no";s:17:"short_description";s:2:"no";s:11:"description";s:2:"no";s:4:"tags";s:2:"no";s:15:"wholesale_price";s:2:"no";s:5:"price";s:2:"no";s:10:"tax_method";s:15:"tax_rate_method";s:3:"tax";s:2:"no";s:11:"tax_rule_id";s:2:"no";s:12:"existing_tax";s:2:"no";s:6:"ecotax";s:2:"no";s:9:"tax_price";s:2:"no";s:10:"unit_price";s:2:"no";s:5:"unity";s:2:"no";s:7:"on_sale";s:2:"no";s:10:"meta_title";s:2:"no";s:13:"meta_keywords";s:2:"no";s:16:"meta_description";s:2:"no";s:12:"link_rewrite";s:2:"no";s:15:"category_method";s:20:"category_name_method";s:19:"default_category_id";s:2:"no";s:16:"default_category";s:2:"no";s:20:"delimiter_categories";s:1:"/";s:25:"associated_categories_ids";s:2:"no";s:19:"manufacturer_method";s:24:"manufacturer_name_method";s:12:"manufacturer";s:2:"no";s:15:"manufacturer_id";s:2:"no";s:21:"existing_manufacturer";s:2:"no";s:5:"width";s:2:"no";s:6:"height";s:2:"no";s:5:"depth";s:2:"no";s:6:"weight";s:2:"no";s:24:"additional_shipping_cost";s:2:"no";s:25:"additional_delivery_times";s:2:"no";s:17:"delivery_in_stock";s:2:"no";s:18:"delivery_out_stock";s:2:"no";s:11:"carriers_id";s:2:"no";s:25:"advanced_stock_management";s:2:"no";s:16:"depends_on_stock";s:2:"no";s:12:"id_warehouse";s:2:"no";s:18:"warehouse_location";s:2:"no";s:15:"quantity_method";s:8:"override";s:8:"quantity";s:2:"no";s:8:"location";s:2:"no";s:19:"low_stock_threshold";s:2:"no";s:15:"low_stock_alert";s:2:"no";s:12:"out_of_stock";s:2:"no";s:16:"minimal_quantity";s:2:"no";s:13:"available_now";s:2:"no";s:15:"available_later";s:2:"no";s:14:"available_date";s:2:"no";s:19:"virtual_product_url";s:2:"no";s:29:"virtual_product_nb_downloable";s:2:"no";s:31:"virtual_product_expiration_date";s:2:"no";s:23:"virtual_product_nb_days";s:2:"no";s:12:"new_products";s:3:"add";s:17:"existing_products";s:6:"update";s:13:"file_products";s:6:"ignore";s:19:"file_store_products";s:6:"ignore";s:12:"skip_product";s:2:"no";s:14:"remove_product";s:2:"no";s:17:"remove_categories";s:1:"0";s:16:"remove_suppliers";s:1:"0";s:21:"disable_zero_products";s:1:"0";}s:14:"field_category";a:1:{i:0;a:3:{i:0;s:13:"Main Category";i:1;s:13:"Subcategory_1";i:2;s:13:"Subcategory_2";}}s:22:"import_from_categories";b:0;s:21:"import_from_suppliers";b:0;s:18:"import_from_brands";b:0;s:14:"price_settings";a:1:{i:0;a:5:{s:12:"price_source";s:5:"store";s:11:"price_field";s:15:"wholesale_price";s:9:"condition";s:4:"&lt;";s:15:"condition_value";s:0:"";s:13:"price_formula";s:0:"";}}s:14:"field_settings";a:1:{i:0;a:5:{s:5:"field";s:2:"no";s:9:"condition";s:4:"&lt;";s:15:"condition_value";s:0:"";s:9:"new_field";s:0:"";s:13:"field_formula";s:0:"";}}s:17:"quantity_settings";a:1:{i:0;a:5:{s:15:"quantity_source";s:5:"store";s:14:"quantity_field";s:16:"product_quantity";s:9:"condition";s:4:"&lt;";s:15:"condition_value";s:0:"";s:16:"quantity_formula";s:0:"";}}s:23:"category_linking_active";s:1:"0";s:16:"category_linking";a:0:{}s:14:"field_discount";b:0;s:12:"field_images";a:1:{i:0;a:6:{s:13:"remove_images";s:1:"0";s:19:"generate_thumbnails";s:1:"1";s:17:"no_product_images";s:1:"1";s:13:"images_stream";s:1:"0";s:10:"images_url";s:5:"Image";s:10:"images_alt";s:2:"no";}}s:18:"field_combinations";a:1:{i:0;a:30:{s:19:"remove_combinations";s:1:"0";s:15:"combination_key";s:10:"attributes";s:24:"combinations_import_type";s:22:"one_field_combinations";s:9:"attribute";s:2:"no";s:5:"value";s:2:"no";s:21:"reference_combination";s:2:"no";s:17:"ean13_combination";s:2:"no";s:15:"upc_combination";s:2:"no";s:16:"isbn_combination";s:2:"no";s:27:"wholesale_price_combination";s:2:"no";s:11:"final_price";s:2:"no";s:20:"final_price_with_tax";s:2:"no";s:12:"impact_price";s:2:"no";s:21:"impact_price_with_tax";s:2:"no";s:17:"impact_unit_price";s:2:"no";s:13:"impact_weight";s:2:"no";s:18:"ecotax_combination";s:2:"no";s:24:"min_quantity_combination";s:2:"no";s:26:"available_date_combination";s:2:"no";s:24:"id_warehouse_combination";s:2:"no";s:30:"warehouse_location_combination";s:2:"no";s:27:"quantity_combination_method";s:8:"override";s:20:"quantity_combination";s:2:"no";s:20:"location_combination";s:2:"no";s:31:"low_stock_threshold_combination";s:2:"no";s:27:"low_stock_alert_combination";s:2:"no";s:7:"default";s:2:"no";s:6:"images";a:1:{i:0;s:2:"no";}s:27:"supplier_method_combination";s:20:"supplier_name_method";s:9:"suppliers";a:1:{i:0;a:6:{s:8:"supplier";s:2:"no";s:12:"supplier_ids";s:2:"no";s:17:"existing_supplier";s:2:"no";s:18:"supplier_reference";s:2:"no";s:14:"supplier_price";s:2:"no";s:17:"supplier_currency";s:2:"no";}}}}s:14:"field_featured";a:1:{i:0;a:5:{s:15:"remove_features";s:1:"0";s:13:"features_name";s:2:"no";s:22:"features_name_manually";s:0:"";s:14:"features_value";s:2:"no";s:13:"features_type";s:19:"feature_pre_defined";}}s:19:"field_customization";a:1:{i:0;a:5:{s:20:"remove_customization";s:1:"0";s:24:"customization_one_column";s:1:"0";s:18:"customization_type";s:2:"no";s:18:"customization_name";s:2:"no";s:22:"customization_required";s:2:"no";}}s:17:"field_attachments";a:1:{i:0;a:5:{s:18:"remove_attachments";s:1:"0";s:37:"import_attachments_from_single_column";s:1:"0";s:15:"attachment_name";s:2:"no";s:22:"attachment_description";s:2:"no";s:14:"attachment_url";s:2:"no";}}s:15:"field_suppliers";a:1:{i:0;a:11:{s:16:"remove_suppliers";s:1:"0";s:15:"supplier_method";s:20:"supplier_name_method";s:16:"supplier_default";s:2:"no";s:19:"supplier_default_id";s:2:"no";s:25:"existing_supplier_default";s:2:"no";s:8:"supplier";s:2:"no";s:12:"supplier_ids";s:2:"no";s:17:"existing_supplier";s:2:"no";s:18:"supplier_reference";s:2:"no";s:14:"supplier_price";s:2:"no";s:17:"supplier_currency";s:2:"no";}}s:17:"field_accessories";b:0;s:19:"field_pack_products";a:5:{s:20:"remove_pack_products";s:1:"0";s:24:"pack_products_identifier";s:2:"no";s:22:"pack_products_quantity";s:2:"no";s:20:"pack_identifier_type";s:9:"reference";s:25:"pack_identifier_delimiter";s:1:";";}s:9:"name_save";s:22:"Demo Categories Import";s:19:"notification_emails";b:0;s:13:"base_settings";a:21:{s:11:"format_file";s:4:"xlsx";s:13:"delimiter_val";b:0;s:15:"import_type_val";s:10:"Add/update";s:7:"id_lang";i:1;s:17:"parser_import_val";s:4:"name";s:18:"name_fields_upload";a:6:{i:0;a:1:{s:4:"name";s:2:"no";}i:1;a:1:{s:4:"name";s:12:"Product name";}i:2;a:1:{s:4:"name";s:13:"Main Category";}i:3;a:1:{s:4:"name";s:13:"Subcategory_1";}i:4;a:1:{s:4:"name";s:13:"Subcategory_2";}i:5;a:1:{s:4:"name";s:5:"Image";}}s:15:"file_import_url";s:56:"https://demo16.myprestamodules.com/files/categories.xlsx";s:22:"file_import_ftp_server";s:0:"";s:20:"file_import_ftp_user";s:0:"";s:24:"file_import_ftp_password";s:0:"";s:25:"file_import_ftp_file_path";s:0:"";s:11:"feed_source";s:8:"file_url";s:11:"use_headers";s:1:"1";s:13:"disable_hooks";s:1:"1";s:12:"search_index";s:1:"1";s:14:"products_range";s:3:"all";s:10:"from_range";s:1:"1";s:8:"to_range";s:2:"10";s:9:"force_ids";s:1:"0";s:9:"iteration";s:3:"100";s:20:"import_settings_name";s:22:"Demo Categories Import";}}', false, Context::getContext()->shop->id_shop_group, Context::getContext()->shop->id);
    Configuration::updateValue('GOMAKOIL_IMPORT_PRODUCTS_2', 'a:22:{s:10:"base_field";a:77:{s:10:"product_id";s:2:"no";s:4:"name";s:12:"Product name";s:9:"reference";s:2:"no";s:5:"ean13";s:2:"no";s:3:"upc";s:2:"no";s:4:"isbn";s:2:"no";s:8:"date_add";s:2:"no";s:6:"active";s:2:"no";s:10:"visibility";s:2:"no";s:19:"available_for_order";s:2:"no";s:10:"show_price";s:2:"no";s:11:"online_only";s:2:"no";s:14:"show_condition";s:2:"no";s:9:"condition";s:2:"no";s:17:"short_description";s:2:"no";s:11:"description";s:2:"no";s:4:"tags";s:2:"no";s:15:"wholesale_price";s:2:"no";s:5:"price";s:2:"no";s:10:"tax_method";s:15:"tax_rate_method";s:3:"tax";s:2:"no";s:11:"tax_rule_id";s:2:"no";s:12:"existing_tax";s:2:"no";s:6:"ecotax";s:2:"no";s:9:"tax_price";s:2:"no";s:10:"unit_price";s:2:"no";s:5:"unity";s:2:"no";s:7:"on_sale";s:2:"no";s:10:"meta_title";s:2:"no";s:13:"meta_keywords";s:2:"no";s:16:"meta_description";s:2:"no";s:12:"link_rewrite";s:2:"no";s:15:"category_method";s:20:"category_name_method";s:19:"default_category_id";s:2:"no";s:16:"default_category";s:2:"no";s:20:"delimiter_categories";s:1:"/";s:25:"associated_categories_ids";s:2:"no";s:19:"manufacturer_method";s:24:"manufacturer_name_method";s:12:"manufacturer";s:2:"no";s:15:"manufacturer_id";s:2:"no";s:21:"existing_manufacturer";s:2:"no";s:5:"width";s:2:"no";s:6:"height";s:2:"no";s:5:"depth";s:2:"no";s:6:"weight";s:2:"no";s:24:"additional_shipping_cost";s:2:"no";s:25:"additional_delivery_times";s:2:"no";s:17:"delivery_in_stock";s:2:"no";s:18:"delivery_out_stock";s:2:"no";s:11:"carriers_id";s:2:"no";s:25:"advanced_stock_management";s:2:"no";s:16:"depends_on_stock";s:2:"no";s:12:"id_warehouse";s:2:"no";s:18:"warehouse_location";s:2:"no";s:15:"quantity_method";s:8:"override";s:8:"quantity";s:2:"no";s:8:"location";s:2:"no";s:19:"low_stock_threshold";s:2:"no";s:15:"low_stock_alert";s:2:"no";s:12:"out_of_stock";s:2:"no";s:16:"minimal_quantity";s:2:"no";s:13:"available_now";s:2:"no";s:15:"available_later";s:2:"no";s:14:"available_date";s:2:"no";s:19:"virtual_product_url";s:2:"no";s:29:"virtual_product_nb_downloable";s:2:"no";s:31:"virtual_product_expiration_date";s:2:"no";s:23:"virtual_product_nb_days";s:2:"no";s:12:"new_products";s:3:"add";s:17:"existing_products";s:6:"update";s:13:"file_products";s:6:"ignore";s:19:"file_store_products";s:6:"ignore";s:12:"skip_product";s:2:"no";s:14:"remove_product";s:2:"no";s:17:"remove_categories";s:1:"0";s:16:"remove_suppliers";s:1:"0";s:21:"disable_zero_products";s:1:"0";}s:14:"field_category";a:1:{i:0;a:2:{i:0;s:13:"Main Category";i:1;s:13:"Subcategory_1";}}s:22:"import_from_categories";b:0;s:21:"import_from_suppliers";b:0;s:18:"import_from_brands";b:0;s:14:"price_settings";a:1:{i:0;a:5:{s:12:"price_source";s:5:"store";s:11:"price_field";s:15:"wholesale_price";s:9:"condition";s:4:"&lt;";s:15:"condition_value";s:0:"";s:13:"price_formula";s:0:"";}}s:14:"field_settings";a:1:{i:0;a:5:{s:5:"field";s:2:"no";s:9:"condition";s:4:"&lt;";s:15:"condition_value";s:0:"";s:9:"new_field";s:0:"";s:13:"field_formula";s:0:"";}}s:17:"quantity_settings";a:1:{i:0;a:5:{s:15:"quantity_source";s:5:"store";s:14:"quantity_field";s:16:"product_quantity";s:9:"condition";s:4:"&lt;";s:15:"condition_value";s:0:"";s:16:"quantity_formula";s:0:"";}}s:23:"category_linking_active";s:1:"0";s:16:"category_linking";a:0:{}s:14:"field_discount";b:0;s:12:"field_images";a:1:{i:0;a:6:{s:13:"remove_images";s:1:"0";s:19:"generate_thumbnails";s:1:"1";s:17:"no_product_images";s:1:"1";s:13:"images_stream";s:1:"0";s:10:"images_url";s:2:"no";s:10:"images_alt";s:2:"no";}}s:18:"field_combinations";a:1:{i:0;a:36:{s:19:"remove_combinations";s:1:"1";s:15:"combination_key";s:10:"attributes";s:24:"combinations_import_type";s:24:"separate_combination_row";s:16:"single_attribute";a:2:{i:0;s:14:"enter_manually";i:1;s:14:"enter_manually";}s:18:"manually_attribute";a:2:{i:0;s:4:"Size";i:1;s:5:"Color";}s:11:"single_type";a:2:{i:0;s:6:"select";i:1;s:5:"color";}s:12:"single_color";a:2:{i:0;s:2:"no";i:1;s:2:"no";}s:12:"single_value";a:2:{i:0;s:4:"Size";i:1;s:5:"Color";}s:16:"single_delimiter";a:2:{i:0;s:1:";";i:1;s:1:";";}s:9:"attribute";s:2:"no";s:5:"value";s:2:"no";s:21:"reference_combination";s:2:"no";s:17:"ean13_combination";s:2:"no";s:15:"upc_combination";s:2:"no";s:16:"isbn_combination";s:2:"no";s:27:"wholesale_price_combination";s:2:"no";s:11:"final_price";s:2:"no";s:20:"final_price_with_tax";s:2:"no";s:12:"impact_price";s:2:"no";s:21:"impact_price_with_tax";s:5:"Price";s:17:"impact_unit_price";s:2:"no";s:13:"impact_weight";s:2:"no";s:18:"ecotax_combination";s:2:"no";s:24:"min_quantity_combination";s:2:"no";s:26:"available_date_combination";s:2:"no";s:24:"id_warehouse_combination";s:2:"no";s:30:"warehouse_location_combination";s:2:"no";s:27:"quantity_combination_method";s:8:"override";s:20:"quantity_combination";s:8:"Quantity";s:20:"location_combination";s:2:"no";s:31:"low_stock_threshold_combination";s:2:"no";s:27:"low_stock_alert_combination";s:2:"no";s:7:"default";s:2:"no";s:6:"images";a:1:{i:0;s:5:"Image";}s:27:"supplier_method_combination";s:20:"supplier_name_method";s:9:"suppliers";a:1:{i:0;a:6:{s:8:"supplier";s:2:"no";s:12:"supplier_ids";s:2:"no";s:17:"existing_supplier";s:2:"no";s:18:"supplier_reference";s:2:"no";s:14:"supplier_price";s:2:"no";s:17:"supplier_currency";s:2:"no";}}}}s:14:"field_featured";a:1:{i:0;a:5:{s:15:"remove_features";s:1:"0";s:13:"features_name";s:2:"no";s:22:"features_name_manually";s:0:"";s:14:"features_value";s:2:"no";s:13:"features_type";s:19:"feature_pre_defined";}}s:19:"field_customization";a:1:{i:0;a:5:{s:20:"remove_customization";s:1:"0";s:24:"customization_one_column";s:1:"0";s:18:"customization_type";s:2:"no";s:18:"customization_name";s:2:"no";s:22:"customization_required";s:2:"no";}}s:17:"field_attachments";a:1:{i:0;a:5:{s:18:"remove_attachments";s:1:"0";s:37:"import_attachments_from_single_column";s:1:"0";s:15:"attachment_name";s:2:"no";s:22:"attachment_description";s:2:"no";s:14:"attachment_url";s:2:"no";}}s:15:"field_suppliers";a:1:{i:0;a:11:{s:16:"remove_suppliers";s:1:"0";s:15:"supplier_method";s:20:"supplier_name_method";s:16:"supplier_default";s:2:"no";s:19:"supplier_default_id";s:2:"no";s:25:"existing_supplier_default";s:2:"no";s:8:"supplier";s:2:"no";s:12:"supplier_ids";s:2:"no";s:17:"existing_supplier";s:2:"no";s:18:"supplier_reference";s:2:"no";s:14:"supplier_price";s:2:"no";s:17:"supplier_currency";s:2:"no";}}s:17:"field_accessories";b:0;s:19:"field_pack_products";a:5:{s:20:"remove_pack_products";s:1:"0";s:24:"pack_products_identifier";s:2:"no";s:22:"pack_products_quantity";s:2:"no";s:20:"pack_identifier_type";s:9:"reference";s:25:"pack_identifier_delimiter";s:1:";";}s:9:"name_save";s:24:"Demo Combinations Import";s:19:"notification_emails";b:0;s:13:"base_settings";a:21:{s:11:"format_file";s:4:"xlsx";s:13:"delimiter_val";b:0;s:15:"import_type_val";s:10:"Add/update";s:7:"id_lang";i:1;s:17:"parser_import_val";s:4:"name";s:18:"name_fields_upload";a:9:{i:0;a:1:{s:4:"name";s:2:"no";}i:1;a:1:{s:4:"name";s:12:"Product name";}i:2;a:1:{s:4:"name";s:4:"Size";}i:3;a:1:{s:4:"name";s:5:"Color";}i:4;a:1:{s:4:"name";s:5:"Image";}i:5;a:1:{s:4:"name";s:13:"Main Category";}i:6;a:1:{s:4:"name";s:13:"Subcategory_1";}i:7;a:1:{s:4:"name";s:8:"Quantity";}i:8;a:1:{s:4:"name";s:5:"Price";}}s:15:"file_import_url";s:58:"https://demo16.myprestamodules.com/files/combinations.xlsx";s:22:"file_import_ftp_server";s:0:"";s:20:"file_import_ftp_user";s:0:"";s:24:"file_import_ftp_password";s:0:"";s:25:"file_import_ftp_file_path";s:0:"";s:11:"feed_source";s:8:"file_url";s:11:"use_headers";s:1:"1";s:13:"disable_hooks";s:1:"1";s:12:"search_index";s:1:"0";s:14:"products_range";s:3:"all";s:10:"from_range";s:1:"1";s:8:"to_range";s:2:"10";s:9:"force_ids";s:1:"0";s:9:"iteration";s:3:"100";s:20:"import_settings_name";s:24:"Demo Combinations Import";}}', false, Context::getContext()->shop->id_shop_group, Context::getContext()->shop->id);
    Configuration::updateValue('GOMAKOIL_IMPORT_PRODUCTS_3', 'a:22:{s:10:"base_field";a:77:{s:10:"product_id";s:2:"no";s:4:"name";s:12:"Product name";s:9:"reference";s:2:"no";s:5:"ean13";s:2:"no";s:3:"upc";s:2:"no";s:4:"isbn";s:2:"no";s:8:"date_add";s:2:"no";s:6:"active";s:2:"no";s:10:"visibility";s:2:"no";s:19:"available_for_order";s:2:"no";s:10:"show_price";s:2:"no";s:11:"online_only";s:2:"no";s:14:"show_condition";s:2:"no";s:9:"condition";s:2:"no";s:17:"short_description";s:2:"no";s:11:"description";s:2:"no";s:4:"tags";s:2:"no";s:15:"wholesale_price";s:2:"no";s:5:"price";s:2:"no";s:10:"tax_method";s:15:"tax_rate_method";s:3:"tax";s:2:"no";s:11:"tax_rule_id";s:2:"no";s:12:"existing_tax";s:2:"no";s:6:"ecotax";s:2:"no";s:9:"tax_price";s:2:"no";s:10:"unit_price";s:2:"no";s:5:"unity";s:2:"no";s:7:"on_sale";s:2:"no";s:10:"meta_title";s:2:"no";s:13:"meta_keywords";s:2:"no";s:16:"meta_description";s:2:"no";s:12:"link_rewrite";s:2:"no";s:15:"category_method";s:20:"category_name_method";s:19:"default_category_id";s:2:"no";s:16:"default_category";s:2:"no";s:20:"delimiter_categories";s:1:"/";s:25:"associated_categories_ids";s:2:"no";s:19:"manufacturer_method";s:24:"manufacturer_name_method";s:12:"manufacturer";s:2:"no";s:15:"manufacturer_id";s:2:"no";s:21:"existing_manufacturer";s:2:"no";s:5:"width";s:2:"no";s:6:"height";s:2:"no";s:5:"depth";s:2:"no";s:6:"weight";s:2:"no";s:24:"additional_shipping_cost";s:2:"no";s:25:"additional_delivery_times";s:2:"no";s:17:"delivery_in_stock";s:2:"no";s:18:"delivery_out_stock";s:2:"no";s:11:"carriers_id";s:2:"no";s:25:"advanced_stock_management";s:2:"no";s:16:"depends_on_stock";s:2:"no";s:12:"id_warehouse";s:2:"no";s:18:"warehouse_location";s:2:"no";s:15:"quantity_method";s:8:"override";s:8:"quantity";s:2:"no";s:8:"location";s:2:"no";s:19:"low_stock_threshold";s:2:"no";s:15:"low_stock_alert";s:2:"no";s:12:"out_of_stock";s:2:"no";s:16:"minimal_quantity";s:2:"no";s:13:"available_now";s:2:"no";s:15:"available_later";s:2:"no";s:14:"available_date";s:2:"no";s:19:"virtual_product_url";s:2:"no";s:29:"virtual_product_nb_downloable";s:2:"no";s:31:"virtual_product_expiration_date";s:2:"no";s:23:"virtual_product_nb_days";s:2:"no";s:12:"new_products";s:3:"add";s:17:"existing_products";s:6:"update";s:13:"file_products";s:6:"ignore";s:19:"file_store_products";s:6:"ignore";s:12:"skip_product";s:2:"no";s:14:"remove_product";s:2:"no";s:17:"remove_categories";s:1:"0";s:16:"remove_suppliers";s:1:"0";s:21:"disable_zero_products";s:1:"0";}s:14:"field_category";a:1:{i:0;a:2:{i:0;s:13:"Main Category";i:1;s:13:"Subcategory_1";}}s:22:"import_from_categories";b:0;s:21:"import_from_suppliers";b:0;s:18:"import_from_brands";b:0;s:14:"price_settings";a:1:{i:0;a:5:{s:12:"price_source";s:5:"store";s:11:"price_field";s:15:"wholesale_price";s:9:"condition";s:4:"&lt;";s:15:"condition_value";s:0:"";s:13:"price_formula";s:0:"";}}s:14:"field_settings";a:1:{i:0;a:5:{s:5:"field";s:2:"no";s:9:"condition";s:4:"&lt;";s:15:"condition_value";s:0:"";s:9:"new_field";s:0:"";s:13:"field_formula";s:0:"";}}s:17:"quantity_settings";a:1:{i:0;a:5:{s:15:"quantity_source";s:5:"store";s:14:"quantity_field";s:16:"product_quantity";s:9:"condition";s:4:"&lt;";s:15:"condition_value";s:0:"";s:16:"quantity_formula";s:0:"";}}s:23:"category_linking_active";s:1:"0";s:16:"category_linking";a:0:{}s:14:"field_discount";b:0;s:12:"field_images";a:1:{i:0;a:6:{s:13:"remove_images";s:1:"0";s:19:"generate_thumbnails";s:1:"1";s:17:"no_product_images";s:1:"1";s:13:"images_stream";s:1:"0";s:10:"images_url";s:2:"no";s:10:"images_alt";s:2:"no";}}s:18:"field_combinations";a:1:{i:0;a:30:{s:19:"remove_combinations";s:1:"0";s:15:"combination_key";s:10:"attributes";s:24:"combinations_import_type";s:22:"one_field_combinations";s:9:"attribute";s:2:"no";s:5:"value";s:2:"no";s:21:"reference_combination";s:2:"no";s:17:"ean13_combination";s:2:"no";s:15:"upc_combination";s:2:"no";s:16:"isbn_combination";s:2:"no";s:27:"wholesale_price_combination";s:2:"no";s:11:"final_price";s:2:"no";s:20:"final_price_with_tax";s:2:"no";s:12:"impact_price";s:2:"no";s:21:"impact_price_with_tax";s:2:"no";s:17:"impact_unit_price";s:2:"no";s:13:"impact_weight";s:2:"no";s:18:"ecotax_combination";s:2:"no";s:24:"min_quantity_combination";s:2:"no";s:26:"available_date_combination";s:2:"no";s:24:"id_warehouse_combination";s:2:"no";s:30:"warehouse_location_combination";s:2:"no";s:27:"quantity_combination_method";s:8:"override";s:20:"quantity_combination";s:2:"no";s:20:"location_combination";s:2:"no";s:31:"low_stock_threshold_combination";s:2:"no";s:27:"low_stock_alert_combination";s:2:"no";s:7:"default";s:2:"no";s:6:"images";a:1:{i:0;s:2:"no";}s:27:"supplier_method_combination";s:20:"supplier_name_method";s:9:"suppliers";a:1:{i:0;a:6:{s:8:"supplier";s:2:"no";s:12:"supplier_ids";s:2:"no";s:17:"existing_supplier";s:2:"no";s:18:"supplier_reference";s:2:"no";s:14:"supplier_price";s:2:"no";s:17:"supplier_currency";s:2:"no";}}}}s:14:"field_featured";a:1:{i:0;a:5:{s:15:"remove_features";s:1:"0";s:13:"features_name";s:2:"no";s:22:"features_name_manually";s:0:"";s:14:"features_value";s:2:"no";s:13:"features_type";s:19:"feature_pre_defined";}}s:19:"field_customization";a:1:{i:0;a:5:{s:20:"remove_customization";s:1:"0";s:24:"customization_one_column";s:1:"0";s:18:"customization_type";s:2:"no";s:18:"customization_name";s:2:"no";s:22:"customization_required";s:2:"no";}}s:17:"field_attachments";a:1:{i:0;a:5:{s:18:"remove_attachments";s:1:"0";s:37:"import_attachments_from_single_column";s:1:"0";s:15:"attachment_name";s:2:"no";s:22:"attachment_description";s:2:"no";s:14:"attachment_url";s:2:"no";}}s:15:"field_suppliers";a:1:{i:0;a:11:{s:16:"remove_suppliers";s:1:"0";s:15:"supplier_method";s:20:"supplier_name_method";s:16:"supplier_default";s:2:"no";s:19:"supplier_default_id";s:2:"no";s:25:"existing_supplier_default";s:2:"no";s:8:"supplier";s:2:"no";s:12:"supplier_ids";s:2:"no";s:17:"existing_supplier";s:2:"no";s:18:"supplier_reference";s:2:"no";s:14:"supplier_price";s:2:"no";s:17:"supplier_currency";s:2:"no";}}s:17:"field_accessories";b:0;s:19:"field_pack_products";a:5:{s:20:"remove_pack_products";s:1:"0";s:24:"pack_products_identifier";s:2:"no";s:22:"pack_products_quantity";s:2:"no";s:20:"pack_identifier_type";s:9:"reference";s:25:"pack_identifier_delimiter";s:1:";";}s:9:"name_save";s:18:"Demo Images Import";s:19:"notification_emails";b:0;s:13:"base_settings";a:21:{s:11:"format_file";s:4:"xlsx";s:13:"delimiter_val";b:0;s:15:"import_type_val";s:10:"Add/update";s:7:"id_lang";i:1;s:17:"parser_import_val";s:4:"name";s:18:"name_fields_upload";a:9:{i:0;a:1:{s:4:"name";s:2:"no";}i:1;a:1:{s:4:"name";s:12:"Product name";}i:2;a:1:{s:4:"name";s:4:"Size";}i:3;a:1:{s:4:"name";s:5:"Color";}i:4;a:1:{s:4:"name";s:5:"Image";}i:5;a:1:{s:4:"name";s:13:"Main Category";}i:6;a:1:{s:4:"name";s:13:"Subcategory_1";}i:7;a:1:{s:4:"name";s:8:"Quantity";}i:8;a:1:{s:4:"name";s:5:"Price";}}s:15:"file_import_url";s:58:"https://demo16.myprestamodules.com/files/combinations.xlsx";s:22:"file_import_ftp_server";s:0:"";s:20:"file_import_ftp_user";s:0:"";s:24:"file_import_ftp_password";s:0:"";s:25:"file_import_ftp_file_path";s:0:"";s:11:"feed_source";s:8:"file_url";s:11:"use_headers";s:1:"1";s:13:"disable_hooks";s:1:"1";s:12:"search_index";s:1:"0";s:14:"products_range";s:3:"all";s:10:"from_range";s:1:"1";s:8:"to_range";s:2:"10";s:9:"force_ids";s:1:"0";s:9:"iteration";s:3:"100";s:20:"import_settings_name";s:18:"Demo Images Import";}}', false, Context::getContext()->shop->id_shop_group, Context::getContext()->shop->id);
    Configuration::updateValue('GOMAKOIL_IMPORT_PRODUCTS_4', 'a:22:{s:10:"base_field";a:77:{s:10:"product_id";s:2:"no";s:4:"name";s:12:"Product name";s:9:"reference";s:2:"no";s:5:"ean13";s:2:"no";s:3:"upc";s:2:"no";s:4:"isbn";s:2:"no";s:8:"date_add";s:2:"no";s:6:"active";s:2:"no";s:10:"visibility";s:2:"no";s:19:"available_for_order";s:2:"no";s:10:"show_price";s:2:"no";s:11:"online_only";s:2:"no";s:14:"show_condition";s:2:"no";s:9:"condition";s:2:"no";s:17:"short_description";s:2:"no";s:11:"description";s:2:"no";s:4:"tags";s:2:"no";s:15:"wholesale_price";s:2:"no";s:5:"price";s:2:"no";s:10:"tax_method";s:15:"tax_rate_method";s:3:"tax";s:2:"no";s:11:"tax_rule_id";s:2:"no";s:12:"existing_tax";s:2:"no";s:6:"ecotax";s:2:"no";s:9:"tax_price";s:2:"no";s:10:"unit_price";s:2:"no";s:5:"unity";s:2:"no";s:7:"on_sale";s:2:"no";s:10:"meta_title";s:2:"no";s:13:"meta_keywords";s:2:"no";s:16:"meta_description";s:2:"no";s:12:"link_rewrite";s:2:"no";s:15:"category_method";s:20:"category_name_method";s:19:"default_category_id";s:2:"no";s:16:"default_category";s:2:"no";s:20:"delimiter_categories";s:1:"/";s:25:"associated_categories_ids";s:2:"no";s:19:"manufacturer_method";s:24:"manufacturer_name_method";s:12:"manufacturer";s:2:"no";s:15:"manufacturer_id";s:2:"no";s:21:"existing_manufacturer";s:2:"no";s:5:"width";s:2:"no";s:6:"height";s:2:"no";s:5:"depth";s:2:"no";s:6:"weight";s:2:"no";s:24:"additional_shipping_cost";s:2:"no";s:25:"additional_delivery_times";s:2:"no";s:17:"delivery_in_stock";s:2:"no";s:18:"delivery_out_stock";s:2:"no";s:11:"carriers_id";s:2:"no";s:25:"advanced_stock_management";s:2:"no";s:16:"depends_on_stock";s:2:"no";s:12:"id_warehouse";s:2:"no";s:18:"warehouse_location";s:2:"no";s:15:"quantity_method";s:8:"override";s:8:"quantity";s:2:"no";s:8:"location";s:2:"no";s:19:"low_stock_threshold";s:2:"no";s:15:"low_stock_alert";s:2:"no";s:12:"out_of_stock";s:2:"no";s:16:"minimal_quantity";s:2:"no";s:13:"available_now";s:2:"no";s:15:"available_later";s:2:"no";s:14:"available_date";s:2:"no";s:19:"virtual_product_url";s:2:"no";s:29:"virtual_product_nb_downloable";s:2:"no";s:31:"virtual_product_expiration_date";s:2:"no";s:23:"virtual_product_nb_days";s:2:"no";s:12:"new_products";s:3:"add";s:17:"existing_products";s:6:"update";s:13:"file_products";s:6:"ignore";s:19:"file_store_products";s:6:"ignore";s:12:"skip_product";s:2:"no";s:14:"remove_product";s:2:"no";s:17:"remove_categories";s:1:"0";s:16:"remove_suppliers";s:1:"0";s:21:"disable_zero_products";s:1:"0";}s:14:"field_category";a:1:{i:0;a:2:{i:0;s:13:"Main Category";i:1;s:13:"Subcategory_1";}}s:22:"import_from_categories";b:0;s:21:"import_from_suppliers";b:0;s:18:"import_from_brands";b:0;s:14:"price_settings";a:1:{i:0;a:5:{s:12:"price_source";s:5:"store";s:11:"price_field";s:15:"wholesale_price";s:9:"condition";s:4:"&lt;";s:15:"condition_value";s:0:"";s:13:"price_formula";s:0:"";}}s:14:"field_settings";a:1:{i:0;a:5:{s:5:"field";s:2:"no";s:9:"condition";s:4:"&lt;";s:15:"condition_value";s:0:"";s:9:"new_field";s:0:"";s:13:"field_formula";s:0:"";}}s:17:"quantity_settings";a:1:{i:0;a:5:{s:15:"quantity_source";s:5:"store";s:14:"quantity_field";s:16:"product_quantity";s:9:"condition";s:4:"&lt;";s:15:"condition_value";s:0:"";s:16:"quantity_formula";s:0:"";}}s:23:"category_linking_active";s:1:"0";s:16:"category_linking";a:0:{}s:14:"field_discount";b:0;s:12:"field_images";a:1:{i:0;a:6:{s:13:"remove_images";s:1:"0";s:19:"generate_thumbnails";s:1:"1";s:17:"no_product_images";s:1:"1";s:13:"images_stream";s:1:"0";s:10:"images_url";s:5:"Image";s:10:"images_alt";s:2:"no";}}s:18:"field_combinations";a:1:{i:0;a:30:{s:19:"remove_combinations";s:1:"0";s:15:"combination_key";s:10:"attributes";s:24:"combinations_import_type";s:22:"one_field_combinations";s:9:"attribute";s:2:"no";s:5:"value";s:2:"no";s:21:"reference_combination";s:2:"no";s:17:"ean13_combination";s:2:"no";s:15:"upc_combination";s:2:"no";s:16:"isbn_combination";s:2:"no";s:27:"wholesale_price_combination";s:2:"no";s:11:"final_price";s:2:"no";s:20:"final_price_with_tax";s:2:"no";s:12:"impact_price";s:2:"no";s:21:"impact_price_with_tax";s:2:"no";s:17:"impact_unit_price";s:2:"no";s:13:"impact_weight";s:2:"no";s:18:"ecotax_combination";s:2:"no";s:24:"min_quantity_combination";s:2:"no";s:26:"available_date_combination";s:2:"no";s:24:"id_warehouse_combination";s:2:"no";s:30:"warehouse_location_combination";s:2:"no";s:27:"quantity_combination_method";s:8:"override";s:20:"quantity_combination";s:2:"no";s:20:"location_combination";s:2:"no";s:31:"low_stock_threshold_combination";s:2:"no";s:27:"low_stock_alert_combination";s:2:"no";s:7:"default";s:2:"no";s:6:"images";a:1:{i:0;s:2:"no";}s:27:"supplier_method_combination";s:20:"supplier_name_method";s:9:"suppliers";a:1:{i:0;a:6:{s:8:"supplier";s:2:"no";s:12:"supplier_ids";s:2:"no";s:17:"existing_supplier";s:2:"no";s:18:"supplier_reference";s:2:"no";s:14:"supplier_price";s:2:"no";s:17:"supplier_currency";s:2:"no";}}}}s:14:"field_featured";a:3:{i:0;a:5:{s:15:"remove_features";s:1:"1";s:13:"features_name";s:14:"enter_manually";s:22:"features_name_manually";s:12:"Compositions";s:14:"features_value";s:20:"FEATURE_Compositions";s:13:"features_type";s:19:"feature_pre_defined";}i:1;a:4:{s:13:"features_name";s:14:"enter_manually";s:22:"features_name_manually";s:6:"Styles";s:14:"features_value";s:14:"FEATURE_Styles";s:13:"features_type";s:19:"feature_pre_defined";}i:2;a:4:{s:13:"features_name";s:14:"enter_manually";s:22:"features_name_manually";s:10:"Properties";s:14:"features_value";s:18:"FEATURE_Properties";s:13:"features_type";s:19:"feature_pre_defined";}}s:19:"field_customization";a:1:{i:0;a:5:{s:20:"remove_customization";s:1:"0";s:24:"customization_one_column";s:1:"0";s:18:"customization_type";s:2:"no";s:18:"customization_name";s:2:"no";s:22:"customization_required";s:2:"no";}}s:17:"field_attachments";a:1:{i:0;a:5:{s:18:"remove_attachments";s:1:"0";s:37:"import_attachments_from_single_column";s:1:"0";s:15:"attachment_name";s:2:"no";s:22:"attachment_description";s:2:"no";s:14:"attachment_url";s:2:"no";}}s:15:"field_suppliers";a:1:{i:0;a:11:{s:16:"remove_suppliers";s:1:"0";s:15:"supplier_method";s:20:"supplier_name_method";s:16:"supplier_default";s:2:"no";s:19:"supplier_default_id";s:2:"no";s:25:"existing_supplier_default";s:2:"no";s:8:"supplier";s:2:"no";s:12:"supplier_ids";s:2:"no";s:17:"existing_supplier";s:2:"no";s:18:"supplier_reference";s:2:"no";s:14:"supplier_price";s:2:"no";s:17:"supplier_currency";s:2:"no";}}s:17:"field_accessories";b:0;s:19:"field_pack_products";a:5:{s:20:"remove_pack_products";s:1:"0";s:24:"pack_products_identifier";s:2:"no";s:22:"pack_products_quantity";s:2:"no";s:20:"pack_identifier_type";s:9:"reference";s:25:"pack_identifier_delimiter";s:1:";";}s:9:"name_save";s:20:"Demo Features Import";s:19:"notification_emails";b:0;s:13:"base_settings";a:21:{s:11:"format_file";s:4:"xlsx";s:13:"delimiter_val";b:0;s:15:"import_type_val";s:10:"Add/update";s:7:"id_lang";i:1;s:17:"parser_import_val";s:4:"name";s:18:"name_fields_upload";a:8:{i:0;a:1:{s:4:"name";s:2:"no";}i:1;a:1:{s:4:"name";s:12:"Product name";}i:2;a:1:{s:4:"name";s:20:"FEATURE_Compositions";}i:3;a:1:{s:4:"name";s:14:"FEATURE_Styles";}i:4;a:1:{s:4:"name";s:18:"FEATURE_Properties";}i:5;a:1:{s:4:"name";s:13:"Main Category";}i:6;a:1:{s:4:"name";s:13:"Subcategory_1";}i:7;a:1:{s:4:"name";s:5:"Image";}}s:15:"file_import_url";s:54:"https://demo16.myprestamodules.com/files/features.xlsx";s:22:"file_import_ftp_server";s:0:"";s:20:"file_import_ftp_user";s:0:"";s:24:"file_import_ftp_password";s:0:"";s:25:"file_import_ftp_file_path";s:0:"";s:11:"feed_source";s:8:"file_url";s:11:"use_headers";s:1:"1";s:13:"disable_hooks";s:1:"1";s:12:"search_index";s:1:"0";s:14:"products_range";s:3:"all";s:10:"from_range";s:1:"1";s:8:"to_range";s:2:"10";s:9:"force_ids";s:1:"0";s:9:"iteration";s:3:"100";s:20:"import_settings_name";s:20:"Demo Features Import";}}', false, Context::getContext()->shop->id_shop_group, Context::getContext()->shop->id);
    Configuration::updateValue('GOMAKOIL_IMPORT_PRODUCTS_5', 'a:22:{s:10:"base_field";a:77:{s:10:"product_id";s:2:"no";s:4:"name";s:12:"Product name";s:9:"reference";s:14:"Reference code";s:5:"ean13";s:2:"no";s:3:"upc";s:2:"no";s:4:"isbn";s:2:"no";s:8:"date_add";s:2:"no";s:6:"active";s:2:"no";s:10:"visibility";s:2:"no";s:19:"available_for_order";s:2:"no";s:10:"show_price";s:2:"no";s:11:"online_only";s:2:"no";s:14:"show_condition";s:2:"no";s:9:"condition";s:2:"no";s:17:"short_description";s:17:"Short description";s:11:"description";s:11:"Description";s:4:"tags";s:2:"no";s:15:"wholesale_price";s:23:"Pre-tax wholesale price";s:5:"price";s:20:"Pre-tax retail price";s:10:"tax_method";s:15:"tax_rate_method";s:3:"tax";s:8:"Tax rate";s:11:"tax_rule_id";s:2:"no";s:12:"existing_tax";s:2:"no";s:6:"ecotax";s:2:"no";s:9:"tax_price";s:2:"no";s:10:"unit_price";s:2:"no";s:5:"unity";s:2:"no";s:7:"on_sale";s:2:"no";s:10:"meta_title";s:2:"no";s:13:"meta_keywords";s:2:"no";s:16:"meta_description";s:2:"no";s:12:"link_rewrite";s:2:"no";s:15:"category_method";s:20:"category_name_method";s:19:"default_category_id";s:2:"no";s:16:"default_category";s:2:"no";s:20:"delimiter_categories";s:1:"/";s:25:"associated_categories_ids";s:2:"no";s:19:"manufacturer_method";s:24:"manufacturer_name_method";s:12:"manufacturer";s:12:"Manufacturer";s:15:"manufacturer_id";s:2:"no";s:21:"existing_manufacturer";s:2:"no";s:5:"width";s:2:"no";s:6:"height";s:2:"no";s:5:"depth";s:2:"no";s:6:"weight";s:2:"no";s:24:"additional_shipping_cost";s:2:"no";s:25:"additional_delivery_times";s:2:"no";s:17:"delivery_in_stock";s:2:"no";s:18:"delivery_out_stock";s:2:"no";s:11:"carriers_id";s:2:"no";s:25:"advanced_stock_management";s:2:"no";s:16:"depends_on_stock";s:2:"no";s:12:"id_warehouse";s:2:"no";s:18:"warehouse_location";s:2:"no";s:15:"quantity_method";s:8:"override";s:8:"quantity";s:2:"no";s:8:"location";s:2:"no";s:19:"low_stock_threshold";s:2:"no";s:15:"low_stock_alert";s:2:"no";s:12:"out_of_stock";s:2:"no";s:16:"minimal_quantity";s:2:"no";s:13:"available_now";s:2:"no";s:15:"available_later";s:2:"no";s:14:"available_date";s:2:"no";s:19:"virtual_product_url";s:2:"no";s:29:"virtual_product_nb_downloable";s:2:"no";s:31:"virtual_product_expiration_date";s:2:"no";s:23:"virtual_product_nb_days";s:2:"no";s:12:"new_products";s:3:"add";s:17:"existing_products";s:6:"update";s:13:"file_products";s:6:"ignore";s:19:"file_store_products";s:6:"ignore";s:12:"skip_product";s:2:"no";s:14:"remove_product";s:2:"no";s:17:"remove_categories";s:1:"0";s:16:"remove_suppliers";s:1:"0";s:21:"disable_zero_products";s:1:"0";}s:14:"field_category";a:1:{i:0;a:2:{i:0;s:13:"Main Category";i:1;s:14:"Child category";}}s:22:"import_from_categories";b:0;s:21:"import_from_suppliers";b:0;s:18:"import_from_brands";b:0;s:14:"price_settings";a:1:{i:0;a:5:{s:12:"price_source";s:5:"store";s:11:"price_field";s:15:"wholesale_price";s:9:"condition";s:4:"&lt;";s:15:"condition_value";s:0:"";s:13:"price_formula";s:0:"";}}s:14:"field_settings";a:1:{i:0;a:5:{s:5:"field";s:2:"no";s:9:"condition";s:4:"&lt;";s:15:"condition_value";s:0:"";s:9:"new_field";s:0:"";s:13:"field_formula";s:0:"";}}s:17:"quantity_settings";a:1:{i:0;a:5:{s:15:"quantity_source";s:5:"store";s:14:"quantity_field";s:16:"product_quantity";s:9:"condition";s:4:"&lt;";s:15:"condition_value";s:0:"";s:16:"quantity_formula";s:0:"";}}s:23:"category_linking_active";s:1:"0";s:16:"category_linking";a:0:{}s:14:"field_discount";b:0;s:12:"field_images";a:1:{i:0;a:6:{s:13:"remove_images";s:1:"0";s:19:"generate_thumbnails";s:1:"1";s:17:"no_product_images";s:1:"1";s:13:"images_stream";s:1:"0";s:10:"images_url";s:2:"no";s:10:"images_alt";s:2:"no";}}s:18:"field_combinations";a:1:{i:0;a:36:{s:19:"remove_combinations";s:1:"0";s:15:"combination_key";s:10:"attributes";s:24:"combinations_import_type";s:24:"separate_combination_row";s:16:"single_attribute";a:2:{i:0;s:14:"enter_manually";i:1;s:14:"enter_manually";}s:18:"manually_attribute";a:2:{i:0;s:4:"Size";i:1;s:5:"Color";}s:11:"single_type";a:2:{i:0;s:6:"select";i:1;s:5:"color";}s:12:"single_color";a:2:{i:0;s:2:"no";i:1;s:2:"no";}s:12:"single_value";a:2:{i:0;s:4:"Size";i:1;s:5:"Color";}s:16:"single_delimiter";a:2:{i:0;s:1:";";i:1;s:1:";";}s:9:"attribute";s:2:"no";s:5:"value";s:2:"no";s:21:"reference_combination";s:2:"no";s:17:"ean13_combination";s:2:"no";s:15:"upc_combination";s:2:"no";s:16:"isbn_combination";s:2:"no";s:27:"wholesale_price_combination";s:2:"no";s:11:"final_price";s:2:"no";s:20:"final_price_with_tax";s:17:"Combination price";s:12:"impact_price";s:2:"no";s:21:"impact_price_with_tax";s:2:"no";s:17:"impact_unit_price";s:2:"no";s:13:"impact_weight";s:2:"no";s:18:"ecotax_combination";s:2:"no";s:24:"min_quantity_combination";s:2:"no";s:26:"available_date_combination";s:2:"no";s:24:"id_warehouse_combination";s:2:"no";s:30:"warehouse_location_combination";s:2:"no";s:27:"quantity_combination_method";s:8:"override";s:20:"quantity_combination";s:8:"Quantity";s:20:"location_combination";s:2:"no";s:31:"low_stock_threshold_combination";s:2:"no";s:27:"low_stock_alert_combination";s:2:"no";s:7:"default";s:2:"no";s:6:"images";a:1:{i:0;s:5:"Image";}s:27:"supplier_method_combination";s:20:"supplier_name_method";s:9:"suppliers";a:1:{i:0;a:6:{s:8:"supplier";s:2:"no";s:12:"supplier_ids";s:2:"no";s:17:"existing_supplier";s:2:"no";s:18:"supplier_reference";s:2:"no";s:14:"supplier_price";s:2:"no";s:17:"supplier_currency";s:2:"no";}}}}s:14:"field_featured";a:3:{i:0;a:5:{s:15:"remove_features";s:1:"1";s:13:"features_name";s:14:"enter_manually";s:22:"features_name_manually";s:12:"Compositions";s:14:"features_value";s:20:"FEATURE_Compositions";s:13:"features_type";s:19:"feature_pre_defined";}i:1;a:4:{s:13:"features_name";s:14:"enter_manually";s:22:"features_name_manually";s:6:"Styles";s:14:"features_value";s:14:"FEATURE_Styles";s:13:"features_type";s:19:"feature_pre_defined";}i:2;a:4:{s:13:"features_name";s:14:"enter_manually";s:22:"features_name_manually";s:10:"Properties";s:14:"features_value";s:18:"FEATURE_Properties";s:13:"features_type";s:19:"feature_pre_defined";}}s:19:"field_customization";a:1:{i:0;a:5:{s:20:"remove_customization";s:1:"0";s:24:"customization_one_column";s:1:"0";s:18:"customization_type";s:2:"no";s:18:"customization_name";s:2:"no";s:22:"customization_required";s:2:"no";}}s:17:"field_attachments";a:1:{i:0;a:5:{s:18:"remove_attachments";s:1:"0";s:37:"import_attachments_from_single_column";s:1:"0";s:15:"attachment_name";s:2:"no";s:22:"attachment_description";s:2:"no";s:14:"attachment_url";s:2:"no";}}s:15:"field_suppliers";a:1:{i:0;a:11:{s:16:"remove_suppliers";s:1:"0";s:15:"supplier_method";s:20:"supplier_name_method";s:16:"supplier_default";s:21:"Default supplier name";s:19:"supplier_default_id";s:2:"no";s:25:"existing_supplier_default";s:2:"no";s:8:"supplier";s:21:"Default supplier name";s:12:"supplier_ids";s:2:"no";s:17:"existing_supplier";s:2:"no";s:18:"supplier_reference";s:2:"no";s:14:"supplier_price";s:2:"no";s:17:"supplier_currency";s:2:"no";}}s:17:"field_accessories";b:0;s:19:"field_pack_products";a:5:{s:20:"remove_pack_products";s:1:"0";s:24:"pack_products_identifier";s:2:"no";s:22:"pack_products_quantity";s:2:"no";s:20:"pack_identifier_type";s:9:"reference";s:25:"pack_identifier_delimiter";s:1:";";}s:9:"name_save";s:21:"Demo Automatic Import";s:19:"notification_emails";b:0;s:13:"base_settings";a:21:{s:11:"format_file";s:4:"xlsx";s:13:"delimiter_val";b:0;s:15:"import_type_val";s:10:"Add/update";s:7:"id_lang";i:1;s:17:"parser_import_val";s:4:"name";s:18:"name_fields_upload";a:20:{i:0;a:1:{s:4:"name";s:2:"no";}i:1;a:1:{s:4:"name";s:12:"Product name";}i:2;a:1:{s:4:"name";s:14:"Reference code";}i:3;a:1:{s:4:"name";s:17:"Short description";}i:4;a:1:{s:4:"name";s:11:"Description";}i:5;a:1:{s:4:"name";s:23:"Pre-tax wholesale price";}i:6;a:1:{s:4:"name";s:20:"Pre-tax retail price";}i:7;a:1:{s:4:"name";s:8:"Tax rate";}i:8;a:1:{s:4:"name";s:21:"Default supplier name";}i:9;a:1:{s:4:"name";s:12:"Manufacturer";}i:10;a:1:{s:4:"name";s:20:"FEATURE_Compositions";}i:11;a:1:{s:4:"name";s:14:"FEATURE_Styles";}i:12;a:1:{s:4:"name";s:18:"FEATURE_Properties";}i:13;a:1:{s:4:"name";s:13:"Main Category";}i:14;a:1:{s:4:"name";s:14:"Child category";}i:15;a:1:{s:4:"name";s:4:"Size";}i:16;a:1:{s:4:"name";s:5:"Color";}i:17;a:1:{s:4:"name";s:5:"Image";}i:18;a:1:{s:4:"name";s:8:"Quantity";}i:19;a:1:{s:4:"name";s:17:"Combination price";}}s:15:"file_import_url";s:55:"https://demo16.myprestamodules.com/files/automatic.xlsx";s:22:"file_import_ftp_server";s:0:"";s:20:"file_import_ftp_user";s:0:"";s:24:"file_import_ftp_password";s:0:"";s:25:"file_import_ftp_file_path";s:0:"";s:11:"feed_source";s:8:"file_url";s:11:"use_headers";s:1:"1";s:13:"disable_hooks";s:1:"1";s:12:"search_index";s:1:"0";s:14:"products_range";s:3:"all";s:10:"from_range";s:1:"1";s:8:"to_range";s:2:"10";s:9:"force_ids";s:1:"0";s:9:"iteration";s:3:"100";s:20:"import_settings_name";s:21:"Demo Automatic Import";}}', false, Context::getContext()->shop->id_shop_group, Context::getContext()->shop->id);
    
    return true;
  }

  public function upgrade_5_0_0()
  {
    Configuration::updateGlobalValue('GOMAKOIL_IMPORT_TASKS_KEY', md5(_COOKIE_KEY_.Configuration::get('PS_SHOP_NAME')));
    return true;
  }

  public function upgrade_5_0_2()
  {
    $sql = 'CREATE TABLE IF NOT EXISTS ' . _DB_PREFIX_ . 'simpleimport_images(
			`id` INT NOT NULL AUTO_INCREMENT,
      `image_url` VARCHAR(500) NULL,
      `id_shop` INT NULL,
      `id_product` INT NULL,
      `id_image` INT NULL,
      PRIMARY KEY (`id`),
      INDEX `simpleimport_images` (`id_product` ASC, `image_url` ASC, `id_shop` ASC),
      INDEX `simpleimport_images2` (`id_product` ASC, `id_shop` ASC)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8';

    Db::getInstance()->execute($sql);
    return true;
  }

  public function upgrade_5_1_0()
  {
    $sql = 'DROP TABLE IF EXISTS ' . _DB_PREFIX_ . 'simpleimport_images_path';
    Db::getInstance()->execute($sql);

    $sql = '
      CREATE TABLE IF NOT EXISTS ' . _DB_PREFIX_ . 'simpleimport_images_path(
        `id_image` INT(11) NOT NULL AUTO_INCREMENT,
        `image_url` VARCHAR(500) NOT NULL,
        `image_path` VARCHAR(500) NOT NULL,
        `processed` INT(1) NOT NULL,
        PRIMARY KEY (`id_image`),
        INDEX `index2` (`image_url` ASC));
    ';
    Db::getInstance()->execute($sql);

    return true;
  }

  public function upgrade_5_1_6()
  {
    $sql = '
      ALTER TABLE ' . _DB_PREFIX_ . 'simpleimport_tasks
        ADD COLUMN `progress` VARCHAR(500) NOT NULL AFTER `last_finish`
        ;
    ';

    Db::getInstance()->execute($sql);

    return true;
  }

  public function upgrade_6_2_7()
  {
    $sql = 'DROP TABLE IF EXISTS ' . _DB_PREFIX_ . 'simpleimport_queue';
    $res = Db::getInstance()->execute($sql);
    if( !$res ){
      return false;
    }

    $sql = '
    CREATE TABLE ' . _DB_PREFIX_ . 'simpleimport_queue (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `id_task` int(11) DEFAULT NULL,
    PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE utf8_general_ci
    ';
    $res = Db::getInstance()->execute($sql);
    if( !$res ){
      return false;
    }

    return true;
  }

  public function upgrade_6_3_0()
  {
    $sql = '
      ALTER TABLE ' . _DB_PREFIX_ . 'simpleimport_tasks
        ADD COLUMN `error_email` INT(1) NOT NULL AFTER `id_shop_group`
        ;
    ';

    $res = Db::getInstance()->execute($sql);
    if( $res ){
      Db::getInstance()->execute('TRUNCATE '._DB_PREFIX_.'simpleimport_tasks');
    }

    return $res;
  }

  public function upgrade_6_2_0()
  {
    $sql = '
      ALTER TABLE ' . _DB_PREFIX_ . 'simpleimport_tasks
        DROP COLUMN `day_of_week`,
        CHANGE COLUMN `hour` `frequency` VARCHAR(255) NOT NULL ,
        CHANGE COLUMN `day` `email_notification` INT(1) NOT NULL ,
        CHANGE COLUMN `month` `notification_emails` VARCHAR(255) NOT NULL
        ;
    ';

    $res = Db::getInstance()->execute($sql);
    if( $res ){
      Db::getInstance()->execute('TRUNCATE '._DB_PREFIX_.'simpleimport_tasks');
    }

    return $res;
  }

  public function upgrade_5_3_0()
  {
    $sql = '
      ALTER TABLE ' . _DB_PREFIX_ . 'simpleimport_images
        ADD COLUMN `processed` int(1) DEFAULT "0" AFTER `id_image`,
        ADD COLUMN `id_task` int(11) NOT NULL AFTER `id_image`
        ;
    ';

    $res = Db::getInstance()->execute($sql);

    if( !$res ){
      return false;
    }

    $sql = '
      ALTER TABLE ' . _DB_PREFIX_ . 'simpleimport_tasks
      CHANGE COLUMN `progress` `progress` VARCHAR(500) NOT NULL
        ;
    ';

    $res = Db::getInstance()->execute($sql);
    if( !$res ){
      return false;
    }

    $sql = 'DROP TABLE IF EXISTS ' . _DB_PREFIX_ . 'simpleimport_data';
    $res = Db::getInstance()->execute($sql);
    if( !$res ){
      return false;
    }

    $sql = 'CREATE TABLE IF NOT EXISTS ' . _DB_PREFIX_ . 'simpleimport_data(
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `row` int(11) NOT NULL,
            `field` varchar(254) NOT NULL,
            `value` text NOT NULL,
            `id_task` int(11) NOT NULL,
            PRIMARY KEY (`id`),
            KEY `index2` (`row`)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8';

    $res = Db::getInstance()->execute($sql);
    if( !$res ){
      return false;
    }

    return true;
  }

  public function installDb()
  {
    // Table  pages lang
    $sql = 'DROP TABLE IF EXISTS ' . _DB_PREFIX_ . 'simpleimport_images';
    $res = Db::getInstance()->execute($sql);
    if( !$res ){
      return false;
    }

    $sql = 'CREATE TABLE IF NOT EXISTS ' . _DB_PREFIX_ . 'simpleimport_images(
			`id` INT NOT NULL AUTO_INCREMENT,
      `image_url` VARCHAR(500) NULL,
      `id_shop` INT NULL,
      `id_product` INT NULL,
      `id_image` INT NULL,
      `processed` int(1) DEFAULT "0",
      `id_task` INT NULL,
      PRIMARY KEY (`id`),
      INDEX `simpleimport_images2` (`id_product` ASC, `id_shop` ASC)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE utf8_general_ci';

    $res = Db::getInstance()->execute($sql);
    if( !$res ){
      return false;
    }

    $sql = 'ALTER TABLE `' . _DB_PREFIX_ . 'simpleimport_images`
            ADD INDEX `simpleimport_images` (`id_product` ASC, `image_url`(255) ASC, `id_shop` ASC);
            ';
    Db::getInstance()->execute($sql);

    $sql = 'DROP TABLE IF EXISTS ' . _DB_PREFIX_ . 'simpleimport_images_path';
    $res = Db::getInstance()->execute($sql);
    if( !$res ){
      return false;
    }

    $sql = '
      CREATE TABLE IF NOT EXISTS ' . _DB_PREFIX_ . 'simpleimport_images_path(
        `id_image` INT(11) NOT NULL AUTO_INCREMENT,
        `image_url` VARCHAR(500) NOT NULL,
        `image_path` VARCHAR(500) NOT NULL,
        `processed` INT(1) NULL,
        PRIMARY KEY (`id_image`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE utf8_general_ci
    ';
    $res = Db::getInstance()->execute($sql);
    if( !$res ){
      return false;
    }

    $sql = 'ALTER TABLE `' . _DB_PREFIX_ . 'simpleimport_images_path`
            ADD INDEX `image` (`image_url`(255) ASC);
            ';
    Db::getInstance()->execute($sql);

    $sql = 'DROP TABLE IF EXISTS ' . _DB_PREFIX_ . 'simpleimport_products';
    $res = Db::getInstance()->execute($sql);
    if( !$res ){
      return false;
    }

    $sql = 'CREATE TABLE IF NOT EXISTS ' . _DB_PREFIX_ . 'simpleimport_products(
			`id` INT NOT NULL AUTO_INCREMENT,
      `id_shop` INT NULL,
      `id_product` INT NULL,
      PRIMARY KEY (`id`),
      INDEX `simpleimport_products` (`id_product` ASC, `id_shop` ASC)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE utf8_general_ci';

    $res = Db::getInstance()->execute($sql);
    if( !$res ){
      return false;
    }

    $sql = 'DROP TABLE IF EXISTS ' . _DB_PREFIX_ . 'simpleimport_tasks';
    $res = Db::getInstance()->execute($sql);
    if( !$res ){
      return false;
    }

    $sql = 'CREATE TABLE IF NOT EXISTS ' . _DB_PREFIX_ . 'simpleimport_tasks(
            `id_task` int(11) NOT NULL AUTO_INCREMENT,
            `description` varchar(255) NOT NULL,
            `import_settings` int(11) NOT NULL,
            `frequency` varchar(255) NOT NULL,
            `email_notification` int(1) NOT NULL,
            `error_email` int(1) NOT NULL,
            `notification_emails` varchar(255) NOT NULL,
            `last_start` varchar(45) NOT NULL,
            `last_finish` varchar(45) NOT NULL,
            `progress` varchar(500) DEFAULT NULL,
            `active` int(1) NOT NULL,
            `one_shot` int(1) NOT NULL,
            `id_shop` int(11) NOT NULL,
            `id_shop_group` int(11) NOT NULL,
            PRIMARY KEY (`id_task`)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE utf8_general_ci';

    $res = Db::getInstance()->execute($sql);
    if( !$res ){
      return false;
    }

    $sql = 'DROP TABLE IF EXISTS ' . _DB_PREFIX_ . 'simpleimport_data';
    $res = Db::getInstance()->execute($sql);
    if( !$res ){
      return false;
    }

    $sql = 'CREATE TABLE IF NOT EXISTS ' . _DB_PREFIX_ . 'simpleimport_data(
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `row` int(11) NOT NULL,
            `field` varchar(254) NOT NULL,
            `value` text NOT NULL,
            `id_task` int(11) NULL,
            PRIMARY KEY (`id`),
            KEY `index2` (`row`)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE utf8_general_ci';

    $res = Db::getInstance()->execute($sql);
    if( !$res ){
      return false;
    }

    $sql = 'DROP TABLE IF EXISTS ' . _DB_PREFIX_ . 'simpleimport_queue';
    $res = Db::getInstance()->execute($sql);
    if( !$res ){
      return false;
    }

    $sql = '
    CREATE TABLE ' . _DB_PREFIX_ . 'simpleimport_queue (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `id_task` int(11) DEFAULT NULL,
    PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE utf8_general_ci
    ';
    $res = Db::getInstance()->execute($sql);
    if( !$res ){
      return false;
    }

    return true;
  }

  public function uninstall(){
    $this->_removeTab();
    $this->uninstallDb();

    $count_save = Tools::unserialize(Configuration::get('GOMAKOIL_IMPORT_COUNT_SETTINGS',null,Context::getContext()->shop->id_shop_group,Context::getContext()->shop->id));
    if(isset($count_save) && $count_save){
      foreach($count_save as $value){
        Configuration::deleteByName('GOMAKOIL_IMPORT_PRODUCTS_'.$value);
      }
    }
    Configuration::deleteByName('GOMAKOIL_IMPORT_COUNT_SETTINGS');
    Configuration::deleteByName('GOMAKOIL_IMPORT_TASKS_KEY');
    return parent::uninstall();
  }

  public function uninstallDb()
  {
    $sql = 'DROP TABLE IF EXISTS '._DB_PREFIX_.'simpleimport_images';
    Db::getInstance()->execute($sql);

    $sql = 'DROP TABLE IF EXISTS '._DB_PREFIX_.'simpleimport_images_path';
    Db::getInstance()->execute($sql);

    $sql = 'DROP TABLE IF EXISTS '._DB_PREFIX_.'simpleimport_products';
    Db::getInstance()->execute($sql);

    $sql = 'DROP TABLE IF EXISTS '._DB_PREFIX_.'simpleimport_tasks';
    Db::getInstance()->execute($sql);

    $sql = 'DROP TABLE IF EXISTS ' . _DB_PREFIX_ . 'simpleimport_data';
    Db::getInstance()->execute($sql);
  }

  private function _createTab()
  {
    $tab = new Tab();
    $tab->active = 1;
    $tab->class_name = 'AdminProductsimport';
    $tab->name = array();
    foreach (Language::getLanguages(true) as $lang)
      $tab->name[$lang['id_lang']] = 'Products_Import';
    $tab->id_parent = -1;
    $tab->module = $this->name;
    $tab->add();
  }

  private function _removeTab()
  {
    $id_tab = (int)Tab::getIdFromClassName('AdminProductsimport');
    if ($id_tab)
    {
      $tab = new Tab($id_tab);
      $tab->delete();
    }
  }

  public function hookActionAdminControllerSetMedia()
  {
    if( Tools::getValue('configure') == "simpleimportproduct" ){
      $this->context->controller->addCSS(_PS_BASE_URL_SSL_.__PS_BASE_URI__.'modules/simpleimportproduct/views/css/simpleimportproduct.css?v='.$this->version);
      $this->context->controller->addCSS(_PS_BASE_URL_SSL_.__PS_BASE_URI__.'modules/simpleimportproduct/views/css/error.css?v='.$this->version);
      $this->context->controller->addCSS(_PS_BASE_URL_SSL_.__PS_BASE_URI__.'modules/simpleimportproduct/views/css/icons.css?v='.$this->version);
      $this->context->controller->addJqueryUI('ui.sortable');
      $this->context->controller->addJS(_PS_BASE_URL_SSL_.__PS_BASE_URI__.'modules/simpleimportproduct/views/js/simpleimportproduct.min.js?v=1'.$this->version);
      $this->context->controller->addJS(_PS_BASE_URL_SSL_.__PS_BASE_URI__.'modules/simpleimportproduct/views/js/error.min.js?v=1'.$this->version);
    }
  }

  public function getTaskInQueue()
  {
    $sql = '
      SELECT *
      FROM ' . _DB_PREFIX_ . 'simpleimport_queue
      ORDER by id ASC
      LIMIT 1
    ';

    $res = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);

    if( isset($res[0]) && $res[0] ){
      $this->_removeTaskFromQueue($res[0]['id']);
      return $res[0]['id_task'];
    }

    return false;
  }

  private function _removeTaskFromQueue( $id )
  {
    Db::getInstance(_PS_USE_SQL_SLAVE_)->delete('simpleimport_queue', 'id='.$id);
  }

  public function addTaskToQueue( $idTask, $updateStatus = false )
  {
    if( !$this->_checkTaskInQueue( $idTask ) ){
      $data = array(
        'id_task' => $idTask
      );
      Db::getInstance(_PS_USE_SQL_SLAVE_)->insert('simpleimport_queue', $data);

      if( $updateStatus ){
        $this->setTaskStatus($idTask, $this->l('In queue'));
      }
    }
  }

  private function _checkTaskInQueue( $idTask )
  {
    $sql = '
      SELECT count(*) as count
      FROM ' . _DB_PREFIX_ . 'simpleimport_queue
      WHERE id_task = '.(int)$idTask.'
    ';

    $res = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
    return (bool)$res[0]['count'];
  }

  public function checkImportRunning( $justProducts = false )
  {
    $productsImport = false;
    $imagesImport = false;
    if( Configuration::getGlobalValue('GOMAKOIL_IMPORT_RUNNING') ){
      $time = (int)Configuration::getGlobalValue('GOMAKOIL_IMPORT_RUNNING');
      if( (time() - $time) < 60 ){
        $productsImport = true;
      }
    }

    if( Configuration::getGlobalValue('GOMAKOIL_IMAGES_IMPORT_RUNNING') ){
      $time = (int)Configuration::getGlobalValue('GOMAKOIL_IMAGES_IMPORT_RUNNING');
      if( (time() - $time) < 60 ){
        $imagesImport = true;
      }
    }

    if( $justProducts && $productsImport ){
      return true;
    }

    if( !$justProducts && ($productsImport || $imagesImport) ){
      return true;
    }


//    Configuration::updateGlobalValue('GOMAKOIL_IMPORT_RUNNING', time());
    return false;
  }

  public function getImportTimeDuration( $images = false ) {

    $start = Configuration::getGlobalValue('GOMAKOIL_IMPORT_START_TIME');

    if( $images ){
      $finish = Configuration::getGlobalValue('GOMAKOIL_IMAGES_IMPORT_TIME');
    }
    else{
      $finish = Configuration::getGlobalValue('GOMAKOIL_IMPORT_RUNNING_TIME');
    }

    if( !$finish || $finish < $start ){
      return $this->l('0 hours 0 minutes');
    }

    $duration = $finish - $start;

    $hours = floor($duration / 3600);
    $minutes = floor($duration / 60);

    return sprintf('%02d hours %02d minutes', $hours, $minutes);

  }

  public function setTaskStatus( $idTask, $status )
  {
    $data = array(
      'progress' => pSQL($status, true)
    );

    Db::getInstance()->update('simpleimport_tasks', $data, 'id_task = ' . $idTask);
  }

  public function updateProgress()
  {
    if( Configuration::getGlobalValue('GOMAKOIL_IMPORT_STOPPED') ){
      return false;
    }
    $idTask = Tools::getValue('id_task');

    if( !$idTask ){
      return false;
    }

    $totalImport = Configuration::getGlobalValue('GOMAKOIL_PRODUCTS_FOR_IMPORT');
    $currentImported = Configuration::getGlobalValue('GOMAKOIL_CURRENTLY_IMPORTED');

    $copied = $this->getImageListsCount(1);
    $needCopy = $this->getImageListsCount();

    $thumbnailsTotal = $this->getNeedThumbnailsCount();
    $thumbnailsGenerated = $this->getNeedThumbnailsCount(1);

    if( Configuration::getGlobalValue('GOMAKOIL_GENERATE_THUMBNAILS') ){
      $typesCount = ImageType::getImagesTypes('products');
      $typesCount = (int)count($typesCount);

      $thumbnailsTotal = $thumbnailsTotal * $typesCount;
      $thumbnailsGenerated = $thumbnailsGenerated * $typesCount;
    }

    $data = array(
      'products'      => 'Products imported: ' . $currentImported . ' of ' . $totalImport,
      'images_copied' => 'Images copied: ' . $copied . ' of ' . $needCopy,
      'thumbnails'    => 'Thumbnails generated: ' . $thumbnailsGenerated . ' of ' . $thumbnailsTotal,
    );

    $data = $data['products'] . "<br>" . $data['images_copied'] . "<br>" . $data['thumbnails'];
    $data = pSQL($data, true);
    if( !$totalImport ){
      $data = $this->l('Preparing import');
    }

    $data = array(
      'progress' => $data
    );

    Db::getInstance()->update('simpleimport_tasks', $data, 'id_task = ' . $idTask);
  }

  public function runImagesCopy( $multithreading = 1, $type = 'copy', $url = false )
  {
    $idTask = Tools::getValue('id_task');

    if( $type == 'generate' ){
      $url = Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.basename(_PS_MODULE_DIR_).'/simpleimportproduct/send.php?ajax=1&generateThumbnails=1&id_task='.$idTask;
      $runCurl = $this->getNeedThumbnailsCount(0);
      if( !$runCurl ){
        sleep(30);
        $runCurl = true;
      }
    }
    elseif( $type == 'process' )
    {
      $url = Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.basename(_PS_MODULE_DIR_).'/simpleimportproduct/send.php?ajax=1&processImages=1&id_task='.$idTask;
      $runCurl = true;
    }
    elseif( $type == 'link' )
    {
      $runCurl = true;
    }
    else{
      $url = Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.basename(_PS_MODULE_DIR_).'/simpleimportproduct/send.php?copyImages=1&ajax=1&id_task='.$idTask;
//       $runCurl = $this->getImageListsCount(0);
	  $runCurl = true;
    }

    ignore_user_abort(true);
    if( $runCurl && function_exists('curl_init')){
      $limit = $multithreading;
      $i = 0;
      $user_agent='Mozilla/5.0 (Windows NT 6.1; rv:8.0) Gecko/20100101 Firefox/8.0';
      while( $i < $limit ){
        $i++;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_NOSIGNAL, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, 10000);

        curl_setopt($ch,CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch,CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_USERAGENT, $user_agent);

        curl_exec($ch);
        curl_close($ch);
      }
    }
  }

  public function copyImages()
  {
    if( !$this->getImageListsCount(0) ){
      return 0;
    }

    $limit = 100;
    $images = array();

    $i = 0;
    while( $i <= $limit ){
      $i++;
      $data = $this->_getImagesLinks(1);
      if( $data && isset($data[0]) ){
        $images[] = $data[0]['image_url'];
      }
    }

    $this->copyImageForResize($images);


    return $this->getImageListsCount(0);
  }

  public function copyImageForResize($links)
  {
    if( !is_array($links) ){
      $links = array($links);
    }
    $this->updateImagesImportRunning();
    $newPaths = $this->_copyImageForResize($links);

    foreach( $newPaths as $link => $path ){
      if( $path ){
        $data = array(
          'image_path' => pSQL($path),
          'processed'  => 1
        );
      }
      else{

        $this->_imageErrorLogs($link);
        $data = array(
          //        'image_path' => '',
          'processed'  => 2
        );
      }

      Db::getInstance()->update('simpleimport_images_path', $data, ' image_url = "'.pSQL($link).'" ');

      if( count($newPaths) == 1 ){
        return $path;
      }
    }
  }

  public function _getImageData($link, $thumbnail = false)
  {
    $this->updateImagesImportRunning();
    if (function_exists('curl_init') || $thumbnail ) {
      $ch = curl_init();

 		  $user_agent='Mozilla/5.0 (Windows NT 6.1; rv:8.0) Gecko/20100101 Firefox/8.0';

      curl_setopt($ch, CURLOPT_HEADER, 0);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($ch, CURLOPT_URL, $link);
      curl_setopt($ch, CURLOPT_TIMEOUT, 30);

      curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
      curl_setopt($ch,CURLOPT_SSL_VERIFYPEER, false);
      curl_setopt($ch,CURLOPT_SSL_VERIFYHOST, false);
      curl_setopt($ch, CURLOPT_USERAGENT, $user_agent);
      curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
      curl_setopt($ch, CURLOPT_MAXREDIRS, 3);

      $data = $ch;
    }
    else{
      $data = Tools::file_get_contents($link);
    }


    return $data;

  }

  private function _copyImageForResize($links)
  {
    $newPaths = array();
    $localPaths = array();
    foreach( $links as $link ){
      $this->updateImagesImportRunning();
      if( ($localImage = $this->_checkLocalImage($link)) ){
        $localPaths[$link] = $localImage;
      }
      else{
        $newPaths[$link] = $this->_getImageData($link);
      }
    }

    if( function_exists('curl_init') ){
      $mh = curl_multi_init();
      foreach( $newPaths as $key => $newPath ){
        if( !is_resource($newPath) ){
          continue;
        }
        curl_multi_add_handle($mh, $newPath);
      }
      $running = null;
      do {
        $this->updateImagesImportRunning();
        curl_multi_exec($mh, $running);
      } while ($running);

      foreach( $newPaths as $key => $newPath ){
        if( !is_resource($newPath) ){
          continue;
        }
        curl_multi_remove_handle($mh, $newPath);
      }
      curl_multi_close($mh);
      foreach( $newPaths as $key => $newPath ){
        $this->updateImagesImportRunning();
        if( !is_resource($newPath) ){
          continue;
        }
        $data = curl_multi_getcontent( $newPath );
        $newPaths[$key] = $this->_createImage($data, $key);
      }
    }
    else{
      foreach( $newPaths as $key => $newPath ){
        $newPaths[$key] = $this->_createImage($newPath, $key);
      }
    }

    $newPaths = array_merge($newPaths, $localPaths);

    return $newPaths;
  }



  private function _createImage( $data, $link )
  {
    $this->updateImagesImportRunning();
    $file_info = new finfo(FILEINFO_MIME_TYPE);
    $mime_type = $file_info->buffer($data);

    if( strpos( $mime_type, 'image' ) === false && strpos( $mime_type, 'img' ) === false ){
      return false;
    }

    $path_parts_prod = pathinfo($link);
    $name_img = $path_parts_prod['basename'];

    $newPath = _PS_MODULE_DIR_ . 'simpleimportproduct/upload/' . microtime(true) . '_' . $name_img;

    file_put_contents( $newPath, $data );

    if( file_exists( $newPath ) ){
      return $newPath;
    }

    return false;
  }

  private function _checkLocalImage( $link )
  {
    $shopUrl = Tools::getShopDomainSsl().__PS_BASE_URI__;
    $shopUrl = str_replace('http://', '', $shopUrl);
    $shopUrl = str_replace('https://', '', $shopUrl);
    $shopUrl = str_replace('www.', '', $shopUrl);
    $localLink = str_replace('http://', '', $link);
    $localLink = str_replace('https://', '', $localLink);
    $localLink = str_replace('www.', '', $localLink);

    if( strpos($localLink, $shopUrl) !== false ){
      $localLink = str_replace($shopUrl, '', $localLink);
      $localLink = _PS_ROOT_DIR_ . '/' . $localLink;
      if( file_exists($localLink) ){

        $path_parts_prod = pathinfo($link);
        $name_img = $path_parts_prod['basename'];

        $newPath = _PS_MODULE_DIR_ . 'simpleimportproduct/upload/' . time() . '_' . $name_img;
        if( copy($localLink, $newPath) ){
          return $newPath;
        }
      }
    }

    return false;
  }

  public function addLog( $message )
  {
    $write_fd = fopen(_PS_MODULE_DIR_ . 'simpleimportproduct/error.log', 'a+');
    if (@$write_fd !== false){
      fwrite($write_fd, '- ' . $message . "\r\n");
    }
    fclose($write_fd);
  }

  public function sendEmail( $error = false, $errorCode = false )
  {
    if( Configuration::getGlobalValue('GOMAKOIL_IMPORT_STOPPED') ){
      $this->updateTaskStatus( Tools::getValue('id_task'), false, true );
      return false;
    }
    include_once(dirname(__FILE__).'/ImportMailer.php');
    $settings = Configuration::getGlobalValue('GOMAKOIL_AUTOMATIC_SETTINGS');

    if( !$settings ){
      return false;
    }

    $config = Configuration::get('GOMAKOIL_IMPORT_PRODUCTS_' . $settings);
    $config = unserialize($config);

    $preheader = $this->l('Import process successfully completed!') . ' ' . $this->l('Import settings: ') . $config['name_save'];
    $successClass = 'success';
    $errorClass = '';
    if( $error ){
      $successClass = '';
      $errorClass = 'error';
      $preheader = $this->l('Import process failed!') . ' ' . $this->l('Error: ') . $error;
    }

    $template_vars = array(
      '{settings_name}'     => $config['name_save'],
      '{settings_id}'       => $settings.'_',
      '{start_time}'        => date('m/d/Y H:i', Configuration::getGlobalValue('GOMAKOIL_IMPORT_START_TIME')),
      '{products_duration}' => $this->getImportTimeDuration(),
      '{imported_products}' => (int)Configuration::getGlobalValue('GOMAKOIL_CURRENTLY_IMPORTED') . ' of ' . (int)Configuration::getGlobalValue('GOMAKOIL_PRODUCTS_FOR_IMPORT'),
      '{products_skipped}'  => Configuration::getGlobalValue('GOMAKOIL_PRODUCTS_WITH_ERRORS'),
      '{images_duration}'   => $this->getImportTimeDuration(true),
      '{copied_images}'     => $this->getImageListsCount(1) . ' of ' . $this->getImageListsCount(),
      '{images_skipped}'    => $this->getImageListsCount(2),
      '{thumbnails}'        => $this->getNeedThumbnailsCount(1) . ' of ' . $this->getNeedThumbnailsCount(),
      '{error_class}'       => $errorClass,
      '{success_class}'     => $successClass,
      '{module_folder}'     => Tools::getShopDomainSsl(true, true).__PS_BASE_URI__ . 'modules/simpleimportproduct/',
      '{skipped_products}'  => '',
      '{skipped_images}'    => '',
      '{error_message}'     => $error,
      '{preheader}'         => $preheader
    );

    if( Configuration::getGlobalValue('GOMAKOIL_GENERATE_THUMBNAILS') ){
      $typesCount = ImageType::getImagesTypes('products');
      $typesCount = (int)count($typesCount);

      $template_vars['{thumbnails}'] = $this->getNeedThumbnailsCount(1)*$typesCount . ' of ' . $this->getNeedThumbnailsCount()*$typesCount;
    }

    if( $template_vars['{products_skipped}'] ){
      $template_vars['{skipped_products}'] = 'show_error';
    }

    if( $template_vars['{images_skipped}'] ){
      $template_vars['{skipped_images}'] = 'show_error';
    }

    if( $errorCode != 333 ){
      if( Tools::getValue('id_task') ){
        $this->updateTaskStatus( Tools::getValue('id_task'), false, true );
        $this->updateProgress();
      }
      $this->resetImportStatus();
    }
    if( Tools::getValue('id_task') ){
      $emails = $this->_getTaskEmails(Tools::getValue('id_task'));
      if( !$error && $emails['error_email'] ){
        return false;
      }
      if( $emails['email_notification'] ){
        $emails = $emails['notification_emails'];
      }
      else{
        $emails = array();
      }
    }
    else{
      $emails = $config['notification_emails'];
    }
    if( !$emails ){
      return false;
    }
    $emails = trim($emails);

    $emails = explode("\n", $emails);

    foreach ($emails as $users_email){
      $users_email = trim($users_email);

      $mail = ImportMailer::Send(
        Configuration::get('PS_LANG_DEFAULT'),
        'notification',
        Module::getInstanceByName('simpleimportproduct')->l('Products Import Report', 'automatic_import'),
        $template_vars,
        "$users_email",
        NULL,
        Tools::getValue('email') ? Tools::getValue('email') : NULL,
        Tools::getValue('fio') ? Tools::getValue('fio') : NULL,
        NULL,
        NULL,
        dirname(__FILE__).'/mails/');
      if( !$mail ){
        echo Module::getInstanceByName('simpleimportproduct')->l('Some error occurred please contact us!', 'automatic_import');
        die;
      }
    }
  }

  private function _getTaskEmails( $idTask )
  {
    $sql = '
      SELECT notification_emails, email_notification, error_email
      FROM ' . _DB_PREFIX_ . 'simpleimport_tasks as t
      WHERE id_task = '.$idTask.'
    ';

    $res = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);

    return $res[0];
  }

  public function updateTaskStatus( $idTask, $oneShot, $finish = false )
  {
    if( !$finish ){
      $data = array(
        'last_start'  => time(),
        'last_finish' => ''
      );
      if( $oneShot ){
        $data['active'] = 0;
      }

      Db::getInstance(_PS_USE_SQL_SLAVE_)->update('simpleimport_tasks', $data, "id_task=".(int)$idTask);
    }
    else{
      $data = array(
        'last_finish' => time()
      );

      Db::getInstance(_PS_USE_SQL_SLAVE_)->update('simpleimport_tasks', $data, "id_task=".(int)$idTask);
    }
  }

  public function getImageListsCount( $processed = false )
  {
    $where = '';
    if( $processed !== false ){
      $where = " AND processed = $processed ";
    }

    $sql = 'SELECT count(*) as count
     FROM '._DB_PREFIX_.'simpleimport_images_path
     WHERE 1
     ' . $where . '
     ';

    $res = Db::getInstance()->executeS($sql);

    return (int)$res[0]['count'];
  }

  private function _checkCopiedImage( $idImage )
  {
    $sql = 'SELECT processed
     FROM '._DB_PREFIX_.'simpleimport_images_path
     WHERE id_image =  '.(int)$idImage.'
     ';

    $res = Db::getInstance()->executeS($sql);
    if( isset($res[0]) ){
      return (bool)$res[0]['processed'];
    }
    return true;
  }

  private function _getImagesLinks( $limit )
  {
    $sql = 'SELECT image_url, id_image
     FROM '._DB_PREFIX_.'simpleimport_images_path
     WHERE processed = 0
     ORDER BY RAND()
     LIMIT '.(int)$limit.'
     ';

    $res = Db::getInstance()->executeS($sql);

    if( isset($res[0]) && $res[0] ){
      $data = array(
        'processed' => 3
      );
      Db::getInstance()->update('simpleimport_images_path', $data, 'id_image='.$res[0]['id_image']);
    }

    return $res;
  }

  public function checkThumbnails()
  {
	$idTask = Tools::getValue('id_task');
    $link = Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . basename(_PS_MODULE_DIR_) . '/simpleimportproduct/send.php?runGenerateThumbnails=1&ajax=1&id_task='.$idTask;
    $multi = array();
    for( $i=1; $i<=10; $i++ ){
      $multi[] = $this->_getImageData($link, true);
    }

    $mh = curl_multi_init();
    foreach( $multi as $ch ){
      if( !is_resource($ch) ){
        continue;
      }
      curl_multi_add_handle($mh, $ch);
    }
    $running = null;
    do {
      $this->updateImagesImportRunning();
      curl_multi_exec($mh, $running);
    } while ($running);

    foreach( $multi as $ch ){
      if( !is_resource($ch) ){
        continue;
      }
      curl_multi_remove_handle($mh, $ch['ch']);
    }
    curl_multi_close($mh);

    return $this->getNeedThumbnailsCount(0);
  }

  public function generateThumbnails()
  {
    $limit = 10;
    if( !$this->getNeedThumbnailsCount(0) ){
      return 0;
    }

    $i = 0;
    sleep(1);
    while( $i < $limit ){
      $i++;
      $data = $this->_getImageLinkForThumbnail();
      if( $data && isset($data[0]) ){
        $this->_generateThumbnail( $data[0] );
      }
      sleep(1);
    }
  }

  public function updateImagesImportRunning( $stop = false )
  {
    if( $stop ){
      Configuration::updateGlobalValue('GOMAKOIL_IMAGES_IMPORT_RUNNING', false);
      $this->cleanImages();
    }
    else{
      Configuration::updateGlobalValue('GOMAKOIL_IMAGES_IMPORT_RUNNING', time());
      $this->updateProgress();
    }

    Configuration::updateGlobalValue('GOMAKOIL_IMAGES_IMPORT_TIME', time());
  }

  public function resetImportStatus()
  {
    $this->truncateImageTable();
    Configuration::updateGlobalValue('GOMAKOIL_IMPORT_RUNNING', false);
    Configuration::updateGlobalValue('GOMAKOIL_AUTOMATIC_SETTINGS', false);
    $this->updateImagesImportRunning(true);
    Configuration::updateGlobalValue('GOMAKOIL_CURRENTLY_IMPORTED', 0);
    Configuration::updateGlobalValue('GOMAKOIL_PRODUCTS_FOR_IMPORT', 0);
    Configuration::updateGlobalValue('GOMAKOIL_PRODUCTS_WITH_ERRORS', 0);
    Configuration::updateGlobalValue('GOMAKOIL_IMPORT_STATUS', 'Preparing import');
  }

  public function truncateImageTable()
  {
    Db::getInstance()->execute('TRUNCATE '._DB_PREFIX_.'simpleimport_images');
    Db::getInstance()->execute('TRUNCATE '._DB_PREFIX_.'simpleimport_products');
    Db::getInstance()->execute('TRUNCATE '._DB_PREFIX_.'simpleimport_images_path');
    Db::getInstance()->execute('TRUNCATE '._DB_PREFIX_.'simpleimport_data');
  }

  public function cleanImages()
  {
    foreach (scandir(dirname(__FILE__).'/upload/') as $d) {
      if (preg_match('/(.*)\.(jpg|jpeg|png|gif)(.*)$/i', $d)) {
        unlink(dirname(__FILE__).'/upload/'.$d);
      }
    }
  }

  private function _getProductImageTypes()
  {
    $query = 'SELECT * FROM `'._DB_PREFIX_.'image_type` WHERE `products`=1 ORDER BY `width` DESC, `height` DESC, `name`ASC';

    $res = Db::getInstance()->executeS($query);

    return $res;
  }

  private function _generateThumbnail( $image )
  {
    $this->updateImagesImportRunning();
    $imagesTypes = $this->_getProductImageTypes();

    if( $image['processed'] == 2 ){
      $data = array(
        'processed' => 2
      );
    }
    elseif( $image['processed'] == 1 ){
      $processed = 1;
      $imageObject = new Image($image['id_image']);
      $path = $imageObject->getPathForCreation();
      $res = ImageManager::resize($image['image_path'], $path.'.'.$imageObject->image_format, null, null, 'jpg', false);
      if( !$res ){
        $processed = 2;
      }

      $newPath = _PS_PROD_IMG_DIR_.$imageObject->getImgFolder() . $imageObject->id . '.jpg';

      if( Configuration::getGlobalValue('GOMAKOIL_GENERATE_THUMBNAILS') ){

        foreach ($imagesTypes as $imageType)
        {
          if( file_exists($newPath) ){
            $image['image_path'] = $newPath;
          }
          ImageManager::resize($image['image_path'], $path.'-'. Tools::stripslashes($imageType['name']).'.'.$imageObject->image_format, $imageType['width'], $imageType['height'], $imageObject->image_format);
          $newPath = _PS_PROD_IMG_DIR_.$imageObject->getImgFolder() . $imageObject->id . '-' . $imageType['name'] . '.jpg';
        }
      }
      $data = array(
        'processed' => $processed
      );
    }
    elseif( $image['processed'] == 0 || $image['processed'] == 3 ){
      $imagePath = $this->copyImageForResize($image['image_url']);
      if( $imagePath ){
        $processed = 1;
        $imageObject = new Image($image['id_image']);
        $path = $imageObject->getPathForCreation();
        $res = ImageManager::resize($imagePath, $path.'.'.$imageObject->image_format, null, null, 'jpg', false);
        if( !$res ){
          $processed = 2;
        }

        if( Configuration::getGlobalValue('GOMAKOIL_GENERATE_THUMBNAILS') ){

          foreach ($imagesTypes as $imageType)
          {
            ImageManager::resize($imagePath, $path.'-'. Tools::stripslashes($imageType['name']).'.'.$imageObject->image_format, $imageType['width'], $imageType['height'], $imageObject->image_format);
          }
        }

        $data = array(
          'processed' => $processed
        );
      }
      else{
        $data = array(
          'processed' => 2
        );
      }
    }
    
    if( $data['processed'] == 2 ){
      $imgObject = new Image($image['id_image']);
      $imgObject->delete();
    }

    Db::getInstance()->update('simpleimport_images', $data, 'id='.$image['id']);

  }

  private function _imageErrorLogs( $imageUrl )
  {
    $settings = '';
    if( Configuration::getGlobalValue('GOMAKOIL_AUTOMATIC_SETTINGS') ){
      $settings = Configuration::getGlobalValue('GOMAKOIL_AUTOMATIC_SETTINGS') . '_';
    }
    $write_fd = fopen(_PS_MODULE_DIR_ . 'simpleimportproduct/error/'.$settings.'image_logs.csv', 'a+');
    if (@$write_fd !== false){
      fwrite($write_fd, 'Image is not available for uploading' . ',' . $imageUrl . "\r\n");
    }
    fclose($write_fd);
  }

  private function _getImageLinkForThumbnail()
  {
    $sql = 'SELECT i.id, p.processed, p.image_path, i.id_image, p.image_url
     FROM '._DB_PREFIX_.'simpleimport_images i
     INNER JOIN '._DB_PREFIX_.'simpleimport_images_path p
     ON i.image_url = p.image_url
     WHERE i.processed = 0
     ORDER BY RAND()
     LIMIT 1
     ';

    $res = Db::getInstance()->executeS($sql);
    foreach($res as $image){
      $data = array(
        'processed' => 4
      );
      Db::getInstance()->update('simpleimport_images', $data, 'id='.$image['id']);
    }

    return $res;
  }

  public function getNeedThumbnailsCount( $processed = false )
  {
    $where = '';
    if( $processed !== false ){
      $where = " AND processed = $processed ";
    }

    $sql = 'SELECT count(*) as count
     FROM '._DB_PREFIX_.'simpleimport_images
     WHERE 1
     ' . $where . '
     ';

    $res = Db::getInstance()->executeS($sql);

    return (int)$res[0]['count'];
  }

  public function getContent(){

    $logo = '<img class="logo_myprestamodules" src="../modules/'.$this->name.'/logo.png" />';
    $name = '<h2 id="bootstrap_orders_export">'.$logo.$this->displayName.'</h2>';

    $this->_html .= $name;

    $this->context->smarty->assign(
      array(
        'location_href'  => AdminController::$currentIndex.'&token='.Tools::getAdminTokenLite('AdminModules').'&configure=simpleimportproduct',
        'tab'            => Tools::getValue('module_tab')
        )
    );

    $this->_html .= $this->display(__FILE__, 'views/templates/hook/tabs.tpl');

    $this->_displayForm();
    return $this->_html;
  }
  private function _displayForm()
  {
    $step = "";
//    if( Tools::getValue('module_tab') == 'step_1' ){
//      $step = " step_1";
//    }
    if( Tools::getValue('module_tab') == 'step_2' ){
      $step = " step_2";
    }
    $config = Tools::unserialize(Configuration::get('GOMAKOIL_CONFIG_IMPORT_PRODUCTS',null,Context::getContext()->shop->id_shop_group,Context::getContext()->shop->id));

//    $automatic = @$config['import_type_schedule'];
//    if( $automatic == 'automatic' ){
//      $this->_automatic = true;
//    }
//    else{
//      $automatic = 'manually';
//    }

    $automatic = 'manually';

    if( Tools::getValue('module_tab') == 'documentation' ){
      $automatic = 'documentation';
    }

    if( Tools::getValue('module_tab') == 'newcronjob' || Tools::getValue('module_tab') == 'schedule' ){
      $automatic = 'task_panel';
    }

    $this->_html .= '<div class="content_'.Tools::getValue('module_tab').'"><div class="panel content_import_page form-horizontal'.$step.' import_type_'.$automatic.'">';
    if(Tools::getValue('module_tab') == 'step_2'){
      $this->initFormImportFields();
//      $this->_getRalatedModules();
      $this->_html .= '</div></div>';
//      $this->_getSettings();
    }
    elseif( Tools::getValue('module_tab') == 'support' ){
      $this->initFormSupport();
      $this->_html .= '</div></div>';
    }
    elseif( Tools::getValue('module_tab') == 'modules' ){
      $this->initFormModules();
      $this->_html .= '</div></div>';
    }
    elseif( Tools::getValue('module_tab') == 'documentation' ){
      $this->initFormDocumentation();
      $this->_html .= '</div></div>';
    }
    elseif( Tools::getValue('module_tab') == 'schedule' ){
      if( Tools::isSubmit('add_task') ){
        $addRes =  $this->_addTask();
        if( !$addRes ){
          $this->context->smarty->assign('form_errors', $this->_errors);
          $this->initFormAddTask();
        }
		else{
          Tools::redirectAdmin($this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name
            .'&token='.Tools::getAdminTokenLite('AdminModules').'&module_tab=schedule');
        }
      }
      elseif(Tools::getValue('stopimport')){
        $this->_stopAutomaticImport();
      }
      $this->initFormScheduleTasks();
      $this->_html .= '</div></div>';
    }
    elseif( Tools::getValue('module_tab') == 'newcronjob' ){
      $this->initFormAddTask();
      $this->initFormScheduleTasks();
      $this->_html .= '</div></div>';
    }
    else{
      $this->initFormImport();
      $this->_html .= '</div></div>';
      $this->_automatic = true;
      $this->_getSettings();
      $this->_getRalatedModules();
      $this->_html .= '</div></div>';
    }
//    else{
////      $this->initFormWelcome();
//      $this->_html .= '</div></div>';
//    }
    if( Tools::getValue('statussimpleimport_tasks') !== false ){
      $this->_updateTaskStatus();
    }
    if( Tools::getValue('oneshotsimpleimport_tasks') !== false ){
      $this->_updatetaskOneShot();
    }
    if( Tools::getValue('deletesimpleimport_tasks') !== false ){
      $this->_deleteTask();
    }
  }

  private function _stopAutomaticImport()
  {
    $idTask = Tools::getValue('id_task');
    if( $this->checkProductsForImport( $idTask ) ){
      Configuration::updateGlobalValue('GOMAKOIL_IMPORT_STOPPED', 1);
      $this->resetImportStatus();
    }
    Tools::redirectAdmin($this->context->link->getAdminLink('AdminModules', false)
      .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name
      .'&token='.Tools::getAdminTokenLite('AdminModules').'&module_tab=schedule');
  }

  public function checkProductsForImport( $idTask )
  {
    $sql = "
      SELECT COUNT(*) as count
      FROM " . _DB_PREFIX_ . "simpleimport_data
      WHERE id_task = $idTask
    ";

    $res = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
    $productsCount = (int)$res[0]['count'];

    $sql = "
      SELECT COUNT(*) as count
      FROM " . _DB_PREFIX_ . "simpleimport_images
      WHERE id_task = $idTask
    ";

    $res = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
    $imagesCount = (int)$res[0]['count'];

    return ($productsCount+$imagesCount);
  }

  private function _deleteTask()
  {
    $idTask = (int)Tools::getValue('id_task');
    if( $idTask ){
      Db::getInstance()->delete('simpleimport_tasks', "id_task=$idTask");
      Tools::redirectAdmin($this->context->link->getAdminLink('AdminModules', false)
        .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name
        .'&token='.Tools::getAdminTokenLite('AdminModules').'&module_tab=schedule');
    }
  }

  private function _updateTaskStatus()
  {
    $idTask = (int)Tools::getValue('id_task');
    if( $idTask ){
      Db::getInstance()->execute('UPDATE '._DB_PREFIX_.'simpleimport_tasks
            SET `active` = IF (`active`, 0, 1) WHERE `id_task` = \''.(int)$idTask.'\'');

      Tools::redirectAdmin($this->context->link->getAdminLink('AdminModules', false)
        .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name
        .'&token='.Tools::getAdminTokenLite('AdminModules').'&module_tab=schedule');
    }
  }

  private function _updatetaskOneShot()
  {
    $idTask = (int)Tools::getValue('id_task');
    if( $idTask ){
      Db::getInstance()->execute('UPDATE '._DB_PREFIX_.'simpleimport_tasks
            SET `one_shot` = IF (`one_shot`, 0, 1) WHERE `id_task` = \''.(int)$idTask.'\'');

      Tools::redirectAdmin($this->context->link->getAdminLink('AdminModules', false)
        .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name
        .'&token='.Tools::getAdminTokenLite('AdminModules').'&module_tab=schedule');
    }
  }

  public function convertSpecialExpression( $specialExpression )
  {
    $expression = $specialExpression;
    switch($specialExpression){
      case "@yearly":
        $expression = "0 0 1 1 *";
        break;
      case "@annually":
        $expression = "0 0 1 1 *";
        break;
      case "@monthly":
        $expression = "0 0 1 * *";
        break;
      case "@weekly":
        $expression = "0 0 * * 0";
        break;
      case "@daily":
        $expression = "0 0 * * *";
        break;
      case "@midnight":
        $expression = "0 0 * * *";
        break;
      case "@hourly":
        $expression = "0 * * * *";
        break;
    }

    return $expression;
  }

  private function _addTask()
  {
    $values = $this->_getScheduleValues();
    if( !$values['import_settings'] ){
      $this->_errors[] = $this->l('You must select import settings!');
      return false;
    }

    if( !$values['frequency'] || !$this->_checkExpressionValid( $values['frequency'] ) ){
      $this->_errors[] = $this->l('Your frequency expression is not valid!');
      return false;
    }

    $values['id_shop'] = (int)Context::getContext()->shop->id;
    $values['id_shop_group'] = (int)Context::getContext()->shop->id_shop_group;
    $values['active'] = 1;

    if( Tools::getValue('id_task')){
      Db::getInstance(_PS_USE_SQL_SLAVE_)->update('simpleimport_tasks', $values, 'id_task='.(int)Tools::getValue('id_task'));
    }
    else{
      Db::getInstance(_PS_USE_SQL_SLAVE_)->insert('simpleimport_tasks', $values);
    }

    return true;
  }

  private function _checkExpressionValid( $expression )
  {
    try{
      include_once(_PS_MODULE_DIR_ . 'simpleimportproduct/libraries/Schedule/CrontabValidator.php');
      $validator = new CrontabValidator();
      $expression = $this->convertSpecialExpression($expression);
      if( !$validator->isExpressionValid( $expression ) ){
        throw new Exception('Not valid');
      }
      return true;
    }
    catch(Exception $e){
      return false;
    }
  }

  private function _getSettings()
  {


    $count_save = Tools::unserialize(Configuration::get('GOMAKOIL_IMPORT_COUNT_SETTINGS',null,Context::getContext()->shop->id_shop_group,Context::getContext()->shop->id));
    $step = Tools::getValue('module_tab','step_1');
    $sClass = '';
    if( $step == 'step_2' ){
      $sClass = 'save_config_block';
    }
    if(isset($count_save) && $count_save){

      $this->_html .= '<div class="panel '.$sClass.' import_settings_block" id="fieldset_0_0"><div class="panel-heading">
                        <i class="icon-cogs"></i><span class="your_saved_settings">'.$this->l('Saved settings').'</span><label class="add_settings btn btn-default"><i class="process-icon-new"></i><input type="file"></label><span class="upload_settings btn btn-default"><i class="icon-upload"></i></span></div><ul class="save_scroll"> ';

      foreach($count_save as $value){
        $save = Tools::unserialize(Configuration::get('GOMAKOIL_IMPORT_PRODUCTS_'.$value,null,Context::getContext()->shop->id_shop_group,Context::getContext()->shop->id));

        $this->_html .= '<li><a class="one_config" href='.AdminController::$currentIndex.'&token='.Tools::getAdminTokenLite('AdminModules').'&configure=simpleimportproduct&module_tab='.$step.'&save='.$value.'>'.$save['name_save'].'</a><div data-settings="'.$value.'" class="download_settings btn btn-default"><i class="icon-download"></i></div><a class="delete_config btn btn-default" settings="'.$value.'"><i class="icon-trash"></i></a></li>';

      }

      $this->_html .= '</ul></div>';
    }

    $this->context->smarty->assign(
      array(
        'count_save'  => $count_save,
      )
    );

    $this->_html .= $this->display(__FILE__, 'views/templates/hook/searchSettings.tpl');
  }

  private function _getRalatedModules()
  {
    $this->context->controller->addCSS('https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap');
    $currentVersion = $this->version;
    $lastVersion = Configuration::getGlobalValue('GOMAKOIL_IMPORT_VERSION');

    $this->context->smarty->assign(
      array(
        'current_version' => $currentVersion,
        'last_version' => $lastVersion,
      )
    );

    $this->_html .= $this->display(__FILE__, 'views/templates/hook/relatedModules.tpl');
  }

  public function initFormSupport()
  {
    $this->context->smarty->assign(
      array(
        'location_href'  => AdminController::$currentIndex.'&token='.Tools::getAdminTokenLite('AdminModules').'&configure=simpleimportproduct&save='.Tools::getValue('save'),
      )
    );

    $this->_html .= $this->display(__FILE__, 'views/templates/hook/support.tpl');
  }

  public function initFormModules()
  {
    $this->context->smarty->assign(
      array(
        'location_href'  => AdminController::$currentIndex.'&token='.Tools::getAdminTokenLite('AdminModules').'&configure=simpleimportproduct&save='.Tools::getValue('save'),
      )
    );

    $this->_html .= $this->display(__FILE__, 'views/templates/hook/modules.tpl');
  }

  public function initFormWelcome()
  {
    $this->context->controller->addCSS('https://fonts.googleapis.com/css?family=Open+Sans:300,400,600');

    $filePerms = Tools::substr(sprintf('%o', fileperms(_PS_MODULE_DIR_ . 'simpleimportproduct/send.php')), -3);
    $folderPerms = Tools::substr(sprintf('%o', fileperms(_PS_MODULE_DIR_ . 'simpleimportproduct/')), -3);

    $allowUrl = false;
    if( in_array(ini_get('allow_url_fopen'), array('On', 'on', '1')) ){
      $allowUrl = true;
    }

    $requirementsOk = false;
    if( $filePerms == 644 && $folderPerms == 755 && $allowUrl == true && class_exists('ZipArchive' ) ){
      $requirementsOk = true;
    }

    $currentVersion = $this->version;
    $lastVersion = Configuration::getGlobalValue('GOMAKOIL_IMPORT_VERSION');


    $this->context->smarty->assign(
      array(
        'module_path'           => Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . basename(_PS_MODULE_DIR_) . '/simpleimportproduct/',
        'products_import_token' => Tools::getAdminTokenLite('AdminProductsimport'),
        'location_href'         => AdminController::$currentIndex.'&token='.Tools::getAdminTokenLite('AdminModules').'&configure=simpleimportproduct',
        'file_perms'            => $filePerms,
        'folder_perms'          => $folderPerms,
        'php_zip'               => class_exists('ZipArchive'),
        'max_execution_time'    => ini_get('max_execution_time'),
        'memory_limit'          => ini_get('memory_limit'),
        'curl'                  => function_exists('curl_init'),
        'allow_url_fopen'       => $allowUrl,
        'requirements_ok'       => $requirementsOk,
        'current_version'       => $currentVersion,
        'last_version'          => $lastVersion,
      )
    );
    $this->_html .= $this->display(__FILE__, 'views/templates/hook/welcome.tpl');
  }

  public function initFormDocumentation()
  {
    $this->context->smarty->assign(
      array(
        'location_href'  => AdminController::$currentIndex.'&token='.Tools::getAdminTokenLite('AdminModules').'&configure=simpleimportproduct&save='.Tools::getValue('save'),
      )
    );

    $this->_html .= $this->display(__FILE__, 'views/templates/hook/documentation.tpl');
  }

  public function initFormImport()
  {
    $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');
    $fields_form = array();
    $format = array(
      array(
        'id' => 'xlsx',
        'name' => $this->l('XLSX')
      ),
      array(
        'id' => 'csv',
        'name' => $this->l('CSV')
      ),
    );
    $delimiter = array(
      array(
        'id' => ';',
        'name' => ';',
      ),
      array(
        'id' => ':',
        'name' => ':',
      ),
      array(
        'id' => ',',
        'name' => ',',
      ),
      array(
        'id' => '.',
        'name' => '.',
      ),
      array(
        'id' => '/',
        'name' => '/',
      ),
      array(
        'id' => '|',
        'name' => '|',
      ),
      array(
        'id' => 'tab',
        'name' => 'tab',
      ),
    );

    $type_import_schedule = array(
      array(
        'id' => 'manually',
        'name' => $this->l('Manually')
      ),
      array(
        'id' => 'automatic',
        'name' => $this->l('Automatically')
      ),
    );
    $parser = array(
      array(
        'id' => 'name',
        'name' => $this->l('Product name')
      ),
      array(
        'id' => 'reference',
        'name' => $this->l('Reference code')
      ),
      array(
        'id' => 'ean13',
        'name' => $this->l('EAN-13 or JAN barcode')
      ),

      array(
        'id' => 'upc',
        'name' => $this->l('UPC barcode')
      ),

      array(
        'id' => 'isbn',
        'name' => $this->l('ISBN')
      ),

      array(
        'id' => 'product_id',
        'name' => $this->l('Product ID')
      ),
    );
    $fields_form[0]['form'] = array(
      'input' => array(
        array(
          'type'     => 'text',
          'label'    => $this->l('Settings Name'),
          'required' => true,
          'name'     => 'import_settings_name',
          'form_group_class' => 'import_settings_name',
        ),
        array(
          'type' => 'select',
          'label' => $this->l('Select file format'),
          'name' => 'format_file',
          'class' => 'format_file',
          'required' => true,
          'options' => array(
            'query' =>$format,
            'id' => 'id',
            'name' => 'name'
          )
        ),
        array(
          'type' => 'select',
          'label' => $this->l('Delimiter'),
          'name' => 'delimiter_val',
          'class' => 'delimiter_val',
          'form_group_class' => 'csv_delimiter',
          'options' => array(
            'query' =>$delimiter,
            'id' => 'id',
            'name' => 'name'
          )
        ),
        array(
          'type' => 'select',
          'label' => $this->l('Language'),
          'name' => 'id_lang',
          'class' => 'id_lang',
          'required' => true,
          'default_value' => (int)$this->context->language->id,
          'options' => array(
            'query' => Language::getLanguages(),
            'id' => 'id_lang',
            'name' => 'name',
          )
        ),
        array(
          'type' => 'select',
          'label' => $this->l('Key for product identification'),
          'name' => 'parser_import_val',
          'class' => 'parser_import_val',
          'required' => true,
          'form_group_class' => 'key_identifier',
          'options' => array(
            'query' =>$parser,
            'id' => 'id',
            'name' => 'name'
          )
        ),
        array(
          'type' => 'select',
          'label' => $this->l('Detect also by combination'),
          'name' => 'combination_identification',
          'class' => 'combination_identification',
          'desc' => $this->l('The module also will detect product by combination selected key (Reference, EAN-13, UPC or ISBN code)'),
          'required' => true,
          'form_group_class' => 'combination_identification_class',
          'options' => array(
            'query' => array(
              array(
                'id' => '0',
                'name' => $this->l('No')
              ),
              array(
                'id' => '1',
                'name' => $this->l('Yes')
              ),
            ),
            'id' => 'id',
            'name' => 'name'
          )
        ),
        array(
          'type' => 'switch',
          'label' => $this->l('Force all ID numbers'),
          'name' => 'force_ids',
          'hint' => 'If you enable this option, your imported items ID number will be used as-is. If you do not enable this option, the imported ID number will be ignored, and PrestaShop will instead create auto-incremented ID numbers for all the imported items.',
          'form_group_class' => 'force_ids',
          'is_bool' => true,
          'values' => array(
            array(
              'id' => 'display_on',
              'value' => 1,
              'label' => $this->l('Yes')),
            array(
              'id' => 'display_off',
              'value' => 0,
              'label' => $this->l('No')),
          ),
        ),
        array(
          'type' => 'select',
          'label' => $this->l('Feed Source'),
          'name' => 'feed_source',
          'class' => 'feed_source',
          'required' => true,
          'form_group_class' => 'feed_source',
          'options' => array(
            'query' => array(
              array(
                'id' => 'file_upload',
                'name' => $this->l('File Upload')
              ),
              array(
                'id' => 'file_url',
                'name' => $this->l('URL')
              ),
              array(
                'id' => 'ftp',
                'name' => $this->l('FTP')
              ),
              array(
                'id' => 'sftp',
                'name' => $this->l('SFTP')
              ),
            ),
            'id' => 'id',
            'name' => 'name'
          )
        ),
        array(
          'type'     => 'file',
          'label'    => $this->l('File'),
          'required' => true,
          'name'     => 'file_import',
          'form_group_class' => 'file_import_select',
        ),
        array(
          'type'     => 'text',
          'label'    => $this->l('File Url'),
          'required' => true,
          'name'     => 'file_import_url',
          'form_group_class' => 'file_import_url',
        ),
        array(
          'type'     => 'text',
          'label'    => $this->l('FTP Server'),
          'required' => true,
          'name'     => 'file_import_ftp_server',
          'form_group_class' => 'file_import_ftp file_import_ftp_ssl',
        ),
        array(
          'type'     => 'text',
          'label'    => $this->l('User Name'),
          'required' => true,
          'name'     => 'file_import_ftp_user',
          'form_group_class' => 'file_import_ftp file_import_ftp_ssl',
        ),
        array(
          'type'     => 'text',
          'label'    => $this->l('Password'),
          'required' => true,
          'name'     => 'file_import_ftp_password',
          'form_group_class' => 'file_import_ftp file_import_ftp_ssl',
        ),
        array(
          'type'     => 'text',
          'label'    => $this->l('Port'),
          'required' => true,
          'name'     => 'file_import_ftp_port',
          'form_group_class' => 'file_import_ftp',
        ),
        array(
          'type'     => 'text',
          'label'    => $this->l('Port'),
          'required' => true,
          'name'     => 'file_import_ftp_port_ssl',
          'form_group_class' => 'file_import_ftp_ssl',
        ),
        array(
          'type' => 'select',
          'label' => $this->l('Passive Mode'),
          'name' => 'passive_mode',
          'class' => 'passive_mode',
          'required' => true,
          'form_group_class' => 'file_import_ftp',
          'options' => array(
            'query' => array(
              array(
                'id' => '0',
                'name' => $this->l('No')
              ),
              array(
                'id' => '1',
                'name' => $this->l('Yes')
              ),
            ),
            'id' => 'id',
            'name' => 'name'
          )
        ),
        array(
          'type'     => 'text',
          'label'    => $this->l('Absolute path to file'),
          'required' => true,
          'name'     => 'file_import_ftp_file_path',
          'form_group_class' => 'file_import_ftp file_import_ftp_ssl',
        ),
        array(
          'type' => 'select',
          'label' => $this->l('Remove File from FTP after import'),
          'name' => 'remove_ftp_file',
          'class' => 'remove_ftp_file',
          'required' => true,
          'form_group_class' => 'file_import_ftp file_import_ftp_ssl',
          'options' => array(
            'query' => array(
              array(
                'id' => '0',
                'name' => $this->l('No')
              ),
              array(
                'id' => '1',
                'name' => $this->l('Yes')
              ),
            ),
            'id' => 'id',
            'name' => 'name'
          )
        ),
        array(
          'type' => 'switch',
          'label' => $this->l('Use the first row as headers'),
          'name' => 'use_headers',
          'form_group_class' => 'use_headers',
          'is_bool' => true,
          'values' => array(
            array(
              'id' => 'display_on',
              'value' => 1,
              'label' => $this->l('Yes')),
            array(
              'id' => 'display_off',
              'value' => 0,
              'label' => $this->l('No')),
          ),
        ),
        array(
          'type' => 'switch',
          'label' => $this->l('Disable modules hooks for import'),
          'desc' => $this->l('This feature disable hooks in all modules during import that will increase products import speed'),
          'name' => 'disable_hooks',
          'form_group_class' => 'disable_hooks',
          'is_bool' => true,
          'values' => array(
            array(
              'id' => 'display_on',
              'value' => 1,
              'label' => $this->l('Yes')),
            array(
              'id' => 'display_off',
              'value' => 0,
              'label' => $this->l('No')),
          ),
        ),
        array(
          'type' => 'switch',
          'label' => $this->l('Add products to search index'),
          'desc' => $this->l('If disabled import is faster, ') . '<a href="http://faq.myprestamodules.com/product-catalog-csv-excel-import/how-to-add-products-to-search-index-after-import.html" target="_blank">' . $this->l('manual ') . '</a>' . $this->l('how to add product to search index after import'),
          'name' => 'search_index',
          'form_group_class' => 'search_index',
          'is_bool' => true,
          'values' => array(
            array(
              'id' => 'display_on',
              'value' => 1,
              'label' => $this->l('Yes')),
            array(
              'id' => 'display_off',
              'value' => 0,
              'label' => $this->l('No')),
          ),
        ),
        array(
          'type' => 'radio',
          'label' => $this->l('Items to import'),
          'name' => 'products_range',
          'form_group_class' => 'products_range',
          'is_bool' => true,
          'values' => array(
            array(
              'id' => 'display_on',
              'value' => 'all',
              'label' => $this->l('All')),
            array(
              'id' => 'display_off',
              'value' => 'range',
              'label' => $this->l('Range')),
          ),
        ),
        array(
          'type'     => 'text',
          'label'    => $this->l('From'),
          'required' => true,
          'name'     => 'from_range',
          'form_group_class' => 'from_range',
          'class' => 'fixed-width-xl',
        ),
        array(
          'type'     => 'text',
          'label'    => $this->l('To'),
          'required' => true,
          'name'     => 'to_range',
          'form_group_class' => 'to_range',
          'class' => 'fixed-width-xl',
        ),
        array(
          'type'     => 'text',
          'label'    => $this->l('Import products per iteration'),
          'required' => true,
          'name'     => 'iteration',
          'form_group_class' => 'iteration',
          'class' => 'fixed-width-xl',
          'desc' => $this->l('If your server is timing out or you experience any memory issues then set this value lower.')
        ),
        array(
          'type' => 'html',
          'name' => '<div class="show_more_settings">'.$this->l('Show additional settings').'</div>',
          'form_group_class' => 'more_settings',
          'html_content' => '<div class="show_more_settings">'.$this->l('Show additional settings').'</div>'
        ),
        array(
          'type'  => 'hidden',
          'name'  => 'id_shop',
          'class' => 'id_shop',
        ),
        array(
          'type'  => 'hidden',
          'name'  => 'import_type_val',
          'class' => 'import_type_val',
        ),
        array(
          'type'  => 'hidden',
          'name'  => 'id_shop_group',
          'class' => 'id_shop_group',
        ),
        array(
          'type'  => 'hidden',
          'name'  => 'location_href',
          'class' => 'location_href',
        ),
        array(
          'type'  => 'hidden',
          'name'  => 'products_import_token',
          'class' => 'products_import_token',
        ),
        array(
          'type'  => 'hidden',
          'name'  => 'setting_id',
          'class' => 'setting_id',
        ),
        array(
          'type' => 'html',
          'name' => '<div class="next_button_import">'.$this->l('Next').'</div>',
          'html_content' => '<div class="next_button_import">'.$this->l('Next').'</div>',
          'hint' => null,
        ),
      ),
    );
    $helper = new HelperForm();
    $helper->module = $this;
    $helper->name_controller = $this->name;
    $helper->token = Tools::getAdminTokenLite('AdminModules');
    $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;
    $helper->default_form_language = $default_lang;
    $helper->allow_employee_form_lang = $default_lang;
    $helper->title = $this->displayName;
    $helper->show_toolbar = true;        // false -> remove toolbar
    $helper->toolbar_scroll = true;      // yes - > Toolbar is always visible on the top of the screen.
    $helper->submit_action = 'saveSubmitImport';
    $helper->toolbar_btn = array(
      'save' =>
      array(
        'desc' => $this->l('Save'),
        'href' => AdminController::$currentIndex.'&configure='.$this->name.'&save'.$this->name.
          '&token='.Tools::getAdminTokenLite('AdminModules'),
      ),
      'back' => array(
        'href' => AdminController::$currentIndex.'&token='.Tools::getAdminTokenLite('AdminModules'),
        'desc' => $this->l('Back to list')
      )
    );
    if( Tools::getValue('save') && Configuration::get('GOMAKOIL_IMPORT_PRODUCTS_' . Tools::getValue('save'), false, Context::getContext()->shop->id_shop_group, Context::getContext()->shop->id) ){
      $savedSettings = Configuration::get('GOMAKOIL_IMPORT_PRODUCTS_' . Tools::getValue('save'), false, Context::getContext()->shop->id_shop_group, Context::getContext()->shop->id);
      $savedSettings = Tools::unSerialize($savedSettings);

      $helper->fields_value['import_settings_name'] = $savedSettings['name_save'];

      $savedSettings = $savedSettings['base_settings'];

      $helper->fields_value['format_file'] = $savedSettings['format_file'];
      $helper->fields_value['delimiter_val'] = $savedSettings['delimiter_val'];
      $helper->fields_value['id_lang'] = $savedSettings['id_lang'];
      $helper->fields_value['import_type_val'] = 'Add/update';
      $helper->fields_value['parser_import_val'] = $savedSettings['parser_import_val'];
      $helper->fields_value['combination_identification'] = $savedSettings['combination_identification'];
      $helper->fields_value['use_headers'] = $savedSettings['use_headers'];
      $helper->fields_value['disable_hooks'] = $savedSettings['disable_hooks'];
      $helper->fields_value['search_index'] = $savedSettings['search_index'];
      $helper->fields_value['products_range'] = $savedSettings['products_range'];
      $helper->fields_value['from_range'] = $savedSettings['from_range'];
      $helper->fields_value['to_range'] = $savedSettings['to_range'];
      $helper->fields_value['force_ids'] = $savedSettings['force_ids'];
      $helper->fields_value['iteration'] = $savedSettings['iteration'];
      $helper->fields_value['file_import_url'] = isset($savedSettings['file_import_url']) ? $savedSettings['file_import_url'] : '';
      $helper->fields_value['file_import_ftp_server'] = isset($savedSettings['file_import_ftp_server']) ? $savedSettings['file_import_ftp_server'] : '';
      $helper->fields_value['file_import_ftp_user'] = isset($savedSettings['file_import_ftp_user']) ? $savedSettings['file_import_ftp_user'] : '';
      $helper->fields_value['file_import_ftp_password'] = isset($savedSettings['file_import_ftp_password']) ? $savedSettings['file_import_ftp_password'] : '';
      $helper->fields_value['file_import_ftp_port'] = isset($savedSettings['file_import_ftp_port']) ? $savedSettings['file_import_ftp_port'] : 21;
      $helper->fields_value['file_import_ftp_port_ssl'] = isset($savedSettings['file_import_ftp_port_ssl']) ? $savedSettings['file_import_ftp_port_ssl'] : 22;
      $helper->fields_value['passive_mode'] = isset($savedSettings['passive_mode']) ? $savedSettings['passive_mode'] : 0;
      $helper->fields_value['remove_ftp_file'] = isset($savedSettings['remove_ftp_file']) ? $savedSettings['remove_ftp_file'] : 0;
      $helper->fields_value['file_import_ftp_file_path'] = isset($savedSettings['file_import_ftp_file_path']) ? $savedSettings['file_import_ftp_file_path'] : '';
      $helper->fields_value['feed_source'] = isset($savedSettings['feed_source']) ? $savedSettings['feed_source'] : 'file_url';
      $helper->fields_value['location_href'] = AdminController::$currentIndex.'&token='.Tools::getAdminTokenLite('AdminModules').'&configure=simpleimportproduct&save='.Tools::getValue('save');
      $helper->fields_value['setting_id'] = Tools::getValue('save');
    }
    else{
      $helper->fields_value['format_file'] = 'xlsx';
      $helper->fields_value['delimiter_val'] = ';';
      $helper->fields_value['import_type_val'] = 'Add/update';
      $helper->fields_value['file_import_url'] = '';
      $helper->fields_value['import_settings_name'] = '';
      $helper->fields_value['file_import_ftp_server'] = '';
      $helper->fields_value['file_import_ftp_user'] = '';
      $helper->fields_value['file_import_ftp_password'] = '';
      $helper->fields_value['file_import_ftp_port'] = 21;
      $helper->fields_value['file_import_ftp_port_ssl'] = 22;
      $helper->fields_value['passive_mode'] = 0;
      $helper->fields_value['remove_ftp_file'] = 0;
      $helper->fields_value['file_import_ftp_file_path'] = '';
      $helper->fields_value['feed_source'] = '';
      $helper->fields_value['parser_import_val'] = 'name';
      $helper->fields_value['combination_identification'] = '0';
      $helper->fields_value['use_headers'] = '1';
      $helper->fields_value['disable_hooks'] = '1';
      $helper->fields_value['search_index'] = '1';
      $helper->fields_value['products_range'] = 'all';
      $helper->fields_value['from_range'] = '1';
      $helper->fields_value['to_range'] = '10';
      $helper->fields_value['force_ids'] = '0';
      $helper->fields_value['iteration'] = '100';
      $helper->fields_value['setting_id'] = '';
      $helper->fields_value['location_href'] = AdminController::$currentIndex.'&token='.Tools::getAdminTokenLite('AdminModules').'&configure=simpleimportproduct';
      $helper->fields_value['id_lang'] = Context::getContext()->language->id;
    }

    $helper->fields_value['id_shop'] = Context::getContext()->shop->id;
    $helper->fields_value['id_shop_group'] = Context::getContext()->shop->id_shop_group;
    $helper->fields_value['products_import_token'] = Tools::getAdminTokenLite('AdminProductsimport');

    $this->_html .= $helper->generateForm($fields_form);
  }

  private function _setImportTabs()
  {
    $importTabs = array(
      'information'         => $this->l('Information'),
      'prices'              => $this->l('Prices'),
      'seo'                 => $this->l('Seo'),
      'associations'        => $this->l('Associations'),
      'shipping'            => $this->l('Shipping'),
      'combinations'        => $this->l('Combinations'),
      'quantities'          => $this->l('Quantities'),
      'virtual_product'     => $this->l('Virtual Product'),
      'images'              => $this->l('Images'),
      'features'            => $this->l('Features'),
      'suppliers'           => $this->l('Suppliers'),
      'customization'       => $this->l('Customization'),
      'attachments'         => $this->l('Attachments'),
      'pack'                => $this->l('Pack Products'),
      'additional_settings' => $this->l('Additional Settings'),
      'import_conditions'   => $this->l('Import Conditions'),
    );

    $this->context->smarty->assign(
      array(
        'import_tabs'  => $importTabs,
      )
    );

    $this->_html .= $this->display(__FILE__, 'views/templates/hook/importTabs.tpl');
  }

  private function _frequencyDescription()
  {
    return $this->display(__FILE__, 'views/templates/hook/frequencyDescription.tpl');
  }

  private function _frequencyInfo()
  {
    return $this->display(__FILE__, 'views/templates/hook/frequencyInfo.tpl');
  }

  public function initFormAddTask()
  {
    $form = array(
      array(
        'form' => array(
          'legend' => array(
            'title' => $this->l('Add cron task'),
            'icon' => 'icon-plus',
          ),
          'input' => array(),
        ),
      ),
    );

    $form[0]['form']['input'][] = array(
      'type'             => 'html',
      'name'             => $this->display(__FILE__, 'views/templates/hook/server-time.tpl'),
      'html_content'     => $this->display(__FILE__, 'views/templates/hook/server-time.tpl'),
      'form_group_class' => ''
    );

    $form[1]['form']['input'][] = array(
      'type' => 'text',
      'name' => 'description',
      'label' => $this->l('Task description'),
      'desc' => $this->l('Enter a description for this task.'),
      'placeholder' => $this->l('My import'),
    );


    $form[1]['form']['input'][] = array(
      'type' => 'select',
      'name' => 'import_settings',
      'label' => $this->l('Import Settings'),
      'desc' => $this->l('Available import settings just with FTP or URL Feed Source'),
      'options' => array(
        'query' => $this->_getAutomaticSettings(),
        'id' => 'id', 'name' => 'name'
      ),
    );

    $form[1]['form']['input'][] = array(
      'type'             => 'text',
      'name'             => 'frequency',
      'label'            => $this->l('Task frequency'),
      'desc'             => $this->l('At what time should this task be executed?'),
      'suffix'           => $this->display(__FILE__, 'views/templates/hook/time-help.tpl'),
      'form_group_class' => 'frequency'
    );

    $form[1]['form']['input'][] = array(
      'type'          => 'hidden',
      'name'          => 'products_import_token',
    );


    $form[1]['form']['input'][] = array(
      'type'             => 'html',
      'name'             => $this->_frequencyDescription(),
      'html_content'     => $this->_frequencyDescription(),
      'form_group_class' => ''
    );

    $form[1]['form']['input'][] = array(
      'type'    => 'switch',
      'label'   => $this->l('One Shot'),
      'name'    => 'one_shot',
      'form_group_class' => 'one_shot',
      'is_bool' => true,
      'values'  => array(
        array(
          'id'    => 'display_on',
          'value' => 1,
          'label' => $this->l('Yes')),
        array(
          'id'    => 'display_off',
          'value' => 0,
          'label' => $this->l('No')),
      ),
    );

    $form[1]['form']['input'][] = array(
      'type'    => 'switch',
      'label'   => $this->l('Email Message'),
      'name'    => 'email_notification',
      'form_group_class' => 'email_notification',
      'is_bool' => true,
      'values'  => array(
        array(
          'id'    => 'display_on',
          'value' => 1,
          'label' => $this->l('Yes')),
        array(
          'id'    => 'display_off',
          'value' => 0,
          'label' => $this->l('No')),
      ),
    );

    $form[1]['form']['input'][] = array(
      'type'    => 'switch',
      'label'   => $this->l('Send just if an error occurred'),
      'name'    => 'error_email',
      'form_group_class' => 'error_email',
      'is_bool' => true,
      'values'  => array(
        array(
          'id'    => 'display_on',
          'value' => 1,
          'label' => $this->l('Yes')),
        array(
          'id'    => 'display_off',
          'value' => 0,
          'label' => $this->l('No')),
      ),
    );

    $form[1]['form']['input'][] = array(
      'type'  => 'textarea',
      'label' => $this->l('Emails For Report'),
      'name'  => 'notification_emails',
      'class' => 'notification_emails',
      'hint'  => 'Each email in per line',
      'form_group_class' => 'emails_form',
    );

    $form[2]['form']['input'][] = array(
      'type'             => 'html',
      'name'             => $this->_frequencyInfo(),
      'html_content'     => $this->_frequencyInfo(),
      'form_group_class' => 'frequency_info'
    );

    $form[1]['form']['submit'] = array(
      'title' => $this->l('Save'),
      'type'  => 'submit',
      'class' => 'save_task btn btn-default'
    );

    $form[3]['form']['input'][] = array(
      'type'             => 'html',
      'name'             => '',
      'html_content'     => '',
      'form_group_class' => 'frequency_bottom'
    );




    $helper = new HelperForm();

    $helper->show_toolbar = false;
    $helper->module = $this;
    $helper->default_form_language = $this->context->language->id;
    $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);
    $helper->submit_action = 'add_task';

    $helper->identifier = $this->identifier;
    $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
      .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name.'&module_tab=schedule';
    if( Tools::getValue('id_task') ){
      $helper->currentIndex .= '&id_task='.(int)Tools::getValue('id_task');
    }

    $helper->token = Tools::getAdminTokenLite('AdminModules');

    $helper->tpl_vars['fields_value'] = $this->_getScheduleValues(true);
    $helper->tpl_vars['fields_value']['products_import_token'] = Tools::getAdminTokenLite('AdminProductsimport');

    $this->_html .= $helper->generateForm($form);
  }

  private function _getScheduleValues( $formValues = false )
  {
    if( Tools::getValue('id_task') && $formValues ){
      $sql = '
      SELECT * 
      FROM ' . _DB_PREFIX_ . 'simpleimport_tasks as t
      WHERE id_task = "'.(int)Tools::getValue('id_task').'"
    ';

      $res = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
      return $res[0];
    }
    $res = array(
      'description' => Tools::safeOutput(Tools::getValue('description', null)),
      'import_settings' => Tools::safeOutput(Tools::getValue('import_settings', 0)),
      'frequency' => Tools::getValue('frequency', '0 * * * *'),
      'email_notification' => (int)Tools::getValue('email_notification', 0),
      'error_email' => (int)Tools::getValue('error_email', 0),
      'notification_emails' => Tools::getValue('notification_emails', ''),
      'one_shot' => (int)Tools::getValue('one_shot', 0),
    );

    $res['frequency'] = trim($res['frequency']);
    $res['frequency'] = preg_replace('#\s+#', ' ', $res['frequency']);

    return $res;
  }

  private function _getAutomaticSettings()
  {
    $settings = array();
    $count_save = Tools::unserialize(Configuration::get('GOMAKOIL_IMPORT_COUNT_SETTINGS',null,Context::getContext()->shop->id_shop_group,Context::getContext()->shop->id));
    $settings[] = array('id' => 0, 'name' => '-');

    if(isset($count_save) && $count_save){
      foreach( $count_save as $value ){
        $save = Tools::unserialize(Configuration::get('GOMAKOIL_IMPORT_PRODUCTS_' . $value, null, Context::getContext()->shop->id_shop_group, Context::getContext()->shop->id));
        if( $save['base_settings']['feed_source'] == 'file_url' || $save['base_settings']['feed_source'] == 'ftp' || $save['base_settings']['feed_source'] == 'sftp' ){
          $settings[] = array('id' => $value, 'name' => $save['name_save']);
        }
      }
    }

    return $settings;
  }

  public function displayStopLink($token = null, $id, $name = null)
  {
    $this->smarty->assign(array(
      'href' => 'index.php?controller=AdminModules&configure=simpleimportproduct&tab_module=quick_bulk_update&module_name=simpleimportproduct&module_tab=schedule&id_task='.$id.'&token='.Tools::getAdminTokenLite('AdminModules').'&stopimport=1',
      'action' => $this->l('Stop'),
      'disable' => !((int) $id > 0),
    ));


    return $this->display(__FILE__, 'views/templates/admin/stop_link.tpl');

  }

  public function initFormScheduleTasks()
  {
    $helper = new HelperList();
    $helper->title = $this->l('Cron tasks');
    $helper->table = 'simpleimport_tasks';
    $helper->module = $this;
    $helper->name_controller = $this->name;
    $helper->no_link = true;
    $helper->shopLinkType = '';
    $helper->identifier = 'id_task';
    $helper->actions = array('edit', 'stop', 'delete');

    $values = $this->_getAddedTasks();
    $helper->listTotal = count($values);
    $helper->tpl_vars = array('show_filters' => false);

    $helper->toolbar_btn['new'] = array(
      'href' => $this->context->link->getAdminLink('AdminModules', false)
        .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name
        .'&module_tab=newcronjob&token='.Tools::getAdminTokenLite('AdminModules'),
      'desc' => $this->l('Add new task')
    );

    $helper->token = Tools::getAdminTokenLite('AdminModules');
    $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
      .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name.'&module_tab=newcronjob';
    $helper->fields_value['location_href'] = AdminController::$currentIndex.'&token='.Tools::getAdminTokenLite('AdminModules').'&configure=simpleimportproduct';

    $token = Configuration::getGlobalValue('GOMAKOIL_IMPORT_TASKS_KEY');
    $admin_folder = str_replace(_PS_ROOT_DIR_.'/', null, basename(_PS_ADMIN_DIR_));
    $id_shop = (int)Context::getContext()->shop->id;
    $id_shop_group = (int)Context::getContext()->shop->id_shop_group;

    if (version_compare(_PS_VERSION_, '1.7', '<') == true) {
      $path = Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.$admin_folder.'/';
      $schedule_url = $path.Context::getContext()->link->getAdminLink('AdminProductsimport', false);
      $schedule_url .= '&id_shop='.$id_shop.'&id_shop_group='.$id_shop_group.'&secure_key='.$token;
    } else {
      $schedule_url = Context::getContext()->link->getAdminLink('AdminProductsimport', false);
      $schedule_url .= '&id_shop='.$id_shop.'&id_shop_group='.$id_shop_group.'&secure_key='.$token;
    }

    $this->context->smarty->assign(
      array(
        'schedule_url'  => $schedule_url,
      )
    );

    $this->_html .= $this->display(__FILE__, 'views/templates/hook/config.tpl');

    $this->_html .=  $helper->generateList($values, $this->getTasksList());
  }

  private function _getAddedTasks()
  {
    $id_shop = (int)Context::getContext()->shop->id;
    $id_shop_group = (int)Context::getContext()->shop->id_shop_group;

    $sql = '
      SELECT * 
      FROM ' . _DB_PREFIX_ . 'simpleimport_tasks as t
      WHERE id_shop = ' . (int)$id_shop . '
      AND id_shop_group = ' . (int)$id_shop_group . '
    ';

    $res = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);

    if( $res ){
      foreach( $res as $key => &$task ){
        $task['progress'] = $task['progress'] ? $task['progress'] : '';
        $task['one_shot'] = (bool)$task['one_shot'];
        $task['active'] = (bool)$task['active'];
        $settings = Tools::unserialize(Configuration::get('GOMAKOIL_IMPORT_PRODUCTS_' . $task['import_settings'], null, Context::getContext()->shop->id_shop_group, Context::getContext()->shop->id));
        $task['import_settings'] = $settings['name_save'];
        $task['next_run'] = $this->_getNextRunTime( $task['frequency'] );
        $task['last_start'] = ($task['last_start'] == 0) ? $this->l('Never') : date(Context::getContext()->language->date_format_full, ($task['last_start']));
        $task['last_finish'] = ($task['last_finish'] == 0) ? $this->l('') : date(Context::getContext()->language->date_format_full, ($task['last_finish']));
      }
    }
    return $res;
  }

  private function _getNextRunTime( $expression )
  {
    include_once(_PS_MODULE_DIR_ . 'simpleimportproduct/libraries/Schedule/csd_parser.php');
    $expression = $this->convertSpecialExpression($expression);
    $schedule = new csd_parser($expression);

    return date(Context::getContext()->language->date_format_full, $schedule->get());
  }

  public function getTasksList()
  {
    return array(
      'description' => array('title' => $this->l('Task description'), 'type' => 'text', 'orderby' => false),
      'import_settings' => array('title' => $this->l('Import Setting'), 'type' => 'text', 'orderby' => false),
      'frequency' => array('title' => $this->l('Task frequency'), 'type' => 'text', 'orderby' => false),
      'next_run' => array('title' => $this->l('Next run'), 'type' => 'text', 'orderby' => false),
      'last_start' => array('title' => $this->l('Last import start'), 'type' => 'text', 'orderby' => false),
      'last_finish' => array('title' => $this->l('Last import finish'), 'type' => 'text', 'orderby' => false),
      'progress' => array('title' => $this->l('Import progress'), 'type' => 'text', 'orderby' => false),
      'one_shot' => array('title' => $this->l('One shot'), 'active' => 'oneshot', 'type' => 'bool', 'align' => 'center'),
      'active' => array('title' => $this->l('Active'), 'active' => 'status', 'type' => 'bool', 'align' => 'center', 'orderby' => false),
    );
  }

  private function _getPreSavedFields( $fields = false, $type = false )
  {
    $newFields = array();
    $resFields = array();
    $needAllFields = true;

    if( $type == 'active' ){
      $newFields = array(
        array(
          'id' => '{pre_saved}_1',
          'value' => '{pre_saved}_1',
          'name' => $this->l('Enabled')
        ),
        array(
          'id' => '{pre_saved}_0',
          'value' => '{pre_saved}_1',
          'name' => $this->l('Disabled')
        )
      );
    }

    if( $type == 'visibility' ){
      $newFields = array(
        array(
          'id' => '{pre_saved}_both',
          'value' => '{pre_saved}_both',
          'name' => $this->l('Everywhere')
        ),
        array(
          'id' => '{pre_saved}_catalog',
          'value' => '{pre_saved}_catalog',
          'name' => $this->l('Catalog only')
        ),
        array(
          'id' => '{pre_saved}_search',
          'value' => '{pre_saved}_search',
          'name' => $this->l('Search only')
        ),
        array(
          'id' => '{pre_saved}_none',
          'value' => '{pre_saved}_none',
          'name' => $this->l('Nowhere')
        )
      );
    }

    if( $type == 'available' ){
      $newFields = array(
        array(
          'id' => '{pre_saved}_1',
          'value' => '{pre_saved}_1',
          'name' => $this->l('Yes')
        ),
        array(
          'id' => '{pre_saved}_0',
          'value' => '{pre_saved}_0',
          'name' => $this->l('No')
        )
      );
    }

    if( $type == 'condition' ){
      $newFields = array(
        array(
          'id' => '{pre_saved}_new',
          'value' => '{pre_saved}_new',
          'name' => $this->l('New')
        ),
        array(
          'id' => '{pre_saved}_used',
          'value' => '{pre_saved}_used',
          'name' => $this->l('Used')
        ),
        array(
          'id' => '{pre_saved}_refurbished',
          'value' => '{pre_saved}_refurbished',
          'name' => $this->l('Refurbished')
        )
      );
    }

    if( $type == 'brands' ){
      $needAllFields = false;
      $brands = Manufacturer::getManufacturers(false, 0, true, false, false, false, true);
      foreach( $brands as $brand ){
        $newFields[] = array(
          'id'   => '{pre_saved}_'.$brand['id_manufacturer'],
          'value'   => '{pre_saved}_'.$brand['id_manufacturer'],
          'name' => $brand['name'],
        );
      }
    }

    if( $type == 'feature_names' ){
      $needAllFields = false;
      $featureNames = Feature::getFeatures( Configuration::get('PS_LANG_DEFAULT') );
      foreach( $featureNames as $featureName ){
        $newFields[] = array(
          'id'   => '{pre_saved}_'.$featureName['id_feature'],
          'value'   => '{pre_saved}_'.$featureName['id_feature'],
          'name' => $featureName['name'],
        );
      }
    }

    if( $type == 'out_of_stock' ){
      $newFields = array(
        array(
          'id' => '{pre_saved}_0',
          'value' => '{pre_saved}_0',
          'name' => $this->l('Deny orders')
        ),
        array(
          'id' => '{pre_saved}_1',
          'value' => '{pre_saved}_1',
          'name' => $this->l('Allow orders')
        ),
        array(
          'id' => '{pre_saved}_2',
          'value' => '{pre_saved}_2',
          'name' => $this->l(' Use default behavior')
        )
      );
    }

    if( $type == 'supplier' ){
      $needAllFields = false;
      $suppliers = Supplier::getSuppliers();
      foreach( $suppliers as $supplier ){
        $newFields[] = array(
          'id'   => '{pre_saved}_'.$supplier['id_supplier'],
          'value'   => '{pre_saved}_'.$supplier['id_supplier'],
          'name' => $supplier['name'],
        );
      }
    }

    if( $type == 'tax_rules' ){
      $needAllFields = false;
      $newFields[] = array(
        'id'   => '{pre_saved}_0',
        'value'   => '{pre_saved}_0',
        'name' => $this->l('No tax'),
      );
      $taxRules = TaxRulesGroup::getTaxRulesGroups();
      foreach( $taxRules as $taxRule ){
        $newFields[] = array(
          'id'   => '{pre_saved}_'.$taxRule['id_tax_rules_group'],
          'value'   => '{pre_saved}_'.$taxRule['id_tax_rules_group'],
          'name' => $taxRule['name'],
        );
      }
    }

    if( $type == 'reduction_type' ){
      $newFields = array(
        array(
          'id' => '{pre_saved}_percentage',
          'value' => '{pre_saved}_percentage',
          'name' => $this->l('Percentage')
        ),
        array(
          'id' => '{pre_saved}_amount',
          'value' => '{pre_saved}_amount',
          'name' => $this->l('Amount')
        )
      );
    }

    if( $type == 'customer_group' ){
      $customerGroups = Group::getGroups(Context::getContext()->language->id);
      foreach( $customerGroups as $customerGroup ){
        $newFields[] = array(
          'id'   => '{pre_saved}_'.$customerGroup['id_group'],
          'value'   => '{pre_saved}_'.$customerGroup['id_group'],
          'name' => $customerGroup['name'],
        );
      }
    }

    if( $fields ){
      foreach( $fields as $key => $field ){
        if( $key == 0 ){
          $resFields[] = $this->_getFieldValue($field);
          foreach( $newFields as $newField ){
            $resFields[] = $newField;
          }
        }
        else{
          if( $needAllFields ){
            $resFields[] = $this->_getFieldValue($field);
          }
        }
      }
    }

    return $resFields;
  }

  public function addCustomFields( $customFields = array(), $fieldSettings = false )
  {
    if( $fieldSettings ){
      $newCustomFields = array();
      foreach( $customFields as $customField ){
        if( $customField['new_field'] ){
          $newCustomFields[] = $customField['new_field'];
        }
      }
      $customFields = $newCustomFields;
    }

    if( !$customFields ){
      return false;
    }

    $fieldKey = false;

    try{
      $config = Tools::unserialize(Configuration::get('GOMAKOIL_CONFIG_IMPORT_PRODUCTS',null,Context::getContext()->shop->id_shop_group,Context::getContext()->shop->id));
      $fields = $config['name_fields_upload'];

      foreach( $customFields as $key => $customField ){
//        foreach( $customFields as $key2 => $customField2 ){
//          if( $key != $key2 && $customField == $customField2 ){
//            $fieldKey = $key;
//            throw new Exception( $this->l('Each custom field name must be unique!') );
//          }
//        }
        foreach( $fields as $fieldKey=>$field ){
          if( $customField == $field['name'] && !isset($field['custom']) ){
            $fieldKey = $key;
            throw new Exception( $this->l('Such field name already exists in current field list!') );
          }
          if( isset($field['custom']) ){
            unset($fields[$fieldKey]);
          }
        }
      }

      foreach( $customFields as $customField ){
        $duplicate = false;
        foreach( $fields as $field ){
          if( isset($field['custom']) && $field['custom'] && $field['name'] == $customField ){
            $duplicate = true;
            break;
          }
        }
        if( !$duplicate ){
          $fields[] = array(
            'name'   => $customField,
            'custom' => true
          );
        }
      }

      $config['name_fields_upload'] = $fields;
      Configuration::updateValue('GOMAKOIL_CONFIG_IMPORT_PRODUCTS', serialize($config), false, Context::getContext()->shop->id_shop_group, Context::getContext()->shop->id);

      if( !$fieldSettings ){
        $json['success'] = true;
        $json['field_list'] = $fields;
        die(Tools::jsonEncode($json));
      }
    }
    catch(Exception $e){
      $json['error'] = $e->getMessage();
      $json['field_key'] = $fieldKey;
      die(Tools::jsonEncode($json));
    }

  }

  private function _getFieldValue($field)
  {
    foreach( $field as $key => $value ){
      if( $key == 'name' ){
        $field['id'] = $value;
        $field['value'] = $value;
      }
    }

    return $field;
  }

  public function initFormImportFields()
  {
    $this->_setImportTabs();

    $config = Tools::unserialize(Configuration::get('GOMAKOIL_CONFIG_IMPORT_PRODUCTS',null,Context::getContext()->shop->id_shop_group,Context::getContext()->shop->id));

    $productIdKey = '';
    if( $config['parser_import_val'] == 'product_id' ){
      $productIdKey = 'active';
    }

    $fields = $config['name_fields_upload'];

    $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');
    $fields_form = array();

    $array_fields = array('input' => array());
    $array_combination = array('input' => array());
    $array_discount = array('input' => array());
    $array_featured = array('input' => array());
    $array_accessories = array('input' => array());
    $array_pack = array('input' => array());
    $array_customization = array('input' => array());
    $array_attachments = array('input' => array());
    $array_suppliers = array('input' => array());
    $array_import = array();
    $array_save = array('input' => array());
    $array_images = array('input' => array());

    $categoryMethods = array(
      array(
        'id' => 'category_name_method',
        'name' => $this->l('Category name')
      ),
      array(
        'id' => 'category_ids_method',
        'name' => $this->l('Category ID')
      ),
      array(
        'id' => 'category_tree_method',
        'name' => $this->l('Categories tree')
      ),
    );

    $taxMethods = array(
      array(
        'id' => 'tax_rate_method',
        'name' => $this->l('Tax rate')
      ),
      array(
        'id' => 'tax_rules_method',
        'name' => $this->l('Tax rules ID')
      ),
      array(
        'id' => 'existing_tax_method',
        'name' => $this->l('Existing Tax')
      ),
    );



    $manufacturerMethods = array(
      array(
        'id' => 'manufacturer_name_method',
        'name' => $this->l('Brand name')
      ),
      array(
        'id' => 'manufacturer_ids_method',
        'name' => $this->l('Brand ID')
      ),
      array(
        'id' => 'existing_manufacturer_method',
        'name' => $this->l('Existing Brand')
      ),
    );

    $delimiter_categories = array(
      array(
        'id' => '/',
        'name' => '/'
      ),
      array(
        'id' => '|',
        'name' => '|'
      ),
      array(
        'id' => '||',
        'name' => '||'
      ),
      array(
        'id' => '-',
        'name' => '-'
      ),
      array(
        'id' => htmlentities('->'),
        'name' => '->'
      ),
      array(
        'id' => htmlentities('=>'),
        'name' => '=>'
      ),
      array(
        'id' => ',',
        'name' => ','
      ),
      array(
        'id' => htmlentities('>'),
        'name' => '>'
      ),
    );

    $has_hint = array(
      'shop_id'      => array(
        'name'             => $this->l('Shop ID'),
        'hint'             => $this->l(''),
        'form_group_class' => 'information shop_id'
      ),
      'product_id'      => array(
        'name'             => $this->l('Product ID'),
        'hint'             => $this->l(''),
        'form_group_class' => 'information product_id '. $productIdKey
      ),
      'name'      => array(
        'name'             => $this->l('Product name'),
        'hint'             => $this->l('The public name for this product. Invalid characters <>;=#{}'),
        'form_group_class' => 'information'
      ),
      'reference' => array(
        'name'             => $this->l('Reference code'),
        'hint'             => $this->l('Your internal reference code for this product. Allowed special characters .-_#'),
        'form_group_class' => 'information'
      ),
      'ean13'     => array(
        'name'             => $this->l('EAN-13 or JAN barcode'),
        'hint'             => $this->l('This type of product code is specific to Europe and Japan, but is widely used internationally. It is a superset of the UPC code: all products marked with an EAN will be accepted in North America.'),
        'form_group_class' => 'information'
      ),

      'upc'                 => array(
        'name'             => $this->l('UPC barcode'),
        'hint'             => $this->l('This type of product code is widely used in the United States, Canada, the United Kingdom, Australia, New Zealand and in other countries.'),
        'form_group_class' => 'information'
      ),
      'isbn'                 => array(
        'name'             => $this->l('ISBN'),
        'hint'             => $this->l('The International Standard Book Number (ISBN) is used to identify books and other publications.'),
        'form_group_class' => 'information'
      ),
      'date_add'                 => array(
        'name'             => $this->l('Date add'),
        'hint'             => $this->l('Product add date'),
        'form_group_class' => 'information'
      ),
      'active'              => array(
        'name'             => $this->l('Enabled'),
        'hint'             => $this->l('Value 0 or 1'),
        'form_group_class' => 'information pre_defined',
        'fields'           => $this->_getPreSavedFields( $fields, 'active' ),
      ),
      'visibility'          => array(
        'name'             => $this->l('Visibility'),
        'hint'             => $this->l('Value both, catalog, search or none'),
        'form_group_class' => 'information pre_defined',
        'fields'           => $this->_getPreSavedFields( $fields, 'visibility' ),
      ),
      'available_for_order' => array(
        'name'             => $this->l('Available for order'),
        'hint'             => $this->l('Value 0 or 1'),
        'form_group_class' => 'information pre_defined',
        'fields'           => $this->_getPreSavedFields( $fields, 'available' ),
      ),

      'show_price' => array(
        'name'             => $this->l('Show price'),
        'hint'             => $this->l('Value 0 or 1'),
        'form_group_class' => 'information pre_defined',
        'fields'           => $this->_getPreSavedFields( $fields, 'available' ),
      ),

      'online_only' => array(
        'name'             => $this->l('Online only (not sold in your retail store)'),
        'hint'             => $this->l('Value 0 or 1'),
        'form_group_class' => 'information pre_defined',
        'fields'           => $this->_getPreSavedFields( $fields, 'available' ),
      ),

      'show_condition' => array(
        'name'             => $this->l('Display condition'),
        'hint'             => $this->l('Value 0 or 1'),
        'form_group_class' => 'information pre_defined',
        'fields'           => $this->_getPreSavedFields( $fields, 'available' ),
      ),

      'condition'         => array(
        'name'             => $this->l('Condition'),
        'hint'             => $this->l('Value new, used or refurbished'),
        'form_group_class' => 'information pre_defined',
        'fields'           => $this->_getPreSavedFields( $fields, 'condition' ),
      ),

      'short_description' => array(
        'name'             => $this->l('Short description'),
        'hint'             => $this->l('Appears in the product list(s), and at the top of the product page.'),
        'form_group_class' => 'information'
      ),
      'description'       => array(
        'name'             => $this->l('Description'),
        'hint'             => $this->l('Appears in the body of the product page.'),
        'form_group_class' => 'information'
      ),
      'tags'              => array(
        'name'             => $this->l('Tags'),
        'hint'             => $this->l('Appear on the shop in the "Tags" block, when enabled. Tags help customers easily find your products.'),
        'form_group_class' => 'information'
      ),

      'wholesale_price' => array(
        'name'             => $this->l('Pre-tax wholesale price'),
        'hint'             => $this->l('The wholesale price is the price you paid for the product. Do not include the tax.'),
        'form_group_class' => 'prices'
      ),

      'price'       => array(
        'name' => $this->l('Pre-tax retail price'),
        'hint' => $this->l('The pre-tax retail price is the price for which you intend sell this product to your customers. It should be higher than the pre-tax wholesale price: the difference between the two will be your margin.'),
        'form_group_class' => 'prices'
      ),
      'tax_method'  => array(
        'name'   => $this->l('Tax import method'),
        'fields' => $taxMethods,
        'form_group_class' => 'prices'
      ),
      'tax'         => array(
        'name'             => $this->l('Tax rate'),
        'hint'             => $this->l('The value must be from 0 to 100'),
        'form_group_class' => 'tax_rate_method prices',
      ),
      'tax_rule_id' => array(
        'name'             => $this->l('Tax rules ID'),
        'form_group_class' => 'tax_rule_method prices',
      ),
      'existing_tax' => array(
        'name'             => $this->l('Tax rule'),
        'form_group_class' => 'existing_tax_method prices',
        'fields'           => $this->_getPreSavedFields( $fields, 'tax_rules' ),
      ),
      'ecotax'          => array(
        'name' => $this->l('Ecotax (tax incl.)'),
        'hint' => $this->l('The ecotax is a local set of taxes intended to "promote ecologically sustainable activities via economic incentives". It is already included in retail price: the higher this ecotax is, the lower your margin will be.'),
        'form_group_class' => 'prices'
      ),
      'tax_price'   => array(
        'name' => $this->l('Retail price with tax'),
        'hint' => null,
        'form_group_class' => 'prices'
      ),

      'unit_price' => array(
        'name' => $this->l('Unit price (tax excl.)'),
        'hint' => $this->l('When selling a pack of items, you can indicate the unit price for each item of the pack. For instance, "per bottle" or "per pound".'),
        'form_group_class' => 'prices'
        ),

      'unity'      => array(
        'name' => $this->l('Unit price (per)'),
        'hint' => null,
        'form_group_class' => 'prices'
      ),

      'on_sale'      => array(
        'name' => $this->l('Display the "on sale" icon'),
        'hint' => $this->l('Display the "on sale" icon on the product page, and in the text found within the product listing. Value 0 or 1'),
        'form_group_class' => 'prices pre_defined',
        'fields'           => $this->_getPreSavedFields( $fields, 'available' ),
        ),

      'meta_title'    => array(
        'name' => $this->l('Meta title'),
        'hint' => $this->l('Public title for the products page, and for search engines. Leave blank to use the product name. The number of remaining characters is displayed to the left of the field.'),
        'form_group_class' => 'seo'
        ),
      'meta_keywords' => array(
        'name' => $this->l('Meta keywords'),
        'hint' => $this->l('Keywords for HTML header, separated by commas.'),
        'form_group_class' => 'seo'
      ),

      'meta_description' => array(
        'name' => $this->l('Meta description'),
        'hint' => $this->l('This description will appear in search engines. You need a single sentence, shorter than 160 characters (including spaces).'),
        'form_group_class' => 'seo'
      ),
      'link_rewrite' => array(
        'name' => $this->l('Friendly URL'),
        'hint' => $this->l('This is the human-readable URL, as generated from the products name. You can change it if you want.'),
        'form_group_class' => 'seo'
        ),
      'remove_categories'           => array(
        'name'   => $this->l('Remove category associations'),
        'form_group_class' => 'associations categories_import_method'
      ),
      'category_method'           => array(
        'name'   => $this->l('Categories import method'),
        'fields' => $categoryMethods,
        'form_group_class' => 'associations categories_import_method'
      ),
      'default_category_id'       => array(
        'name'             => $this->l('Category Default ID'),
        'form_group_class' => 'category_id_method associations',
      ),

      'default_category'          => array(
        'name'             => $this->l('Default category'),
        'hint'             => $this->l('The default category is the main category for your product, and is displayed by default.'),
        'form_group_class' => 'category_name_method associations',
      ),
      'associated_categories'          => array(
        'name'             => $this->l('Associated Categories'),
      ),
      'category_linking'          => array(
          'name'             => '',
          'form_group_class' => 'category_name_method associations',
      ),
      'delimiter_categories'          => array(
        'name'             => $this->l('Delimiter of categories'),
        'hint'             => $this->l('Categories identifier delimiter.'),
        'fields'           => $delimiter_categories,
        'form_group_class' => 'category_tree_method associations',
      ),
      'associated_categories_ids' => array(
        'name'             => $this->l('Associated categories (ID)'),
        'hint'             => $this->l('Each associated category id must be separated by a comma'),
        'form_group_class' => 'category_id_method associations',
      ),
      'manufacturer_method' => array(
        'name'   => $this->l('Brand import method'),
        'fields' => $manufacturerMethods,
        'form_group_class' => 'associations'
      ),
      'manufacturer'        => array(
        'name'             => $this->l('Brand name'),
        'hint'             => $this->l('Brand Name. Invalid characters &lt;&gt;;=#{}'),
        'form_group_class' => 'manufacturer_name_method associations',
      ),
      'manufacturer_id'     => array(
        'name'             => $this->l('Brand ID'),
        'form_group_class' => 'manufacturer_id_method associations',
      ),
      'existing_manufacturer'     => array(
        'name'             => $this->l('Existing Brand'),
        'form_group_class' => 'existing_manufacturer_method associations',
        'fields'           => $this->_getPreSavedFields( $fields, 'brands' )
      ),
      'width'                     => array(
        'name' => $this->l('Package width'),
        'hint' => null,
        'form_group_class' => 'shipping'
      ),
      'height'                    => array(
        'name' => $this->l('Package height'),
        'hint' => null,
        'form_group_class' => 'shipping'
      ),
      'depth'                     => array(
        'name' => $this->l('Package depth'),
        'hint' => null,
        'form_group_class' => 'shipping'
      ),
      'weight'                    => array(
        'name' => $this->l('Package weight'),
        'hint' => null,
        'form_group_class' => 'shipping'
      ),
      'additional_shipping_cost'  => array(
        'name' => $this->l('Additional shipping fees (for a single item)'),
        'hint' => $this->l('If a carrier has a tax, it will be added to the shipping fees.'),
        'form_group_class' => 'shipping'
      ),
      'additional_delivery_times' => array(
          'name' => $this->l('Delivery Time'),
          'hint' => $this->l('Display delivery time for a product is advised for merchants selling in Europe to comply with the local laws.'),
          'form_group_class' => 'shipping'
      ),
      'delivery_in_stock' => array(
          'name' => $this->l('Delivery time of in-stock products'),
          'hint' => null,
          'form_group_class' => 'shipping'
      ),
      'delivery_out_stock' => array(
          'name' => $this->l('Delivery time of out-of-stock products with allowed orders'),
          'hint' => null,
          'form_group_class' => 'shipping'
      ),
      'carriers_id'                    => array(
        'name' => $this->l('Product Carriers ID'),
        'hint' => $this->l('Each carrier ID must be separated by a comma'),
        'form_group_class' => 'shipping'
      ),
      'advanced_stock_management'                  => array(
        'name' => $this->l('Advanced stock management'),
        'hint' => $this->l('To use the advanced stock management system for this product.'),
        'form_group_class' => 'quantities',
      ),

      'depends_on_stock'                  => array(
        'name' => $this->l('Depends On Stock'),
        'hint' => $this->l('Depends On Stock.'),
        'form_group_class' => 'quantities',
      ),
      'id_warehouse'                  => array(
        'name' => $this->l('Warehouse'),
        'hint' => $this->l('ID Warehouse.'),
        'form_group_class' => 'quantities',
      ),
      'warehouse_location' => array(
        'name'             => $this->l('Warehouse Location'),
        'hint'             => $this->l('This value will be set only if product does not have any combinations'),
        'form_group_class' => 'quantities warehouse_location'
      ),
      'quantity_method'                  => array(
        'name' => $this->l('Quantity Import Method'),
        'hint' => $this->l('You can override add or deduct to existing quantity values.'),
        'form_group_class' => 'quantities',
      ),
      'quantity'                  => array(
        'name' => $this->l('Quantity'),
        'hint' => $this->l('Available quantities for sale.'),
        'form_group_class' => 'quantities',
      ),
      'location'                  => array(
        'name' => $this->l('Stock location'),
        'hint' => null,
        'form_group_class' => 'quantities',
      ),
      'low_stock_threshold'     => array(
        'name' => $this->l('Low stock level'),
        'hint' => null,
        'form_group_class' => 'quantities',
      ),
      'low_stock_alert'     => array(
        'name' => $this->l('Email alert'),
        'hint' => $this->l('Send me an email when the quantity is below or equals this level'),
        'form_group_class' => 'quantities pre_defined',
        'fields'           => $this->_getPreSavedFields( $fields, 'available' ),
      ),
      'out_of_stock'                  => array(
        'name' => $this->l('Behavior when out of stock'),
        'hint' => $this->l('0 - Deny orders; 1 - Allow orders; 2 - Default'),
        'form_group_class' => 'quantities pre_defined',
        'fields'           => $this->_getPreSavedFields( $fields, 'out_of_stock' ),
      ),
      'minimal_quantity'                  => array(
        'name' => $this->l('Minimum quantity'),
        'hint' => $this->l('The minimum quantity to buy this product (set to 1 to disable this feature)'),
        'form_group_class' => 'quantities',
      ),
      'available_now'   => array(
        'name' => $this->l('Displayed text when in-stock'),
        'hint' => $this->l('Displayed text when in-stock. Forbidden characters <>;=#{}'),
        'form_group_class' => 'quantities',
      ),
      'available_later' => array(
        'name' => $this->l('Displayed text when backordering is allowed'),
        'hint' => $this->l('Displayed text when backordering is allowed. If empty, the message "in stock" will be displayed. Forbidden characters <>;=#{}'),
        'form_group_class' => 'quantities',
      ),
      'available_date'                  => array(
        'name' => $this->l('Availability date'),
        'hint' => $this->l('The next date of availability for this product when it is out of stock. (0000-00-00)'),
        'form_group_class' => 'quantities',
      ),
      'virtual_product_url' => array(
        'name' => $this->l('File URL'),
        'hint' => $this->l('Link on file'),
        'form_group_class' => 'virtual_product file_url',
      ),
      'virtual_product_nb_downloable' => array(
        'name' => $this->l('Number of allowed downloads'),
        'hint' => $this->l('Number of downloads allowed per customer. Set to 0 for unlimited downloads.'),
        'form_group_class' => 'virtual_product',
      ),
      'virtual_product_expiration_date' => array(
        'name' => $this->l('Expiration date'),
        'hint' => $this->l('Format: YYYY-MM-DD.'),
        'form_group_class' => 'virtual_product',
      ),
      'virtual_product_nb_days' => array(
        'name' => $this->l('Number of days'),
        'hint' => $this->l('Number of days this file can be accessed by customers. Set to zero for unlimited access.'),
        'form_group_class' => 'virtual_product',
      ),
      'field_settings' => array(
        'name' => $this->l('field_settings'),
        //        'hint' => $this->l('Module will import products that are associated just for selected brands'),
        'form_group_class' => 'additional_settings',
      ),
      'price_settings' => array(
        'name' => $this->l('price_settings'),
        //        'hint' => $this->l('Module will import products that are associated just for selected brands'),
        'form_group_class' => 'additional_settings',
      ),
      'quantity_settings' => array(
        'name' => $this->l('quantity_settings'),
        //        'hint' => $this->l('Module will import products that are associated just for selected brands'),
        'form_group_class' => 'additional_settings',
      ),
      'disable_zero_products' => array(
        'name' => $this->l('Disable products with zero quantity'),
        'hint' => $this->l('Means products that are in the file'),
        'form_group_class' => 'import_conditions',
      ),
      'new_products' => array(
        'name' => $this->l('New Products'),
        'hint' => $this->l('Add or skip new products to store'),
        'form_group_class' => 'import_conditions',
        'fields' => array(
          array(
            'id' => 'add',
            'name' => $this->l('Add')
          ),
          array(
            'id' => 'skip',
            'name' => $this->l('Skip')
          ),
        ),
      ),
      'existing_products' => array(
        'name' => $this->l('Existing Products'),
        'hint' => $this->l('Update or skip existing products in the store'),
        'form_group_class' => 'import_conditions',
        'fields' => array(
          array(
            'id' => 'update',
            'name' => $this->l('Update')
          ),
          array(
            'id' => 'skip',
            'name' => $this->l('Skip')
          ),
        ),
      ),
      'file_products' => array(
        'name' => $this->l('Products in store but not in the file'),
        'hint' => null,
        'form_group_class' => 'import_conditions',
        'fields' => array(
          array(
            'id' => 'ignore',
            'name' => $this->l('Ignore')
          ),
          array(
            'id' => 'disable',
            'name' => $this->l('Disable')
          ),
          array(
            'id' => 'zero_quantity',
            'name' => $this->l('Quantity to Zero')
          ),
        ),
      ),
      'file_store_products' => array(
        'name' => $this->l('Products in store and in the file'),
        'hint' => null,
        'form_group_class' => 'import_conditions',
        'fields' => array(
          array(
            'id' => 'ignore',
            'name' => $this->l('Ignore')
          ),
          array(
            'id' => 'enable',
            'name' => $this->l('Enable')
          ),
          array(
            'id' => 'disable',
            'name' => $this->l('Disable')
          ),
          array(
            'id' => 'zero_quantity',
            'name' => $this->l('Quantity to Zero')
          ),
        ),
      ),
      'skip_product' => array(
        'name' => $this->l('Skip product from import'),
        'hint' => $this->l('Must be value - 1 to skip product from import'),
        'form_group_class' => 'import_conditions',
      ),
      'remove_product' => array(
        'name' => $this->l('Remove the product from store'),
        'hint' => $this->l('Must be value - 1 to remove the product from store'),
        'form_group_class' => 'import_conditions',
      ),
      'import_from_categories' => array(
        'name' => $this->l('Import just from categories'),
        'hint' => $this->l('Module will import products that are associated just for selected categories'),
        'form_group_class' => 'import_conditions',
      ),
      'import_from_suppliers' => array(
        'name' => $this->l('Import just from suppliers'),
        'hint' => $this->l('Module will import products that are associated just for selected suppliers'),
        'form_group_class' => 'import_conditions',
      ),
      'import_from_brands' => array(
        'name' => $this->l('Import just from brands'),
        'hint' => $this->l('Module will import products that are associated just for selected brands'),
        'form_group_class' => 'import_conditions',
      ),
    );

    $hasHintCount = 0;
    $hasCombinationCount = 2;
    if( !Configuration::get('PS_MULTISHOP_FEATURE_ACTIVE') ){
      unset($has_hint['shop_id']);
    } else {
      $shop_fields = array();

      $all_shops = Shop::getShops();

      array_push($shop_fields, array('name' => 'no', 'id' => 'no'));
      array_push($shop_fields, array('name' => 'All Shops', 'id' => ('ImportToShop_All Shops_(0)')));

      foreach ($all_shops as $shop) {
        array_push($shop_fields, array('name' => htmlentities($shop['name']), 'id' => ('ImportToShop_' . htmlentities($shop['name']) . '_(' . $shop['id_shop'] . ')')));
      }
    }

    foreach($has_hint as $key => $val){
      if ($key == 'shop_id') {
        $val['fields'] = $shop_fields;
      }

      if( $key == 'remove_categories' ){

        $array_fields['input'][$hasHintCount] = array(
          'type'    => 'switch',
          'label'   => $this->l('Remove category associations'),
          'name'    => 'remove_categories',
          'form_group_class' => 'associations',
          'is_bool' => true,
          'hint'    => $this->l('Remove current product categories associations'),
          'values'  => array(
            array(
              'id'    => 'display_on',
              'value' => 1,
              'label' => $this->l('Yes')),
            array(
              'id'    => 'display_off',
              'value' => 0,
              'label' => $this->l('No')),
          ),
        );

        $hasHintCount++;
        continue;
      }

      if( $key == 'quantity_method' ){
        $array_fields['input'][$hasHintCount] =  array(
          'type' => 'select',
          'label' => $val['name'],
          'hint' => isset($val['hint']) ? $val['hint'] : null,
          'name' => $key,
          'form_group_class' => 'quantities',
          'class' => 'chosen',
          'options' => array(
            'query' => array(
              array(
                'id' => 'override',
                'name' => $this->l('Override')
              ),
              array(
                'id' => 'add',
                'name' => $this->l('Add')
              ),
              array(
                'id' => 'deduct',
                'name' => $this->l('Deduct')
              )
            ),
            'id' => 'id',
            'name' => 'name',
            'value' => 'name'
          )
        );

        $hasHintCount++;
        continue;
      }

      if( $key == 'field_settings' ){
        $tpl = $this->moreFieldSettings();
        $fieldList = $this->_getFieldList();
        $array_fields['input'][$hasHintCount] =  array(
          'type' => 'html',
          'name' => 'field_settings',
          'html_content' => $tpl . $fieldList,
          'form_group_class' => 'additional_settings field_settings',
        );

        $hasHintCount++;
        continue;
      }

      if( $key == 'price_settings' ){
        $tpl = $this->morePriceSettings();
        $array_fields['input'][$hasHintCount] =  array(
          'type' => 'html',
          'name' => 'price_settings',
          'html_content' => $tpl,
          'form_group_class' => 'additional_settings price_settings',
        );

        $hasHintCount++;
        continue;
      }

      if( $key == 'quantity_settings' ){
        $tpl = $this->moreQuantitySettings();
        $array_fields['input'][$hasHintCount] =  array(
          'type' => 'html',
          'name' => 'quantity_settings',
          'html_content' => $tpl,
          'form_group_class' => 'additional_settings quantity_settings',
        );

        $hasHintCount++;
        continue;
      }

      if( $key == 'category_linking' ){

        $array_fields['input'][$hasHintCount] =  array(
          'type' => 'html',
          'label' => $this->l('Link categories from file to existing shop categories'),
          'name' => $this->getCategoryLinkingTpl($fields),
          'class' => 'categories_all',
          'form_group_class' => 'category_name_method associations',
          'value' => '',
          'options' => '',
        );

        $hasHintCount++;
        continue;
      }

      if( $key == 'disable_zero_products' ){
        $array_fields['input'][$hasHintCount] = array(
          'type'    => 'switch',
          'label'   => $this->l('Disable products with zero quantity'),
          'name'    => 'disable_zero_products',
          'form_group_class' => 'import_conditions',
          'is_bool' => true,
          'hint'    => $this->l('Disable products with zero quantity that are in the file'),
          'values'  => array(
            array(
              'id'    => 'display_on',
              'value' => 1,
              'label' => $this->l('Yes')),
            array(
              'id'    => 'display_off',
              'value' => 0,
              'label' => $this->l('No')),
          ),
        );

        $hasHintCount++;
        continue;
      }

      if( $key == 'import_from_categories' ){
        $selected_categories = array();

        if(Tools::getValue('module_tab') == 'step_2' && Tools::getValue('save') !== false) {
          $save = Tools::unserialize(Configuration::get('GOMAKOIL_IMPORT_PRODUCTS_'.Tools::getValue('save') ,null,Context::getContext()->shop->id_shop_group,Context::getContext()->shop->id));
          if( isset($save['import_from_categories']) && $save['import_from_categories'] )
            $selected_categories = $save['import_from_categories'];
        }

        $array_fields['input'][$hasHintCount] =  array(
          'type' => 'categories',
          'label' => $this->l('Import just from categories'),
          'name' => 'import_from_categories',
          'hint' => 'Module will import products that are associated just for selected categories',
          'class' => 'categories_all',
          'form_group_class' => 'import_conditions from_categories',
          'tree' => array(
            'id' => 'import_from_categories',
            'use_checkbox' => true,
            'use_search' => true,
            'selected_categories' => $selected_categories,
//            'disabled_categories' => (!Tools::isSubmit('add' . $this->table) && !Tools::isSubmit('submitAdd' . $this->table)) ? array($this->_category->id) : null,
//            'root_category' => $context->shop->getCategory(),
          ),
        );

        $hasHintCount++;
        continue;
      }

      if( $key == 'import_from_suppliers' ){
        $suppliers = Supplier::getSuppliers(false, 0, false);
        foreach( $suppliers as $key => $supplier ){
          $suppliers[$key]['val'] = $supplier['id_supplier'];
        }

        $array_fields['input'][$hasHintCount] =  array(
          'type' => 'checkbox',
          'label' => $this->l('Import just from suppliers'),
          'name' => 'import_from_suppliers',
          'hint' => 'Module will import products that are associated just for selected suppliers',
          'class' => 'suppliers_all',
          'multiple' => true,
          'form_group_class' => 'import_conditions from_suppliers',
          'values' => array(
            'query' => $suppliers,
            'id' => 'id_supplier',
            'name' => 'name',
          )
        );

        $hasHintCount++;
        continue;
      }

      if( $key == 'import_from_brands' ){
        $brands = Manufacturer::getManufacturers(false, 0, false);
        foreach( $brands as $key => $brand ){
          $brands[$key]['val'] = $brand['id_manufacturer'];
        }

        $array_fields['input'][$hasHintCount] =  array(
          'type' => 'checkbox',
          'label' => $this->l('Import just from brands'),
          'name' => 'import_from_brands',
          'hint' => 'Module will import products that are associated just for selected brands',
          'class' => 'brands_all',
          'multiple' => true,
          'form_group_class' => 'import_conditions from_brands',
          'values' => array(
            'query' => $brands,
            'id' => 'id_manufacturer',
            'name' => 'name',
          )
        );

        $hasHintCount++;
        continue;
      }

      if( $key == "associated_categories" ){
        $array_fields['input'][$hasHintCount]=  array(
          'type' => 'html_categories',
          'label' => $this->l('Associated categories'),
          'name' => 'html_data',
          'class' => 'categories_all',
          'form_group_class' => 'category_name_method associations',
          'value' => $fields,
          'options' => '',
          'hint' => null,
        );
        $hasHintCount++;
        continue;
      }

      $array_fields['input'][$hasHintCount] = array(
        'type' => 'select',
        'label' => $val['name'],
        'name' => $key,
        'class' => 'chosen',
        'form_group_class' => isset($val['form_group_class']) ? $val['form_group_class'] : false,
        'options' => array(
          'query' => isset($val['fields']) ? $val['fields'] : $fields,
          'id' => isset($val['fields']) ? 'id' : 'name',
          'name' => 'name',
          'value' => 'name'
        )
      );
      if( isset($val['hint']) && $val['hint'] ){
        $array_fields['input'][$hasHintCount]['hint'] = $val['hint'];
      }
      $hasHintCount++;

    }

    $fields_form[0]['form'] = $array_fields;
    $fields_form[1]['form'] = array();


    $fields_form[2]['form'] = array();

    $array_combination['input'][] = array(
      'type'    => 'switch',
      'label'   => $this->l('Remove current combinations'),
      'name'    => 'remove_combinations',
      'is_bool' => true,
      'hint'    => '',
      'values'  => array(
        array(
          'id'    => 'display_on',
          'value' => 1,
          'label' => $this->l('Yes')),
        array(
          'id'    => 'display_off',
          'value' => 0,
          'label' => $this->l('No')),
      ),
    );

    $array_combination['input'][] = array(
      'type' => 'select',
      'label' => 'Combination identification key',
      'hint' => null,
      'name' => 'combination_key',
      'form_group_class' => 'combination_key',
      'class' => 'chosen',
      'options' => array(
        'query' => array(
          array(
            'id' => 'attributes',
            'name' => $this->l('Attributes')
          ),
          array(
            'id' => 'reference',
            'name' => $this->l('Reference')
          ),
          array(
            'id' => 'ean13',
            'name' => $this->l('EAN-13 or JAN barcode')
          ),
          array(
            'id' => 'upc',
            'name' => $this->l('UPC barcode')
          ),
          array(
            'id' => 'isbn',
            'name' => $this->l('ISBN')
          ),
          array(
            'id' => 'all_combinations',
            'name' => $this->l('Update All Combinations')
          ),
        ),
        'id' => 'id',
        'name' => 'name',
        'value' => 'name'
      )
    );

    $value = array();
    $count_comb = 1;
    if(Tools::getValue('module_tab') == 'step_2' && Tools::getValue('save') !== false){
      $save = Tools::unserialize(Configuration::get('GOMAKOIL_IMPORT_PRODUCTS_'.Tools::getValue('save') ,null,Context::getContext()->shop->id_shop_group,Context::getContext()->shop->id));
      if(isset($save['field_combinations'][0]['images']) && $save['field_combinations'][0]['images']){
        $value = $save['field_combinations'][0]['images'];
      }

      $count_comb = count($save['field_combinations']);
    }



    foreach($this->_has_hint_combinations as $key => $val){

      if( $key == 'quantity_combination_method' ){
        $array_combination['input'][$hasCombinationCount] =  array(
          'type' => 'select',
          'label' => $val['name'],
          'hint' => isset($val['hint']) ? $val['hint'] : null,
          'name' => $key,
          'form_group_class' => 'combinations full_combination',
          'class' => 'chosen',
          'options' => array(
            'query' => array(
              array(
                'id' => 'override',
                'name' => $this->l('Override')
              ),
              array(
                'id' => 'add',
                'name' => $this->l('Add')
              ),
              array(
                'id' => 'deduct',
                'name' => $this->l('Deduct')
              )
            ),
            'id' => 'id',
            'name' => 'name',
            'value' => 'name'
          )
        );

        $hasCombinationCount++;
        continue;
      }

      if( $key == "images_combination" ){

        $array_combination['input'][$hasCombinationCount]=  array(
          'type' => 'html_images',
          'label' => $this->l('Combination images'),
          'name' => 'html_data',
          'class' => 'images_all',
          'form_group_class' => 'images_combination combinations',
          'value' => $value,
          'fields' => $fields,
          'options' => '',
          'hint' => isset($val['hint']) ? $val['hint'] : null,
        );

        $hasCombinationCount++;
        continue;
      }

      $array_combination['input'][$hasCombinationCount] = array(
        'type'             => 'select',
        'label'            => $val['name'],
        'name'             => $key,
        'class'            => 'chosen',
        'hint'             => isset($val['hint']) ? $val['hint'] : '',
        'form_group_class' => isset($val['form_group_class']) ? $val['form_group_class'] : false,
        'options'          => array(
          'query' => isset($val['fields']) ? $val['fields'] : $fields,
          'id'    => isset($val['fields']) ? 'value' : 'name',
          'name'  => 'name'
        )
      );

      $hasCombinationCount++;
    }
    foreach( $array_combination['input'] as $key => $value ){
      if( !$value['hint'] ){
        unset($array_combination['input'][$key]['hint']);
      }
    }

    $k = false;
    if(Tools::getValue('module_tab') == 'step_2' && Tools::getValue('save') !== false){
      $k =  Tools::getValue('save');
    }
    $tpl = $this->moreCombinationSuppliers($k, 1);


    $array_combination['input'][] = array(
      'type' => 'html',
      'name' => $tpl,
      'hint' => null,
      'form_group_class' => 'full_combination full_combination_suppliers',
      'html_content' => $tpl
    );

    $array_combination['input'][] = array(
      'type' => 'html',
      'name' => '<button type="button" class="btn btn-default more_combination">'.$this->l('add combination').'</button>',
      'hint' => null,
      'form_group_class' => 'full_combination full_combination_button',
      'html_content' => '<button type="button" class="btn btn-default more_combination">'.$this->l('add combination').'</button>'
    );
    $fields_form[3]['form'] = $array_combination;


    $fields_form[4]['form'] = array('input' => array(array(
      'type' => 'switch',
      'label' => $this->l('Has discount'),
      'name' => 'has_discount',
      'form_group_class' => 'has_discount',
      'is_bool' => true,
      'values' => array(
        array(
          'id' => 'display_on',
          'value' => 1,
          'label' => $this->l('Yes')),
        array(
          'id' => 'display_off',
          'value' => 0,
          'label' => $this->l('No')),
      ),
    )));

    $array_discount['input'][] = array(
      'type'    => 'switch',
      'label'   => $this->l('Remove current specific prices'),
      'name'    => 'remove_specific_prices',
      'is_bool' => true,
      'hint'    => '',
      'values'  => array(
        array(
          'id'    => 'display_on',
          'value' => 1,
          'label' => $this->l('Yes')),
        array(
          'id'    => 'display_off',
          'value' => 0,
          'label' => $this->l('No')),
      ),
    );

    foreach($this->_has_hint_discount as $key => $val){
      $array_discount['input'][]=  array(
        'type' => 'select',
        'label' => $val['name'],
        'name' => $key,
        'class' => 'chosen',
        'hint' => isset( $val['hint'] ) ? $val['hint'] : null,
        'form_group_class' => isset($val['fields']) ? 'pre_defined' : '',
        'options' => array(
          'query' => isset($val['fields']) ? $val['fields'] : $fields,
          'id' => isset($val['fields']) ? 'id' : 'name',
          'name' => 'name'
        )
      );
    }
    foreach( $array_discount['input'] as $key => $value ){
      if( !$value['hint'] ){
        unset($array_discount['input'][$key]['hint']);
      }
    }




    $select = array(
      array(
        'id' => '0',
        'name' => $this->l('Whole product'),
      ),
    );


    for ($x=0; $x++<$count_comb;){
      $select[] = array(
        'id' => $x,
        'name' => $this->l('Combination ').$x,
      );
    }

    $array_discount['input'][]=  array(
      'type' => 'select',
      'label' => $this->l('Specific prices for product/combination'),
      'name' => 'specific_prices_for',
      'hint' => null,
      'options' => array(
        'query' =>$select,
        'id' =>   'id',
        'name' => 'name'
      )
    );


    $array_discount['input'][] = array(
      'type' => 'html',
      'name' => '<button type="button" class="btn btn-default more_discount">'.$this->l('add special price').'</button>',
      'hint' => null,
      'html_content' => '<button type="button" class="btn btn-default more_discount">'.$this->l('add special price').'</button>'
    );
    $fields_form[5]['form'] = $array_discount;



    $array_images['input'][] = array(
      'type'    => 'switch',
      'label'   => $this->l('Remove current images'),
      'name'    => 'remove_images',
      'is_bool' => true,
      'form_group_class' => 'remove_images',
      'hint' => null,
      'values'  => array(
        array(
          'id'    => 'display_on',
          'value' => 1,
          'label' => $this->l('Yes')),
        array(
          'id'    => 'display_off',
          'value' => 0,
          'label' => $this->l('No')),
      ),
    );

    $array_images['input'][] = array(
      'type' => 'switch',
      'label' => $this->l('Generate Thumbnails'),
      'name' => 'generate_thumbnails',
      'hint' => null,
      'is_bool' => true,
      'values' => array(
        array(
          'id' => 'display_on',
          'value' => 1,
          'label' => $this->l('Yes')),
        array(
          'id' => 'display_off',
          'value' => 0,
          'label' => $this->l('No')),
      ),
    );

    $array_images['input'][] = array(
      'type' => 'switch',
      'label' => $this->l('Import just for products without images'),
      'hint' => null,
      'name' => 'no_product_images',
      'class' => 'no_product_images',
      'is_bool' => true,
      'values' => array(
        array(
          'id' => 'display_on',
          'value' => 1,
          'label' => $this->l('Yes')),
        array(
          'id' => 'display_off',
          'value' => 0,
          'label' => $this->l('No')),
      ),
    );

    $array_images['input'][] = array(
      'type' => 'switch',
      'label' => $this->l('Import images in few streams'),
      'desc' => $this->l('Module imports images separately from products in few streams (require more server power)'),
      'name' => 'images_stream',
      'hint' => null,
      'is_bool' => true,
      'values' => array(
        array(
          'id' => 'display_on',
          'value' => 1,
          'label' => $this->l('Yes')),
        array(
          'id' => 'display_off',
          'value' => 0,
          'label' => $this->l('No')),
      ),
    );

    foreach($this->_has_hint_images as $key => $val){
      $array_images['input'][]=  array(
        'type' => 'select',
        'label' => $val['name'],
        'name' => $key,
        'class' => 'chosen',
        'hint' => isset( $val['hint'] ) ? $val['hint'] : null,
        'options' => array(
          'query' =>$fields,
          'id' => 'name',
          'name' => 'name'
        )
      );
    }
    foreach( $array_images['input'] as $key => $value ){
      if( !$value['hint'] ){
        unset($array_images['input'][$key]['hint']);
      }
    }
    $array_images['input'][] = array(
      'type' => 'html',
      'name' => '<button type="button" class="btn btn-default more_image">'.$this->l('add image').'</button>',
      'hint' => null,
      'html_content' => '<button type="button" class="btn btn-default more_image">'.$this->l('add image').'</button>'
    );
    $fields_form[6]['form'] = $array_images;



    $array_featured['input'][] = array(
      'type'    => 'switch',
      'label'   => $this->l('Remove current features'),
      'name'    => 'remove_features',
      'form_group_class' => 'remove_features',
      'hint' => null,
      'is_bool' => true,
      'values'  => array(
        array(
          'id'    => 'display_on',
          'value' => 1,
          'label' => $this->l('Yes')),
        array(
          'id'    => 'display_off',
          'value' => 0,
          'label' => $this->l('No')),
      ),
    );

    foreach($this->_has_hint_featured as $key => $val){

      if( $key == 'features_name' || $key == 'features_id' ){
        $attrNames = $fields;
        foreach( $attrNames as $keyAttr => $attrName ){
          $attrNames[$keyAttr]['value'] = $attrName['name'];
        }
        $noAttr = array(
          'name'   => $this->l('no'),
          'value'  => 'no'
        );

        $attrNames[0] = array(
          'name'   => $this->l('Enter manually'),
          'value'  => 'enter_manually'
        );

        array_unshift($attrNames, $noAttr);

        $val['fields'] = $attrNames;
      }

      if( $key == 'features_name_manually' ){
        $array_featured['input'][]=  array(
          'type' => 'text',
          'label' => $val['name'],
          'name' => $key,
          'hint' => isset( $val['hint'] ) ? $val['hint'] : null,
          'value' => '',
          'form_group_class' => 'features_name_manually'
        );
      }
      else{
        $array_featured['input'][]=  array(
          'type' => 'select',
          'label' => $val['name'],
          'name' => $key,
          'class' => 'chosen',
          'hint' => isset( $val['hint'] ) ? $val['hint'] : null,
          'form_group_class' => isset( $val['form_group_class'] ) ? $val['form_group_class'] : '',
          'options' => array(
            'query' => isset($val['fields']) ? $val['fields'] : $fields,
            'id' => isset($val['fields']) ? 'value' : 'name',
            'name' => 'name'
          )
        );
      }
    }
    $array_featured['input'][] = array(
      'type' => 'html',
      'name' => '<button type="button" class="btn btn-default more_featured">'.$this->l('add features').'</button>',
      'hint' => null,
      'html_content' => '<button type="button" class="btn btn-default more_featured">'.$this->l('add features').'</button>'
    );
    $fields_form[7]['form'] = $array_featured;

    $fields_form[8]['form'] = array('input' => array(array(
      'type' => 'switch',
      'label' => $this->l('Has related products'),
      'name' => 'has_accessories',
      'form_group_class' => 'has_accessories',
      'is_bool' => true,
      'values' => array(
        array(
          'id' => 'display_on',
          'value' => 1,
          'label' => $this->l('Yes')),
        array(
          'id' => 'display_off',
          'value' => 0,
          'label' => $this->l('No')),
      ),
    )));

    $array_accessories['input'][] = array(
      'type'    => 'switch',
      'label'   => $this->l('Remove current related products'),
      'name'    => 'remove_accessories',
      'is_bool' => true,
      'hint'    => '',
      'values'  => array(
        array(
          'id'    => 'display_on',
          'value' => 1,
          'label' => $this->l('Yes')),
        array(
          'id'    => 'display_off',
          'value' => 0,
          'label' => $this->l('No')),
      ),
    );

    foreach($this->_has_hint_accessories as $key => $val){
      $array_accessories['input'][]=  array(
        'type' => 'select',
        'label' => $val['name'],
        'name' => $key,
        'class' => 'chosen',
        'hint' => isset( $val['hint'] ) ? $val['hint'] : null,
        'options' => array(
          'query' => isset($val['fields']) ? $val['fields'] : $fields,
          'id' => isset($val['fields']) ? 'value' : 'name',
          'name' => 'name'
        )
      );
    }

    foreach( $array_accessories['input'] as $key => $value ){
      if( !$value['hint'] ){
        unset($array_accessories['input'][$key]['hint']);
      }
    }

    $fields_form[9]['form'] = $array_accessories;

    $fields_form[10]['form'] = array();

    $array_pack['input'][] = array(
      'type'    => 'switch',
      'label'   => $this->l('Remove current list of products of Pack'),
      'name'    => 'remove_pack_products',
      'form_group_class' => 'remove_pack_products',
      'is_bool' => true,
      'hint'    => '',
      'values'  => array(
        array(
          'id'    => 'display_on',
          'value' => 1,
          'label' => $this->l('Yes')),
        array(
          'id'    => 'display_off',
          'value' => 0,
          'label' => $this->l('No')),
      ),
    );

    foreach($this->_has_hint_pack as $key => $val){
      $array_pack['input'][]=  array(
        'type' => 'select',
        'label' => $val['name'],
        'name' => $key,
        'class' => 'chosen',
        'hint' => isset( $val['hint'] ) ? $val['hint'] : null,
        'options' => array(
          'query' => isset($val['fields']) ? $val['fields'] : $fields,
          'id' => isset($val['fields']) ? 'value' : 'name',
          'name' => 'name'
        )
      );
    }

    foreach( $array_pack['input'] as $key => $value ){
      if( !$value['hint'] ){
        unset($array_pack['input'][$key]['hint']);
      }
    }

    $fields_form[11]['form'] = $array_pack;



    foreach($this->_has_hint_suppliers as $key => $val){

      if( $key == 'remove_suppliers' ){

        $array_suppliers['input'][] = array(
          'type'    => 'switch',
          'label'   => $this->l('Remove supplier associations'),
          'name'    => 'remove_suppliers',
          'form_group_class' => 'suppliers',
          'is_bool' => true,
          'hint'    => $this->l('Remove current product suppliers associations'),
          'values'  => array(
            array(
              'id'    => 'display_on',
              'value' => 1,
              'label' => $this->l('Yes')),
            array(
              'id'    => 'display_off',
              'value' => 0,
              'label' => $this->l('No')),
          ),
        );

        continue;
      }

      $array_suppliers['input'][]=  array(
        'type' => 'select',
        'label' => $val['name'],
        'name' => $key,
        'class' => 'chosen',
        'hint' => isset( $val['hint'] ) ? $val['hint'] : null,
        'form_group_class' => isset($val['form_group_class']) ? $val['form_group_class'] : false,
        'options' => array(
          'query' => isset($val['fields']) ? $val['fields'] : $fields,
          'id' => isset($val['fields']) ? 'value' : 'name',
          'name' => 'name',
          'value' => 'value'
        )
      );
    }

    foreach( $array_suppliers['input'] as $key => $value ){
      if( !$value['hint'] ){
        unset($array_suppliers['input'][$key]['hint']);
      }
    }
    $array_suppliers['input'][] = array(
      'type' => 'html',
      'name' => '<button type="button" class="btn btn-default more_suppliers">'.$this->l('add supplier').'</button>',
      'hint' => null,
      'html_content' => '<button type="button" class="btn btn-default more_suppliers">'.$this->l('add supplier').'</button>'
    );
    $fields_form[12]['form'] = $array_suppliers;

    $array_customization['input'][] = array(
      'type'    => 'switch',
      'label'   => $this->l('Remove current customization fields'),
      'name'    => 'remove_customization',
      'form_group_class' => 'remove_customization',
      'is_bool' => true,
      'hint'    => '',
      'values'  => array(
        array(
          'id'    => 'display_on',
          'value' => 1,
          'label' => $this->l('Yes')),
        array(
          'id'    => 'display_off',
          'value' => 0,
          'label' => $this->l('No')),
      ),
    );

    $array_customization['input'][] = array(
      'type'    => 'switch',
      'label'   => $this->l('Customization field values in one column'),
      'name'    => 'customization_one_column',
      'is_bool' => true,
      'hint'    => 'Values must be separated by comma',
      'values'  => array(
          array(
              'id'    => 'display_on',
              'value' => 1,
              'label' => $this->l('Yes')),
          array(
              'id'    => 'display_off',
              'value' => 0,
              'label' => $this->l('No')),
      ),
    );

    foreach($this->_has_hint_customization as $key => $val){
      $array_customization['input'][]=  array(
        'type' => 'select',
        'label' => $val['name'],
        'name' => $key,
        'class' => 'chosen',
        'hint' => isset( $val['hint'] ) ? $val['hint'] : null,
        'options' => array(
          'query' => isset($val['fields']) ? $val['fields'] : $fields,
          'id' => isset($val['fields']) ? 'value' : 'name',
          'name' => 'name'
        )
      );
    }


    foreach( $array_customization['input'] as $key => $value ){
      if( !$value['hint'] ){
        unset($array_customization['input'][$key]['hint']);
      }
    }

    $array_customization['input'][] = array(
      'type' => 'html',
      'name' => '<button type="button" class="btn btn-default more_customization">'.$this->l('add field').'</button>',
      'hint' => null,
      'html_content' => '<button type="button" class="btn btn-default more_customization">'.$this->l('add field').'</button>'
    );
    $fields_form[13]['form'] = $array_customization;


    $array_attachments['input'][] = array(
      'type'    => 'switch',
      'label'   => $this->l('Remove current attachments'),
      'name'    => 'remove_attachments',
      'form_group_class' => 'remove_attachments',
      'is_bool' => true,
      'hint'    => '',
      'values'  => array(
        array(
          'id'    => 'display_on',
          'value' => 1,
          'label' => $this->l('Yes')),
        array(
          'id'    => 'display_off',
          'value' => 0,
          'label' => $this->l('No')),
      ),
    );

    $array_attachments['input'][] = array(
      'type'    => 'switch',
      'label'   => $this->l('Import Attachments from single column'),
      'name'    => 'import_attachments_from_single_column',
      'is_bool' => true,
      'hint'    => 'Values must be separated by comma',
      'values'  => array(
        array(
          'id'    => 'display_on',
          'value' => 1,
          'label' => $this->l('Yes')),
        array(
          'id'    => 'display_off',
          'value' => 0,
          'label' => $this->l('No')),
      ),
    );

    foreach($this->_has_hint_attachments as $key => $val){
      $array_attachments['input'][]=  array(
        'type' => 'select',
        'label' => $val['name'],
        'name' => $key,
        'class' => 'chosen',
        'hint' => isset( $val['hint'] ) ? $val['hint'] : null,
        'options' => array(
          'query' => isset($val['fields']) ? $val['fields'] : $fields,
          'id' => isset($val['fields']) ? 'value' : 'name',
          'name' => 'name'
        )
      );
    }


    foreach( $array_attachments['input'] as $key => $value ){
      if( !$value['hint'] ){
        unset($array_attachments['input'][$key]['hint']);
      }
    }

    $array_attachments['input'][] = array(
      'type' => 'html',
      'name' => '<button type="button" class="btn btn-default more_attachments">'.$this->l('add attachment').'</button>',
      'hint' => null,
      'html_content' => '<button type="button" class="btn btn-default more_attachments">'.$this->l('add attachment').'</button>'
    );
    $fields_form[14]['form'] = $array_attachments;

    $fields_form[15]['form'] = array();

    if( $this->_automatic ){
      $array_import['legend'] =  array(
        'title' => $this->l('AUTOMATIC Products Import'),
        'icon' => 'icon-cogs'
      );
    }

    $array_import['input'][] = array(
      'type'  => 'hidden',
      'name'  => 'label_combinations',
    );

    $array_import['input'][] = array(
      'type'  => 'hidden',
      'name'  => 'id_shop',
      'class' => 'id_shop',
    );
    $array_import['input'][] = array(
      'type'  => 'hidden',
      'name'  => 'id_lang',
      'class' => 'id_lang',
    );
    $array_import['input'][] = array(
      'type'  => 'hidden',
      'name'  => 'id_shop_group',
      'class' => 'id_shop_group',
    );
    $array_import['input'][] = array(
      'type'  => 'hidden',
      'name'  => 'combinations_more',
      'class' => 'combinations_more',
    );
    $array_import['input'][] = array(
      'type'  => 'hidden',
      'name'  => 'features_more',
      'class' => 'features_more',
    );
    $array_import['input'][] = array(
      'type'  => 'hidden',
      'name'  => 'customization_more',
      'class' => 'customization_more',
    );
    $array_import['input'][] = array(
      'type'  => 'hidden',
      'name'  => 'field_images',
      'class' => 'field_images',
    );
    $array_import['input'][] = array(
      'type'  => 'hidden',
      'name'  => 'attachments_more',
      'class' => 'attachments_more',
    );
    $array_import['input'][] = array(
      'type'  => 'hidden',
      'name'  => 'discount_more',
      'class' => 'discount_more',
    );
    $array_import['input'][] = array(
      'type'  => 'hidden',
      'name'  => 'suppliers_more',
      'class' => 'suppliers_more',
    );
    $array_import['input'][] = array(
      'type'  => 'hidden',
      'name'  => 'key_settings',
      'class' => 'key_settings',
    );
    $array_import['input'][] = array(
      'type'  => 'hidden',
      'name'  => 'location_href',
      'class' => 'location_href',
    );
    $array_import['input'][] = array(
      'type'  => 'hidden',
      'name'  => 'products_import_token',
      'class' => 'products_import_token',
    );

    $array_import['input'][] = array(
      'type'  => 'hidden',
      'name'  => 'settings_save',
      'class' => 'settings_save',
    );

    $automaticLink = false;
    $array_automatic = array();
    if(Tools::getValue('module_tab') == 'step_2' && Tools::getValue('save') !== false){
      $autoSettings = Tools::unserialize(Configuration::get('GOMAKOIL_IMPORT_PRODUCTS_' . Tools::getValue('save'), null, Context::getContext()->shop->id_shop_group, Context::getContext()->shop->id));
      if($autoSettings['base_settings']['feed_source'] == 'file_url'
        || $autoSettings['base_settings']['feed_source'] == 'ftp'
        || $autoSettings['base_settings']['feed_source'] == 'sftp')
      {
        $automaticLink = Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.basename(_PS_MODULE_DIR_).'/simpleimportproduct/automatic_import.php?settings='.Tools::getValue('save').'&id_shop_group='.Context::getContext()->shop->id_shop_group.'&id_shop='.Context::getContext()->shop->id.'&id_lang='.$autoSettings['base_settings']['id_lang'].'&secure_key='.Configuration::getGlobalValue('GOMAKOIL_IMPORT_TASKS_KEY');
      }
    }

    if( $automaticLink ){
//      $description = '<p>You must save this settings before enable automatic import.</p>';
//      if( Tools::getValue('save') ){
//        $savedSettings = Configuration::get('GOMAKOIL_IMPORT_PRODUCTS_' . Tools::getValue('save'), false, Context::getContext()->shop->id_shop_group, Context::getContext()->shop->id);
//        $savedSettings = Tools::unSerialize($savedSettings);
//        $savedSettings = $savedSettings['base_settings'];

//        $description = '<p>You can place the following URL in your crontab file, or you can click it yourself regularly</p>';
//        $description .= '<p><strong><a href="'.Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.basename(_PS_MODULE_DIR_).'/simpleimportproduct/automatic_import.php?settings='.Tools::getValue('save').'&id_shop_group='.Context::getContext()->shop->id_shop_group.'&id_shop='.Context::getContext()->shop->id.'&id_lang='.$savedSettings['id_lang'].'&secure_key='.Configuration::getGlobalValue('GOMAKOIL_IMPORT_TASKS_KEY').'" onclick="return !window.open($(this).attr(\'href\'));">'.Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.basename(_PS_MODULE_DIR_).'/simpleimportproduct/automatic_import.php?settings='.Tools::getValue('save').'&id_shop_group='.Context::getContext()->shop->id_shop_group.'&id_shop='.Context::getContext()->shop->id.'&id_lang='.$savedSettings['id_lang'].'&secure_key='.Configuration::getGlobalValue('GOMAKOIL_IMPORT_TASKS_KEY').'</a></strong></p>';
//      }

//      $array_automatic['input'][] =  array(
//        'type' => 'html',
//        'name' => $description,
//        'form_group_class' => 'auto_description',
//      );

//      $array_automatic['input'][] =      array(
//        'type'  => 'textarea',
//        'label' => $this->l('Emails For Products Import Report'),
//        'name'  => 'notification_emails',
//        'class' => 'notification_emails',
//        'hint'  => 'Each email in per line',
//        'form_group_class' => 'auto_notif',
//      );

      $description = $this->l('To execute import in automatic mode add the following link in your cron tasks manager:');
      $description .= '<br><br><ul class="list-unstyled"><li><code>'.$automaticLink.'</code></li></ul>';
      $array_automatic['description'] = $description;
    }

      $array_import['input'][] = array(
        'type' => 'html',
        'name' => '<button type="button" class="btn btn-default button_import">'.$this->l('Import').'</button>',
        'hint' => null,
        'form_group_class' => 'form_import_button',
        'html_content' => '<div class="button_import">'.$this->l('Import').'</div><div class="save_all_settings">'.$this->l('Save').'</div>'
      );

    $fields_form[16]['form'] = $array_automatic;
    $fields_form[17]['form'] = $array_import;

    $helper = new HelperForm();
    $helper->module = $this;
    $helper->name_controller = $this->name;
    $helper->token = Tools::getAdminTokenLite('AdminModules');
    $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name.'&id_employee'.Context::getContext()->employee->id;
    $helper->default_form_language = $default_lang;
    $helper->allow_employee_form_lang = $default_lang;
    $helper->title = $this->displayName;
    $helper->show_toolbar = true;        // false -> remove toolbar
    $helper->toolbar_scroll = true;      // yes - > Toolbar is always visible on the top of the screen.
    $helper->submit_action = 'saveSubmitImport';

    $helper->toolbar_btn = array(
      'save' =>
      array(
        'desc' => $this->l('Save'),
        'href' => AdminController::$currentIndex.'&configure='.$this->name.'&save'.$this->name.
          '&token='.Tools::getAdminTokenLite('AdminModules'),
      ),
      'back' => array(
        'href' => AdminController::$currentIndex.'&token='.Tools::getAdminTokenLite('AdminModules'),
        'desc' => $this->l('Back to list')
      )
    );

    $helper->fields_value['label_combinations'] =  $this->l('Combination');

    $helper->fields_value['format_file'] = 'xlsx';
    $helper->fields_value['id_lang'] = Context::getContext()->language->id;
    $helper->fields_value['id_shop'] = Context::getContext()->shop->id;
    $helper->fields_value['id_shop_group'] = Context::getContext()->shop->id_shop_group;
    $helper->fields_value['location_href'] = AdminController::$currentIndex.'&token='.Tools::getAdminTokenLite('AdminModules').'&configure=simpleimportproduct';
    $helper->fields_value['products_import_token'] = Tools::getAdminTokenLite('AdminProductsimport');
    $helper->fields_value['settings_save'] = $config['import_settings_name'];


    if(Tools::getValue('module_tab') == 'step_2' && Tools::getValue('save') !== false){
      $save = Tools::unserialize(Configuration::get('GOMAKOIL_IMPORT_PRODUCTS_'.Tools::getValue('save') ,null,Context::getContext()->shop->id_shop_group,Context::getContext()->shop->id));
//      $save = $save[Tools::getValue('save')];


      $helper->fields_value['key_settings'] = Tools::getValue('save');
      foreach($save['base_field'] as $key => $value){
        $helper->fields_value[$key] = $value;
      }

      if(isset($save['import_from_suppliers']) && $save['import_from_suppliers']){
        foreach( $save['import_from_suppliers'] as $from_supplier ){
          $helper->fields_value['import_from_suppliers_'.$from_supplier] = true;
        }
      }

      if(isset($save['import_from_brands']) && $save['import_from_brands']){
        foreach( $save['import_from_brands'] as $from_brand ){
          $helper->fields_value['import_from_brands_'.$from_brand] = true;
        }
      }

      if(isset($save['field_suppliers']) && $save['field_suppliers']){

        if(count($save['field_suppliers']) > 1){
          $helper->fields_value['suppliers_more'] = 1;
        }
        else{
          $helper->fields_value['suppliers_more'] = 0;
        }

        foreach($save['field_suppliers'][0] as $key => $value){
          $helper->fields_value[$key] = $value;
        }
      }
      else{
        $helper->fields_value['suppliers_more'] = 0;
        foreach($this->_has_hint_suppliers as $key => $value){
          $helper->fields_value[$key] = 'no';
        }
        $helper->fields_value['supplier_method'] = 'supplier_name_method';
      }

      if($save['field_combinations']){
        $helper->fields_value['remove_combinations'] = $save['field_combinations'][0]['remove_combinations'];
        if(count($save['field_combinations']) > 1){
          $helper->fields_value['combinations_more'] = 1;
        }
        else{
          $helper->fields_value['combinations_more'] = 0;
        }
        foreach($save['field_combinations'][0] as $key => $value){
          $helper->fields_value[$key] = $value;
        }
      }
      else{
        $helper->fields_value['combinations_more'] = 0;
        $helper->fields_value['remove_combinations'] = 1;
        foreach($this->_has_hint_combinations as $key => $value){
          $helper->fields_value[$key] = 'no';
        }
      }
      if($save['field_discount']){
        $helper->fields_value['has_discount'] = 1;
        $helper->fields_value['remove_specific_prices'] = $save['field_discount'][0]['remove_specific_prices'];

        if(count($save['field_discount']) > 1){
          $helper->fields_value['discount_more'] = 1;
        }
        else{
          $helper->fields_value['discount_more'] = 0;
        }
        foreach($save['field_discount'][0] as $key => $value){
          $helper->fields_value[$key] = $value;
        }
      }
      else{
        $helper->fields_value['remove_specific_prices'] = 1;
        $helper->fields_value['has_discount'] = 0;
        $helper->fields_value['specific_prices_for'] = 0;
        foreach($this->_has_hint_discount as $key => $value){
          $helper->fields_value[$key] = 'no';
        }
        $helper->fields_value['discount_more'] = 0;
      }
      if($save['field_featured']){
        $helper->fields_value['remove_features'] = $save['field_featured'][0]['remove_features'];
        if(count($save['field_featured']) > 1){
          $helper->fields_value['features_more'] = 1;
        }
        else{
          $helper->fields_value['features_more'] = 0;
        }
        foreach($save['field_featured'][0] as $key => $value){
          $helper->fields_value[$key] = $value;
          if( $key == 'features_name_manually' ){
            $helper->fields_value[$key] = html_entity_decode($value);
          }
        }
      }
      else{
        $helper->fields_value['remove_features'] = 1;
        foreach($this->_has_hint_featured as $key => $value){
          $helper->fields_value[$key] = 'no';
          if( $key == 'features_name_manually' ){
            $helper->fields_value[$key] = '';
          }
        }
        $helper->fields_value['features_more'] = 0;
      }

      if($save['field_customization']){
        $helper->fields_value['remove_customization'] = $save['field_customization'][0]['remove_customization'];
        $helper->fields_value['customization_one_column'] = $save['field_customization'][0]['customization_one_column'];

        if(count($save['field_customization']) > 1){
          $helper->fields_value['customization_more'] = 1;
        }
        else{
          $helper->fields_value['customization_more'] = 0;
        }
        foreach($save['field_customization'][0] as $key => $value){
          $helper->fields_value[$key] = $value;
        }
      }
      else{
        $helper->fields_value['remove_customization'] = 1;
        $helper->fields_value['customization_one_column'] = 0;

        foreach($this->_has_hint_customization as $key => $value){
          $helper->fields_value[$key] = 'no';
        }
        $helper->fields_value['customization_more'] = 0;
      }

      if($save['field_images']){
        $helper->fields_value['remove_images'] = $save['field_images'][0]['remove_images'];
        $helper->fields_value['generate_thumbnails'] = $save['field_images'][0]['generate_thumbnails'];
        $helper->fields_value['no_product_images'] = $save['field_images'][0]['no_product_images'];
        $helper->fields_value['images_stream'] = $save['field_images'][0]['images_stream'];
        if(count($save['field_images']) > 1){
          $helper->fields_value['field_images'] = 1;
        }
        else{
          $helper->fields_value['field_images'] = 0;
        }
        foreach($save['field_images'][0] as $key => $value){
          $helper->fields_value[$key] = $value;
        }
      }
      else{
        $helper->fields_value['remove_images'] = 1;
        $helper->fields_value['generate_thumbnails'] = 1;
        $helper->fields_value['no_product_images'] = 1;
        $helper->fields_value['images_stream'] = 0;
        foreach($this->_has_hint_customization as $key => $value){
          $helper->fields_value[$key] = 'no';
        }
        $helper->fields_value['field_images'] = 0;
      }

      if($save['field_attachments']){
        $helper->fields_value['remove_attachments'] = $save['field_attachments'][0]['remove_attachments'];
        $helper->fields_value['import_attachments_from_single_column'] = $save['field_attachments'][0]['import_attachments_from_single_column'];

        if(count($save['field_attachments']) > 1){
          $helper->fields_value['attachments_more'] = 1;
        }
        else{
          $helper->fields_value['attachments_more'] = 0;
        }
        foreach($save['field_attachments'][0] as $key => $value){
          $helper->fields_value[$key] = $value;
        }
      }
      else{
        $helper->fields_value['remove_attachments'] = 1;
        $helper->fields_value['import_attachments_from_single_column'] = 0;

        foreach($this->_has_hint_attachments as $key => $value){
          $helper->fields_value[$key] = 'no';
        }
        $helper->fields_value['attachments_more'] = 0;
      }

      if($save['field_accessories']){
        $helper->fields_value['has_accessories'] = 1;
        $helper->fields_value['remove_accessories'] = $save['field_accessories']['remove_accessories'];
        foreach($save['field_accessories'] as $key => $value){
          $helper->fields_value[$key] = $value;
        }
      }
      else{
        $helper->fields_value['remove_accessories'] = 1;
        $helper->fields_value['has_accessories'] = 0;
        foreach($this->_has_hint_accessories as $key => $value){
          $helper->fields_value[$key] = 'no';
        }
      }

      if($save['field_pack_products']){
        $helper->fields_value['remove_pack_products'] = $save['field_pack_products']['remove_pack_products'];
        foreach($save['field_pack_products'] as $key => $value){
          $helper->fields_value[$key] = $value;
        }
      }
      else{
        $helper->fields_value['remove_pack_products'] = 1;
        foreach($this->_has_hint_pack as $key => $value){
          $helper->fields_value[$key] = 'no';
        }
      }

      $helper->fields_value['notification_emails'] = $save['notification_emails'];
    }
    else{
      $helper->fields_value['key_settings'] = false;
      $helper->fields_value['has_discount'] = 0;
      $helper->fields_value['has_accessories'] = 0;
      $helper->fields_value['notification_emails'] = '';
      $helper->fields_value['combinations_more'] = 0;
      $helper->fields_value['features_more'] = 0;
      $helper->fields_value['field_images'] = 0;
      $helper->fields_value['customization_more'] = 0;
      $helper->fields_value['images_more'] = 0;
      $helper->fields_value['attachments_more'] = 0;
      $helper->fields_value['discount_more'] = 0;
      $helper->fields_value['remove_specific_prices'] = 1;
      $helper->fields_value['specific_prices_for'] = 0;
      $helper->fields_value['remove_features'] = 0;
      $helper->fields_value['remove_images'] = 0;
      $helper->fields_value['images_stream'] = 0;
      $helper->fields_value['generate_thumbnails'] = 1;
      $helper->fields_value['no_product_images'] = 1;
      $helper->fields_value['remove_customization'] = 0;
      $helper->fields_value['customization_one_column'] = 0;
      $helper->fields_value['remove_attachments'] = 0;
      $helper->fields_value['import_attachments_from_single_column'] = 0;
      $helper->fields_value['remove_combinations'] = 0;
      $helper->fields_value['remove_accessories'] = 1;
      $helper->fields_value['remove_pack_products'] = 0;

      $helper->fields_value['supplier_method'] = 'supplier_name_method';
      $helper->fields_value['supplier_default'] = 'no';
      $helper->fields_value['supplier_default_id'] = 'no';
      $helper->fields_value['existing_supplier_default'] = 'no';
      $helper->fields_value['existing_supplier'] = 'no';
      $helper->fields_value['supplier'] = 'no';
      $helper->fields_value['supplier_ids'] = 'no';
      $helper->fields_value['supplier_reference'] = 'no';
      $helper->fields_value['supplier_price'] = 'no';
      $helper->fields_value['supplier_currency'] = 'no';
      $helper->fields_value['suppliers_more'] = 0;
      $helper->fields_value['combination_key'] = 'attributes';



      foreach($has_hint as $key => $val){
        $helper->fields_value[$key] = 'no';
      }
      foreach($this->_has_hint_combinations as $key => $val){
        $helper->fields_value[$key] = 'no';
      }
      foreach($this->_has_hint_discount as $key => $val){
        $helper->fields_value[$key] = 'no';
      }
      foreach($this->_has_hint_featured as $key => $val){
        $helper->fields_value[$key] = 'no';
        if( $key == 'features_name_manually' ){
          $helper->fields_value[$key] = '';
        }
      }
      foreach($this->_has_hint_customization as $key => $val){
        $helper->fields_value[$key] = 'no';
      }
      foreach($this->_has_hint_attachments as $key => $val){
        $helper->fields_value[$key] = 'no';
      }
      foreach($this->_has_hint_accessories as $key => $val){
        $helper->fields_value[$key] = 'no';
      }
      foreach($this->_has_hint_pack as $key => $val){
        $helper->fields_value[$key] = 'no';
      }
      foreach($this->_has_hint_images as $key => $val){
        $helper->fields_value[$key] = 'no';
      }

      $helper->fields_value['remove_categories'] = 1;
      $helper->fields_value['remove_suppliers'] = 1;
      $helper->fields_value['new_products'] = 'add';
      $helper->fields_value['existing_products'] = 'update';
      $helper->fields_value['file_products'] = 'ignore';
      $helper->fields_value['file_store_products'] = 'ignore';
    }


    $this->_html .= $helper->generateForm($fields_form);
  }

  public function encodeHeaders( $fields )
  {
    if( !$fields ){
      return $fields;
    }
    array_walk_recursive($fields, 'Simpleimportproduct::_encodeHeaders');

    return $fields;
  }

  private function _encodeHeaders(&$item)
  {
    if( strpos( $item, '<' ) !== false || strpos( $item, '>' ) !== false ){
      $item = htmlentities($item);
    }
    else{
      $item = htmlentities(strip_tags($item));
    }
  }

  public function moreFeatures(){
    $config = Tools::unserialize(Configuration::get('GOMAKOIL_CONFIG_IMPORT_PRODUCTS',null,Context::getContext()->shop->id_shop_group,Context::getContext()->shop->id));
    $fields = $config['name_fields_upload'];

    $this->context->smarty->assign(
      array(
        'has_hint_featured'  => $this->_has_hint_featured,
        'default_fields'     => $fields,
      )
    );
    return $this->display(__FILE__, 'views/templates/hook/moreFeatured.tpl');
  }

  public function moreCustomization(){
    $customizationTypes = Tools::unserialize(Configuration::get('GOMAKOIL_CONFIG_IMPORT_PRODUCTS',null,Context::getContext()->shop->id_shop_group,Context::getContext()->shop->id));
    $customizationTypes = $customizationTypes['name_fields_upload'];
    $fields = $customizationTypes;

    foreach( $customizationTypes as $key=>$type ){
      $customizationTypes[$key]['value'] = $type['name'];
    }

    $noField = $customizationTypes[0];
    $customizationRequired = $customizationTypes;

    $customizationTypes[0] =     array(
      'name'  => $this->l('File'),
      'value' => 'file'
    );

    array_unshift($customizationTypes, array(
      'name'  => $this->l('Text'),
      'value' => 'text'
    ) );

    array_unshift( $customizationTypes, $noField );

    $customizationRequired[0] = array(
      'name'  => $this->l('No'),
      'value' => '0'
    );

    array_unshift($customizationRequired, array(
      'name'  => $this->l('Yes'),
      'value' => '1'
    ) );

    array_unshift( $customizationRequired, $noField );

    $this->context->smarty->assign(
      array(
        'has_hint_customization' => $this->_has_hint_customization,
        'default_fields'         => $fields,
        'type_fields'            => $customizationTypes,
        'required_fields'        => $customizationRequired,
      )
    );
    return $this->display(__FILE__, 'views/templates/hook/moreCustomization.tpl');
  }

  public function cleanCsvLine( $data )
  {
    if( is_array($data) ){
      foreach( $data as $key => $line ){
        $data[$key] = preg_replace('/\s+/', ' ', trim($line));
      }
    }
    else{
      $data = preg_replace('/\s+/', ' ', trim($data));
    }

    return $data;
  }

  public function moreDiscount($key){
    $config = Tools::unserialize(Configuration::get('GOMAKOIL_CONFIG_IMPORT_PRODUCTS',null,Context::getContext()->shop->id_shop_group,Context::getContext()->shop->id));
    $fields = $config['name_fields_upload'];
    $count_comb = 1;
    if($key){
      $save = Tools::unserialize(Configuration::get('GOMAKOIL_IMPORT_PRODUCTS_'.$key,null,Context::getContext()->shop->id_shop_group,Context::getContext()->shop->id));
      $count_comb = count($save['field_combinations']);
    }


    $select = array(
      array(
        'id' => '0',
        'name' => $this->l('Whole product'),
      ),
    );

    for ($x=0; $x++<$count_comb;){
      $select[] = array(
        'id' => $x,
        'name' => $this->l('Combination ').$x,
      );
    }

    $this->context->smarty->assign(
      array(
        'has_hint_discount' => $this->_has_hint_discount,
        'default_fields'    => $fields,
        'select'            => $select,
      )
    );
    return $this->display(__FILE__, 'views/templates/hook/moreDiscount.tpl');
  }



  public function addDiscount($key_settings){
    $config = Tools::unserialize(Configuration::get('GOMAKOIL_CONFIG_IMPORT_PRODUCTS',null,Context::getContext()->shop->id_shop_group,Context::getContext()->shop->id));
    $fields = $config['name_fields_upload'];
    $save = Tools::unserialize(Configuration::get('GOMAKOIL_IMPORT_PRODUCTS_'.$key_settings,null,Context::getContext()->shop->id_shop_group,Context::getContext()->shop->id));


    $count_comb = 1;
    $count_comb = count($save['field_combinations']);


    $select = array(
      array(
        'id' => '0',
        'name' => $this->l('Whole product'),
      ),
    );

    for ($x=0; $x++<$count_comb;){
      $select[] = array(
        'id' => $x,
        'name' => $this->l('Combination ').$x,
      );
    }


    $this->context->smarty->assign(
      array(
        'has_hint_discount' => $this->_has_hint_discount,
        'save'              => $save['field_discount'],
        'default_fields'    => $fields,
        'select'            => $select,
      )
    );
    return $this->display(__FILE__, 'views/templates/hook/addDiscount.tpl');
  }

  public function moreImages(){
    $config = Tools::unserialize(Configuration::get('GOMAKOIL_CONFIG_IMPORT_PRODUCTS',null,Context::getContext()->shop->id_shop_group,Context::getContext()->shop->id));
    $fields = $config['name_fields_upload'];

    $this->context->smarty->assign(
      array(
        'has_hint_images'  => $this->_has_hint_images,
        'fields'     => $fields,
      )
    );

    return $this->display(__FILE__, 'views/templates/hook/moreImages.tpl');
  }

  public function moreAttachments(){
    $config = Tools::unserialize(Configuration::get('GOMAKOIL_CONFIG_IMPORT_PRODUCTS',null,Context::getContext()->shop->id_shop_group,Context::getContext()->shop->id));
    $fields = $config['name_fields_upload'];

    $this->context->smarty->assign(
      array(
        'has_hint_attachments'  => $this->_has_hint_attachments,
        'fields'     => $fields,
      )
    );
    return $this->display(__FILE__, 'views/templates/hook/moreAttachments.tpl');
  }

  public function moreSubcategory($hidden_count_subcategory){
    $config = Tools::unserialize(Configuration::get('GOMAKOIL_CONFIG_IMPORT_PRODUCTS',null,Context::getContext()->shop->id_shop_group,Context::getContext()->shop->id));
    $fields = $config['name_fields_upload'];
    $this->context->smarty->assign(
      array(
        'fields'                   => $fields,
        'hidden_count_subcategory' => $hidden_count_subcategory,
      )
    );
    return $this->display(__FILE__, 'views/templates/hook/moreSubcategory.tpl');
  }

  public function moreCategory($hidden_count_category){
    $config = Tools::unserialize(Configuration::get('GOMAKOIL_CONFIG_IMPORT_PRODUCTS',null,Context::getContext()->shop->id_shop_group,Context::getContext()->shop->id));
    $fields = $config['name_fields_upload'];
    $this->context->smarty->assign(
      array(
        'fields'                 => $fields,
        'hidden_count_category'  => $hidden_count_category,
      )
    );
    return $this->display(__FILE__, 'views/templates/hook/moreCategory.tpl');
  }

  public function moreImagesCombination($hidden_count_images){
    $config = Tools::unserialize(Configuration::get('GOMAKOIL_CONFIG_IMPORT_PRODUCTS',null,Context::getContext()->shop->id_shop_group,Context::getContext()->shop->id));
    $fields = $config['name_fields_upload'];
    $this->context->smarty->assign(
      array(
        'fields'                 => $fields,
        'hidden_count_images'  => $hidden_count_images,
      )
    );
    return $this->display(__FILE__, 'views/templates/hook/moreImagesCombination.tpl');
  }

  public function addCombinations($key_settings){


    $config = Tools::unserialize(Configuration::get('GOMAKOIL_CONFIG_IMPORT_PRODUCTS',null,Context::getContext()->shop->id_shop_group,Context::getContext()->shop->id));
    $fields = $config['name_fields_upload'];
    $save = Tools::unserialize(Configuration::get('GOMAKOIL_IMPORT_PRODUCTS_'.$key_settings,null,Context::getContext()->shop->id_shop_group,Context::getContext()->shop->id));




    $has_hint_suppliers = array(
      'supplier'            => array(
        'name'             => $this->l('Supplier name'),
        'hint'             => $this->l('Supplier name'),
        'form_group_class' => ' full_combination supplier_name_combination',
      ),
      'supplier_ids'        => array(
        'name'             => $this->l('Supplier Id'),
        'hint'             => $this->l('Supplier Id'),
        'form_group_class' => ' full_combination supplier_id_combination',
      ),
      'existing_supplier'            => array(
        'name'             => $this->l('Supplier'),
        'hint'             => $this->l('Existing Supplier name'),
        'form_group_class' => 'existing_supplier_combination suppliers',
        'fields'           => $this->_getPreSavedFields( $fields, 'supplier' ),
      ),
      'supplier_reference'        => array(
        'name'             => $this->l('Supplier reference'),
        'hint'             => $this->l('Supplier reference.'),
        'form_group_class' => ' full_combination',
      ),
      'supplier_price'        => array(
        'name'             => $this->l('Supplier price'),
        'hint'             => $this->l('Supplier price (TAX EXCL.)'),
        'form_group_class' => 'supplier_price_method full_combination',
      ),
      'supplier_currency'        => array(
        'name'             => $this->l('Supplier currency'),
        'hint'             => $this->l('Supplier currency'),
        'form_group_class' => ' full_combination',
      ),
    );



    $type = array(
      array(
        'id' => 'supplier_name_method',
        'value' => 'supplier_name_method',
        'name' => $this->l('Supplier name'),
        'hint'             => $this->l('Supplier name'),
      ),
      array(
        'id' => 'supplier_ids_method',
        'value' => 'supplier_ids_method',
        'name' => $this->l('Supplier ID'),
        'hint'             => $this->l('Supplier ID'),
      ),
      array(
        'id' => 'existing_supplier_method',
        'value' => 'existing_supplier_method',
        'name' => $this->l('Existing Supplier')
      ),
    );

    $importType = array(
      array(
        'name'  => 'Combination in one field',
        'value' => 'one_field_combinations',
        'hint'             => $this->l('Combination in one field'),
      ),
      array(
        'name'  => 'Each attribute and value in separate field',
        'value' => 'single_field_value',
        'hint'             => $this->l('Each attribute and value in separate field'),
      ),
      array(
        'name'  => 'Each combination in a separate row in the file',
        'value' => 'separate_combination_row',
        'hint'             => $this->l('Each combination in a separate row in the file'),
      ),
      array(
        'name'  => 'Generate combinations from attribute values',
        'value' => 'separated_field_value',
        'hint'             => $this->l('Generate combinations from attribute values'),
      )
    );

    $quantityMethod = array(
      array(
        'value' => 'override',
        'name' => $this->l('Override')
      ),
      array(
        'value' => 'add',
        'name' => $this->l('Add')
      ),
      array(
        'value' => 'deduct',
        'name' => $this->l('Deduct')
      )
    );



    $this->context->smarty->assign(
      array(
        'has_hint_combinations' => $this->_has_hint_combinations,
        'quantity_method'       => $quantityMethod,
        'save'                  => $save['field_combinations'],
        'default_fields'        => $fields,
        'import_type'           => $importType,
        'type'                  => $type,
        'has_hint_suppliers'    => $has_hint_suppliers,
      )
    );
    return $this->display(__FILE__, 'views/templates/hook/addCombinations.tpl');
  }


  public function moreCombination(){
    $config = Tools::unserialize(Configuration::get('GOMAKOIL_CONFIG_IMPORT_PRODUCTS',null,Context::getContext()->shop->id_shop_group,Context::getContext()->shop->id));
    $fields = $config['name_fields_upload'];

    $tpl = $this->moreCombinationSuppliers(0, 1);

    $type = array(
      array(
        'id' => 'supplier_name_method',
        'value' => 'supplier_name_method',
        'name' => $this->l('Supplier name')
      ),
      array(
        'id' => 'supplier_ids_method',
        'value' => 'supplier_ids_method',
        'name' => $this->l('Supplier ID')
      ),
      array(
        'id' => 'existing_supplier_method',
        'value' => 'existing_supplier_method',
        'name' => $this->l('Existing Supplier')
      ),
    );

    $importType = array(
      array(
        'name'  => 'Combination in one field',
        'value' => 'one_field_combinations',
        'hint'             => $this->l('Combination in one field'),
      ),
      array(
        'name'  => 'Each attribute and value in separate field',
        'value' => 'single_field_value',
        'hint'             => $this->l('Each attribute and value in separate field'),
      ),
      array(
        'name'  => 'Each combination in a separate row in the file',
        'value' => 'separate_combination_row',
        'hint'             => $this->l('Each combination in a separate row in the file'),
      ),
      array(
        'name'  => 'Generate combinations from attribute values',
        'value' => 'separated_field_value',
        'hint'             => $this->l('Generate combinations from attribute values'),
      )
    );

    $quantityMethod = array(
      array(
        'value' => 'override',
        'name' => $this->l('Override')
      ),
      array(
        'value' => 'add',
        'name' => $this->l('Add')
      ),
      array(
        'value' => 'deduct',
        'name' => $this->l('Deduct')
      )
    );

    $this->context->smarty->assign(
      array(
        'has_hint_combinations' => $this->_has_hint_combinations,
        'default_fields'        => $fields,
        'import_type'           => $importType,
        'quantity_method'       => $quantityMethod,
        'type'                  => $type,
        'tpl'                   => $tpl,
      )
    );
    return $this->display(__FILE__, 'views/templates/hook/moreCombination.tpl');
  }


  public function addFeatures($key_settings){
    $config = Tools::unserialize(Configuration::get('GOMAKOIL_CONFIG_IMPORT_PRODUCTS',null,Context::getContext()->shop->id_shop_group,Context::getContext()->shop->id));
    $fields = $config['name_fields_upload'];
    $save = Tools::unserialize(Configuration::get('GOMAKOIL_IMPORT_PRODUCTS_'.$key_settings,null,Context::getContext()->shop->id_shop_group,Context::getContext()->shop->id));

    $this->context->smarty->assign(
      array(
        'has_hint_featured'  => $this->_has_hint_featured,
        'save'               => $save['field_featured'],
        'default_fields'     => $fields,
      )
    );
    return $this->display(__FILE__, 'views/templates/hook/addFeatures.tpl');
  }

  public function addAttachments($key_settings){
    $config = Tools::unserialize(Configuration::get('GOMAKOIL_CONFIG_IMPORT_PRODUCTS',null,Context::getContext()->shop->id_shop_group,Context::getContext()->shop->id));
    $fields = $config['name_fields_upload'];
    $save = Tools::unserialize(Configuration::get('GOMAKOIL_IMPORT_PRODUCTS_'.$key_settings,null,Context::getContext()->shop->id_shop_group,Context::getContext()->shop->id));

    $this->context->smarty->assign(
      array(
        'has_hint_attachments' => $this->_has_hint_attachments,
        'fields'               => $fields,
        'save'                 => $save['field_attachments'],
      )
    );
    return $this->display(__FILE__, 'views/templates/hook/addAttachments.tpl');
  }

  public function addCustomization($key_settings){
    $config = Tools::unserialize(Configuration::get('GOMAKOIL_CONFIG_IMPORT_PRODUCTS',null,Context::getContext()->shop->id_shop_group,Context::getContext()->shop->id));
    $fields = $config['name_fields_upload'];
    $save = Tools::unserialize(Configuration::get('GOMAKOIL_IMPORT_PRODUCTS_'.$key_settings,null,Context::getContext()->shop->id_shop_group,Context::getContext()->shop->id));

    $customizationTypes = Tools::unserialize(Configuration::get('GOMAKOIL_CONFIG_IMPORT_PRODUCTS',null,Context::getContext()->shop->id_shop_group,Context::getContext()->shop->id));
    $customizationTypes = $customizationTypes['name_fields_upload'];
    $fields = $customizationTypes;

    foreach( $customizationTypes as $key=>$type ){
      $customizationTypes[$key]['value'] = $type['name'];
    }

    $noField = $customizationTypes[0];
    $customizationRequired = $customizationTypes;

    $customizationTypes[0] =     array(
      'name'  => $this->l('File'),
      'value' => 'file'
    );

    array_unshift($customizationTypes, array(
      'name'  => $this->l('Text'),
      'value' => 'text'
    ) );

    array_unshift( $customizationTypes, $noField );

    $customizationRequired[0] = array(
      'name'  => $this->l('No'),
      'value' => '0'
    );

    array_unshift($customizationRequired, array(
      'name'  => $this->l('Yes'),
      'value' => '1'
    ) );

    array_unshift( $customizationRequired, $noField );

    $this->context->smarty->assign(
      array(
        'has_hint_customization' => $this->_has_hint_customization,
        'save'                   => $save['field_customization'],
        'default_fields'         => $fields,
        'type_fields'            => $customizationTypes,
        'required_fields'        => $customizationRequired,
      )
    );
    return $this->display(__FILE__, 'views/templates/hook/addCustomization.tpl');
  }



  public function addImages($key_settings){
    $config = Tools::unserialize(Configuration::get('GOMAKOIL_CONFIG_IMPORT_PRODUCTS',null,Context::getContext()->shop->id_shop_group,Context::getContext()->shop->id));
    $fields = $config['name_fields_upload'];
    $save = Tools::unserialize(Configuration::get('GOMAKOIL_IMPORT_PRODUCTS_'.$key_settings,null,Context::getContext()->shop->id_shop_group,Context::getContext()->shop->id));

    $this->context->smarty->assign(
      array(
        'has_hint_images'  => $this->_has_hint_images,
        'save'               => $save['field_images'],
        'fields'     => $fields,
      )
    );
    return $this->display(__FILE__, 'views/templates/hook/addImages.tpl');
  }

  public function addCategories($key_settings){
    $config = Tools::unserialize(Configuration::get('GOMAKOIL_CONFIG_IMPORT_PRODUCTS',null,Context::getContext()->shop->id_shop_group,Context::getContext()->shop->id));
    $fields = $config['name_fields_upload'];
    $save = Tools::unserialize(Configuration::get('GOMAKOIL_IMPORT_PRODUCTS_'.$key_settings,null,Context::getContext()->shop->id_shop_group,Context::getContext()->shop->id));
    $this->context->smarty->assign(
      array(
        'has_hint_featured'      => $this->_has_hint_featured,
        'save'                   => $save['field_category'],
        'fields'                 => $fields,
      )
    );
    return $this->display(__FILE__, 'views/templates/hook/addCategories.tpl');
  }

  public function moreSuppliers($key_settings){

    $key_settings = (int)$key_settings;
    $config = Tools::unserialize(Configuration::get('GOMAKOIL_CONFIG_IMPORT_PRODUCTS',null,Context::getContext()->shop->id_shop_group,Context::getContext()->shop->id));
    $fields = $config['name_fields_upload'];
    $save = false;
    $type = array(
      array(
        'id' => 'supplier_name_method',
        'value' => 'supplier_name_method',
        'name' => $this->l('Supplier name'),
        'hint' => $this->l('Supplier name'),
      ),
      array(
        'id' => 'supplier_ids_method',
        'value' => 'supplier_ids_method',
        'name' => $this->l('Supplier ID'),
        'hint' => $this->l('Supplier ID'),
      ),
      array(
        'id' => 'existing_supplier_method',
        'value' => 'existing_supplier_method',
        'name' => $this->l('Existing Supplier'),
      ),
    );

    if($key_settings){
      $save_settings = Tools::unserialize(Configuration::get('GOMAKOIL_IMPORT_PRODUCTS_'.$key_settings,null,Context::getContext()->shop->id_shop_group,Context::getContext()->shop->id));
      $save =  $save_settings['field_suppliers'];

      if(count($save)>=1){
        unset($save[0]);
      }
    }

    $has_hint_suppliers = array(
      'supplier'            => array(
        'name'             => $this->l('Supplier name'),
        'hint'             => $this->l('Supplier name'),
        'form_group_class' => 'supplier_name_method suppliers',
      ),
      'supplier_ids'        => array(
        'name'             => $this->l('Supplier Id'),
        'hint'             => $this->l('Supplier Id'),
        'form_group_class' => 'supplier_id_method suppliers',
      ),
      'existing_supplier'            => array(
        'name'             => $this->l('Supplier'),
        'hint'             => $this->l('Existing Supplier name'),
        'form_group_class' => 'existing_supplier_method suppliers',
        'fields'           => $this->_getPreSavedFields( $fields, 'supplier' ),
      ),
      'supplier_reference'        => array(
        'name'             => $this->l('Supplier reference'),
        'hint'             => $this->l('Supplier reference.'),
        'form_group_class' => 'supplier_reference_method suppliers',
      ),
      'supplier_price'        => array(
        'name'             => $this->l('Supplier price'),
        'hint'             => $this->l('Supplier price (TAX EXCL.)'),
        'form_group_class' => 'supplier_price_method suppliers',
      ),
      'supplier_currency'        => array(
        'name'             => $this->l('Supplier currency'),
        'hint'             => $this->l('Supplier currency ID'),
        'form_group_class' => 'supplier_currency_method suppliers',
      ),
    );

    $this->context->smarty->assign(
      array(
        'has_hint_suppliers'  => $has_hint_suppliers,
        'default_fields'     => $fields,
        'type'            => $type,
        'save'            => $save,
      )
    );
    return $this->display(__FILE__, 'views/templates/hook/moreSuppliers.tpl');
  }

  public function moreQuantitySettings()
  {
    $key_settings = false;
    $save = false;
    if(Tools::getValue('module_tab') == 'step_2' && Tools::getValue('save') !== false){
      $key_settings =  Tools::getValue('save');
    }

    if($key_settings) {
      $save_settings = Tools::unserialize(Configuration::get('GOMAKOIL_IMPORT_PRODUCTS_' . $key_settings, null, Context::getContext()->shop->id_shop_group, Context::getContext()->shop->id));
      $save = $save_settings['quantity_settings'];
    }

    $has_hint_quantity_settings = array(
      'quantity_source'            => array(
        'name'             => $this->l('Quantity Source'),
        //        'hint'             => $this->l('Price Field'),
        'form_group_class' => '',
        'type'   => 'select',
        'values' => array(
          'store' => $this->l('Store'),
          'file' => $this->l('File'),
        )
      ),
      'quantity_field'            => array(
        'name'             => $this->l('Quantity Field'),
        //        'hint'             => $this->l('Price Field'),
        'form_group_class' => '',
        'type'   => 'select',
        'values' => array(
          'product_quantity' => $this->l('Product Quantity'),
          'combination_quantity' => $this->l('Combination Quantity'),
        )
      ),
      'condition'        => array(
        'name'             => $this->l('Condition'),
        'type'   => 'select',
        'form_group_class' => '',
        'values' => array(
          '<'               => $this->l('< Less than'),
          Tools::getValue('addQuantityCondition') ? '>' : htmlentities('>') => $this->l('> Greater than'),
          '=='              => $this->l('= Equal'),
          'zero'            => $this->l('Zero'),
          'any'             => $this->l('Any'),
        )
      ),
      'condition_value'        => array(
        'name'             => $this->l('Condition value'),
        'form_group_class' => '',
        'type'   => 'input'
      ),
      'quantity_formula'        => array(
        'name'             => $this->l('Quantity formula'),
        'form_group_class' => '',
        'type'   => 'input'
      ),
    );

    $this->context->smarty->assign(
      array(
        'has_hint_quantity_settings' => $has_hint_quantity_settings,
        'saved_quantity_settings'    => $save,
        'quantity_settings_ajax'     => Tools::getValue('addQuantityCondition', false)
      )
    );
    return $this->display(__FILE__, 'views/templates/hook/quantitySettings.tpl');
  }

  private function _getFieldList()
  {
    $config = Tools::unserialize(Configuration::get('GOMAKOIL_CONFIG_IMPORT_PRODUCTS',null,Context::getContext()->shop->id_shop_group,Context::getContext()->shop->id));
    $fields = $config['name_fields_upload'];

    unset($fields[0]);
    foreach( $fields as $key => $field ){
      if( isset($field['custom']) ){
        unset($fields[$key]);
        continue;
      }
      $fields[$field['name']] = $field['name'];
      unset($fields[$key]);
    }

    $this->context->smarty->assign(
      array(
        'fields' => $fields,
      )
    );

    return $this->display(__FILE__, 'views/templates/hook/fieldList.tpl');
  }

  public function moreFieldSettings()
  {
    $key_settings = false;
    $save = false;
    $config = Tools::unserialize(Configuration::get('GOMAKOIL_CONFIG_IMPORT_PRODUCTS',null,Context::getContext()->shop->id_shop_group,Context::getContext()->shop->id));
    $fields = $config['name_fields_upload'];


//    foreach( $fields as $key => $field ){
//      if( isset($field['custom']) ){
////        unset($fields[$key]);
////        continue;
//      }
//      $fields[$field['name']] = $field['name'];
//      unset($fields[$key]);
//    }

    if(Tools::getValue('module_tab') == 'step_2' && Tools::getValue('save') !== false){
      $key_settings =  Tools::getValue('save');
    }

    if($key_settings) {
      $save_settings = Tools::unserialize(Configuration::get('GOMAKOIL_IMPORT_PRODUCTS_' . $key_settings, null, Context::getContext()->shop->id_shop_group, Context::getContext()->shop->id));
      $save = $save_settings['field_settings'];
    }

    $has_hint_field_settings = array(
      'field'            => array(
        'name'             => $this->l('Field'),
        //        'hint'             => $this->l('Price Field'),
        'form_group_class' => '',
        'type'   => 'select',
        'values' => $fields
      ),
      'condition'        => array(
        'name'             => $this->l('Condition'),
        'type'   => 'select',
        'form_group_class' => '',
        'values' => array(
          '<'     => $this->l('< Less than'),
          Tools::getValue('addFieldCondition') ? '>' : htmlentities('>')     => $this->l('> Greater than'),
          '=='        => $this->l('= Equal'),
          '!='        => $this->l('!= Not Equal'),
          'list'      => $this->l('Comma List (ex: itm1,itm2...)'),
          'not_list'  => $this->l('Not in Comma List (ex: itm1,itm2...)'),
          'empty'     => $this->l('Empty'),
          'not_empty' => $this->l('Not empty'),
          'regex'     => $this->l('regex'),
          'any'       => $this->l('Any'),
        )
      ),
      'condition_value'        => array(
        'name'             => $this->l('Condition value'),
        'form_group_class' => '',
        'type'   => 'input'
      ),
      'new_field'        => array(
        'name'             => $this->l('Custom Field Name'),
        'form_group_class' => '',
        'type'   => 'input'
      ),
      'formula_type'        => array(
        'name'             => $this->l('Formula Type'),
        'form_group_class' => '',
        'type'   => 'select',
        'values' => array(
          'string'     => $this->l('String'),
          'math'       => $this->l('Mathematical Formula'),
          'replace'    => $this->l('Find & Replace'),
          'round'      => $this->l('Round'),
        )
      ),
      'field_formula'        => array(
        'name'             => $this->l('Formula'),
        'form_group_class' => '',
        'type'   => 'input'
      ),
    );

    $this->context->smarty->assign(
      array(
        'has_hint_field_settings' => $has_hint_field_settings,
        'saved_field_settings'    => $save,
        'field_settings_ajax'     => Tools::getValue('addFieldCondition', false)
      )
    );

    return $this->display(__FILE__, 'views/templates/hook/fieldSettings.tpl');

  }

  public function morePriceSettings()
  {
    $key_settings = false;
    $save = false;
    if(Tools::getValue('module_tab') == 'step_2' && Tools::getValue('save') !== false){
      $key_settings =  Tools::getValue('save');
    }

    if($key_settings) {
      $save_settings = Tools::unserialize(Configuration::get('GOMAKOIL_IMPORT_PRODUCTS_' . $key_settings, null, Context::getContext()->shop->id_shop_group, Context::getContext()->shop->id));
      $save = $save_settings['price_settings'];
    }

    $has_hint_price_settings = array(
      'price_source'            => array(
        'name'             => $this->l('Price Source'),
        //        'hint'             => $this->l('Price Field'),
        'form_group_class' => '',
        'type'   => 'select',
        'values' => array(
          'store' => $this->l('Store'),
          'file' => $this->l('File'),
        )
      ),
      'price_field'            => array(
        'name'             => $this->l('Price Field'),
//        'hint'             => $this->l('Price Field'),
        'form_group_class' => '',
        'type'   => 'select',
        'values' => array(
          'wholesale_price' => $this->l('Pre-tax wholesale price'),
          'price' => $this->l('Pre-tax retail price'),
          'tax_price' => $this->l('Retail price with tax'),
          'unit_price' => $this->l('Unit price (tax excl.)'),
          'wholesale_price_combination' => $this->l('Combination Wholesale price'),
          'final_price' => $this->l('Final on price (tax exlc.)'),
          'final_price_with_tax' => $this->l('Final on price (tax incl.)'),
          'impact_price' => $this->l('Impact on price (tax exlc.)'),
          'impact_price_with_tax' => $this->l('Impact on price (tax incl.)'),
        )
      ),
      'condition'        => array(
        'name'             => $this->l('Condition'),
        'type'   => 'select',
        'form_group_class' => '',
        'values' => array(
          '<' => $this->l('< Less than'),
          Tools::getValue('addPriceCondition') ? '>' : htmlentities('>') => $this->l('> Greater than'),
          '==' => $this->l('= Equal'),
          'zero' => $this->l('Zero'),
          'any' => $this->l('Any'),
        )
      ),
      'condition_value'        => array(
        'name'             => $this->l('Condition value'),
        'form_group_class' => '',
        'type'   => 'input'
      ),
      'price_formula'        => array(
        'name'             => $this->l('Price formula'),
//        'hint'             => $this->l('Supplier price (TAX EXCL.)'),
        'form_group_class' => '',
        'type'   => 'input'
      ),
    );

    $this->context->smarty->assign(
      array(
        'has_hint_price_settings' => $has_hint_price_settings,
        'saved_price_settings'    => $save,
        'price_settings_ajax'     => Tools::getValue('addPriceCondition', false)
      )
    );
    return $this->display(__FILE__, 'views/templates/hook/priceSettings.tpl');

  }


  public function moreCombinationSuppliers($key_settings, $n){
    $key_settings = (int)$key_settings;
    $config = Tools::unserialize(Configuration::get('GOMAKOIL_CONFIG_IMPORT_PRODUCTS',null,Context::getContext()->shop->id_shop_group,Context::getContext()->shop->id));
    $fields = $config['name_fields_upload'];
    $save = false;

    if($key_settings) {
      $save_settings = Tools::unserialize(Configuration::get('GOMAKOIL_IMPORT_PRODUCTS_' . $key_settings, null, Context::getContext()->shop->id_shop_group, Context::getContext()->shop->id));
      $save = $save_settings['field_combinations'];
    }

    if($n && $n == 1){
      $save = $save[0];
    }

    $save = $save['suppliers'];

    $has_hint_suppliers = array(
      'supplier'            => array(
        'name'             => $this->l('Supplier name'),
        'hint'             => $this->l('Supplier name'),
        'form_group_class' => ' full_combination supplier_name_combination',
      ),
      'supplier_ids'        => array(
        'name'             => $this->l('Supplier Id'),
        'hint'             => $this->l('Supplier Id'),
        'form_group_class' => ' full_combination supplier_id_combination',
      ),
      'existing_supplier'            => array(
        'name'             => $this->l('Supplier'),
        'hint'             => $this->l('Existing Supplier name'),
        'form_group_class' => 'existing_supplier_combination suppliers',
        'fields'           => $this->_getPreSavedFields( $fields, 'supplier' ),
      ),
      'supplier_reference'        => array(
        'name'             => $this->l('Supplier reference'),
        'hint'             => $this->l('Supplier reference.'),
        'form_group_class' => ' full_combination',
      ),
      'supplier_price'        => array(
        'name'             => $this->l('Supplier price'),
        'hint'             => $this->l('Supplier price (TAX EXCL.)'),
        'form_group_class' => 'supplier_price_method full_combination',
      ),
      'supplier_currency'        => array(
        'name'             => $this->l('Supplier currency'),
        'hint'             => $this->l('Supplier currency ID'),
        'form_group_class' => ' full_combination',
      ),
    );

    $this->context->smarty->assign(
      array(
        'has_hint_suppliers' => $has_hint_suppliers,
        'default_fields'     => $fields,
        'save'               => $save,
        'supplier_ajax'      => Tools::getValue('moreSuppliersCombination', false)
      )
    );
    return $this->display(__FILE__, 'views/templates/hook/moreCombinationSuppliers.tpl');
  }

  public function getCategoryLinkingTpl($file_fields)
  {
    $form_template = Context::getContext()->smarty->createTemplate(_PS_MODULE_DIR_ . 'simpleimportproduct/views/templates/hook/category_linking.tpl');
    $categories = self::getAllCategoriesName(null, Context::getContext()->language->id);

    $save = false;
    $is_active = false;

    if(Tools::getValue('module_tab') == 'step_2' && Tools::getValue('save') !== false) {
      $settings_full = Tools::unserialize(Configuration::get('GOMAKOIL_IMPORT_PRODUCTS_' . Tools::getValue('save'), null, Context::getContext()->shop->id_shop_group, Context::getContext()->shop->id));
      $has_saved_category_linking = $settings_full['category_linking_active'] == 1 && !empty($settings_full['category_linking']);

      if ($has_saved_category_linking) {
        $is_active = true;
        $save = $settings_full['category_linking'];

        $i = 1;
        foreach ($save as $file_name => &$shop_cat) {
          $shop_cat['row_number'] = $i;
          $tree_id = 'mpm_sip_shop_categories_tree_' . $i;
          $tree_input_name = 'mpm_sip_shop_category_' . $i;
          $tree_selected_id = array($shop_cat['id']);

          $shop_cat['tree'] = self::getCategoriesTree($tree_id, $tree_input_name, $tree_selected_id);
          $i++;
        }
      }
    }

    $tree_id = 'mpm_sip_shop_categories_tree_1';
    $tree_input_name = 'mpm_sip_shop_category_1';
    $tree_selected_id = array();

    $form_template->assign(
      array(
        'file_fields' => $file_fields,
        'categories' => $categories,
        'is_active' => $is_active,
        'save' => $save,
        'default_tree' => self::getCategoriesTree($tree_id, $tree_input_name, $tree_selected_id)
      )
    );

    return $form_template->fetch();
  }

  public static function getCategoriesTree($id, $input_name, $selected_ids)
  {
    $tree = new HelperTreeCategories($id);
    $tree->setTemplate('tree_categories.tpl')
      ->setUseCheckBox(false)
      ->setUseSearch(true)
      ->setSelectedCategories($selected_ids)
      ->setRootCategory(Category::getRootCategory()->id)
      ->setInputName($input_name);

    return $tree->render();
  }

  /**
   * COPY OF CATEGORY CLASS METHOD WITH THE SAME NAME.
   * MUST BE HERE FOR RETRO COMPATIBILITY WITH OLDER PS VERSIONS.
   *
   * @param int      $idRootCategory     ID of root Category
   * @param int|bool $idLang             Language ID
   *                                     `false` if language filter should not be applied
   * @param bool     $active             Only return active categories
   * @param null     $groups
   * @param bool     $useShopRestriction Restrict to current Shop
   * @param string   $sqlFilter          Additional SQL clause(s) to filter results
   * @param string   $orderBy            Change the default order by
   * @param string   $limit              Set the limit
   *                                     Both the offset and limit can be given
   *
   * @return array|false|mysqli_result|null|PDOStatement|resource Array with `id_category` and `name`
   */
  private static function getAllCategoriesName(
    $idRootCategory = null,
    $idLang = false,
    $active = true,
    $groups = null,
    $useShopRestriction = true,
    $sqlFilter = '',
    $orderBy = '',
    $limit = ''
  ) {
    if (isset($idRootCategory) && !Validate::isInt($idRootCategory)) {
      die(Tools::displayError());
    }

    if (!Validate::isBool($active)) {
      die(Tools::displayError());
    }

    if (isset($groups) && Group::isFeatureActive() && !is_array($groups)) {
      $groups = (array) $groups;
    }

    $cacheId = 'Category::getAllCategoriesName_'.md5(
        (int) $idRootCategory.
        (int) $idLang.
        (int) $active.
        (int) $useShopRestriction.
        (isset($groups) && Group::isFeatureActive() ? implode('', $groups) : '').
        (isset($sqlFilter) ? $sqlFilter : '').
        (isset($orderBy) ? $orderBy : '').
        (isset($limit) ? $limit : '')
      );

    if (!Cache::isStored($cacheId)) {
      $result = Db::getInstance()->executeS('
				SELECT c.`id_category`, cl.`name`
				FROM `'._DB_PREFIX_.'category` c
				'.($useShopRestriction ? Shop::addSqlAssociation('category', 'c') : '').'
				LEFT JOIN `'._DB_PREFIX_.'category_lang` cl ON c.`id_category` = cl.`id_category`'.Shop::addSqlRestrictionOnLang('cl').'
				'.(isset($groups) && Group::isFeatureActive() ? 'LEFT JOIN `'._DB_PREFIX_.'category_group` cg ON c.`id_category` = cg.`id_category`' : '').'
				'.(isset($idRootCategory) ? 'RIGHT JOIN `'._DB_PREFIX_.'category` c2 ON c2.`id_category` = '.(int) $idRootCategory.' AND c.`nleft` >= c2.`nleft` AND c.`nright` <= c2.`nright`' : '').'
				WHERE 1 '.$sqlFilter.' '.($idLang ? 'AND `id_lang` = '.(int) $idLang : '').'
				'.($active ? ' AND c.`active` = 1' : '').'
				'.(isset($groups) && Group::isFeatureActive() ? ' AND cg.`id_group` IN ('.implode(',', array_map('intval', $groups)).')' : '').'
				'.(!$idLang || (isset($groups) && Group::isFeatureActive()) ? ' GROUP BY c.`id_category`' : '').'
				'.($orderBy != '' ? $orderBy : ' ORDER BY c.`level_depth` ASC').'
				'.($orderBy == '' && $useShopRestriction ? ', category_shop.`position` ASC' : '').'
				'.($limit != '' ? $limit : '')
      );

      Cache::store($cacheId, $result);
    } else {
      $result = Cache::retrieve($cacheId);
    }

    return $result;
  }
}
