<div class="panel additional_combinations combinations" id="fieldset_3_3" style="display: block;">
<div class="form-wrapper">

{foreach from=$has_hint_combinations item=combinations  key=k }
  {$fields = $default_fields}
  {if $k == 'combinations_import_type' }
    {$fields = $import_type}
  {/if}
    {if $k == 'supplier_method_combination' }
        {$fields = $type}
    {/if}
    {if $k == 'quantity_combination_method' }
        {$fields = $quantity_method}
    {/if}
    {if isset($combinations['fields']) && $combinations['fields']}
        {$fields = $combinations['fields']}
    {/if}

    {if $k !== 'images_combination'}
        <div class="form-group {if isset($combinations.form_group_class)} {$combinations.form_group_class|escape:'htmlall':'UTF-8'}{/if}">
            {if $combinations.hint}
                <label class="control-label col-lg-3">
        <span class="label-tooltip" data-toggle="tooltip" data-html="true" title="" data-original-title="{$combinations.hint|escape:'htmlall':'UTF-8'}">
          {$combinations.name|escape:'htmlall':'UTF-8'}
        </span>
                </label>
            {else}
                <label class="control-label col-lg-3">
                    {$combinations.name|escape:'htmlall':'UTF-8'}
                </label>
            {/if}
            <div class="col-lg-9 ">
                <select name="{$k|escape:'htmlall':'UTF-8'}" class="chosen fixed-width-xl" id="{$k|escape:'htmlall':'UTF-8'}"  style="display: none; width:350px;">
                    {foreach from=$fields key=key item=field}
                        <option value="{if isset($field['value']) && $field['value']}{$field['value']|escape:'htmlall':'UTF-8'|html_entity_decode}{else}{$field['name']|escape:'htmlall':'UTF-8'|html_entity_decode}{/if}">{$field['name']|escape:'htmlall':'UTF-8'|html_entity_decode}</option>
                    {/foreach}
                </select>
                {if $k == 'combinations_import_type' }
                    <div class="tutorial"><a class="need_help" target="_blank" href="http://faq.myprestamodules.com/product-catalog-csv-excel-import/combinations-import-combination-in-one-field-method.html">{l s='Need Help?' mod='simpleimportproduct'}</a></div>
                {/if}
            </div>
        </div>
    {else}



        <div class="form-group form-group-images images_combination combinations" >
        <label class="control-label col-lg-3">
            <span class="label-tooltip" data-toggle="tooltip" data-html="true" title="" data-original-title=" {l s='Combination images' mod='simpleimportproduct'}">
              {l s='Combination images' mod='simpleimportproduct'}
            </span>
        </label>
            <div class="col-lg-9" style="padding-left: 15px;">
                <input type="hidden" value="1" class="hidden_count_images">
                <div class="one_image one_image_block  one_image_block_1"><span class="count_img">1</span>
                    <select name="images" class="chosen fixed-width-xl" id="images"  style="display: none; width:350px;">
                        {foreach from=$fields key=key item=field}
                            <option value="{if isset($field['value']) && $field['value']}{$field['value']|escape:'htmlall':'UTF-8'|html_entity_decode}{else}{$field['name']|escape:'htmlall':'UTF-8'|html_entity_decode}{/if}">{$field['name']|escape:'htmlall':'UTF-8'|html_entity_decode}</option>
                        {/foreach}
                    </select>
                    <div style="clear: both"></div>
                </div>
                <div class="more_image_comb more_image_1"><button type="button" class="btn btn-default">{l s='add image' mod='simpleimportproduct'}</button></div>
            </div>
        </div>
    {/if}


{/foreach}

{$tpl nofilter}


<div class="form-group full_combination_button">
  <div class="col-lg-9 col-lg-offset-3">
    <button type="button" class="btn btn-default more_combination">{l s='add combination' mod='simpleimportproduct'}</button>&nbsp;&nbsp;<button type="button" class="btn btn-default delete_combination">{l s='delete' mod='simpleimportproduct'}</button>
  </div>
</div>
</div><!-- /.form-wrapper -->
</div>

<script type="text/javascript">
  $('.chosen').chosen();
  $('.label-tooltip').tooltip();
</script>