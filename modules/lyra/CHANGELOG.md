1.13.7, 2020-11-24:
- [embedded] Bug fix: Embedded payment fields not correctly displayed since the last gateway JS library delivery on PrestaShop 1.6.
- [embedded] Bug fix: Update token on minicart change on PrestaShop 1.6.
- Minor fix.

1.13.6, 2020-10-27:
- [embedded] Bug fix: Display 3DS result for REST API payments.
- Display warning message when only offline refund is possible.

1.13.5, 2020-10-05:
- Bug fix: Fix IPN management in multistore environment.
- Bug fix: Fix Order->total_real_paid value on payment cancellation.
- Bug fix: Possibility to refund orders offline if merchant did not configure REST API keys.
- [oney] Do not display payment installments for buyer (to avoid inconsistencies).

1.13.4, 2020-08-18:
- [embedded] Bug fix: Error due to strongAuthenticationState field renaming in REST token creation.
- [embedded] Minor code improve: use KR.openPopin() and KR.submit().
- [embedded] Improve payment with embedded fields button display in PrestaShop 1.6.x versions.
- Update payment means logos.

1.13.3, 2020-06-19:
- [embedded] Bug fix: Compatibility of payment with embedded fields with Internet Explorer 11.
- Bug fix: Possibility to make refunds for a payment with many attempts.
- [embedded] Bug fix: Fix JS error if payment token not created.
- Bug fix: Delete double invoice entry in ps_order_invoice_payment table.
- Improve refund payments feature.
- [oney] Phone numbers are mandatory for Oney payment method.

1.13.2, 2020-05-20:
- [embedded] Manage new metadata field format returned in REST API IPN.
- Bug fix: Fix sent data according to new Transaction/Update REST WS.
- Send PrestaShop username and IP as a comment on refund WS calls.
- Improve some plugin translations.
- Improve redirection to gateway page.

1.13.1, 2020-04-07:
- Restore compatibility with PHP v5.3.
- [embedded] Bug fix: Payment fields error relative to new JavaScript client library.

1.13.0, 2020-03-04:
- Bug fix: Fix amount issue relative to multiple partial refunds.
- Bug fix: Shipping costs not included in the refunded amount through the PrestaShop backend.
- [oney] Adding 3x 4x Oney means of payment as submodule.
- Improve payment statuses management.

1.12.1, 2020-02-04:
- [alias] Bug fix: card data was requested even if the buyer chose to use his registered means of payment.

1.12.0, 2020-01-30:
- Bug fix: 3DS result is not correctly saved in backend order details when using embedded payment fields.
- Bug fix: Fix theme config setting for iframe mode.
- [embedded] Added possibility to display REST API fields in pop-in mode.
- Possibility to make refunds for payments.
- Possibility to cancel payment in iframe mode.
- [alias] Added payment by token.
- [technical] Do not use vads\_order\_info2 gateway parameter.
- [oney] Added warning when delivery methods are updated.
- Removed feature data acquisition on merchant website.
- Possibility to not send shopping cart content when not mandatory.
- Restrict payment submodules to specific countries.

1.11.4, 2019-11-28:
- Bug fix: duplicate entry error on table ps\_message\_readed at the end of the payment.

1.11.3, 2019-11-12:
- Bug fix: currency and effective currency fields are inverted in REST API response.
- Bug fix: redirection form loaded from cache in some cases in iframe mode.
- Bug fix: URL error in iframe mode relative to slash at end of base URL.

1.11.2, 2019-07-31:
- Bug fix: JavaScript loaded but not executed in iframe mode (on some PrestaShop 1.7 themes).
- Bug fix: Minimum and maximum amounts are not considered if equal to zero in customer group amount restriction.
- Compatibility with PrestaShop 1.7.6 (fix fatal error on IPN call).
- Possibility to disable payment result display on order details using a flag within lyra.php file (on PrestaShop > 1.7.1.1).

1.11.1, 2019-06-21:
- Bug fix: compatibility of iframe mode with new 1.7.5.x PrestaShop versions.
- Bug fix: filter HTML special characters in REST API placeholders settings.
- Bug fix: Do not display an amount error for multi-carrier orders.
- Improve some configuration fields validation messages.
- Improve amount errors management.
- Added transaction UUID on order details.
- Send products tax rate to payment gateway.
- Fix some plugin translations.
- Display the payment result as a private message on order details (on PrestaShop > 1.7.1.1).

1.11.0, 2019-01-21:
- [embedded] Added payment with embedded fields option using REST API.
- Possibility to propose other payment means by redirection.
- [conecs] Added CONECS payment means logos.
- Improve payment buttons interface.
- Display payment submodules logos in checkout page on PrestaShop 1.7.
- Optimize payment cancellation in iframe mode.

1.10.2, 2018-12-24:
- Fix new signature algorithm name (HMAC-SHA-256).
- Compatibility with PrestaShop 1.7.4.x versions (fix logs directory).
- Update payment means logos.
- Added Spanish translation.
- Improve iframe mode interface.

1.10.1, 2018-07-06:
- Bug fix: Fixed negative amount for order "total_paid_real" field on out of stock orders (PrestaShop 1.5 only).
- Bug fix: Deleted payment error message shown for buyer on out of stock orders (PrestaShop < 1.6.1 only).
- [shatwo] Enable HMAC-SHA-256 signature algorithm by default.
- Ignore spaces at the beginning and the end of certificates on return signature processing.

1.10.0, 2018-05-23:
- Bug fix: relative to JavaScript action of payment button on order validation page (with one page checkout only).
- Bug fix: fatal error when creating order from PrestaShop backend with Colissimo carrier enabled.
- Bug fix: use frontend shop name available under "Preferences > Store contacts".
- Bug fix: do not update order state from "Accepted payment" to "Payment error" when replaying IPN URL for orders with many attempts.
- Enable signature algorithm selection (SHA-1 or HMAC-SHA-256).
- Improve JS code redirecting to payment gateway to avoid possible conflicts with other modules.
- Re-order configuration options in submodules backend.
- Display all links to multilingual documentation files in module backend.
- Possibility to cancel payment in iframe mode.
- Possibility to configure 3D Secure by customer group.
- [technical] Manage enabled/disabled features by plugin variant.