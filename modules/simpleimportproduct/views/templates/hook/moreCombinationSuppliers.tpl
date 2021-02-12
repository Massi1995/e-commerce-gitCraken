{if $save}

    {foreach from=$save item=fieldset  key=n }

        <div class="full_combination_supplier_item{if $n != 0 } additional{/if}" >
            <div class="form-group-item">

                {foreach from=$has_hint_suppliers item=suppliers  key=k }
                    {$fields = $default_fields}
                    {if isset($suppliers['fields']) && $suppliers['fields']}
                        {$fields = $suppliers['fields']}
                    {/if}
                    <div class="form-group-sup {$suppliers.form_group_class|escape:'htmlall':'UTF-8'}">
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
                    <label class="control-label col-lg-3"></label>
                    <div class="col-lg-9">
                        <button type="button" class="btn btn-default more_suppliers_combination">{l s='add supplier' mod='simpleimportproduct'}</button>
                        {if $n != 0 }
                            &nbsp;&nbsp;<button type="button" class="btn btn-default delete_suppliers_combination">{l s='delete' mod='simpleimportproduct'}</button>
                        {/if}
                    </div>
                </div>
            </div><!-- /.form-wrapper -->
        </div>

    {/foreach}
    {else}
        <div class="full_combination_supplier_item{if $supplier_ajax} additional{/if}" >
            <div class="form-group-item">

                {foreach from=$has_hint_suppliers item=suppliers  key=k }
                    {$fields = $default_fields}
                    {if isset($suppliers['fields']) && $suppliers['fields']}
                        {$fields = $suppliers['fields']}
                    {/if}
                    <div class="form-group-sup {$suppliers.form_group_class|escape:'htmlall':'UTF-8'}">
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
                                    <option  value="{if isset($field['value']) && $field['value']}{$field['value']|escape:'htmlall':'UTF-8'}{else}{$field['name']|escape:'htmlall':'UTF-8'}{/if}">{$field['name']|escape:'htmlall':'UTF-8'}</option>
                                {/foreach}
                            </select>
                        </div>
                    </div>
                {/foreach}


                <div class="form-group">
                    <label class="control-label col-lg-3"></label>
                    <div class="col-lg-9">
                        <button type="button" class="btn btn-default more_suppliers_combination">{l s='add supplier' mod='simpleimportproduct'}</button>
                        {if $supplier_ajax}
                            &nbsp;&nbsp;<button type="button" class="btn btn-default delete_suppliers_combination">{l s='delete' mod='simpleimportproduct'}</button>
                        {/if}
                    </div>
                </div>
            </div><!-- /.form-wrapper -->
        </div>
{/if}



<script type="text/javascript">
    $('.chosen').chosen();
    $('.label-tooltip').tooltip();
</script>