/**
 *   2009-2016 ohmyweb!
 *
 *   @author    ohmyweb <contact@ohmyweb.fr>
 *   @copyright 2009-2016 ohmyweb!
 *   @license   Proprietary - no redistribution without authorization
 */

/* globals $, _paq, jQuery */

var HupilyticsEnhancedECommerce = {
        setCustomVariable: function(object) {
            id = parseInt(object.id);
            cvar_name = object.cvar_name;
            cvar_value = object.cvar_value;
            scope = object.scope;
            
            if(cvar_name == "current_ts") {
                if (!Date.now) {
                    Date.now = function() {
                        return new Date().getTime();
                    }
                }
                cvar_value = Math.floor(Date.now() / 1000);
            }
            
            _paq.push(['setCustomVariable', id, cvar_name, cvar_value, scope]);
        },

        add: function(Product, Order, Impression) {
            var Products = {};
            var Orders = {};

            var ProductFieldObject = ['id', 'name', 'category', 'brand', 'variant', 'price', 'quantity', 'coupon', 'list', 'position', 'dimension1'];
            var OrderFieldObject = ['id', 'affiliation', 'revenue', 'tax', 'shipping', 'coupon', 'list', 'step', 'option'];

            if (Product != null) {
                if (Impression && Product.quantity !== undefined) {
                    delete Product.quantity;
                }

                for (var productKey in Product) {
                    for (var i = 0; i < ProductFieldObject.length; i++) {
                        if (productKey.toLowerCase() == ProductFieldObject[i]) {
                            if (Product[productKey] != null) {
                                Products[productKey.toLowerCase()] = Product[productKey];
                            }

                        }
                    }

                }
            }

            if (Order != null) {
                for (var orderKey in Order) {
                    for (var j = 0; j < OrderFieldObject.length; j++) {
                        if (orderKey.toLowerCase() == OrderFieldObject[j]) {
                            Orders[orderKey.toLowerCase()] = Order[orderKey];
                        }
                    }
                }
            }
            
            if (Impression) {
                console.log('setEcommerceView ' + Products.id + ' | ' + Products.name + ' | ' + Products.category + ' | ' + Products.price);
                _paq.push(['setEcommerceView', Products.id, Products.name, Products.category, Products.price]);
            } else {
                console.log('addEcommerceItem ' + Products.id + ' | ' + Products.name + ' | ' + Products.category + ' | ' + Products.price + ' | ' + Products.quantity);
                _paq.push(['addEcommerceItem', Products.id, Products.name, Products.category, Products.price, Products.quantity]);
            }
        },

        trackCartUpdate: function(Amount) {
            console.log('trackEcommerceCartUpdate => ' + Amount);
            _paq.push(['trackEcommerceCartUpdate', Amount]);
        },
        
        addProductDetailView: function(Product) {
            this.add(Product, false, true);
            /*_paq.push([setEcommerceView, ]);
            ga('ec:setAction', 'detail');
            ga('send', 'event', 'UX', 'detail', 'Product Detail View',{'nonInteraction': 1});*/
            /*console.log('trackEvent => UX : Product Detail View');
            _paq.push(['trackEvent', 'Product Detail View', 'UX']);*/
        },
        
        addToCart: function(Product) {
            this.add(Product);

            console.log('trackEvent => UX : Add to Cart : '+Product.quantity+'x'+Product.id);
            _paq.push(['trackEvent', 'Add to Cart', Product.id, Product.quantity]);
        },

        removeFromCart: function(Product) {
            this.add(Product);

            console.log('trackEvent => UX : Remove From cart : '+Product.quantity+'x'+Product.id);
            _paq.push(['trackEvent', 'Remove From cart', Product.id, Product.quantity]);
        },

        addProductImpression: function(Product) {
            //ga('send', 'pageview');
        },
        
        /**
		id, type, affiliation, revenue, tax, shipping and coupon.
		**/
		refundByOrderId: function(Order) {
			/**
			 * Refund an entire transaction.
			 **/
//			ga('ec:setAction', 'refund', {
//				'id': Order.id // Transaction ID is only required field for full refund.
//			});
            console.log('trackEvent => Ecommerce : Refund');
            _paq.push(['trackEvent', 'Refund', 'Ecommerce']);
		},

        refundByProduct: function(Order) {
            /**
             * Refund a single product.
             **/
            //this.add(Product);

//            ga('ec:setAction', 'refund', {
//                'id': Order.id, // Transaction ID is required for partial refund.
//            });
            console.log('trackEvent => Ecommerce : Refund');
            _paq.push(['trackEvent', 'Refund', 'Ecommerce']);
//            ga('send', 'event', 'Ecommerce', 'Refund', {'nonInteraction': 1});
        },

        addProductClick: function(Product) {
            var ClickPoint = jQuery('a[href$="' + decodeURIComponent(Product.url) + '"].quick-view');

            ClickPoint.on("click", function() {
                HupilyticsEnhancedECommerce.add(Product);
                /*ga('ec:setAction', 'click', {
                    list: Product.list
                });*/

                console.log('trackEvent => Clic : Product Quick View');
                _paq.push(['trackEvent', 'Product Quick View', 'Clic', Product.list]);
            });

        },

        addProductRecommendationClick: function(Product) {
            console.log('init tracking recommendation');
            jQuery('#hupirecommend > [data-product]').each(function() {
                jQuery(this).find('a[href]:not(.ajax_add_to_cart_button)').bind('click', function(e) {
                    e.preventDefault();
                    id_product = jQuery(this).closest('[data-product]').data('product');
                    endpoint = jQuery(this).closest('[data-endpoint]').data('endpoint');
                    _paq.push(['trackEvent', 'Recommandation_HUPI', endpoint, id_product]);

                    var hrefProduct = $(this).attr('href');
                    console.log('Redirection : '+ hrefProduct);
                    setTimeout(function() { window.location.href = hrefProduct; }, 500);
                });
            });
        },
        
        addProductClickByHttpReferal: function(Product) {
            //this.add(Product, false, true);
//            ga('ec:setAction', 'click', {
//                list: Product.list
//            });
            console.log('trackEvent => Clic : Product Click');
            _paq.push(['trackEvent', 'Product Click', 'Clic', Product.list]);
        },
        
        addTransaction: function(Order) {

            //this.add(Product);
//            ga('ec:setAction', 'purchase', Order);
//            console.log('trackEvent => purchase : Transaction');
//            _paq.push(['trackEvent', 'Transaction', 'purchase']);
            
            console.log('trackEcommerceOrder' + Order.id + ' | ' + Order.total_tax_incl + ' | ' + Order.total_tax_excl + ' | ' + Order.tax + ' | ' + Order.shipping);
            _paq.push(['trackEcommerceOrder', Order.id, Order.total_tax_incl, Order.total_tax_excl, Order.tax, Order.shipping]);
            
//            ga('send', 'event','Transaction','purchase', {
//                'hitCallback': function() {
//                    $.get(Order.url, {
//                        orderid: Order.id,
//                        customer: Order.customer
//                    });
//                }
//            });

        },

        addCheckout: function(Step) {
//            ga('ec:setAction', 'checkout', {
//                'step': Step
//                //'option':'Visa'
//            });
            //ga('send', 'pageview');
        }
}