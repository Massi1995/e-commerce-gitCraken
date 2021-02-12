<div class="panel-heading-gomakoil heading_{$tab|escape:'htmlall':'UTF-8'}">
  <ul class="tabs_block">
    <li class="step_1{if !isset($smarty.get.module_tab) || ( isset($smarty.get.module_tab) && $smarty.get.module_tab == 'step_1' )} active{/if}">
      <a href="{$location_href|escape:'htmlall':'UTF-8'}&module_tab=step_1">
        {l s='Step 1' mod='simpleimportproduct'}
      </a>
    </li>
    <li class="step_2{if isset($smarty.get.module_tab) && $smarty.get.module_tab == 'step_2'} active{/if}">
      <a href="#">
        {l s='Step 2' mod='simpleimportproduct'}
      </a>
    </li>
    <li class="{if isset($smarty.get.module_tab) && $smarty.get.module_tab == 'schedule'} active{/if}">
      <a href="{$location_href|escape:'htmlall':'UTF-8'}&module_tab=schedule">
        {l s='Scheduled Tasks' mod='simpleimportproduct'}
      </a>
    </li>
    <li>
      <a href="http://demo16.myprestamodules.com/example_import.xlsx">
        {l s='Example of import file (XLSX)' mod='simpleimportproduct'}
      </a>
    </li>
  </ul>
</div>