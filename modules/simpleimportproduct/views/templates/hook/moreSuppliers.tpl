

{foreach from=$save item=fieldset  key=n }

    <div class="panel suppliers suppliers_block_no_first" id="fieldset_12_12" style="{if $save}display: none;{else} display: block;{/if}" >
        <div class="form-wrapper">

            {foreach from=$has_hint_suppliers item=suppliers  key=k }
                {$fields = $default_fields}
                {if isset($suppliers['fields']) && $suppliers['fields']}
                    {$fields = $suppliers['fields']}
                {/if}
                <div class="form-group {$suppliers.form_group_class|escape:'htmlall':'UTF-8'}">
                    <label class="control-label col-lg-3">
                        {if isset($suppliers.hint) && $suppliers.hint}
                            <span class="label-tooltip" data-toggle="tooltip" data-html="true" title="" data-original-title="{$suppliers.hint|escape:'htmlall':'UTF-8'}">
                              {$suppliers.name|escape:'htmlall':'UTF-8'}
                            </span>
                        {else}
                            {$suppliers.name|escape:'htmlall':'UTF-8'}
                        {/if}
                    </label>
                    <div class="col-lg-9 ">
                        <select name="{$k|escape:'htmlall':'UTF-8'}" class="chosen fixed-width-xl" id="{$k|escape:'htmlall':'UTF-8'}"  style="display: none; width:350px;">
                            {foreach from=$fields key=key item=field}
                                <option {if $save && count($save)>0}{if $fieldset[$k] == $field['name']}selected="selected"{/if}  {if isset($field['value']) && $field['value'] == $fieldset[$k]}selected="selected"{/if}{/if}   value="{if isset($field['value']) && $field['value']}{$field['value']|escape:'htmlall':'UTF-8'|html_entity_decode}{else}{$field['name']|escape:'htmlall':'UTF-8'|html_entity_decode}{/if}">{$field['name']|escape:'htmlall':'UTF-8'|html_entity_decode}</option>
                            {/foreach}
                        </select>
                    </div>
                </div>
            {/foreach}


            <div class="form-group">
                <div class="col-lg-9 col-lg-offset-3">
                    <button type="button" class="btn btn-default more_suppliers">{l s='add supplier' mod='simpleimportproduct'}</button>&nbsp;&nbsp;<button type="button" class="btn btn-default delete_suppliers">{l s='delete' mod='simpleimportproduct'}</button>
                </div>
            </div>
        </div><!-- /.form-wrapper -->
    </div>

{/foreach}

<script type="text/javascript">
    $('.chosen').chosen();
    $('.label-tooltip').tooltip();
</script>