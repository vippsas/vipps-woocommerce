jQuery(document).ready(function ($) {
  $('#check_charge_statuses_now').on('click', function () {
    const $button = $(this);
    $button.addClass('disabled');

    $.post(ajaxurl, {
      action: 'vipps_recurring_force_check_charge_statuses'
    }).done(function (response) {
      $button.removeClass('disabled');
      alert(response);
    }).fail(function (xhr, status, error) {
      $button.removeClass('disabled');
      alert(error);
    });
  })

  $('.notice-vipps-recurring').on('click', '.notice-dismiss', function () {
    const $notice = $(this).parent('.notice.is-dismissible');
    const dismiss_url = $notice.attr('data-dismiss-url');

    if (dismiss_url) {
      $.get(dismiss_url);
    }
  });

  if (pagenow === 'shop_order' || pagenow === 'woocommerce_page_wc-orders') {
    $('button[data-action="capture_payment"]').click(function (e) {
      e.preventDefault();

      const button = $(this)

      if (button.hasClass('disabled')) return;
      button.attr('disabled', 'disabled');
      button.addClass('disabled');

      const { nonce } = VippsRecurringConfig;
      const orderId = button.data('order-id')

      const data = {
        action: 'wc_vipps_recurring_order_action',
        do: 'capture_payment',
        orderId: orderId,
        nonce: nonce
      };

      $.ajax(ajaxurl, {
        method: "POST",
        data,
        cache: false,
        dataType: "json",
        timeout: 0,
        error: function (xhr, message, error) {
          button.removeAttr('disabled');
          button.removeClass('disabled');

          alert("Error performing Vipps/MobilePay Recurring action " + message + " " + error);
        },
        success: function () {
          window.location.reload();
        }
      })
    })
  }

  if (pagenow === 'woocommerce_page_wc-settings') {
    const {currency} = window.VippsRecurringConfig

    function vippsRecurringToggleInputRow(input, currentValue, showIfValue) {
      if (currentValue !== showIfValue) {
        input.closest('tr').hide();
      } else {
        input.closest('tr').show();
      }
    }

    function toggleMobilePayReservationsWarning(brand, checked) {
      const note = wp.i18n.__('Note: Reservations in MobilePay will be cancelled after 14 days. Remember to ship and fulfill your orders.', 'woo-vipps');

      const brandInput = $('#woocommerce_vipps_recurring_brand');
      const fieldset = brandInput.parent();

      if (brand !== 'mobilepay' || checked) {
        fieldset.find('.mobilepay-warning').remove();
      } else {
        fieldset.append('<div class="ui message warning notice notice-warning mobilepay-warning" style="margin-bottom: 0;"><p>' + note + '</p></div>');
      }
    }

    function toggleVippsCurrencyWarning(brand, currency) {
      // translators: %s is the current store currency code
      const note = wp.i18n.sprintf(__('Note: Vipps is only available with the NOK currency. Your store currency is set to %s', 'woo-vipps'), currency);

      const brandInput = $('#woocommerce_vipps_recurring_brand');
      const fieldset = brandInput.parent();

      if (brand !== 'vipps' || currency === 'NOK') {
        fieldset.find('.vipps-currency-warning').remove();
      } else {
        fieldset.append('<div class="ui message warning notice notice-warning vipps-currency-warning" style="margin-bottom: 0;"><p>' + note + '</p></div>');
      }
    }

    const brandInput = $('#woocommerce_vipps_recurring_brand');
    const autoCaptureMobilePayInput = $('#woocommerce_vipps_recurring_auto_capture_mobilepay');

    brandInput.on('change', function (event) {
      vippsRecurringToggleInputRow(autoCaptureMobilePayInput, event.target.value, 'mobilepay');
      toggleMobilePayReservationsWarning(event.target.value, autoCaptureMobilePayInput.is(':checked'));
      toggleVippsCurrencyWarning(event.target.value, currency)
    }).trigger('change');

    autoCaptureMobilePayInput.on('change', function (event) {
      toggleMobilePayReservationsWarning(brandInput.val(), event.target.checked);
    });
  }
});
