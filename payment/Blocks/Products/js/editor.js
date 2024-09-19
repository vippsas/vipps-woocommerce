( function( wp ) {

if (!wp) return; 
if (typeof(wp['blocks']) == 'undefined') return;
if (typeof(wp['element']) == 'undefined') return;

const { getCategories, registerBlockType, setCategories } = wp.blocks;
var el = wp.element.createElement;
var __ = wp.i18n.__;


const VippsBuyNow = ( props ) => {
       return el('div', {"class": 'wp-block-button  wc-block-components-product-button wc-block-button-vipps'},
                 el ('a', {"javascript":'void(0)', "data-product_id":props.product.id,
                        "class": "single-product button vipps-buy-now wp-block-button__link",
                        "title":VippsConfig['BuyNowWithVipps']},
                       el('span', { "class": "vippsbuynow" }, VippsConfig['BuyNowWith']),
                       el('img', { "class":"inline vipps-logo negative", 
                                  "src":VippsConfig['vippslogourl'],
                                  "alt":"Vipps", "border":0})));
};


const blockConfig = {
        category: 'woocommerce',
        keywords: [ __( 'WooCommerce', 'woo-gutenberg-products-block' ) ],
        supports: {
                html: false,
        },
        parent: [ 'woocommerce/all-products' ],
        icon: el('img', {"class": "vipps-smile vipps-component-icon", "src": VippsConfig['vippssmileurl'] }),
        title: VippsConfig['vippsbuynowbutton'],
        description: VippsConfig['vippsbuynowdescription'],
        edit: function( props ) {
                return el(VippsBuyNow, { product:  {} } );
        }
};

registerBlockType( 'vipps/buy-now', blockConfig );

}(
	window.wp
) );


