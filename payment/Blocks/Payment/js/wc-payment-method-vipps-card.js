
(function () { 


// Imports
const { __ } = wp.i18n;
const { decodeEntities }  = wp.htmlEntities;
const { getSetting }  = wc.wcSettings;
const { registerPaymentMethod }  = wc.wcBlocksRegistry;
const { applyFilters } = wp.hooks;

//( 'hookName', content, arg1, arg2, ... )


// Data
const settings = getSetting('vipps_card_data', {});
const defaultLabel = VippsLocale['pay_with_card'];
const label = decodeEntities(settings.title) || defaultLabel;
const iconsrc = settings.iconsrc;


/**
 * Card checkout uses WooCommerce's standard order submission and gateway redirect.
 * Content only renders the description: it must not intercept checkout success
 * or clear redirectUrl as the Vipps widget method does.
 */
const Content = () => {
        var content = React.createElement(
		'div',
		null,
		decodeEntities(settings.description || '')
	);
       return applyFilters('woo_vipps_card_checkout_description', content, settings);
};

/** Render the card title and card-brand logos, preserving merchant/plugin filters. */
const Label = props => {
        const { PaymentMethodLabel } = props.components;
        let textlabel = React.createElement( 'span', null, decodeEntities(settings.title || ''));
        let icon = React.createElement('img', { style: {display: 'inline-block'}, alt: textlabel, title: textlabel, className: 'vipps-card-payment-logo', src:iconsrc});
        let label =  React.createElement(PaymentMethodLabel, { text: textlabel, icon: icon });
        return applyFilters('woo_vipps_card_checkout_label', label, settings);
};

/** Allow existing integrations to control visibility after server-side enablement. */
const canMakePayment = (args) => {
        var candoit = true;
        return applyFilters('woo_vipps_card_checkout_block_show_vipps', candoit, settings);
};

/**
 * Register vipps_card with a normal WooCommerce place-order button. Only its label
 * is customized; the legacy Store API adapter calls the card gateway and Blocks
 * follows its redirect. No widget controller or custom payment events are needed.
 */
const VippsCardPaymentMethod = {
        name: 'vipps_card',
        label: React.createElement(Label, null),
        content: React.createElement(Content, null),
        edit: React.createElement(Content, null),
        placeOrderButtonLabel: VippsLocale['pay_with_card'],
        icons: null,
        canMakePayment: canMakePayment,
        ariaLabel: label
};

registerPaymentMethod(VippsCardPaymentMethod);


}());
