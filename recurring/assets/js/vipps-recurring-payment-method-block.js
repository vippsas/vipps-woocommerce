/* The payment plugin owns the SDK controller; recurring supplies its method data and hooks. */
(() => {
  window.registerVippsWidgetPaymentMethod(
    window.wc.wcSettings.getSetting('vipps_recurring_data', {}),
    'vipps_recurring',
    {
      woo_vipps_checkout_description: 'wc_vipps_recurring_checkout_description',
      woo_vipps_checkout_label: 'wc_vipps_recurring_checkout_label',
      woo_vipps_checkout_block_show_vipps: 'wc_vipps_recurring_checkout_show_gateway'
    }
  );
})();
