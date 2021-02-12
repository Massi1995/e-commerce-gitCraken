<div class="panel" id="fieldset_5_5" style="display: block;">
<div class="form-wrapper">

    {foreach from=$has_hint_discount item=discount  key=k }
      {$fields = $default_fields}
      {if isset($discount['fields']) && $discount['fields']}
          {$fields = $discount['fields']}
      {/if}

      <div class="form-group">
        <label class="control-label col-lg-3">
          {if $discount.hint}
            <span class="label-tooltip" data-toggle="tooltip" data-html="true" title="" data-original-title="{$discount.hint|escape:'htmlall':'UTF-8'}">
              {$discount.name|escape:'htmlall':'UTF-8'}
            </span>
          {else}
            {$discount.name|escape:'htmlall':'UTF-8'}
          {/if}
        </label>
        <div class="col-lg-9 ">
          <select name="{$k|escape:'htmlall':'UTF-8'}" class="chosen fixed-width-xl" id="{$k|escape:'htmlall':'UTF-8'}"  style="display: none; width:350px;">
            {foreach from=$fields key=key item=field}
              <option value="{if isset($field['value']) && $field['value']}{$field['value']|escape:'htmlall':'UTF-8'|html_entity_decode}{else}{$field['name']|escape:'htmlall':'UTF-8'|html_entity_decode}{/if}">{$field['name']|escape:'htmlall':'UTF-8'|html_entity_decode}</option>
            {/foreach}
          </select>
        </div>
      </div>
    {/foreach}

   {if $select}
       <div class="form-group">
           <label class="control-label col-lg-3">
               <span class="label-tooltip" data-toggle="tooltip" data-html="true" title="" data-original-title="">
                  {l s='Specific prices for product/combination' mod='simpleimportproduct'}
               </span>
           </label>
           <div class="col-lg-9">
               <select name="specific_prices_for" id="specific_prices_for" class=" fixed-width-xl">
                   {foreach $select as $value}
                       <option value="{$value['id']|escape:'htmlall':'UTF-8'|html_entity_decode}">{$value['name']|escape:'htmlall':'UTF-8'|html_entity_decode}</option>
                   {/foreach}
               </select>
           </div>
       </div>
   {/if}




<div class="form-group">
  <div class="col-lg-9 col-lg-offset-3">
    <button type="button" class="btn btn-default more_discount">{l s='add special price' mod='simpleimportproduct'}</button>&nbsp;&nbsp;<button type="button" class="btn btn-default delete_discount">{l s='delete' mod='simpleimportproduct'}</button>
  </div>
</div>
</div><!-- /.form-wrapper -->
</div>

<script type="text/javascript">
  $('.chosen').chosen();
  $('.label-tooltip').tooltip();
</script>