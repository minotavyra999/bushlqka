jQuery(document).ready(function($) {
    let currentPrices = null;
    let currentUnavailable = [];

    // ✅ Зареждаме цените от REST API
    fetch(bushlyaka.restUrl + 'pricing')
        .then(r => r.json())
        .then(data => {
            currentPrices = data;
            updateEstimate();
        });

    // Слушаме за промяна на селектите и чекбокса
    $('select[name="anglers"], input[name="secondHasCard"]').on('change', function() {
        updateEstimate();
    });

    // Слушаме за избор на сектор
    $('.bush-sector').on('click', function() {
        if ($(this).hasClass('unavailable')) return;
        $('.bush-sector').removeClass('selected');
        $(this).addClass('selected');
    });

    // ✅ Функция: обновяване на цената
    function updateEstimate() {
        if (!currentPrices) return;

        let anglers = parseInt($('select[name="anglers"]').val());
        let secondHasCard = $('input[name="secondHasCard"]').is(':checked');
        let price = 0;

        if (anglers === 1) {
            price = currentPrices.base;
        } else if (anglers === 2 && secondHasCard) {
            price = currentPrices.base + currentPrices.second_with_card;
        } else if (anglers === 2) {
            price = currentPrices.base + currentPrices.second;
        }

        $('.bush-price-estimate').text(price.toFixed(2) + ' лв.');
    }

    // ✅ Submit на формата
    $('.bushlyaka-booking-form form').on('submit', function(e) {
        e.preventDefault();

        let sector = $('.bush-sector.selected').data('sector');
        if (!sector) {
            alert('Моля, изберете сектор.');
            return;
        }

        let anglers = parseInt($('select[name="anglers"]').val());
        let secondHasCard = $('input[name="secondHasCard"]').is(':checked');

        let data = {
            start: $('.bush-date-range').val().split(' - ')[0],
            end: $('.bush-date-range').val().split(' - ')[1],
            sector: sector,
            anglers: anglers,
            secondHasCard: secondHasCard,
            client: {
                firstName: $('input[name="firstName"]').val(),
                lastName: $('input[name="lastName"]').val(),
                email: $('input[name="email"]').val(),
                phone: $('input[name="phone"]').val()
            },
            notes: $('textarea[name="notes"]').val(),
            payMethodId: $('select[name="payMethod"]').val()
        };

        $('.bush-error-global').hide().text('');
        $('button[type="submit"]').prop('disabled', true).text(bushlyaka.messages.loading);

        fetch(bushlyaka.restUrl + 'bookings', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': bushlyaka.nonce
            },
            body: JSON.stringify(data)
        })
        .then(r => r.json())
        .then(res => {
            if (res.ok) {
                alert(bushlyaka.messages.success + " Цена: " + res.price + " лв.");
                window.location.href = bushlyaka.redirectUrl;
            } else {
                let msg = res.message || bushlyaka.messages.error;
                $('.bush-error-global').text(msg).show();
            }
        })
        .catch(() => {
            $('.bush-error-global').text(bushlyaka.messages.error).show();
        })
        .finally(() => {
            $('button[type="submit"]').prop('disabled', false).text('Изпрати резервация');
        });
    });

    // ✅ Blackouts и заетост (пример)
    $('.bush-date-range').on('change', function() {
        let range = $(this).val().split(' - ');
        if (range.length < 2) return;

        fetch(bushlyaka.restUrl + 'availability', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': bushlyaka.nonce
            },
            body: JSON.stringify({ start: range[0], end: range[1] })
        })
        .then(r => r.json())
        .then(data => {
            currentUnavailable = data.unavailable || [];
            updateUnavailableSectors();
        });
    });

    function updateUnavailableSectors() {
        $('.bush-sector').each(function() {
            let sector = $(this).data('sector').toString();
            if (currentUnavailable.includes(sector)) {
                $(this).addClass('unavailable').removeClass('selected');
            } else {
                $(this).removeClass('unavailable');
            }
        });
    }
});
