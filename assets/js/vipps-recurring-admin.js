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
});
