{foreach from=$save item=fieldset  key=n }

 {if $n !== 0}
    <div class="panel additional_combinations combinations" id="fieldset_3_3">
    <div class="form-wrapper">

    {foreach from=$has_hint_combinations item=combinations  key=k }
      {$fields = $default_fields}
      {if $k == 'combinations_import_type' }
        {$fields = $import_type}
      {/if}
        {if $k == 'supplier_method_combination'}
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
                    <select name="{$k|escape:'htmlall':'UTF-8'}" class="chosen fixed-width-xl{if $k=='combinations_import_type'} combinations_import_type_{$n|escape:'htmlall':'UTF-8'}{/if}" id="{$k|escape:'htmlall':'UTF-8'}"  style="display: none; width:350px;">
                        {foreach from=$fields key=key item=field}
                            <option {if $fieldset[$k] == $field['name']}selected="selected"{/if} {if isset($field['value']) && $field['value'] == $fieldset[$k]}selected="selected"{/if} value="{if isset($field['value']) && $field['value']}{$field['value']|escape:'htmlall':'UTF-8'|html_entity_decode}{else}{$field['name']|escape:'htmlall':'UTF-8'|html_entity_decode}{/if}">{$field['name']|escape:'htmlall':'UTF-8'|html_entity_decode}</option>
                        {/foreach}
                    </select>
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
                    <input type="hidden" value="{count($fieldset['images'])|escape:'htmlall':'UTF-8'}" class="hidden_count_images">

                    {foreach $fieldset['images'] as $k => $images}
                        <div class="one_image one_image_block {if $k>0}more_images_chosen {/if} one_image_block_{($key+1)|escape:'htmlall':'UTF-8'}">

                            {if $k>0}
                                <span class="delete_one_image"> <a class="btn btn-default"> <i class="icon-trash"></i> </a> </span>
                            {/if}

                            <span class="count_img">{($k+1)|escape:'htmlall':'UTF-8'}</span>
                            <select name="images" class="chosen fixed-width-xl" id="images"  style="display: none; width:350px;">
                                {foreach from=$fields key=key item=field}
                                    <option {if $images == $field['name']}selected="selected"{/if} {if isset($field['value']) && $field['value'] == $images}selected="selected"{/if} value="{if isset($field['value']) && $field['value']}{$field['value']|escape:'htmlall':'UTF-8'|html_entity_decode}{else}{$field['name']|escape:'htmlall':'UTF-8'|html_entity_decode}{/if}">{$field['name']|escape:'htmlall':'UTF-8'|html_entity_decode}</option>
                                {/foreach}
                            </select>
                            <div style="clear: both"></div>
                        </div>

                    {/foreach}


                    <div class="more_image_comb more_image_1"><button type="button" class="btn btn-default">{l s='add image' mod='simpleimportproduct'}</button></div>
                </div>
            </div>
        {/if}



    {/foreach}


        <div class="form-group full_combination_suppliers full_combination">
            {if isset($fieldset['suppliers']) && $fieldset['suppliers']}

                {foreach from=$fieldset['suppliers'] item=sup}

                    <div class="  full_combination_supplier_item" >
                        <div class="form-group-item">

                            {foreach from=$has_hint_suppliers item=suppliers  key=k }
                                {$supplier_fields = $default_fields}
                                {if isset($suppliers['fields']) && $suppliers['fields']}
                                    {$supplier_fields = $suppliers['fields']}
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
                                            {foreach from=$supplier_fields key=key item=field}
                                                <option {if $sup[$k] == $field['name']}selected="selected"{/if}  {if isset($field['value']) && $field['value'] == $sup[$k]}selected="selected"{/if}   value="{if isset($field['value']) && $field['value']}{$field['value']|escape:'htmlall':'UTF-8'|html_entity_decode}{else}{$field['name']|escape:'htmlall':'UTF-8'|html_entity_decode}{/if}">{$field['name']|escape:'htmlall':'UTF-8'|html_entity_decode}</option>
                                            {/foreach}
                                        </select>
                                    </div>
                                </div>
                            {/foreach}


                            <div class="form-group">
                                <label class="control-label col-lg-3"></label>
                                <div class="col-lg-9">
                                    <button type="button" class="btn btn-default more_suppliers_combination">{l s='add supplier' mod='simpleimportproduct'}</button>&nbsp;&nbsp;<button type="button" class="btn btn-default delete_suppliers_combination">{l s='delete' mod='simpleimportproduct'}</button>
                                </div>
                            </div>
                        </div><!-- /.form-wrapper -->
                    </div>

                {/foreach}
            {else}


                <div class=" full_combination_supplier_item" >
                    <div class="form-group-item">

                        {foreach from=$has_hint_suppliers item=suppliers  key=k }
                            {$supplier_fields = $default_fields}
                            {if isset($suppliers['fields']) && $suppliers['fields']}
                                {$supplier_fields = $suppliers['fields']}
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
                                        {foreach from=$supplier_fields key=key item=field}
                                            <option  value="{if isset($field['value']) && $field['value']}{$field['value']|escape:'htmlall':'UTF-8'|html_entity_decode}{else}{$field['name']|escape:'htmlall':'UTF-8'|html_entity_decode}{/if}">{$field['name']|escape:'htmlall':'UTF-8'|html_entity_decode}</option>
                                        {/foreach}
                                    </select>
                                </div>
                            </div>
                        {/foreach}

                        <div class="form-group">
                            <label class="control-label col-lg-3"></label>
                            <div class="col-lg-9">
                                <button type="button" class="btn btn-default more_suppliers_combination">{l s='add supplier' mod='simpleimportproduct'}</button>&nbsp;&nbsp;<button type="button" class="btn btn-default delete_suppliers_combination">{l s='delete' mod='simpleimportproduct'}</button>
                            </div>
                        </div>
                    </div><!-- /.form-wrapper -->
                </div>

            {/if}
        </div>

    <div class="form-group full_combination_button">
      <div class="col-lg-9 col-lg-offset-3">
        <button type="button" class="btn btn-default more_combination">{l s='add combination' mod='simpleimportproduct'}</button>&nbsp;&nbsp;<button type="button" class="btn btn-default delete_combination">{l s='delete' mod='simpleimportproduct'}</button>
      </div>
    </div>
    </div><!-- /.form-wrapper -->
    </div>
   <script type="text/javascript">
     addAttribute($(".combinations_import_type_{$n|escape:'htmlall':'UTF-8'}"),{$n|escape:'htmlall':'UTF-8'});
   </script>
   {if $fieldset['combinations_import_type'] == 'single_field_value' || $fieldset['combinations_import_type'] == 'separate_combination_row'}
     <script type="text/javascript">
       $(".combinations_import_type_{$n|escape:'htmlall':'UTF-8'}").parents('#fieldset_3_3').find('.old_type').hide();
     </script>
   {/if}
  {/if}
{/foreach}

<script type="text/javascript">
  $('.chosen').chosen();
  $('.label-tooltip').tooltip();
</script>