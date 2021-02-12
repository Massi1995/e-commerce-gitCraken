<div id="mpm_sip_category_linking_switch">
        <span class="switch prestashop-switch fixed-width-lg">
            <input type="radio" name="show_category_linking_block" id="show_category_linking_block_on" value="1" {if $is_active}checked{/if}>
            <label for="show_category_linking_block_on">{l s='Yes' mod='simpleimportproduct'}</label>
            <input type="radio" name="show_category_linking_block" id="show_category_linking_block_off" value="0" {if !$is_active}checked{/if}>
            <label for="show_category_linking_block_off">{l s='No' mod='simpleimportproduct'}</label>
            <a class="slide-button btn"></a>
        </span>
</div>

<div id="mpm_sip_category_linking_container" class="panel panel-default">
    <div id="mpm_sip_category_linking_block_main_content">
        {if !empty($save)}
            {foreach $save as $file_name => $shop_categories}
                <div class="mpm-sip-clb-row" data-row-number="{$shop_categories['row_number']|escape:'htmlall':'UTF-8'}">
                    <div class="mpm-sip-clb-file-category-container">
                        <label>{l s='Category name in file:' mod='simpleimportproduct'}</label>
                        <input type="text" class="form-control mpm-sip-clb-file-category" style="width: 70%;"
                               value="{$file_name|escape:'htmlall':'UTF-8'}">
                    </div>

                    <div class="mpm-sip-clb-shop-category-container">
                        <label>{l s='Category in shop:' mod='simpleimportproduct'}</label>
                        <div class="mpm-sip-clb-shop-category-tree">
                            {$shop_categories['tree']|escape:'htmlall':'UTF-8'|unescape:'htmlall'}
                        </div>
                    </div>

                    <div class="mpm-sip-clb-delete-container">
                        <div class="btn btn-danger btn-sm mpm-sip-clb-delete">{l s='Delete' mod='simpleimportproduct'}</div>
                    </div>
                </div>
            {/foreach}
        {else}
            <div class="mpm-sip-clb-row" data-row-number="1">
                <div class="mpm-sip-clb-file-category-container">
                    <label>{l s='Category name in file:' mod='simpleimportproduct'}</label>
                    <input type="text" class="form-control mpm-sip-clb-file-category" value="" style="width: 70%;">
                </div>

                <div class="mpm-sip-clb-shop-category-container">
                    <label>{l s='Category in shop:' mod='simpleimportproduct'}</label>
                    <div class="mpm-sip-clb-shop-category-tree">
                        {$default_tree|escape:'htmlall':'UTF-8'|unescape:'htmlall'}
                    </div>
                </div>

                <div class="mpm-sip-clb-delete-container">
                    <div class="btn btn-danger btn-sm mpm-sip-clb-delete">{l s='Delete' mod='simpleimportproduct'}</div>
                </div>
            </div>
        {/if}
    </div>

    <button type="button" class="btn btn-primary" id="mpm_sip_clb_add">{l s='Add new' mod='simpleimportproduct'}</button>
</div>