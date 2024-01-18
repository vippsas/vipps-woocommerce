jQuery(document).ready(function ($) {
  $('#check_charge_statuses_now').on('click', function () {
    var $button = $(this);
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

  $('.notice-vipps-recurring').on('click', '.notice-dismiss', function (event, el) {
    var $notice = $(this).parent('.notice.is-dismissible');
    var dismiss_url = $notice.attr('data-dismiss-url');
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
        error: function(xhr, message, error) {
          button.removeAttr('disabled');
          button.removeClass('disabled');

          alert("Error performing Vipps/MobilePay Recurring action " + message + " " + error);
        },
        success: function() {
          window.location.reload();
        }
      })
    })
  }
});
