jQuery(document).ready(function($) {

    let pricingData = null;

    // Взимаме цените от REST API
    $.get(bushlyaka.restUrl + 'pricing', function(data) {
        pricingData = data;
        console.log("Цени заредени:", pricingData);
        updatePrice();
    });

    // Flatpickr за избор на дати
    flatpickr("#daterange", {
        mode: "range",
        dateFormat: "Y-m-d",
        minDate: "today",
        onClose: function(selectedDates) {
            if (selectedDates.length === 1) {
                const start = selectedDates[0];
                const day = start.getDay(); // 0=Неделя, 5=Петък
                if (day === 5) {
                    const sunday = new Date(start);
                    sunday.setDate(start.getDate() + 2); // Петък +2 дни = Неделя
                    this.setDate([start, sunday], true);
                }
            }
            updatePrice();
        }
    });

    // Обновяване на цената
    function updatePrice() {
        if (!pricingData) return;

        const daterange = $('#daterange').val();
        if (!daterange || !daterange.includes(" to ")) {
            $('#price').text('—');
            return;
        }

        const [start, end] = daterange.split(" to ");
        const anglers = parseInt($('#anglers').val()) || 1;
        const secondHasCard = $('#secondHasCard').is(':checked');

        const s = new Date(start);
        const e = new Date(end);
        const days = Math.max(1, Math.ceil((e - s) / (1000 * 60 * 60 * 24)));

        let total = 0;
        if (anglers === 1) {
            total = pricingData.base * days;
        } else if (anglers === 2 && secondHasCard) {
            total = (pricingData.base + pricingData.second_with_card) * days;
        } else {
            total = (pricingData.base + pricingData.second) * days;
        }

        $('#price').text(total + ' лв.');
    }

    // Обновяване при промяна на броя рибари или чекбокса
    $('#anglers, #secondHasCard').on('change', updatePrice);

    // Изпращане на формата AJAX
    $(document).on('submit', '.bushlyaka-booking-form form', function(e) {
        e.preventDefault();

        const daterange = $('#daterange').val().split(" to ");
        const start = daterange[0] || '';
        const end = daterange[1] || '';

        const data = {
            start: start,
            end: end,
            sector: $('#sector').val(),
            anglers: $('#anglers').val(),
            secondHasCard: $('#secondHasCard').is(':checked') ? 1 : 0,
            payMethod: $('#payMethod').val(),
            notes: $('#notes').val(),
            firstName: $('#firstName').val(),
            lastName: $('#lastName').val(),
            email: $('#email').val(),
            phone: $('#phone').val()
        };

        console.log("Изпращаме резервация:", data);

        $.ajax({
            url: bushlyaka.restUrl + 'bookings',
            method: 'POST',
            data: data,
            success: function(res) {
                console.log("Резервация успешна:", res);
                window.location.href = bushlyaka.redirectUrl + '?id=' + res.id;
            },
            error: function(err) {
                console.error("Грешка при резервация:", err);
                alert("Възникна грешка при изпращане на резервацията.");
            }
        });
    });

});
