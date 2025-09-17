jQuery(document).ready(function($) {
    // Flatpickr с always open
    const picker = flatpickr("#daterange", {
        mode: "range",
        dateFormat: "Y-m-d",
        minDate: "today",
        inline: true,
        onChange: function(selectedDates, dateStr) {
            if (selectedDates.length === 2) {
                fetchAvailableSectors(selectedDates[0], selectedDates[1]);
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

    // Обновяване на цената
    function updatePrice() {
        let anglers = $("#anglers").val();
        let hasCard = $("#secondHasCard").is(":checked") ? 1 : 0;
        let dates   = $("#daterange").val().split(" to ");

        if (!dates[0] || !dates[1]) return;

        $.get(bushlyaka.restUrl + 'pricing', { anglers, hasCard }, function(res) {
            // TODO: логика за пресмятане на цената спрямо res
            $("#price").text("Изчислена цена");
        });
    }

    $("#anglers, #secondHasCard, #daterange").on("change", updatePrice);

    // Изпращане на резервацията
    $("#bushlyakaBookingForm").on("submit", function(e) {
        e.preventDefault();

        let data = {
            start: $("#daterange").val().split(" to ")[0],
            end: $("#daterange").val().split(" to ")[1],
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
