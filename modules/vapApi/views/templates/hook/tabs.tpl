<div class="panel-heading-gomakoil heading_{$tab|escape:'htmlall':'UTF-8'}">
  <ul class="tabs_block">
    <li class="remonte{if !isset($smarty.get.module_tab) || ( isset($smarty.get.module_tab) && $smarty.get.module_tab == 'Remonter_Status' )} active{/if}">
      <button type="button" class="btn btn-primary"><a href="{$location_href|escape:'htmlall':'UTF-8'}&module_tab=Remonter_Status">
          {l s='Remonter Status' mod='vapApi'}
        </a></button>
    </li>
    <li class="descente{if isset($smarty.get.module_tab) && $smarty.get.module_tab == 'Descente_des_commandes'} active{/if}">
      <button type="button" class="btn btn-primary"><a href="{$location_href|escape:'htmlall':'UTF-8'}&module_tab=Descente_commande">
          {l s='Descente des commandes ' mod='vapApi'}
        </a></button>
    </li>
    <li class="{if isset($smarty.get.module_tab) && $smarty.get.module_tab == 'Cron'} active{/if}">
      <button type="button" class="btn btn-primary"><a href="{$location_href|escape:'htmlall':'UTF-8'}&module_tab=schedule">
          {l s='Cron' mod='vapApi'}
        </a></button>
    </li>
    <li class="{if isset($smarty.get.module_tab) && $smarty.get.module_tab == 'Cron'} active{/if}">
      <button type="button" class="btn btn-primary"><a href="{$location_href|escape:'htmlall':'UTF-8'}&module_tab=paramétrage_API">
          {l s='paramétrage API' mod='vapApi'}
        </a></button>
    </li>
  </ul>
</div>