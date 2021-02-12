{extends file="helpers/form/form.tpl"}
{block name="defaultForm"}

  {if (isset($form_errors)) && (count($form_errors) > 0)}
    <div class="alert alert-danger">
      <h4>{l s='Error!' mod='simpleimportproduct'}</h4>
      <ul class="list-unstyled">
        {foreach from=$form_errors item='message'}
          <li>{$message|escape:'htmlall':'UTF-8'}</li>
        {/foreach}
      </ul>
    </div>
  {/if}

  {if (isset($form_infos)) && (count($form_infos) > 0)}
    <div class="alert alert-warning">
      <h4>{l s='Warning!' mod='simpleimportproduct'}</h4>
      <ul class="list-unstyled">
        {foreach from=$form_infos item='message'}
          <li>{$message|escape:'htmlall':'UTF-8'}</li>
        {/foreach}
      </ul>
    </div>
  {/if}

  {if (isset($form_successes)) && (count($form_successes) > 0)}
    <div class="alert alert-success">
      <h4>{l s='Success!' mod='simpleimportproduct'}</h4>
      <ul class="list-unstyled">
        {foreach from=$form_successes item='message'}
          <li>{$message|escape:'htmlall':'UTF-8'}</li>
        {/foreach}
      </ul>
    </div>
  {/if}

  {$smarty.block.parent}
{/block}

{block name="input_row"}

  {if $input.type == 'html_categories'}
    {assign var=options value=$input.options}
    <div class="form-group form-group-categories{if isset($input.form_group_class)} {$input.form_group_class|escape:'htmlall':'UTF-8'}{/if}" >
      <label class="control-label col-lg-3">
        <span class="{if $input.hint|escape:'htmlall':'UTF-8'}label-tooltip{else}control-label{/if}" data-toggle="tooltip" data-html="true" title="" data-original-title="{$input.hint|escape:'htmlall':'UTF-8'}">
          {$input.label|escape:'htmlall':'UTF-8'}
        </span>
      </label>
      <div class="col-lg-9" style="padding-left: 15px;">
        <input type="hidden" value="1" class="hidden_count_category">
        <div class="one_category one_category_block  one_category_block_1"><span class="count_cat">1</span>
          <input type="hidden" value="1" class="hidden_count_subcategory_1">
          <div class="one_subcategory one_subcategory_1" style="width: 260px;">
            <select name="category" class="chosen fixed-width-xl" id="category"  style="display: none; width:350px;">
              {foreach from=$input.value key=key item=field}
                <option value="{$field['name']|escape:'htmlall':'UTF-8'|html_entity_decode}">{$field['name']|escape:'htmlall':'UTF-8'|html_entity_decode}</option>
              {/foreach}
            </select>
          </div>
          <div style="clear: both"></div>
          <div class="more_subcategory more_subcategory_1" category="1"><button type="button" class="btn btn-default">{l s='add subcategory' mod='simpleimportproduct'}</button></div>
        </div>
        <div class="more_category more_category_1"><button type="button" class="btn btn-default">{l s='add category' mod='simpleimportproduct'}</button></div>
      </div>
    </div>
  {elseif $input.type == 'html_images'}

    {if !$input.value}


      {assign var=options value=$input.options}
      <div class="form-group form-group-images{if isset($input.form_group_class)} {$input.form_group_class|escape:'htmlall':'UTF-8'}{/if}" >
        <label class="control-label col-lg-3">
        <span class="{if $input.hint|escape:'htmlall':'UTF-8'}label-tooltip{else}control-label{/if}" data-toggle="tooltip" data-html="true" title="" data-original-title="{$input.hint|escape:'htmlall':'UTF-8'}">
          {$input.label|escape:'htmlall':'UTF-8'}
        </span>
        </label>
        <div class="col-lg-9" style="padding-left: 15px;">
          <input type="hidden" value="1" class="hidden_count_images">
          <div class="one_image one_image_block  one_image_block_1"><span class="count_img">1</span>
            <select name="images" class="chosen fixed-width-xl" id="images"  style="display: none; width:350px;">
              {foreach from=$input.fields key=key item=field}
                <option value="{$field['name']|escape:'htmlall':'UTF-8'|html_entity_decode}">{$field['name']|escape:'htmlall':'UTF-8'|html_entity_decode}</option>
              {/foreach}
            </select>
            <div style="clear: both"></div>
          </div>
          <div class="more_image_comb more_image_1"><button type="button" class="btn btn-default">{l s='add image' mod='simpleimportproduct'}</button></div>
        </div>
      </div>

    {else}


          {assign var=options value=$input.options}
          <div class="form-group form-group-images{if isset($input.form_group_class)} {$input.form_group_class|escape:'htmlall':'UTF-8'}{/if}" >
            <label class="control-label col-lg-3">
              <span class="{if $input.hint|escape:'htmlall':'UTF-8'}label-tooltip{else}control-label{/if}" data-toggle="tooltip" data-html="true" title="" data-original-title="{$input.hint|escape:'htmlall':'UTF-8'}">
                {$input.label|escape:'htmlall':'UTF-8'}
              </span>
            </label>
            <div class="col-lg-9" style="padding-left: 15px;">
              <input type="hidden" value="{count($input.value)|escape:'htmlall':'UTF-8'}" class="hidden_count_images">
              {foreach $input.value as $k => $images}
                      <div class="one_image one_image_block {if $k>0}more_images_chosen {/if}  one_image_block_{($k+1)|escape:'htmlall':'UTF-8'}">

                        {if $k>0}
                          <span class="delete_one_image"> <a class="btn btn-default"> <i class="icon-trash"></i> </a> </span>
                        {/if}
                        <span class="count_img">{($k+1)|escape:'htmlall':'UTF-8'}</span>

                        <select name="images" class="chosen fixed-width-xl" id="images"  style="display: none; width:350px;">
                          {foreach from=$input.fields key=key item=field}
                            <option {if $images == $field['name']}selected="selected"{/if} {if isset($field['value']) && $field['value'] == $images}selected="selected"{/if} value="{$field['name']|escape:'htmlall':'UTF-8'|html_entity_decode}">{$field['name']|escape:'htmlall':'UTF-8'|html_entity_decode}</option>
                          {/foreach}
                        </select>
                        <div style="clear: both"></div>
                      </div>

                      {if ($k+1) == count($input.value)}

                        <div class="more_image_comb more_image_1"><button type="button" class="btn btn-default">{l s='add image' mod='simpleimportproduct'}</button></div>
                      {/if}
              {/foreach}
            </div>
          </div>


    {/if}


  {else}
    {$smarty.block.parent}
  {/if}
{/block}
{block name="script"}
    var import_show_more = '{l s='Show more...' mod='simpleimportproduct'}';
    var import_hide = '{l s='Hide...' mod='simpleimportproduct'}';
{/block}
<script type="text/javascript">
  $('.chosen').chosen();
</script>

