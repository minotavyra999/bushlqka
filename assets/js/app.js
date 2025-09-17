document.addEventListener("DOMContentLoaded", function () {
    const form = document.querySelector(".bushlyaka-booking-form form");
    if (!form) return;

    const dateRangeInput = form.querySelector(".bush-date-range");
    const sectorElements = form.querySelectorAll(".bush-sector");
    const priceEstimate = form.querySelector(".bush-price-estimate");
    const globalError = form.querySelector(".bush-error-global");

    let selectedSector = null;
    let pricing = null;

    /** Показване на грешки */
    function showError(message) {
        if (globalError) {
            globalError.style.display = "block";
            globalError.textContent = message;
        } else {
            alert(message);
        }
    }

    /** Скриване на грешки */
    function clearError() {
        if (globalError) {
            globalError.style.display = "none";
            globalError.textContent = "";
        }
    }

    /** Зареждане на цени */
    async function loadPricing() {
        try {
            const res = await fetch(bushlyaka.restUrl + "pricing");
            pricing = await res.json();
        } catch (err) {
            console.error("Грешка при зареждане на цени", err);
        }
    }

    /** Изчисляване на цена */
    function updatePrice() {
        if (!pricing) return;

        const anglers = parseInt(form.querySelector("select[name='anglers']").value, 10);
        const secondHasCard = form.querySelector("input[name='secondHasCard']").checked;

        let total = 0;
        if (anglers === 1) {
            total = parseFloat(pricing.base);
        } else if (anglers === 2) {
            total = secondHasCard
                ? parseFloat(pricing.second_with_card)
                : parseFloat(pricing.second);
        }

        priceEstimate.textContent = total.toFixed(2) + " лв.";
    }

    /** Избор на сектор */
    sectorElements.forEach(el => {
        el.addEventListener("click", function () {
            sectorElements.forEach(s => s.classList.remove("selected"));
            this.classList.add("selected");
            selectedSector = this.dataset.sector;
        });
    });

    form.querySelector("select[name='anglers']").addEventListener("change", updatePrice);
    form.querySelector("input[name='secondHasCard']").addEventListener("change", updatePrice);

    /** Валидация на форма */
    function validateForm(data) {
        if (!data.start || !data.end) return "Моля изберете дати.";
        if (!data.sector) return "Моля изберете сектор.";
        if (!data.client.firstName || !data.client.lastName) return "Въведете име и фамилия.";
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(data.client.email)) return "Невалиден имейл.";
        if (!/^[0-9+\-\s]{6,20}$/.test(data.client.phone)) return "Невалиден телефон.";
        return null;
    }

    /** Изпращане на резервация */
    form.addEventListener("submit", async function (e) {
        e.preventDefault();
        clearError();

        const [start, end] = (dateRangeInput.value || "").split(" до ");

        const data = {
            start: start ? start.trim() : "",
            end: end ? end.trim() : "",
            sector: selectedSector,
            anglers: parseInt(form.querySelector("select[name='anglers']").value, 10),
            client: {
                firstName: form.querySelector("input[name='firstName']").value.trim(),
                lastName: form.querySelector("input[name='lastName']").value.trim(),
                email: form.querySelector("input[name='email']").value.trim(),
                phone: form.querySelector("input[name='phone']").value.trim(),
            },
            notes: form.querySelector("textarea[name='notes']").value.trim(),
            payMethodId: form.querySelector("select[name='payMethod']").value,
        };

        const error = validateForm(data);
        if (error) {
            showError(error);
            return;
        }

        try {
            const submitBtn = form.querySelector("button[type='submit']");
            submitBtn.disabled = true;
            submitBtn.textContent = bushlyaka.messages.loading;

            const res = await fetch(bushlyaka.restUrl + "bookings", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-WP-Nonce": bushlyaka.nonce,
                },
                body: JSON.stringify(data),
            });

            const json = await res.json();

            if (!res.ok || !json.ok) {
                throw new Error(json.message || bushlyaka.messages.error);
            }

            alert(bushlyaka.messages.success);
            window.location.href = bushlyaka.redirectUrl;

        } catch (err) {
            console.error(err);
            showError(err.message || bushlyaka.messages.error);
        } finally {
            const submitBtn = form.querySelector("button[type='submit']");
            submitBtn.disabled = false;
            submitBtn.textContent = "Изпрати резервация";
        }
    });

    /** Зареждане на начални данни */
    loadPricing();
});
