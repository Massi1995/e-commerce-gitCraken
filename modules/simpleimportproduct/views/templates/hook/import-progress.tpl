<div class="products_import">
  <div class="progress_bar{if $error_message} error{/if}">
    <div class="title">
      {l s='Product import progress' mod='simpleimportproduct'}
    </div>
    <div class="progress_block">
      <div class="progress_count">
        {if $error_message}
          {l s='There was an import error!' mod='simpleimportproduct'}
        {else}
          {$progress|escape:'htmlall':'UTF-8'}% {l s='completed' mod='simpleimportproduct'}
        {/if}
      </div>
      <div class="time">
        {l s='Time ellapsed: ' mod='simpleimportproduct'} {$duration|escape:'htmlall':'UTF-8'}
      </div>
      <div style="clear: both"></div>
    </div>
    <div class="bar_container">
      <div class="bar{if !$finished } active{/if}" style="width: {$progress|escape:'htmlall':'UTF-8'}%">

      </div>
    </div>
  </div>
  {if $finished}
    <div class="back_to_settings">{l s='Back to settings' mod='simpleimportproduct'}</div>
    <div class="re_import">{l s='Re-import' mod='simpleimportproduct'}</div>
    <div></div>
  {else}
    <div class="stop">
      {l s='Stop import' mod='simpleimportproduct'}
    </div>
  {/if}

  <div class="import_info">
    {if $error_message}
      <div class="error">
        <strong>{l s='Error!' mod='simpleimportproduct'}</strong>
        {if $error_message == 'fatal'}
          <span>Some error occurred, please <a target="_blank" href="https://addons.prestashop.com/en/contact-us?id_product=19091">contact us</a> or see <a class="log" target="_blank" href="{$log_folder|escape:'htmlall':'UTF-8'}error.log">error logs</a></span>
        {else}
          <span>{$error_message|escape:'htmlall':'UTF-8'|html_entity_decode}</span>
        {/if}
      </div>
    {else}
      <div class="status{if !$finished} active{/if}">
        <strong>{l s='Status: ' mod='simpleimportproduct'}</strong><span>{$status|escape:'htmlall':'UTF-8'}</span>
      </div>
    {/if}
    <div class="processed">
      <strong>{l s='Processed: ' mod='simpleimportproduct'}</strong>{if $total} {$current|escape:'htmlall':'UTF-8'}/{$total}{/if}
    </div>
    <div class="skipped">
      <strong>{l s='With errors: ' mod='simpleimportproduct'} </strong>{$error_products|escape:'htmlall':'UTF-8'} {if $error_products}<a href="{$log_folder|escape:'htmlall':'UTF-8'}error_logs.csv" target="_blank">Open log file</a>{/if}
    </div>
  </div>
</div>
<div class="images_import">
  <div class="progress_bar{if $error_message} error{/if}">
    <div class="title">
      {l s='Images import progress' mod='simpleimportproduct'}
    </div>
    <div class="progress_block">
      <div class="progress_count">
        {if $error_message}
          {l s='There was an import error!' mod='simpleimportproduct'}
        {else}
          {$images_data['progress']|escape:'htmlall':'UTF-8'}% {l s='completed' mod='simpleimportproduct'}
        {/if}
      </div>
      <div class="time">
        {l s='Time ellapsed: ' mod='simpleimportproduct'} {$images_data['duration']|escape:'htmlall':'UTF-8'}
      </div>
      <div style="clear: both"></div>
    </div>
    <div class="bar_container">
      <div class="bar{if !$finished } active{/if}" style="width: {$images_data['progress']|escape:'htmlall':'UTF-8'}%">

      </div>
    </div>
  </div>
  <div class="import_info">
    <div class="copied">
      <strong>{l s='Copied images: ' mod='simpleimportproduct'}</strong> {$images_data['copied']|escape:'htmlall':'UTF-8'} of {$images_data['need_copy']|escape:'htmlall':'UTF-8'}
    </div>
    <div class="skipped">
      <strong>{l s='Skipped images: ' mod='simpleimportproduct'}</strong>{$images_data['skipped']|escape:'htmlall':'UTF-8'} {if $images_data['skipped']|escape:'htmlall':'UTF-8'}<a href="{$log_folder|escape:'htmlall':'UTF-8'}image_logs.csv" target="_blank">Open log file</a>{/if}
    </div>
    <div class="thumbnails">
      <strong>{l s='Thumbnails generated: ' mod='simpleimportproduct'} </strong> {$images_data['thumbnails_generated']|escape:'htmlall':'UTF-8'} of {$images_data['thumbnails_total']|escape:'htmlall':'UTF-8'}
    </div>
  </div>

</div>
<div class="notice">
  <span class="important">{l s='Important:' mod='simpleimportproduct'}</span>
  The import process can take from a few seconds to several hours, depending on the number of items.
  Don't close this window and don't refresh this page until the import process is completed.
  After successful import, you will see the corresponding message.
</div>