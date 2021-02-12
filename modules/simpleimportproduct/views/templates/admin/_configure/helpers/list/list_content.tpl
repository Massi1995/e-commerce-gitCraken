{extends file="helpers/list/list_content.tpl"}
{block name="td_content"}
  {$tr.$key|escape:'html':'UTF-8'|unescape}
{/block}