( function( wp ) {

if (!wp || (typeof(wp['element']) == 'undefined') || typeof(wp['i18n']) == 'undefined') {
 console.log("wp.element or wp.i18n not found - cannot load support for WooCommerce blocks");
}
if (typeof(window['React']) == 'undefined') {
  console.log("No react, so we can't continue adding support for Vipps to blocks."); 
  return;
}

var el = wp.element.createElement;
var __ = wp.i18n.__;


const VippsBuyNow = ( props ) => {
    if (typeof props.product['description'] != 'undefined' && props.product.description.match(/data-vipps-purchasable='1'/)) {
       // Ensure the Vipps-behaviour gets attached to the components.
       React.useLayoutEffect(() => {
          var event = new Event('vippsInit');
          document.body.dispatchEvent(event);
       });
       return el('div', {"class": 'wp-block-button  wc-block-components-product-button wc-block-button-vipps'},
                 el ('a', {"javascript":'void(0)', "data-product_id":props.product.id,
                        "class": "single-product button vipps-buy-now wp-block-button__link",
                        "title":VippsConfig['BuyNowWithVipps']},
                       el('span', { "class": "vippsbuynow" }, VippsConfig['BuyNowWith']),
                       el('img', { "class":"inline vipps-logo negative", 
                                  "src":VippsConfig['vippslogourl'],
                                  "alt":"Vipps", "border":0})));
    } else {
      // console.log("Product %j not purchasable", props.product);
    }
    return null;
};

const { registerBlockComponent } = wc.wcBlocksRegistry;
const mainBlock = 'woocommerce/all-products';

registerBlockComponent( {
        main: mainBlock,
        blockName:'vipps/buy-now',
        component: VippsBuyNow,
        context: mainBlock,
});

}(
	window.wp
) );
