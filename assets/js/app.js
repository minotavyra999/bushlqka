jQuery(document).ready(function($){

    /** Обновяване на цената */
    function updatePrice() {
        const range = $(".bush-date-range").val();
        const anglers = parseInt($("[name=anglers]").val());
        const secondHasCard = $("[name=secondHasCard]").is(":checked") ? 1 : 0;

        if (!range) return;

        const dates = range.split(" to ");
        const start = dates[0];
        const end   = dates[1] || dates[0];

        $.getJSON(bushlyaka.restUrl + "pricing", function(prices){
            const s = new Date(start);
            const e = new Date(end);
            const days = Math.max(1, Math.ceil((e - s) / (1000*60*60*24)));

            let total = 0;
            if (anglers === 1) {
                total = prices.base * days;
            } else if (anglers === 2 && secondHasCard) {
                total = (prices.base + prices.second_with_card) * days;
            } else {
                total = (prices.base + prices.second) * days;
            }

            $(".bush-price-estimate").text(total.toFixed(2) + " лв.");
        });
    }

    /** Flatpickr календар */
    if (typeof flatpickr !== "undefined") {
        flatpickr(".bush-date-range", {
            mode: "range",
            dateFormat: "Y-m-d",
            minDate: "today",
            appendTo: document.body, // ✅ за да не е половинчат
            onChange: updatePrice
        });
    }

    /** Селектор за сектор */
    $(".bush-sector").on("click", function(){
        $(".bush-sector").removeClass("selected");
        $(this).addClass("selected");
        $("#bush-sector-input").val($(this).data("sector"));
    });

    /** Обновяване на цената при промяна на полетата */
    $("[name=anglers], [name=secondHasCard]").on("change", updatePrice);

    /** Смяна на метод на плащане */
    $("#bush-paymethod").on("change", function(){
        const info = $(this).find(":selected").data("info") || "";
        $("#bush-paymethod-info").text(info);
    });

    /** Изпращане на формата */
    $(".bushlyaka-booking-form form").on("submit", function(e){
        e.preventDefault();

        $(".bush-error-global").hide().text("");

        const formData = {
            daterange: $(".bush-date-range").val(),
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
                if (xhr.status === 409) {
                    msg = "Секторът вече е зает за избрания период.";
                }
                $(".bush-error-global").show().text(msg);
            }
        });
    });

});
