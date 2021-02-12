{foreach from=$save item=fieldset  key=n }

  {if $n !== 0}
    <div class="panel attachments additional-attachments" id="fieldset_14_14">
    <div class="form-wrapper">

    {foreach from=$has_hint_attachments item=attachments  key=k }

      <div class="form-group">
        <label class="control-label col-lg-3">
          <span class="label-tooltip" data-toggle="tooltip" data-html="true" title="" data-original-title="{$attachments.hint|escape:'htmlall':'UTF-8'}">
            {$attachments.name|escape:'htmlall':'UTF-8'}
          </span>
        </label>
        <div class="col-lg-9 ">
          <select name="{$k|escape:'htmlall':'UTF-8'}" class="chosen fixed-width-xl" id="{$k|escape:'htmlall':'UTF-8'}"  style="display: none; width:350px;">
            {foreach from=$fields key=key item=field}
              <option {if $fieldset[$k] == $field['name']}selected="selected"{/if} {if isset($field['value']) && $field['value'] == $fieldset[$k]}selected="selected"{/if} value="{if isset($field['value']) && $field['value']}{$field['value']|escape:'htmlall':'UTF-8'|html_entity_decode}{else}{$field['name']|escape:'htmlall':'UTF-8'|html_entity_decode}{/if}">{$field['name']|escape:'htmlall':'UTF-8'|html_entity_decode}</option>
            {/foreach}
          </select>
        </div>
      </div>
    {/foreach}


    <div class="form-group">
      <div class="col-lg-9 col-lg-offset-3">
        <button type="button" class="btn btn-default more_attachments">{l s='add attachment' mod='simpleimportproduct'}</button>&nbsp;&nbsp;<button type="button" class="btn btn-default delete_attachments">{l s='delete' mod='simpleimportproduct'}</button>
      </div>
    </div>
    </div><!-- /.form-wrapper -->
    </div>
  {/if}
{/foreach}
<script type="text/javascript">
  $('.chosen').chosen();
  $('.label-tooltip').tooltip();
</script>