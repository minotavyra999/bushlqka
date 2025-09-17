document.addEventListener("DOMContentLoaded", function () {
    const bookingForm = document.getElementById("bush-booking-form");
    const priceBox = document.getElementById("bush-price");
    const sectorButtons = document.querySelectorAll(".bush-sector");
    const payMethodSelect = document.getElementById("bush-paymethod");

    let selectedSector = null;
    let startDate = null;
    let endDate = null;

    /** -------------------------
     *  ИНИЦИАЛИЗАЦИЯ НА КАЛЕНДАРА
     * ------------------------- */
    const calendarInput = document.querySelector(".bush-date-range");
    if (calendarInput) {
        flatpickr(calendarInput, {
            mode: "range",
            dateFormat: "Y-m-d",
            minDate: "today",
            locale: {
                firstDayOfWeek: 1 // понеделник
            },
            onChange: function (selectedDates) {
                if (selectedDates.length === 2) {
                    startDate = selectedDates[0].toISOString().split("T")[0];
                    endDate = selectedDates[1].toISOString().split("T")[0];
                    updatePrice();
                }
            }
        });
    }

    /** -------------------------
     *  СЕКТОРИ
     * ------------------------- */
    sectorButtons.forEach(btn => {
        btn.addEventListener("click", function () {
            sectorButtons.forEach(b => b.classList.remove("selected"));
            this.classList.add("selected");
            selectedSector = this.dataset.sector;
            updatePrice();
        });
    });

    /** -------------------------
     *  ЗАРЕЖДАНЕ НА МЕТОДИ ЗА ПЛАЩАНЕ
     * ------------------------- */
    async function loadPayMethods() {
        if (!payMethodSelect) return;

        try {
            const response = await fetch("/wp-json/bush/v1/paymethods");
            const methods = await response.json();

            payMethodSelect.innerHTML = "<option value=''>-- изберете --</option>";

            methods.forEach(method => {
                const opt = document.createElement("option");
                opt.value = method.method_name;
                opt.textContent = method.method_name + " – " + (method.method_note || "");
                payMethodSelect.appendChild(opt);
            });
        } catch (err) {
            console.error("Грешка при зареждане на методи за плащане:", err);
        }
    }
    loadPayMethods();

    /** -------------------------
     *  ОБНОВЯВАНЕ НА ЦЕНАТА
     * ------------------------- */
    async function updatePrice() {
        if (!startDate || !endDate || !selectedSector) return;

        const anglers = document.getElementById("bush-anglers")?.value || 1;
        const secondHasCard = document.getElementById("bush-second-card")?.checked ? 1 : 0;

        try {
            const response = await fetch("/wp-json/bush/v1/pricing");
            const prices = await response.json();

            const s = new Date(startDate);
            const e = new Date(endDate);
            const days = Math.max(1, Math.ceil((e - s) / (1000 * 60 * 60 * 24)));

            let total = 0;
            if (anglers == 1) {
                total = prices.base * days;
            } else if (anglers == 2 && secondHasCard) {
                total = (prices.base + prices.second_with_card) * days;
            } else {
                total = (prices.base + prices.second) * days;
            }

            if (priceBox) {
                priceBox.textContent = "Цена: " + total.toFixed(2) + " лв.";
            }
        } catch (err) {
            console.error("Грешка при калкулация на цена:", err);
        }
    }

    /** -------------------------
     *  СМЯНА НА АНГЛЕРИ
     * ------------------------- */
    const anglersSelect = document.getElementById("bush-anglers");
    if (anglersSelect) {
        anglersSelect.addEventListener("change", updatePrice);
    }
    const secondCardCheckbox = document.getElementById("bush-second-card");
    if (secondCardCheckbox) {
        secondCardCheckbox.addEventListener("change", updatePrice);
    }

    /** -------------------------
     *  СУБМИТ НА ФОРМАТА
     * ------------------------- */
    if (bookingForm) {
        bookingForm.addEventListener("submit", async function (e) {
            e.preventDefault();

            const formData = {
                daterange: calendarInput.value,
                sector: selectedSector,
                anglers: anglersSelect?.value || 1,
                secondHasCard: secondCardCheckbox?.checked ? 1 : 0,
                firstName: document.getElementById("bush-first")?.value || "",
                lastName: document.getElementById("bush-last")?.value || "",
                email: document.getElementById("bush-email")?.value || "",
                phone: document.getElementById("bush-phone")?.value || "",
                notes: document.getElementById("bush-notes")?.value || "",
                payMethod: payMethodSelect?.value || ""
            };

            try {
                const response = await fetch("/wp-json/bush/v1/bookings", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify(formData)
                });

                const result = await response.json();

                if (result.id) {
                    alert("✅ Резервацията е успешно създадена! Номер: " + result.id);
                    bookingForm.reset();
                } else {
                    alert("❌ Грешка: " + (result.message || "Опитайте отново."));
                }
            } catch (err) {
                alert("❌ Грешка при изпращане на резервацията!");
                console.error(err);
            }
        });
    }
});
