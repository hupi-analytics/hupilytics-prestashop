{**
 *   2009-2016 ohmyweb!
 *
 *   @author	ohmyweb <contact@ohmyweb.fr>
 *   @copyright 2009-2016 ohmyweb!
 *   @license   Proprietary - no redistribution without authorization
*}

{if isset($products) && $products}
<div id="hupirecommend-container" class="hupirecommend tab-pane" data-endpoint="{$endpoint}">
{include file="$tpl_dir./product-list.tpl" id='hupirecommend'}
</div>
{/if}
