<div class="panel customization" id="fieldset_13_13" style="display: block;">
<div class="form-wrapper">

{foreach from=$has_hint_customization item=customization  key=k }
  {$fields = $default_fields}

  <div class="form-group">
    <label class="control-label col-lg-3">
      <span class="label-tooltip" data-toggle="tooltip" data-html="true" title="" data-original-title="{$customization.hint|escape:'htmlall':'UTF-8'}">
        {$customization.name|escape:'htmlall':'UTF-8'}
      </span>
    </label>
    <div class="col-lg-9 ">
      <select name="{$k|escape:'htmlall':'UTF-8'}" class="chosen fixed-width-xl" id="{$k|escape:'htmlall':'UTF-8'}"  style="display: none; width:350px;">
        {if $k == 'customization_type'}
          {foreach from=$type_fields key=key item=field}
            <option value="{if isset($field['value']) && $field['value']}{$field['value']|escape:'htmlall':'UTF-8'|html_entity_decode}{else}{$field['name']|escape:'htmlall':'UTF-8'|html_entity_decode}{/if}">{$field['name']|escape:'htmlall':'UTF-8'|html_entity_decode}</option>
          {/foreach}
        {elseif $k == 'customization_required'}
          {foreach from=$required_fields key=key item=field}
            <option value="{if isset($field['value']) && $field['value']}{$field['value']|escape:'htmlall':'UTF-8'|html_entity_decode}{else}{$field['name']|escape:'htmlall':'UTF-8'|html_entity_decode}{/if}">{$field['name']|escape:'htmlall':'UTF-8'|html_entity_decode}</option>
          {/foreach}
        {else}
          {foreach from=$fields key=key item=field}
            <option value="{if isset($field['value']) && $field['value']}{$field['value']|escape:'htmlall':'UTF-8'|html_entity_decode}{else}{$field['name']|escape:'htmlall':'UTF-8'|html_entity_decode}{/if}">{$field['name']|escape:'htmlall':'UTF-8'|html_entity_decode}</option>
          {/foreach}
        {/if}
      </select>
    </div>
  </div>
{/foreach}

<div class="form-group">
  <div class="col-lg-9 col-lg-offset-3">
    <button type="button" class="btn btn-default more_customization">{l s='add field' mod='simpleimportproduct'}</button>&nbsp;&nbsp;<button type="button" class="btn btn-default delete_customization">{l s='delete' mod='simpleimportproduct'}</button>
  </div>
</div>
</div><!-- /.form-wrapper -->
</div>

<script type="text/javascript">
  $('.chosen').chosen();
  $('.label-tooltip').tooltip();
</script>