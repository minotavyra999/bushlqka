jQuery(document).ready(function($){

    // Безопасен парсинг на дата (Y-m-d → JS Date)
    function parseDate(str){
        if(!str) return null;
        const parts = str.split("-");
        return new Date(parts[0], parts[1]-1, parts[2]);
    }

    // Обновяване на цената
    function updatePrice() {
        const range = $(".bush-date-range").val();
        const anglers = parseInt($("[name=anglers]").val(), 10);
        const secondHasCard = $("[name=secondHasCard]").is(":checked") ? 1 : 0;

        if (!range) return;

        const dates = range.split(" to ");
        const start = parseDate(dates[0]);
        const end   = dates[1] ? parseDate(dates[1]) : start;

        if (!start || !end) return;

        const days = Math.max(1, Math.ceil((end - start) / (1000*60*60*24)));

        $.getJSON(bushlyaka.restUrl + "pricing", function(prices){
            // превръщаме в числа, за да няма конкатенация / NaN
            const base           = parseFloat(prices.base) || 0;
            const second         = parseFloat(prices.second) || 0;
            const secondWithCard = parseFloat(prices.second_with_card) || 0;

            let total = 0;
            if (anglers === 1) {
                total = base * days;
            } else if (anglers === 2 && secondHasCard) {
                total = (base + secondWithCard) * days;
            } else {
                total = (base + second) * days;
            }

            $(".bush-price-estimate").text(total.toFixed(2) + " лв.");
        });
    }

    // ✅ Календар – винаги отворен, full-width, седмица от понеделник
    if (typeof flatpickr !== "undefined") {
        flatpickr(".bush-date-range", {
            mode: "range",
            dateFormat: "Y-m-d",
            minDate: "today",
            inline: true,
            locale: { firstDayOfWeek: 1 }, // понеделник
            onChange: updatePrice,
            onReady: updatePrice
        });
    }

    // Избор на сектор
    $(".bush-sector").on("click", function(){
        $(".bush-sector").removeClass("selected");
        $(this).addClass("selected");
        $("#bush-sector-input").val($(this).data("sector"));
    });

    // Обновяване на цената при промяна на полетата
    $("[name=anglers], [name=secondHasCard]").on("change", updatePrice);

    // Показване на инструкции за плащане
    $("#bush-paymethod").on("change", function(){
        const info = $(this).find(":selected").data("info") || "";
        $("#bush-paymethod-info").text(info);
    });

    // Изпращане на формата
    $(".bushlyaka-booking-form form").on("submit", function(e){
        e.preventDefault();

        $(".bush-error-global").hide().text("");

        const dr = $(".bush-date-range").val().split(" to ");
        const start = dr[0] || "";
        const end   = dr[1] || dr[0] || "";

        const formData = {
            // изпращаме start/end (REST вече ги приема), а daterange не е задължителен
            start: start,
            end: end,
            sector: $("#bush-sector-input").val(),
            anglers: $("[name=anglers]").val(),
            secondHasCard: $("[name=secondHasCard]").is(":checked") ? 1 : 0,
            firstName: $("[name=firstName]").val(),
            lastName: $("[name=lastName]").val(),
            email: $("[name=email]").val(),
            phone: $("[name=phone]").val(),
            notes: $("[name=notes]").val(),
            payMethod: $("[name=payMethod]").val()
        };

        $.ajax({
            url: bushlyaka.restUrl + "bookings",
            method: "POST",
            beforeSend: function(xhr) {
                xhr.setRequestHeader("X-WP-Nonce", bushlyaka.nonce);
                $(".bush-error-global").show().text(bushlyaka.messages.loading);
            },
            data: formData,
            success: function(res) {
                window.location.href = bushlyaka.redirectUrl + "?id=" + res.id;
            },
            error: function(xhr) {
                let msg = bushlyaka.messages.error;
                if (xhr && xhr.status === 409) {
                    msg = "Секторът вече е зает за избрания период.";
                } else if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                } else if (xhr && xhr.responseText) {
                    msg = xhr.responseText;
                }
                $(".bush-error-global").show().text(msg);
            }
        });
    });

});
