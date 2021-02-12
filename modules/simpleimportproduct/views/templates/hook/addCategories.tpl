<div class="col-lg-9" style="padding-left: 15px;">
  <input type="hidden" value="{count($save)|escape:'htmlall':'UTF-8'}" class="hidden_count_category">

  {foreach from=$save item=fieldset  key=n }

    <div class="one_category one_category_block  one_category_block_{$n+1|escape:'htmlall':'UTF-8'}"><span class="count_cat">{$n+1|escape:'htmlall':'UTF-8'}</span>
      {if $n !== 0}
        <span class="delete_one_category"><a class="btn btn-default"><i class="icon-trash"></i></a></span><span class="count_cat">{$n+1|escape:'htmlall':'UTF-8'}</span>
      {/if}
      <input type="hidden" value="{count($fieldset)|escape:'htmlall':'UTF-8'}" class="hidden_count_subcategory_{$n+1|escape:'htmlall':'UTF-8'}">
      {foreach from=$fieldset item=cat  key=k }
        <div class="one_subcategory one_subcategory_{$k+1|escape:'htmlall':'UTF-8'}" {if $k == 0}style="width: 260px;"{/if}>
          {if $k !== 0}
            <span class="delete_one_subcategory"><a class="btn btn-default"><i class="icon-trash"></i></a></span>
          {/if}
          <select name="category" class="chosen fixed-width-xl" id="category"  style="display: none; width:350px;">
            {foreach from=$fields key=key item=field}
              <option {if $cat == $field['name']}selected="selected"{/if} value="{$field['name']|escape:'htmlall':'UTF-8'|html_entity_decode}">{$field['name']|escape:'htmlall':'UTF-8'|html_entity_decode}</option>
            {/foreach}
          </select>
        </div>
      {/foreach}
      <div style="clear: both"></div>
      <div class="more_subcategory more_subcategory_{$n+1|escape:'htmlall':'UTF-8'}" category="{$n+1|escape:'htmlall':'UTF-8'}"><button type="button" class="btn btn-default">{l s='add subcategory' mod='simpleimportproduct'}</button></div>
    </div>
  {/foreach}
  <div class="more_category more_category_1"><button type="button" class="btn btn-default">{l s='add category' mod='simpleimportproduct'}</button></div>
</div>





<script type="text/javascript">
  $('.chosen').chosen()
</script>