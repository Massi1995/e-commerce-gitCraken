<div class="productTabs col-lg-2 col-md-3">
  <div class="import_tabs list-group">
    {foreach $import_tabs as $key=>$tab}
      <div class="list-group-item import_tab{if $key=='information'} active{/if}" data-tab="{$key|escape:'htmlall':'UTF-8'}">
        {$tab|escape:'htmlall':'UTF-8'}
      </div>
    {/foreach}
  </div>
</div>
<div class="import_progress">

</div>
