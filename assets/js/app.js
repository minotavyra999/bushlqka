jQuery(document).ready(function($) {
    const picker = flatpickr("#daterange", {
        mode: "range",
        dateFormat: "Y-m-d",
        minDate: "today",
        inline: true,
        onChange: function(selectedDates) {
            if (selectedDates.length === 2) {
                fetchAvailableSectors(selectedDates[0], selectedDates[1]);
                updatePrice();
            }
        }
    });

    function fetchAvailableSectors(startDate, endDate) {
        let start = startDate.toISOString().split('T')[0];
        let end   = endDate.toISOString().split('T')[0];

        $.get(bushlyaka.restUrl + 'available-sectors', { start, end }, function(res) {
            let $sector = $("#sector");
            $sector.empty();
            if (res.available && res.available.length) {
                res.available.forEach(s => {
                    $sector.append(`<option value="${s}">Сектор ${s}</option>`);
                });
            } else {
                $sector.append('<option value="">Няма свободни</option>');
            }
        });
    }

    function calculateDays(start, end) {
        let s = new Date(start);
        let e = new Date(end);
        let diff = Math.ceil((e - s) / (1000 * 60 * 60 * 24));

        // Ако стартира в петък → минимум 2 дни (петък–неделя)
        if (s.getDay() === 5 && diff < 2) {
            diff = 2;
        }
        return diff;
    }

    function updatePrice() {
        let dates = $("#daterange").val().split(" to ");
        if (!dates[0] || !dates[1]) return;

        let start = dates[0];
        let end   = dates[1];
        let days  = calculateDays(start, end);

        let anglers = $("#anglers").val();
        let hasCard = $("#secondHasCard").is(":checked") ? 1 : 0;

        $.get(bushlyaka.restUrl + 'pricing', {}, function(rules) {
            let basePrice = 0;

            rules.forEach(rule => {
                if (parseInt(rule.anglers) === parseInt(anglers) &&
                    parseInt(rule.secondHasCard) === hasCard) {
                    basePrice = parseFloat(rule.price);
                }
            });

            let total = basePrice * days;
            $("#price").text(total ? total + " лв." : "—");
        });
    }

    $("#anglers, #secondHasCard").on("change", updatePrice);

    $("#bushlyakaBookingForm").on("submit", function(e) {
        e.preventDefault();

        let dates = $("#daterange").val().split(" to ");
        let data = {
            start: dates[0],
            end: dates[1],
            sector: $("#sector").val(),
            anglers: $("#anglers").val(),
            secondHasCard: $("#secondHasCard").is(":checked") ? 1 : 0,
            payMethod: $("#payMethod").val(),
            notes: $("#notes").val(),
            firstName: $("#firstName").val(),
            lastName: $("#lastName").val(),
            email: $("#email").val(),
            phone: $("#phone").val()
        };

        $.ajax({
            url: bushlyaka.restUrl + "bookings",
            method: "POST",
            data: data,
            success: function(res) {
                if (res.success) {
                    window.location.href = bushlyaka.redirectUrl + "?id=" + res.id;
                } else {
                    alert("Грешка: " + res.message);
                }
            },
            error: function(err) {
                alert("Грешка при запис: " + err.responseJSON.message);
            }
        });
    });
});
