
<div class="one_subcategory one_subcategory_{$hidden_count_subcategory|escape:'htmlall':'UTF-8'}">
  <span class="delete_one_subcategory"><a class="btn btn-default"><i class="icon-trash"></i></a></span>
  <select name="category" class="chosen fixed-width-xl" id="category"  style="display: none; width:350px; ">
    {foreach from=$fields key=key item=field}
      <option value="{$field['name']|escape:'htmlall':'UTF-8'|html_entity_decode}">{$field['name']|escape:'htmlall':'UTF-8'|html_entity_decode}</option>
    {/foreach}
  </select>
</div>
<div style="clear: both"></div>

<script type="text/javascript">
  $('.chosen').chosen();
</script>