{foreach from=$attributes item=attribute  key=k }
  {$fields = $default_fields}
  {if $k == 'single_type' }
    {$fields = $attribute_types}
  {elseif $k == 'single_delimiter'}
    {$fields = $attribute_delimiter}
  {elseif $k == 'single_attribute'}
    {$fields = $attribute_names}
  {elseif $k == 'existing_attribute'}
      {$fields = $existing_attributes}
  {/if}
  <div class="form-group single_attribute{if isset($attribute['form_group_class']) && $attribute['form_group_class']} {$attribute['form_group_class']|escape:'htmlall':'UTF-8'}{/if}" {if ($k == 'single_color' && isset($saved_settings['single_type']) && $saved_settings['single_type'] == 'color')}style="display: block"{/if} {if isset($saved_settings['single_attribute']) && $saved_settings['single_attribute'] == 'existing_attribute' && $k == 'existing_attribute'}style="display: block" {/if} {if isset($saved_settings['single_attribute']) && $saved_settings['single_attribute'] == 'existing_attribute' && $k == 'single_type'}style="display: none" {/if}>
    {if $attribute.hint}
      <label class="control-label col-lg-3">
        <span class="label-tooltip" data-toggle="tooltip" data-html="true" title="" data-original-title="{$attribute.hint|escape:'htmlall':'UTF-8'}">
          {$attribute.name|escape:'htmlall':'UTF-8'}
        </span>
      </label>
    {else}
      <label class="control-label col-lg-3">
        {$attribute.name|escape:'htmlall':'UTF-8'}
      </label>
    {/if}
    <div class="col-lg-9 ">
      <select name="{$k|escape:'htmlall':'UTF-8'}" class="chosen fixed-width-xl" id="{$k|escape:'htmlall':'UTF-8'}"  style="display: none; width:350px;">
        {foreach from=$fields key=key item=field}
          <option {if isset($saved_settings[$k]) && $saved_settings[$k] == $field['name'] }selected{/if} {if isset($saved_settings[$k]) && isset($field['value']) && $saved_settings[$k] == $field['value'] }selected{/if} value="{if isset($field['value']) && $field['value']}{$field['value']|escape:'htmlall':'UTF-8'|html_entity_decode}{else}{$field['name']|escape:'htmlall':'UTF-8'|html_entity_decode}{/if}">{$field['name']|escape:'htmlall':'UTF-8'|html_entity_decode}</option>
        {/foreach}
      </select>
    </div>
  </div>
  {if $k == 'single_attribute'}
    <div class="form-group manually_attribute_name single_attribute" {if isset($saved_settings['single_attribute']) && $saved_settings['single_attribute'] == 'enter_manually'}style="display: block" {/if}>
      <label class="control-label col-lg-3">
        {l s='Attribute name' mod='simpleimportproduct'}
      </label>
      <div class="col-lg-9">
        <input type="text" name="manually_attribute"  value="{if isset($saved_settings['manually_attribute'])}{$saved_settings['manually_attribute']|escape:'htmlall':'UTF-8'|html_entity_decode}{/if}" class="manually_attribute">
      </div>
    </div>
  {/if}
{/foreach}
<div class="form-group single_attribute add_attr_buttons">
  <div class="col-lg-9 col-lg-offset-3">
    <button type="button" class="btn btn-default add_attribute">{l s='add attribute' mod='simpleimportproduct'}</button>&nbsp;&nbsp;<button type="button" class="btn btn-default delete_attribute">{l s='delete' mod='simpleimportproduct'}</button>
  </div>
</div>