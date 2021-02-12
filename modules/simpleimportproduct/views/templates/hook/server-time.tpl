<div class="help_info">
  <div class="top">
      {l s='This is your current server Coordinated Universal Time (UTC):' mod='simpleimportproduct'}
  </div>
  <div class="hour">{date('H:i:s')|escape:'htmlall':'UTF-8'}</div>
  <div class="date">{date('d F Y')|escape:'htmlall':'UTF-8'}</div>
  <div class="timezone">{l s='Coordinated Universal Time' mod='simpleimportproduct'}<br>(UTC) {date('P')|escape:'htmlall':'UTF-8'} UTC</div>
</div>