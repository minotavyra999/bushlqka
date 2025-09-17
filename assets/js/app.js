jQuery(document).ready(function($) {

    // показване на информация за метода на плащане
    $('#bush-paymethod').on('change', function() {
        let info = $(this).find(':selected').data('info') || '';
        $('#bush-paymethod-info').text(info);
    });

    // функция за калкулация на цената
    function updatePrice() {
        let dr = $('.bush-date-range').val().split(' до ');
        if (dr.length !== 2) return;

        let start = dr[0].trim();
        let end = dr[1].trim();
        let anglers = $('[name="anglers"]').val();
        let secondHasCard = $('[name="secondHasCard"]').is(':checked');

        $.ajax({
            url: bushlyaka.restUrl + 'pricing',
            method: 'GET',
            success: function(prices) {
                let s = new Date(start);
                let e = new Date(end);
                let days = Math.max(1, (e - s) / (1000*60*60*24));

                let total = 0;
                if (anglers == 1) {
                    total = prices.base * days;
                } else if (anglers == 2 && secondHasCard) {
                    total = (prices.base + prices.second_with_card) * days;
                } else if (anglers == 2) {
                    total = (prices.base + prices.second) * days;
                }

                $('.bush-price-estimate').text(total.toFixed(2) + ' лв.');
            }
        });
    }

    // init flatpickr always open
    flatpickr(".bush-date-range", {
        mode: "range",
        dateFormat: "Y-m-d",
        inline: true,
        onChange: function() { updatePrice(); }
    });

    $('[name="anglers"], [name="secondHasCard"]').on('change', updatePrice);
});
