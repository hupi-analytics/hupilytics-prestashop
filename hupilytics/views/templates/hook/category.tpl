{**
 *   2009-2016 ohmyweb!
 *
 *   @author	ohmyweb <contact@ohmyweb.fr>
 *   @copyright 2009-2016 ohmyweb!
 *   @license   Proprietary - no redistribution without authorization
*}

{if isset($products) && $products}
<div id="hupirecommend_container" class="block" data-endpoint="{$endpoint}">
    <p class="title_block">{l s='We recommend' mod='hupilytics'}</p>
    <ul id='hupirecommend' class="hupirecommend block_content products-block clearfix">
        {foreach from=$products item=product name=recommendedProducts}
            <li data-product="{$product.id_product}" class="clearfix{if $smarty.foreach.recommendedProducts.last} last_item{elseif $smarty.foreach.recommendedProducts.first} first_item{else} item{/if}">
                <a href="{$product.link|escape:'html':'UTF-8'}" title="{l s='About' mod='hupilytics'} {$product.name|escape:html:'UTF-8'}" class="products-block-image">
                    <img src="{$link->getImageLink($product.link_rewrite, $product.id_image, 'home_default')|escape:'html':'UTF-8'}" alt="{if !empty($product.legend)}{$product.legend|escape:'html':'UTF-8'}{else}{$product.name|escape:'html':'UTF-8'}{/if}" title="{$product.legend|escape:html:'UTF-8'}" width="98" />
                </a>
                <div class="product-content">
                    <h5><a href="{$product.link|escape:'html':'UTF-8'}" title="{l s='About' mod='hupilytics'} {$product.name|escape:html:'UTF-8'}" class="product-name">{$product.name|truncate:14:'...'|escape:html:'UTF-8'}</a></h5>
                    <p class="product-description">{$product.description_short|strip_tags:'UTF-8'|truncate:44}</p>
                </div>
             </li>
        {/foreach}
    </ul>
</div>
{/if}