{**
 * Copyright © Lyra Network.
 * This file is part of Lyra Collect plugin for PrestaShop. See COPYING.md for license details.
 *
 * @author    Lyra Network (https://www.lyra.com/)
 * @copyright Lyra Network
 * @license   https://opensource.org/licenses/afl-3.0.php Academic Free License (AFL 3.0)
 *}

<div style="padding-left: 40px;" id="lyra_oneclick_payment_description">
  <ul id="lyra_oneclick_payment_description_1">
    <li>
      <span class="lyra_span">{l s='You will pay with your registered means of payment' mod='lyra'}<b> {$lyra_saved_payment_mean|escape:'html':'UTF-8'}. </b>{l s='No data entry is needed.' mod='lyra'}</span>
    </li>

    <li style="margin: 8px 0px 8px;">
      <span class="lyra_span">{l s='OR' mod='lyra'}</span>
    </li>

    <li>
      <p class="lyra_link" onclick="lyraOneclickPaymentSelect(0)">{l s='Click here to pay with another means of payment.' mod='lyra'}</p>
    </li>
  </ul>
{if ($lyra_std_card_data_mode == '2')}
  <script type="text/javascript">
    function lyraOneclickPaymentSelect(paymentByIdentifier) {
      if (paymentByIdentifier) {
        $('#lyra_oneclick_payment_description').show();
        $('#lyra_standard').hide();
        $('#lyra_payment_by_identifier').val('1');
      } else {
        $('#lyra_oneclick_payment_description').hide();
        $('#lyra_standard').show();
        $('#lyra_payment_by_identifier').val('0');
      }
    }
  </script>
{else}
  <ul id="lyra_oneclick_payment_description_2" style="display: none;">
    {if ($lyra_std_card_data_mode != '5') || $lyra_rest_popin}
      <li>{l s='You will enter payment data after order confirmation.' mod='lyra'}</li>
    {/if}

      <li style="margin: 8px 0px 8px;">
        <span class="lyra_span">{l s='OR' mod='lyra'}</span>
      </li>
      <li>
        <p class="lyra_link" onclick="lyraOneclickPaymentSelect(1)">{l s='Click here to pay with your registered means of payment.' mod='lyra'}</p>
      </li>
  </ul>

  <script type="text/javascript">
    $(document).ready(function() {
       sessionStorage.setItem('lyraIdentifierToken', "{$lyra_rest_identifier_token|escape:'html':'UTF-8'}");
       sessionStorage.setItem('lyraToken', "{$lyra_rest_form_token|escape:'html':'UTF-8'}");
    });

    function lyraOneclickPaymentSelect(paymentByIdentifier) {
      if (paymentByIdentifier) {
        $('#lyra_oneclick_payment_description_1').show();
        $('#lyra_oneclick_payment_description_2').hide()
        $('#lyra_payment_by_identifier').val('1');
      } else {
        $('#lyra_oneclick_payment_description_1').hide();
        $('#lyra_oneclick_payment_description_2').show();
        $('#lyra_payment_by_identifier').val('0');
      }

      {if ($lyra_std_card_data_mode == '5')}
        $('.lyra .kr-form-error').html('');

        var token;
        if ($('#lyra_payment_by_identifier').val() == '1') {
          token = sessionStorage.getItem('lyraIdentifierToken');
        } else {
          token = sessionStorage.getItem('lyraToken');
        }

        KR.setFormConfig({ formToken: token, language: LYRA_LANGUAGE });
      {/if}
    }
    </script>
{/if}

{if ($lyra_std_card_data_mode != '5')}
    {if version_compare($smarty.const._PS_VERSION_, '1.6', '<')}
      <input id="lyra_standard_link" value="{l s='Pay' mod='lyra'}" class="button" />
    {else}
      <button id="lyra_standard_link" class="button btn btn-default standard-checkout button-medium">
        <span>{l s='Pay' mod='lyra'}</span>
      </button>
    {/if}
{/if}
</div>