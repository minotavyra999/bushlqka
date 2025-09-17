jQuery(document).ready(function ($) {
    // Flatpickr – винаги видим календар, започва от понеделник
    $(".bush-date-range").flatpickr({
        mode: "range",
        inline: true,
        dateFormat: "Y-m-d",
        locale: {
            firstDayOfWeek: 1
        },
        onChange: function (selectedDates, dateStr) {
            $(".bush-date-range").val(dateStr);
            updatePrice();
        }
    });

    // Сектори
    $(".bush-sector").on("click", function () {
        $(".bush-sector").removeClass("selected");
        $(this).addClass("selected");
        $("#bush-sector-input").val($(this).data("sector"));
        updatePrice();
    });

    // Слушаме промени в броя рибари и чекбокса
    $("select[name=anglers], input[name=secondHasCard]").on("change", function () {
        updatePrice();
    });

    // Слушаме промяна на метода за плащане
    $("#bush-paymethod").on("change", function () {
        let info = $(this).find(":selected").data("info") || "";
        $("#bush-paymethod-info").text(info);
    });

    // Ъпдейт на цената
    function updatePrice() {
        let anglers = parseInt($("select[name=anglers]").val()) || 1;
        let secondHasCard = $("input[name=secondHasCard]").is(":checked") ? 1 : 0;
        let daterange = $(".bush-date-range").val();

        if (!daterange) return;

        let parts = daterange.split(" to ");
        let start = parts[0];
        let end = parts.length > 1 ? parts[1] : parts[0];

        $.ajax({
            url: bushlyaka.restUrl + "pricing",
            method: "GET",
            beforeSend: function (xhr) {
                xhr.setRequestHeader("X-WP-Nonce", bushlyaka.nonce);
            },
            success: function (prices) {
                if (!prices || !prices.base) {
                    $(".bush-price-estimate").text("—");
                    return;
                }

                let s = new Date(start);
                let e = new Date(end);
                let days = Math.max(1, Math.ceil((e - s) / (1000 * 60 * 60 * 24)));

                let total = 0;
                if (anglers === 1) {
                    total = prices.base * days;
                } else if (anglers === 2 && secondHasCard) {
                    total = (parseFloat(prices.base) + parseFloat(prices.second_with_card)) * days;
                } else {
                    total = (parseFloat(prices.base) + parseFloat(prices.second)) * days;
                }

                $(".bush-price-estimate").text(total.toFixed(2) + " лв.");
            }
        });
    }

    // Изпращане на резервация
    $(".bushlyaka-booking-form form").on("submit", function (e) {
        e.preventDefault();

        let form = $(this);
        let data = {
            start: $(".bush-date-range").val().split(" to ")[0],
            end: $(".bush-date-range").val().split(" to ")[1] || $(".bush-date-range").val().split(" to ")[0],
            sector: $("#bush-sector-input").val(),
            anglers: $("select[name=anglers]").val(),
            secondHasCard: $("input[name=secondHasCard]").is(":checked") ? 1 : 0,
            firstName: $("input[name=firstName]").val(),
            lastName: $("input[name=lastName]").val(),
            email: $("input[name=email]").val(),
            phone: $("input[name=phone]").val(),
            notes: $("textarea[name=notes]").val(),
            payMethod: $("#bush-paymethod").val(),
        };

        $(".bush-error-global").hide();

        $.ajax({
            url: bushlyaka.restUrl + "bookings",
            method: "POST",
            beforeSend: function (xhr) {
                xhr.setRequestHeader("X-WP-Nonce", bushlyaka.nonce);
            },
            data: data,
            success: function (response) {
                window.location.href = bushlyaka.redirectUrl + "?id=" + response.id;
            },
            error: function (xhr) {
                $(".bush-error-global").text(bushlyaka.messages.error).show();
                console.error("Booking error:", xhr.responseText);
            }
        });
    });
});
