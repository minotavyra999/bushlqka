jQuery(document).ready(function($) {

    // Инициализация на flatpickr
    if (typeof flatpickr !== 'undefined') {
        flatpickr(".bush-date-range", {
            mode: "range",
            dateFormat: "Y-m-d",
            inline: true,
            minDate: "today",
            onChange: function() { updatePrice(); }
        });
    } else {
        console.error("flatpickr не е зареден!");
    }

    // избор на сектор
    $(document).on('click', '.bush-sector', function() {
        console.log('Сектор кликнат:', $(this).data('sector'));
        $('.bush-sector').removeClass('selected');
        $(this).addClass('selected');
        $('#bush-sector-input').val($(this).data('sector'));
    });

    // показване на информация за метода на плащане
    $('#bush-paymethod').on('change', function() {
        let info = $(this).find(':selected').data('info') || '';
        $('#bush-paymethod-info').text(info);
    });

    // слушатели за смяна на брой рибари
    $('[name="anglers"], [name="secondHasCard"]').on('change', updatePrice);

    // функция за калкулация на цената
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
