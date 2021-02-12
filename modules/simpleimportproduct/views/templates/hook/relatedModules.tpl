<div class="related_modules panel">
  <div class="panel-heading">
    <i class="icon-suitcase"></i>
    <span>{l s='Support & related modules' mod='simpleimportproduct'}</span>
  </div>
  <div class="content">
    <div class="info_block">
      <div class="top_info">
        <div class="title">
            {l s='About module & support' mod='simpleimportproduct'}
        </div>
        <div class="new_version">
          {if $last_version && $last_version != $current_version}
            <span>{l s='UPDATE AVAILABLE!' mod='simpleimportproduct'}</span>
              <a href="https://addons.prestashop.com/en/agency-products.php" target="_blank">
                <i class="ip-download"></i>
                  {l s='UPDATE' mod='simpleimportproduct'} [{$last_version}]
              </a>
          {/if}
        </div>
      </div>
        <div class="middle_info">
          <div class="item">
            <i class="ip-info-circle-solid"></i>
            <span>{l s='Module Version:' mod='simpleimportproduct'}</span>
            <span class="current_version">{$current_version}</span>
            {if $last_version && $last_version != $current_version}
              <i class="ip-info-circle-solid alert_version"></i>
            {/if}
          </div>
          <div class="item">
            <i class="ip-heart"></i>
            <span>{l s='Love this product? Please rate it!' mod='simpleimportproduct'}</span>
            <a target="_blank" href="https://addons.prestashop.com//en/ratings.php">
                {l s='Rate module' mod='simpleimportproduct'}
              <i class="icon-share-square"></i>
            </a>
          </div>
          <div class="item">
            <i class="ip-book"></i>
            <span>{l s='The best start for beginners to use module.' mod='simpleimportproduct'}</span>
            <a target="_blank" href="http://faq.myprestamodules.com/product-catalog-csv-excel-import.html">
                {l s='Documntation' mod='simpleimportproduct'}
              <i class="icon-share-square"></i>
            </a>
          </div>
          <div class="item">
            <i class="ip-facebook"></i>
            <span>{l s='Follow us on Facebook:' mod='simpleimportproduct'}</span>
            <div class="facebook">
              <div id="fb-root"></div>
              <script>(function(d, s, id) {
                  var js, fjs = d.getElementsByTagName(s)[0];
                  if (d.getElementById(id)) return;
                  js = d.createElement(s); js.id = id;
                  js.src = 'https://connect.facebook.net/en_US/sdk.js#xfbml=1&version=v2.11&appId=407929015935690';
                  fjs.parentNode.insertBefore(js, fjs);
                }(document, 'script', 'facebook-jssdk'));</script>
              <div class="fb-like" data-href="https://www.facebook.com/myprestamodules" data-layout="button_count" data-action="like" data-size="small" data-show-faces="false" data-share="true"></div>
            </div>
          </div>
          <div class="item">
            <i class="ip-help-web-button"></i>
            <span>{l s='Need help or found problems?' mod='simpleimportproduct'}</span>
            <a target="_blank" href="https://addons.prestashop.com/en/contact-us?id_product=19091">
                {l s='Contact us' mod='simpleimportproduct'}
              <i class="icon-share-square"></i>
            </a>
          </div>
        </div>
      <a target="_blank" href="https://addons.prestashop.com/en/2_community-developer?contributor=258471" class="browse_all">
          {l s='BROWSE ALL OUR PRODUCTS' mod='simpleimportproduct'}
      </a>
    </div>
    <div class="modules_block">
      <iframe class="iframe_modules" style="border: none; width: 100%" src="//myprestamodules.com/modules/relatedmodules/get-products.php?id_module=19091"></iframe>
    </div>
  </div>
</div>