<div class="panel features" id="fieldset_7_7" style="display: block;">
<div class="form-wrapper">

{foreach from=$has_hint_featured item=featured  key=k }
  {$fields = $default_fields}
  {if  isset($featured['fields']) && $featured['fields'] }
    {$fields = $featured['fields']}
  {/if}
  <div class="form-group{if $k == 'features_name_manually'} features_name_manually{/if}{if isset($featured['form_group_class']) && $featured['form_group_class']} {$featured['form_group_class']|escape:'htmlall':'UTF-8'}{/if}">
    <label class="control-label col-lg-3">
      <span {if $featured.hint}class="label-tooltip" title="" data-toggle="tooltip" data-html="true" data-original-title="{$featured.hint|escape:'htmlall':'UTF-8'}"{/if}>
        {$featured.name|escape:'htmlall':'UTF-8'}
      </span>
    </label>
    <div class="col-lg-9 ">
      {if $k == 'features_name_manually'}
        <input type="text" name="features_name_manually" id="features_name_manually" value="" class="">
      {else}
        <select name="{$k|escape:'htmlall':'UTF-8'}" class="chosen fixed-width-xl" id="{$k|escape:'htmlall':'UTF-8'}"  style="display: none; width:350px;">
          {foreach from=$fields key=key item=field}
            {if ( $k == 'features_name' || $k == 'features_id' ) && $key==1}
              <option value="enter_manually">{l s='Enter manually' mod='simpleimportproduct'}</option>
            {/if}
            <option value="{if isset($field['value'])}{$field['value']|escape:'htmlall':'UTF-8'|html_entity_decode}{else}{$field['name']|escape:'htmlall':'UTF-8'|html_entity_decode}{/if}">{$field['name']|escape:'htmlall':'UTF-8'|html_entity_decode}</option>
          {/foreach}
        </select>
      {/if}
    </div>
  </div>
{/foreach}


<div class="form-group">
  <div class="col-lg-9 col-lg-offset-3">
    <button type="button" class="btn btn-default more_featured">{l s='add features' mod='simpleimportproduct'}</button>&nbsp;&nbsp;<button type="button" class="btn btn-default delete_featured">{l s='delete' mod='simpleimportproduct'}</button>
  </div>
</div>
</div><!-- /.form-wrapper -->
</div>

<script type="text/javascript">
  $('.chosen').chosen();
  $('.label-tooltip').tooltip();
</script>