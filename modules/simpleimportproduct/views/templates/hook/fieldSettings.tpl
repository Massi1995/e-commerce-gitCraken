{if !$field_settings_ajax}
  <div class="field_settings_title">
    {l s='Custom Fields' mod='simpleimportproduct'}
  </div>
{/if}
{if $saved_field_settings}
    {$allowedCustomFields = array()}
    <div class="custom_fields_block">
      {foreach from=$saved_field_settings item=field_setting  key=n }
        <div class="field_settings_block" >
          <div class="move"></div>
          <div class="form-group-item">
            <div class="form-group-block">
                {foreach from=$has_hint_field_settings item=settings  key=k }
                  <div class="form-group-field-settings setting_{$k|escape:'htmlall':'UTF-8'}">
                    <label class="control-label col-lg-3">
                        {if isset($settings.hint) && $settings.hint}
                          <span class="label-tooltip" settings-toggle="tooltip" data-html="true" title="" data-original-title="{$settings.hint|escape:'htmlall':'UTF-8'}">
                                    {$settings.name|escape:'htmlall':'UTF-8'}
                                  </span>
                        {else}
                            {$settings.name|escape:'htmlall':'UTF-8'}
                        {/if}
                        {if $k == 'condition_value'}
                          <div class="label-tooltip" settings-toggle="tooltip" data-html="true" title="" data-original-title="{l s='Selected field value in the file' mod='simpleimportproduct'}"></div>
                        {/if}
                        {if $k == 'formula_type'}
                          <div class="tutorial"><a class="need_help" target="_blank" href="http://faq.myprestamodules.com/product-catalog-csv-excel-import/custom-fields-formula-type-features.html">{l s='Need Help?' mod='simpleimportproduct'}</a></div>
                        {/if}
                    </label>
                      {if $settings.type == 'select'}
                        <div class="col-lg-9 ">
                          <select name="{$k|escape:'htmlall':'UTF-8'}" class="chosen fixed-width-xl" id="{$k|escape:'htmlall':'UTF-8'}"  style="display: none; width:350px;">
                              {if $k == 'field'}
                                {foreach from=$settings.values key=key item=field}
                                    {if isset($field['custom']) && !in_array($field['name'], $allowedCustomFields)}
                                        {continue}
                                    {/if}
                                  <option {if $field_setting[$k] == $field['name']}selected="selected"{/if} value="{$field['name']|escape:'htmlall':'UTF-8'|unescape}">{$field['name']|escape:'htmlall':'UTF-8'|unescape}</option>
                                {/foreach}
                              {else}
                                  {foreach from=$settings.values key=key item=field}
                                    <option {if $field_setting[$k] == $key}selected="selected"{/if} value="{$key|escape:'htmlall':'UTF-8'|unescape}">{$field|escape:'htmlall':'UTF-8'|unescape}</option>
                                  {/foreach}
                              {/if}
                          </select>
                        </div>
                      {elseif $settings.type == 'input'}
                        <div class="col-lg-9 ">
                          <input type="text" value="{$field_setting[$k]|escape:'htmlall':'UTF-8'|html_entity_decode}" name="{$k|escape:'htmlall':'UTF-8'}">
                            {if $k == 'new_field'}
                                {if !in_array($field_setting[$k], $allowedCustomFields)}
                                    {$allowedCustomFields[] = $field_setting[$k]}
                                {/if}
                            {/if}
                        </div>
                      {/if}
                  </div>
                {/foreach}
                <div class="form-group-add">
                <label class="control-label col-lg-3"></label>
                <div class="col-lg-9">
                  {if $n != 0}
                    <div class="delete_field_condition">

                    </div>
                  {/if}
                </div>
              </div>
              <div style="clear: both"></div>
            </div>
          </div><!-- /.form-wrapper -->
        </div>
      {/foreach}
    </div>
  {if !$field_settings_ajax}
    <div class="add_field_condition">
        {l s='Add Condition' mod='simpleimportproduct'}
    </div>
    <div class="add_to_fieldlist">
        {l s='Add Custom Fields & Save Import Settings' mod='simpleimportproduct'}
    </div>
    <div class="custom_fields_added">
        {l s='Custom Fields Added' mod='simpleimportproduct'}
    </div>
  {/if}
{else}
  {if !$field_settings_ajax}
    <div class="custom_fields_block">
  {/if}
    <div class="field_settings_block" >
      <div class="move"></div>
      <div class="form-group-item">
        <div class="form-group-block">
          {foreach from=$has_hint_field_settings item=settings  key=k }
            <div class="form-group-field-settings setting_{$k|escape:'htmlall':'UTF-8'}">
              <label class="control-label col-lg-3">
                {if isset($settings.hint) && $settings.hint}
                  <span class="label-tooltip" settings-toggle="tooltip" data-html="true" title="" data-original-title="{$settings.hint|escape:'htmlall':'UTF-8'}">
                                  {$settings.name|escape:'htmlall':'UTF-8'}
                                </span>
                {else}
                  {$settings.name|escape:'htmlall':'UTF-8'}
                {/if}
                  {if $k == 'condition_value'}
                    <div class="label-tooltip" settings-toggle="tooltip" data-html="true" title="" data-original-title="{l s='Selected field value in the file' mod='simpleimportproduct'}"></div>
                  {/if}
                  {if $k == 'formula_type'}
                    <div class="tutorial"><a class="need_help" target="_blank" href="http://faq.myprestamodules.com/product-catalog-csv-excel-import/custom-fields-formula-type-features.html">{l s='Need Help?' mod='simpleimportproduct'}</a></div>
                  {/if}
              </label>
              {if $settings.type == 'select'}
                <div class="col-lg-9 ">
                  <select name="{$k|escape:'htmlall':'UTF-8'}" class="chosen fixed-width-xl" id="{$k|escape:'htmlall':'UTF-8'}"  style="display: none; width:350px;">
                    {if $k == 'field'}
                      {foreach from=$settings.values key=key item=field}
                        <option value="{$field['name']|escape:'htmlall':'UTF-8'|unescape}">{$field['name']|escape:'htmlall':'UTF-8'|unescape}</option>
                      {/foreach}
                    {else}
                      {foreach from=$settings.values key=key item=field}
                        <option  value="{$key|escape:'htmlall':'UTF-8'}">{$field|escape:'htmlall':'UTF-8'|unescape}</option>
                      {/foreach}
                    {/if}
                  </select>
                </div>
              {elseif $settings.type == 'input'}
                <div class="col-lg-9 ">
                  <input type="text" name="{$k|escape:'htmlall':'UTF-8'}">
                  {if $k == 'field_formula'}
  {*                  <div class="label-tooltip" settings-toggle="tooltip" data-html="true" title="" data-original-title="{l s='e.g. to add 10%, formula is *1.10' mod='simpleimportproduct'}"></div>*}
                  {/if}
                </div>
              {/if}
            </div>
          {/foreach}
          <div class="form-group-add">
            <label class="control-label col-lg-3"></label>
            <div class="col-lg-9">
              {if $field_settings_ajax}
                <div class="delete_field_condition">

                </div>
              {/if}
            </div>
          </div>
          <div style="clear: both"></div>
        </div>
      </div><!-- /.form-wrapper -->
    </div>
   {if !$field_settings_ajax}
  	</div>
    <div class="add_field_condition">
        {l s='Add Condition' mod='simpleimportproduct'}
    </div>
    <div class="add_to_fieldlist">
        {l s='Add Custom Fields & Save Import Settings' mod='simpleimportproduct'}
    </div>
    <div class="custom_fields_added">
        {l s='Custom Fields Added' mod='simpleimportproduct'}
    </div>
  {/if}
{/if}

<script type="text/javascript">
  $('.chosen').chosen();
  $('.label-tooltip').tooltip();
</script>