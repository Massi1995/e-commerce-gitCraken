<div class="panel images" id="fieldset_6_6" style="display: block;">
<div class="form-wrapper">

{foreach from=$has_hint_images item=images  key=k }
  <div class="form-group">
    <label class="control-label col-lg-3">
      {if $images.hint}
        <span class="label-tooltip" data-toggle="tooltip" data-html="true" title="" data-original-title="{$images.hint|escape:'htmlall':'UTF-8'}">
          {$images.name|escape:'htmlall':'UTF-8'}
        </span>
      {else}
        {$images.name|escape:'htmlall':'UTF-8'}
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


<div class="form-group">
  <div class="col-lg-9 col-lg-offset-3">
    <button type="button" class="btn btn-default more_image">{l s='add image' mod='simpleimportproduct'}</button>&nbsp;&nbsp;<button type="button" class="btn btn-default delete_image">{l s='delete' mod='simpleimportproduct'}</button>
  </div>
</div>
</div><!-- /.form-wrapper -->
</div>

<script type="text/javascript">
  $('.chosen').chosen();
  $('.label-tooltip').tooltip();
</script>