const {__} = wp.i18n;

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

      const nonce = VippsRecurringConfig['nonce'];
      const orderId = button.data('order-id')

      const data = {
        action: 'woo_vipps_recurring_order_action',
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
    function vippsRecurringToggleInputRow(input, currentValue, showIfValue) {
      if (currentValue !== showIfValue) {
        input.closest('tr').hide();
      } else {
        input.closest('tr').show();
      }
    }

    function toggleMobilePayReservationsWarning(brand, checked) {
      const note = __('Note: Reservations in MobilePay will be cancelled after 7 days. Remember to ship and fulfill your orders.', 'vipps-recurring-payments-gateway-for-woocommerce');

      const brandInput = $('#woocommerce_vipps_recurring_brand');
      const fieldset = brandInput.parent();

      if (brand !== 'mobilepay' || checked) {
        fieldset.find('.mobilepay-warning').remove();
      } else {
        fieldset.append('<div class="ui message warning notice notice-warning mobilepay-warning" style="margin-bottom: 0;"><p>' + note + '</p></div>');
      }
    }

    const brandInput = $('#woocommerce_vipps_recurring_brand');
    const autoCaptureMobilePayInput = $('#woocommerce_vipps_recurring_auto_capture_mobilepay');

    brandInput.on('change', function (event) {
      vippsRecurringToggleInputRow(autoCaptureMobilePayInput, event.target.value, 'mobilepay');
      toggleMobilePayReservationsWarning(event.target.value, autoCaptureMobilePayInput.is(':checked'));
    }).trigger('change');

    autoCaptureMobilePayInput.on('change', function (event) {
      toggleMobilePayReservationsWarning(brandInput.val(), event.target.checked);
    }).trigger('change');
  }
});
