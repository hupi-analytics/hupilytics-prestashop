{**
 *   2009-2016 ohmyweb!
 *
 *   @author    ohmyweb <contact@ohmyweb.fr>
 *   @copyright 2009-2016 ohmyweb!
 *   @license   Proprietary - no redistribution without authorization
 *}



<form id="configuration_form" class="defaultForm form-horizontal hupirecommend" method="post" enctype="multipart/form-data" novalidate="">
    <div class="panel" id="fieldset_0">
        <div class="panel-heading">Configuration des recommandations</div>
        
        <div class="form-group">
            <label class="control-label col-lg-3">Activation des recommandations</label>
            <div class="col-lg-9">
                <span class="switch prestashop-switch fixed-width-lg">
                    <input name="active_recommendation" id="active_recommendation_on" value="1"{if $active_recommendation} checked="checked"{/if} type="radio">
                    <label for="active_recommendation_on">Oui</label>
                    <input name="active_recommendation" id="active_recommendation_off" value="0"{if !$active_recommendation} checked="checked"{/if} type="radio">
                    <label for="active_recommendation_off">Non</label>
                    <a class="slide-button btn"></a>
                </span>
            </div>
        </div>
        
        
        <div class="form-wrapper"{if !$active_recommendation} style="display:none;"{/if}>
			{if isset($errorFile) && $errorFile}{$errorFile}{/if}
            <div class="row">
                <div class="form-group">
                    <label class="control-label col-lg-3">Token API</label>
                    <div class="col-lg-9">
                        <input name="hupireco_token" id="hupireco_token" value="{$hupireco_token}" class="form-control" type="text">
                    </div>
                </div>
            </div>
            <!-- /.row -->

            <hr />
            
            <div class="row">
                <div class="col-lg-12">
                    <h4>Affichage sur fiche produit ?</h4>
                    <div class="row form-inline">
                        <div class="col-xs-12 col-lg-8">
                            <span class="switch prestashop-switch fixed-width-lg pull-left">
                                <input name="product_page[active]" id="product_page_on" value="1"{if $product_page.active} checked="checked"{/if} type="radio">
                                <label for="product_page_on">Activé</label>
                                <input name="product_page[active]" id="product_page_off" value="0"{if !$product_page.active} checked="checked"{/if} type="radio">
                                <label for="product_page_off">Désactivé</label> 
                                <a class="slide-button btn"></a>
                            </span>
                            <span class="m-r-l">
                                <label>End Point : <input type="text" name="product_page[end_point]" class="form-control" value="{$product_page.end_point}" /></label>
                            </span>
                            <span>
                                <label>Nombre de produits : <input type="text" name="product_page[nb_products]" class="form-control fixed-width-sm" value="{$product_page.nb_products}" /></label>
                            </span>
                        </div>
                    </div>
                    <!-- /input-group -->
                </div>
                <!-- /.col-lg-6 -->
            </div>
            <!-- /.row -->

            <hr />
            
            <div class="row">
                <div class="col-lg-12">
                    <h4>Affichage sur le panier ?</h4>
                    <div class="row form-inline">
                        <div class="col-xs-12 col-lg-8">
                            <span class="switch prestashop-switch fixed-width-lg pull-left">
                                <input name="shopping_cart[active]" id="shopping_cart_on" value="1"{if $shopping_cart.active} checked="checked"{/if}type="radio">
                                <label for="shopping_cart_on">Activé</label>
                                <input name="shopping_cart[active]" id="shopping_cart_off" value="0"{if !$shopping_cart.active} checked="checked"{/if} type="radio">
                                <label for="shopping_cart_off">Désactivé</label> 
                                <a class="slide-button btn"></a>
                            </span>
                            <span class="m-r-l">
                                <label>End Point : <input type="text" name="shopping_cart[end_point]" class="form-control" value="{$shopping_cart.end_point}" /></label>
                            </span>
                            <span>
                                <label>Nombre de produits : <input type="text" name="shopping_cart[nb_products]" class="form-control fixed-width-sm" value="{$shopping_cart.nb_products}" /></label>
                            </span>
                        </div>
                    </div>
                    <!-- /input-group -->
                </div>
                <!-- /.col-lg-6 -->
            </div>
            <!-- /.row -->

            <hr />
            
            <div class="row">
                <div class="col-lg-12">
                    <h4>Affichage sur les pages catégories ?</h4>
                    <div class="row form-inline">
                        <div class="col-xs-12 col-lg-8">
                            <span class="switch prestashop-switch fixed-width-lg pull-left">
                                <input name="category[active]" id="category_on" value="1"{if $category.active} checked="checked"{/if} type="radio">
                                <label for="category_on">Activé</label>
                                <input name="category[active]" id="category_off" value="0"{if !$category.active} checked="checked"{/if} type="radio">
                                <label for="category_off">Désactivé</label> 
                                <a class="slide-button btn"></a>
                            </span>
                            <span class="m-r-l">
                                <label>End Point : <input type="text" name="category[end_point]" class="form-control" value="{$category.end_point}" /></label>
                            </span>
                            <span>
                                <label>Nombre de produits : <input type="text" name="category[nb_products]" class="form-control fixed-width-sm" value="{$category.nb_products}" /></label>
                            </span>
                        </div>
                    </div>
                    <!-- /input-group -->
                </div>
                <!-- /.col-lg-6 -->
            </div>
            <!-- /.row -->

            <hr />
            
            <div class="row">
                <div class="col-lg-12">
                    <h4>Affichage sur la page d'accueil ?</h4>
                    <div class="row form-inline">
                        <div class="col-xs-12 col-lg-8">
                            <span class="switch prestashop-switch fixed-width-lg pull-left">
                                <input name="homepage[active]" id="homepage_on" value="1"{if $homepage.active} checked="checked"{/if} type="radio">
                                <label for="homepage_on">Activé</label>
                                <input name="homepage[active]" id="homepage_off" value="0"{if !$homepage.active} checked="checked"{/if} type="radio">
                                <label for="homepage_off">Désactivé</label> 
                                <a class="slide-button btn"></a>
                            </span>
                            <span class="m-r-l">
                                <label>End Point : <input type="text" name="homepage[end_point]" class="form-control" value="{$homepage.end_point}" /></label>
                            </span>
                            <span>
                                <label>Nombre de produits : <input type="text" name="homepage[nb_products]" class="form-control fixed-width-sm" value="{$homepage.nb_products}" /></label>
                            </span>
                        </div>
                    </div>
                    <!-- /input-group -->
                </div>
                <!-- /.col-lg-6 -->
            </div>
            <!-- /.row -->
        </div>
        <!-- /.form-wrapper -->

        <div class="panel-footer">
            <button type="submit" value="1" id="configuration_form_submit_btn" name="updateConfig" class="btn btn-default pull-right">
                <i class="process-icon-save"></i> 
                Enregistrer
            </button>
        </div>
    </div>
</form>
