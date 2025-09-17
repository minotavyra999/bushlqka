document.addEventListener("DOMContentLoaded", function () {

    // === Flatpickr ===
    const dateInput = document.querySelector(".bush-date-range");
    if (dateInput) {
        flatpickr(dateInput, {
            mode: "range",
            minDate: "today",
            dateFormat: "Y-m-d",
            locale: {
                firstDayOfWeek: 1, // понеделник
                weekdays: {
                    shorthand: ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"],
                    longhand: [
                        "Понеделник", "Вторник", "Сряда",
                        "Четвъртък", "Петък", "Събота", "Неделя"
                    ],
                },
                months: {
                    longhand: [
                        "Януари", "Февруари", "Март", "Април", "Май", "Юни",
                        "Юли", "Август", "Септември", "Октомври", "Ноември", "Декември"
                    ]
                }
            },
            inline: true, // винаги отворен
            onChange: function () {
                calculatePrice();
            }
        });
    }

    // === Сектори ===
    const sectorButtons = document.querySelectorAll(".bush-sector");
    let selectedSector = null;

    sectorButtons.forEach(btn => {
        btn.addEventListener("click", function () {
            if (btn.classList.contains("unavailable")) return;

            sectorButtons.forEach(b => b.classList.remove("selected"));
            btn.classList.add("selected");
            selectedSector = btn.dataset.sector;
            calculatePrice();
        });
    });

    // === Цена ===
    const anglersInput = document.querySelector("#bush-anglers");
    const hasCardCheckbox = document.querySelector("#bush-second-card");
    const priceBox = document.querySelector("#bush-total-price");

    if (anglersInput) anglersInput.addEventListener("change", calculatePrice);
    if (hasCardCheckbox) hasCardCheckbox.addEventListener("change", calculatePrice);

    function calculatePrice() {
        if (!dateInput || !selectedSector) return;

        const dates = dateInput.value.split(" to ");
        if (dates.length < 1) return;

        const start = dates[0];
        const end = dates[1] || dates[0];

        const anglers = parseInt(anglersInput.value || 1);
        const secondHasCard = hasCardCheckbox.checked ? 1 : 0;

        fetch(`/wp-json/bush/v1/pricing`)
            .then(r => r.json())
            .then(prices => {
                const s = new Date(start);
                const e = new Date(end);
                const diffDays = Math.max(1, Math.ceil((e - s) / (1000 * 60 * 60 * 24)));

                let total = 0;
                if (anglers === 1) {
                    total = prices.base * diffDays;
                } else if (anglers === 2 && secondHasCard) {
                    total = (prices.base + prices.second_with_card) * diffDays;
                } else {
                    total = (prices.base + prices.second) * diffDays;
                }

                priceBox.innerHTML = `Цена: <strong>${total.toFixed(2)} лв.</strong>`;
            });
    }

    // === Изпращане на резервация ===
    const bookingForm = document.querySelector("#bush-booking-form");
    if (bookingForm) {
        bookingForm.addEventListener("submit", function (e) {
            e.preventDefault();

            const formData = new FormData(bookingForm);
            formData.append("sector", selectedSector);

            fetch("/wp-json/bush/v1/bookings", {
                method: "POST",
                body: formData
            })
                .then(r => r.json())
                .then(res => {
                    if (res.id) {
                        alert("✅ Резервацията е изпратена успешно!");
                        window.location.reload();
                    } else {
                        alert("❌ Възникна грешка: " + (res.message || "Опитайте отново."));
                    }
                })
                .catch(() => {
                    alert("❌ Грешка при връзка със сървъра.");
                });
        });
    }
});
