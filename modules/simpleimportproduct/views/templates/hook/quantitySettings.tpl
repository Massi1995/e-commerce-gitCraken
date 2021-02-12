{if !$quantity_settings_ajax}
  <div class="quantity_settings_title">
    {l s='Quantity Rules' mod='simpleimportproduct'}
  </div>
{/if}
{if $saved_quantity_settings}
  {foreach from=$saved_quantity_settings item=quantity_setting  key=n }
    <div class="quantity_settings_block" >
      <div class="form-group-item">
        <div class="form-group-block">
            {foreach from=$has_hint_quantity_settings item=settings  key=k }
              <div class="form-group-quantity-settings setting_{$k|escape:'htmlall':'UTF-8'}">
                <label class="control-label col-lg-3">
                    {if isset($settings.hint) && $settings.hint}
                      <span class="label-tooltip" settings-toggle="tooltip" data-html="true" title="" data-original-title="{$settings.hint|escape:'htmlall':'UTF-8'}">
                                {$settings.name|escape:'htmlall':'UTF-8'}
                              </span>
                    {else}
                        {$settings.name|escape:'htmlall':'UTF-8'}
                    {/if}
                    {if $k == 'condition_value'}
                      <div class="label-tooltip" settings-toggle="tooltip" data-html="true" title="" data-original-title="{l s='Selected field value in the store or file' mod='simpleimportproduct'}"></div>
                    {/if}
                </label>
                  {if $settings.type == 'select'}
                    <div class="col-lg-9 ">
                      <select name="{$k|escape:'htmlall':'UTF-8'}" class="chosen fixed-width-xl" id="{$k|escape:'htmlall':'UTF-8'}"  style="display: none; width:350px;">
                          {foreach from=$settings.values key=key item=field}
                            <option {if $quantity_setting[$k] == $key}selected="selected"{/if} value="{$key|escape:'htmlall':'UTF-8'|unescape}">{$field|escape:'htmlall':'UTF-8'|unescape}</option>
                          {/foreach}
                      </select>
                    </div>
                  {elseif $settings.type == 'input'}
                    <div class="col-lg-9 ">
                      <input type="text" value="{$quantity_setting[$k]|escape:'htmlall':'UTF-8'}" name="{$k|escape:'htmlall':'UTF-8'}">
                        {if $k == 'quantity_formula'}
                          <div class="label-tooltip" settings-toggle="tooltip" data-html="true" title="" data-original-title="{l s='e.g. to add 10 stock, formula is +10' mod='simpleimportproduct'}"></div>
                        {/if}
                    </div>
                  {/if}
              </div>
            {/foreach}
          <div style="clear: both"></div>
        </div>

        <div class="form-group-add">
          <label class="control-label col-lg-3"></label>
          <div class="col-lg-9">
            <button type="button" class="btn btn-default add_quantity_condition">{l s='Add Rule' mod='simpleimportproduct'}</button>
              {if $n != 0}
                &nbsp;&nbsp;<button type="button" class="btn btn-default delete_quantity_condition">{l s='delete' mod='simpleimportproduct'}</button>
              {/if}
          </div>
        </div>
      </div><!-- /.form-wrapper -->
    </div>
  {/foreach}
{else}
  <div class="quantity_settings_block" >
    <div class="form-group-item">
      <div class="form-group-block">
        {foreach from=$has_hint_quantity_settings item=settings  key=k }
          <div class="form-group-quantity-settings setting_{$k|escape:'htmlall':'UTF-8'}">
            <label class="control-label col-lg-3">
              {if isset($settings.hint) && $settings.hint}
                <span class="label-tooltip" settings-toggle="tooltip" data-html="true" title="" data-original-title="{$settings.hint|escape:'htmlall':'UTF-8'}">
                                {$settings.name|escape:'htmlall':'UTF-8'}
                              </span>
              {else}
                {$settings.name|escape:'htmlall':'UTF-8'}
              {/if}
              {if $k == 'condition_value'}
                <div class="label-tooltip" settings-toggle="tooltip" data-html="true" title="" data-original-title="{l s='Selected field value in the store or file' mod='simpleimportproduct'}"></div>
              {/if}
            </label>
            {if $settings.type == 'select'}
              <div class="col-lg-9 ">
                <select name="{$k|escape:'htmlall':'UTF-8'}" class="chosen fixed-width-xl" id="{$k|escape:'htmlall':'UTF-8'}"  style="display: none; width:350px;">
                  {foreach from=$settings.values key=key item=field}
                    <option  value="{$key|escape:'htmlall':'UTF-8'}">{$field|escape:'htmlall':'UTF-8'|unescape}</option>
                  {/foreach}
                </select>
              </div>
            {elseif $settings.type == 'input'}
              <div class="col-lg-9 ">
                <input type="text" name="{$k|escape:'htmlall':'UTF-8'}">
                {if $k == 'quantity_formula'}
                  <div class="label-tooltip" settings-toggle="tooltip" data-html="true" title="" data-original-title="{l s='e.g. to add 10 stock, formula is +10' mod='simpleimportproduct'}"></div>
                {/if}
              </div>
            {/if}
          </div>
        {/foreach}
        <div style="clear: both"></div>
      </div>

      <div class="form-group-add">
        <label class="control-label col-lg-3"></label>
        <div class="col-lg-9">
          <button type="button" class="btn btn-default add_quantity_condition">{l s='Add Rule' mod='simpleimportproduct'}</button>
          {if $quantity_settings_ajax}
            &nbsp;&nbsp;<button type="button" class="btn btn-default delete_quantity_condition">{l s='delete' mod='simpleimportproduct'}</button>
          {/if}
        </div>
      </div>
    </div><!-- /.form-wrapper -->
  </div>
{/if}

<script type="text/javascript">
  $('.chosen').chosen();
  $('.label-tooltip').tooltip();
</script>