<div class="mpm-sip-clb-row" data-row-number="{$row_number|escape:'htmlall':'UTF-8'}">
    <div class="mpm-sip-clb-file-category-container">
        <label>{l s='Category name in file:' mod='simpleimportproduct'}</label>
        <input type="text" class="form-control mpm-sip-clb-file-category" value="" style="width: 70%;">
    </div>

    <div class="mpm-sip-clb-shop-category-container">
        <label>{l s='Category in shop:' mod='simpleimportproduct'}</label>
        <div class="mpm-sip-clb-shop-category-tree">
            {$tree|escape:'htmlall':'UTF-8'|unescape:'htmlall'}
        </div>
    </div>

    <div class="mpm-sip-clb-delete-container">
        <div class="btn btn-danger btn-sm mpm-sip-clb-delete">{l s='Delete' mod='simpleimportproduct'}</div>
    </div>
</div>