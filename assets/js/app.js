jQuery(document).ready(function($) {
  // ✅ Flatpickr инициализация
  if (typeof flatpickr !== 'undefined') {
    flatpickr(".bush-date-range", {
      mode: "range",
      dateFormat: "Y-m-d",
      inline: true,
      minDate: "today",
      appendTo: document.body, // ✅ добавено – да не се реже на desktop
      onChange: function() {
        updatePrice();
      }
    });
  } else {
    console.error("flatpickr не е зареден!");
  }

  // ✅ Избор на сектор
  $(document).on('click', '.bush-sector', function() {
    $('.bush-sector').removeClass('selected');
    $(this).addClass('selected');
    $('#bush-sector-input').val($(this).data('sector'));
  });

  // ✅ Показване на инфо за метод на плащане
  $('#bush-paymethod').on('change', function() {
    let info = $(this).find(':selected').data('info') || '';
    $('#bush-paymethod-info').text(info);
  });

  // ✅ Слушатели за промяна на брой рибари или втора карта
  $('[name="anglers"], [name="secondHasCard"]').on('change', updatePrice);

  // ✅ Обработчик за изпращане на формата
  $(document).on('submit', '.bushlyaka-booking-form form', function(e) {
    e.preventDefault();
    let formData = $(this).serialize();

    $.ajax({
      url: bushlyaka.restUrl + 'bookings',
      method: 'POST',
      data: formData,
      beforeSend: function(xhr) {
        xhr.setRequestHeader('X-WP-Nonce', bushlyaka.nonce);
        $('.bush-error-global').hide().text('');
      },
      success: function(res) {
        alert(bushlyaka.messages.success);
        if (res.id) {
          window.location.href = bushlyaka.redirectUrl + '?id=' + res.id;
        }
      },
      error: function(xhr) {
        // ✅ ДОБАВЕНО: ако секторът е вече зает (409 Conflict)
        if (xhr && xhr.status === 409) {
          $('.bush-error-global').show().text("❌ Секторът е зает в този период (има одобрена резервация). Моля, изберете други дати или сектор.");
          return;
        }
        let msg = bushlyaka.messages.error;
        if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
          msg = xhr.responseJSON.message;
        }
        $('.bush-error-global').show().text(msg);
      }
    });
  });

  // ✅ Функция за калкулация на цената
  function updatePrice() {
    let dr = $('.bush-date-range').val();
    if (!dr) return;
    let parts = dr.split(' to ');
    if (parts.length !== 2) return;

    let start = parts[0].trim();
    let end = parts[1].trim();
    let anglers = parseInt($('[name="anglers"]').val()) || 1;
    let secondHasCard = $('[name="secondHasCard"]').is(':checked');

    $.ajax({
      url: bushlyaka.restUrl + 'pricing',
      method: 'GET',
      success: function(prices) {
        let s = new Date(start);
        let e = new Date(end);
        let ms = e - s;
        let days = Math.max(1, Math.ceil(ms / (1000 * 60 * 60 * 24)));
        let total = 0;
        if (anglers === 1) {
          total = prices.base * days;
        } else if (anglers === 2 && secondHasCard) {
          total = (prices.base + prices.second_with_card) * days;
        } else if (anglers === 2) {
          total = (prices.base + prices.second) * days;
        }
        $('.bush-price-estimate').text(total.toFixed(2) + ' лв.');
      },
      error: function(err) {
        console.error("Грешка при зареждане на цените", err);
      }
    });
  }
});
