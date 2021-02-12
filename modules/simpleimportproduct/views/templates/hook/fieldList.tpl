<div class="field_list_block">
  <div class="title">{l s='Your current fields list' mod='simpleimportproduct'} <span>({count($fields)|escape:'htmlall':'UTF-8'})</span></div>
  <div class="description">
    <div class="text">
      {l s='This is a list of your available fields from the file you uploaded. You can use them to create your formulas.' mod='simpleimportproduct'}
      <a href="http://faq.myprestamodules.com/product-catalog-csv-excel-import/additional-settings-creating-custom-fields-custom-formulas.html" target="_blank">{l s='How to use formulas?' mod='simpleimportproduct'}</a>
    </div>
    <div class="search_field">
      <input type="text">
    </div>
    <div class="hide_fields">&nbsp;</div>
  </div>
  <div class="field_list">
    <div class="field_names_block">
      {foreach $fields as $key=>$field}
        <div class="field_name" >[{$field|escape:'htmlall':'UTF-8'}]</div>
      {/foreach}
    </div>
    <div class="copy_list_block">
      {foreach $fields as $key=>$field}
        <div class="copy_block">
          <div class="copy" data-field="[{$field|escape:'htmlall':'UTF-8'}]" data-copy="{l s='Copy' mod='simpleimportproduct'}" data-copied="{l s='Copied' mod='simpleimportproduct'}">{l s='Copy' mod='simpleimportproduct'}</div>
        </div>
      {/foreach}
    </div>
  </div>
</div>