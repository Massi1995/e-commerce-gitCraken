<div class="search_settings_block">
  <div class="icon"></div>
  <div class="saved_settings">
    {foreach $count_save as $value}
        {$save = Tools::unserialize(Configuration::get("GOMAKOIL_IMPORT_PRODUCTS_$value",null,Context::getContext()->shop->id_shop_group,Context::getContext()->shop->id))}
          <a href='{AdminController::$currentIndex|escape:'htmlall':'UTF-8'}&token={Tools::getAdminTokenLite('AdminModules')|escape:'htmlall':'UTF-8'}&configure=simpleimportproduct&module_tab=step_1&save={$value|escape:'htmlall':'UTF-8'}'>
              {$save['name_save']|escape:'htmlall':'UTF-8'}
          </a>
    {/foreach}
  </div>
</div>