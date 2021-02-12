<div class="one_image one_image_block more_images_chosen one_image_block_{$hidden_count_images|escape:'htmlall':'UTF-8'}">
  <span class="delete_one_image">
    <a class="btn btn-default">
      <i class="icon-trash"></i>
    </a>
  </span>
  <span class="count_img">{$hidden_count_images|escape:'htmlall':'UTF-8'}</span>
  <select name="images" class="chosen fixed-width-xl" id="images"  style="display: none; width:350px; ">
    {foreach from=$fields key=key item=field}
      <option value="{$field['name']|escape:'htmlall':'UTF-8'|html_entity_decode}">{$field['name']|escape:'htmlall':'UTF-8'|html_entity_decode}</option>
    {/foreach}
  </select>
  <div style="clear: both"></div>
</div>
