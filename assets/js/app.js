document.addEventListener("DOMContentLoaded", function() {
    // Ако не използваш jQuery, тази версия е с чист JavaScript + Flatpickr
    const dateInput = document.querySelector(".bush-date-range");
    const sectorEls = document.querySelectorAll(".bush-sector");
    const anglersSelect = document.querySelector("select[name='anglers']");
    const secondHasCardCheckbox = document.querySelector("input[name='secondHasCard']");
    const priceEstimateEl = document.querySelector(".bush-price-estimate");
    const errorGlobal = document.querySelector(".bush-error-global");
    const form = document.querySelector(".bushlyaka-booking-form form");
    let currentPrices = null;
    let currentUnavailable = [];

    // Flatpickr setup
    if (typeof flatpickr !== "undefined" && dateInput) {
        flatpickr(dateInput, {
            mode: "range",
            dateFormat: "Y-m-d",
            inline: true,
            minDate: "today",
            onChange: function(selectedDates, dateStr, instance) {
                if (selectedDates.length === 2) {
                    // проверка за availability
                    fetchAvailability(selectedDates[0], selectedDates[1]);
                }
            }
        });
    }

    // Зареждане на цените
    fetch(bushlyaka.restUrl + "pricing")
        .then(resp => resp.json())
        .then(data => {
            currentPrices = data;
            updateEstimate();
        })
        .catch(() => {
            console.error("Не може да зареди цени");
        });

    // Установи слушатели
    if (anglersSelect) anglersSelect.addEventListener("change", updateEstimate);
    if (secondHasCardCheckbox) secondHasCardCheckbox.addEventListener("change", updateEstimate);

    sectorEls.forEach(el => {
        el.addEventListener("click", () => {
            if (el.classList.contains("unavailable")) return;
            sectorEls.forEach(s => s.classList.remove("selected"));
            el.classList.add("selected");
        });
    });

    // Функция: обновяване на estimate
    function updateEstimate() {
        if (!currentPrices) return;
        let anglers = parseInt(anglersSelect.value) || 1;
        let secondHasCard = secondHasCardCheckbox.checked;

        let price = 0;
        if (anglers === 1) {
            price = currentPrices.base;
        } else if (anglers === 2 && secondHasCard) {
            price = currentPrices.base + currentPrices.second_with_card;
        } else if (anglers === 2) {
            price = currentPrices.base + currentPrices.second;
        }

        priceEstimateEl.textContent = price.toFixed(2) + " лв.";
    }

    // Функция: fetch availability
    function fetchAvailability(startDate, endDate) {
        clearError();
        fetch(bushlyaka.restUrl + "availability", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-WP-Nonce": bushlyaka.nonce
            },
            body: JSON.stringify({
                start: startDate.toISOString().split("T")[0],
                end: endDate.toISOString().split("T")[0]
            })
        })
        .then(r => r.json())
        .then(data => {
            currentUnavailable = data.unavailable || [];
            updateUnavailableSectors();
        })
        .catch(() => {
            showError("Неуспешна проверка за заетост.");
        });
    }

    function updateUnavailableSectors() {
        sectorEls.forEach(el => {
            const sector = el.dataset.sector;
            if (currentUnavailable.includes(sector.toString())) {
                el.classList.add("unavailable");
                el.classList.remove("selected");
            } else {
                el.classList.remove("unavailable");
            }
        });
    }

    function showError(msg) {
        if (errorGlobal) {
            errorGlobal.textContent = msg;
            errorGlobal.style.display = "block";
        } else {
            alert(msg);
        }
    }

    function clearError() {
        if (errorGlobal) {
            errorGlobal.style.display = "none";
            errorGlobal.textContent = "";
        }
    }

    // Submit на формата
    if (form) {
        form.addEventListener("submit", function(e) {
            e.preventDefault();
            clearError();

            const selectedSectorEl = document.querySelector(".bush-sector.selected");
            if (!selectedSectorEl) {
                showError("Моля изберете сектор.");
                return;
            }
            const sector = selectedSectorEl.dataset.sector;
            const anglers = parseInt(anglersSelect.value) || 1;
            const secondHasCard = secondHasCardCheckbox.checked;

            // датите:
            const dateStr = dateInput.value;
            // при inline Flatpickr — стойността е нещо като "YYYY-MM-DD to YYYY-MM-DD"
            const dates = dateStr.split(" to ");
            if (dates.length < 2) {
                showError("Моля, изберете начална и крайна дата.");
                return;
            }
            const start = dates[0].trim();
            const end = dates[1].trim();

            const client = {
                firstName: form.querySelector("input[name='firstName']").value.trim(),
                lastName: form.querySelector("input[name='lastName']").value.trim(),
                email: form.querySelector("input[name='email']").value.trim(),
                phone: form.querySelector("input[name='phone']").value.trim()
            };

            if (!client.firstName || !client.lastName) {
                showError("Моля, въведете име и фамилия.");
                return;
            }
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(client.email)) {
                showError("Невалиден имейл адрес.");
                return;
            }
            if (!/^[0-9+\-\s]{6,20}$/.test(client.phone)) {
                showError("Невалиден телефонен номер.");
                return;
            }

            const notes = form.querySelector("textarea[name='notes']").value.trim();
            const payMethod = form.querySelector("select[name='payMethod']").value;

            // създаваме заявка
            const payload = {
                start: start,
                end: end,
                sector: sector,
                anglers: anglers,
                secondHasCard: secondHasCard,
                client: client,
                notes: notes,
                payMethodId: payMethod
            };

            // бутон submit
            const btn = form.querySelector("button[type='submit']");
            if (btn) {
                btn.disabled = true;
                btn.textContent = bushlyaka.messages.loading;
            }

            fetch(bushlyaka.restUrl + "bookings", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-WP-Nonce": bushlyaka.nonce
                },
                body: JSON.stringify(payload)
            })
            .then(r => r.json())
            .then(res => {
                if (res.ok) {
                    alert(bushlyaka.messages.success + " Цена: " + res.price + " лв.");
                    window.location.href = bushlyaka.redirectUrl;
                } else {
                    const msg = res.message || bushlyaka.messages.error;
                    showError(msg);
                }
            })
            .catch(() => {
                showError(bushlyaka.messages.error);
            })
            .finally(() => {
                if (btn) {
                    btn.disabled = false;
                    btn.textContent = "Изпрати резервация";
                }
            });
        });
    }
});
