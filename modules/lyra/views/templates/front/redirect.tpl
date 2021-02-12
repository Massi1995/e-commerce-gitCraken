{**
 * Copyright © Lyra Network.
 * This file is part of Lyra Collect plugin for PrestaShop. See COPYING.md for license details.
 *
 * @author    Lyra Network (https://www.lyra.com/)
 * @copyright Lyra Network
 * @license   https://opensource.org/licenses/afl-3.0.php Academic Free License (AFL 3.0)
 *}

{extends file='checkout/checkout.tpl'}
{block name='content'}
  <section id="content">
    <div class="row">
      <div class="col-xs-12 col-lg-8">
        <section id="lyra_content" class="checkout-step -current" style="text-align: center;">
          <h1 class="step-title h3" style="margin-bottom: 20px;">
            {l s='Redirection to payment gateway' mod='lyra'}
          </h1>

          <div class="content">
            <h3>{$lyra_title|escape:'html':'UTF-8'}</h3>

            <form action="{$lyra_url|escape:'html':'UTF-8'}" method="post" id="lyra_form" name="lyra_form" onsubmit="lyraDisablePayment();">
              {foreach from=$lyra_params key='key' item='value'}
                <input type="hidden" name="{$key|escape:'html':'UTF-8'}" value="{$value|escape:'html':'UTF-8'}" />
              {/foreach}
              <p>
                {if version_compare($smarty.const._PS_VERSION_, '1.7', '>=')}
                  {include file="module:lyra/views/templates/front/iframe/loader.tpl"}
                {else}
                  {include file="./loader.tpl"}
                {/if}
              </p>
            </form>

            <script type="text/javascript">
              function lyraSubmitForm() {
                document.getElementById('lyra_form').submit();
              }

              if (window.addEventListener) { // for most browsers
                window.addEventListener('load', lyraSubmitForm, false);
              } else if (window.attachEvent) { // for IE 8 and earlier versions
                window.attachEvent('onload', lyraSubmitForm);
              }
            </script>
              <p>
                <img src="{$lyra_logo|escape:'html':'UTF-8'}" alt="Lyra Collect" style="margin-bottom: 5px" />
                <br />
              <p>
                {if version_compare($smarty.const._PS_VERSION_, '1.7', '>=')}
                  {include file="module:lyra/views/templates/front/iframe/loader.tpl"}
                {else}
                  {include file="./loader.tpl"}
                {/if}
              </p>
            </form>

            <script type="text/javascript">
              function lyraSubmitForm() {
                document.getElementById('lyra_form').submit();
              }

              if (window.addEventListener) { // for most browsers
                window.addEventListener('load', lyraSubmitForm, false);
              } else if (window.attachEvent) { // for IE 8 and earlier versions
                window.attachEvent('onload', lyraSubmitForm);
              }
            </script>
                {l s='Please wait, you will be redirected to the payment gateway.' mod='lyra'}

                <br /> <br />
                {l s='If nothing happens in 10 seconds, please click the button below.' mod='lyra'}
                <br /><br />
              </p>

              <p class="cart_navigation clearfix">
                <button type="submit" id="lyra_submit_payment" class="button btn btn-default standard-checkout button-medium" >
                  <span>{l s='Pay' mod='lyra'}</span>
                </button>
              </p>
            </form>
          </div>
        </section>
      </div>

       <div class="col-md-4">
      </div>
    </div>

    <script type="text/javascript">
      function lyraDisablePayment() {
        document.getElementById('lyra_submit_payment').disabled = true;
      }

      function lyraSubmitForm() {
        document.getElementById('lyra_submit_payment').click();
      }

      if (window.addEventListener) { // for most browsers
        window.addEventListener('load', lyraSubmitForm, false);
      } else if (window.attachEvent) { // for IE 8 and earlier versions
        window.attachEvent('onload', lyraSubmitForm);
      }
    </script>
  </section>
{/block}