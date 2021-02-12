<div class="one_category one_category_block one_category_block_{$hidden_count_category|escape:'htmlall':'UTF-8'}">
  <span class="delete_one_category"><a class="btn btn-default"><i class="icon-trash"></i></a></span><span class="count_cat">{$hidden_count_category|escape:'htmlall':'UTF-8'}</span>
  <input type="hidden" value="1" class="hidden_count_subcategory_{$hidden_count_category|escape:'htmlall':'UTF-8'}">
  <div class="one_subcategory one_subcategory_1" style="width: 260px;">
    <select name="category" class="chosen fixed-width-xl" id="category"  style="display: none; width:350px; ">
      {foreach from=$fields key=key item=field}
        <option value="{$field['name']|escape:'htmlall':'UTF-8'|html_entity_decode}">{$field['name']|escape:'htmlall':'UTF-8'|html_entity_decode}</option>
      {/foreach}
    </select>
  </div>
  <div style="clear: both"></div>
  <div class="more_subcategory more_subcategory_{$hidden_count_category|escape:'htmlall':'UTF-8'|html_entity_decode}" category="{$hidden_count_category|escape:'htmlall':'UTF-8'|html_entity_decode}"><button type="button" class="btn btn-default">{l s='add subcategory' mod='simpleimportproduct'}</button></div>
</div>
<script type="text/javascript">
  $('.chosen').chosen();
</script>