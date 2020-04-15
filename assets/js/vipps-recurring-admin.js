jQuery(document).ready(function ($) {
  $('#check_charge_statuses_now').on('click', function () {
    var $button = $(this);
    $button.addClass('disabled');

    $.post(ajaxurl, {
      action: 'vipps_recurring_force_check_charge_statuses'
    }, function (response) {
      $button.removeClass('disabled');
      // translators: Amount of orders checked
      alert(response);
    });
  })
});
