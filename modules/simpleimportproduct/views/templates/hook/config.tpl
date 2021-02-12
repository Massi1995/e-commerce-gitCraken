<div class="alert alert-info">
  {l s='To execute your scheduled tasks, please insert the following line in your cron tasks manager:' mod='simpleimportproduct'}
  <br>
  <br>
  <ul class="list-unstyled">
    <li><code>*/1 * * * * curl "{$schedule_url|escape:'htmlall':'UTF-8'}"</code></li>
  </ul>
</div>
<div class="alert alert-warning">
  <div class="title">{l s='Server time' mod='simpleimportproduct'}</div>
  <div class="description">
      {l s='All dates and times in the scheduler are measured according to the server\’s time, as the scheduler is run purely on the server-side.' mod='simpleimportproduct'}
  </div>
  <div class="time">
      {l s='Current server time:' mod='simpleimportproduct'} {date(Context::getContext()->language->date_format_full)|escape:'htmlall':'UTF-8'} (UTC) {date('P')|escape:'htmlall':'UTF-8'} UTC
  </div>
</div>