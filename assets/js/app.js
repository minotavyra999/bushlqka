document.addEventListener("DOMContentLoaded", function () {
  const form = document.querySelector(".bushlyaka-booking-form form");
  if (!form) return;

  const dateInput = form.querySelector(".bush-date-range");
  const sectorEls = form.querySelectorAll(".bush-sector");
  const anglersSelect = form.querySelector("select[name='anglers']");
  const secondHasCardCheckbox = form.querySelector("input[name='secondHasCard']");
  const priceEstimateEl = form.querySelector(".bush-price-estimate");
  const errorGlobal = form.querySelector(".bush-error-global");
  const paySelect = form.querySelector("select[name='payMethod']");

  let prices = null;
  let unavailableSectors = [];
  let blackouts = []; // [{ from:'2025-09-18', to:'2025-09-22' }, ...]

  /** Helpers */
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
      errorGlobal.textContent = "";
      errorGlobal.style.display = "none";
    }
  }

  /** 1) Зареждане на цени */
  async function loadPrices() {
    const r = await fetch(bushlyaka.restUrl + "pricing");
    if (!r.ok) throw new Error("Pricing API error");
    prices = await r.json();
  }

  /** 2) Зареждане на методи за плащане и попълване на dropdown */
  async function loadPayMethods() {
    if (!paySelect) return;
    paySelect.innerHTML = '<option value="">' + "— изберете —" + "</option>";
    const r = await fetch(bushlyaka.restUrl + "payments/methods");
    if (!r.ok) return;
    const methods = await r.json();
    if (Array.isArray(methods) && methods.length) {
      methods.forEach(m => {
        const opt = document.createElement("option");
        opt.value = m.id || m.name || ""; // ако нямаш id в таблицата, ползвай name
        opt.textContent = m.name || "Метод";
        paySelect.appendChild(opt);
      });
    } else {
      // няма методи – оставяме placeholder
      const opt = document.createElement("option");
      opt.value = "";
      opt.textContent = "Няма настроени методи";
      paySelect.appendChild(opt);
    }
  }

  /** 3) Зареждане на blackout периоди */
  async function loadBlackouts() {
    const r = await fetch(bushlyaka.restUrl + "blackouts");
    if (!r.ok) return;
    const data = await r.json();
    if (Array.isArray(data)) {
      blackouts = data.map(b => {
        // очакваме start/end = 'YYYY-MM-DD'
        return { from: b.start, to: b.end };
      });
    }
  }

  /** 4) Инициализация на Flatpickr – inline, винаги отворен, с blackout-и */
  function initCalendar() {
    if (typeof flatpickr === "undefined" || !dateInput) return;
    flatpickr(dateInput, {
      mode: "range",
      dateFormat: "Y-m-d",
      inline: true,        // ✅ винаги отворен
      minDate: "today",
      disable: blackouts,  // блокиране на дата/интервал
      onChange: function (selectedDates, dateStr) {
        // dateStr при range е "YYYY-MM-DD to YYYY-MM-DD"
        if (selectedDates.length === 2) {
          fetchAvailability(selectedDates[0], selectedDates[1]);
        }
      },
    });
  }

  /** 5) Проверка за заети сектори за избрания диапазон */
  async function fetchAvailability(startDate, endDate) {
    clearError();
    try {
      const payload = {
        start: startDate.toISOString().split("T")[0],
        end: endDate.toISOString().split("T")[0],
      };
      const r = await fetch(bushlyaka.restUrl + "availability", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-WP-Nonce": bushlyaka.nonce,
        },
        body: JSON.stringify(payload),
      });
      const data = await r.json();
      unavailableSectors = Array.isArray(data.unavailable) ? data.unavailable.map(String) : [];
      paintUnavailable();
    } catch (e) {
      showError("Грешка при проверка за заетост.");
    }
  }

  /** 6) Обновяване на визуализацията на секторите */
  function paintUnavailable() {
    sectorEls.forEach(el => {
      const s = String(el.dataset.sector);
      if (unavailableSectors.includes(s)) {
        el.classList.add("unavailable");
        el.classList.remove("selected");
      } else {
        el.classList.remove("unavailable");
      }
    });
  }

  /** 7) Калкулация на цена спрямо исканата логика */
  function updateEstimate() {
    if (!prices) return;
    const anglers = parseInt(anglersSelect.value || "1", 10);
    const secondHasCard = !!secondHasCardCheckbox.checked;
    let total = 0;

    if (anglers === 1) {
      total = Number(prices.base);
    } else if (anglers === 2 && secondHasCard) {
      total = Number(prices.base) + Number(prices.second_with_card);
    } else if (anglers === 2) {
      total = Number(prices.base) + Number(prices.second);
    }

    priceEstimateEl.textContent = total.toFixed(2) + " лв.";
  }

  /** 8) Слушатели */
  sectorEls.forEach(el => {
    el.addEventListener("click", () => {
      if (el.classList.contains("unavailable")) return;
      sectorEls.forEach(s => s.classList.remove("selected"));
      el.classList.add("selected");
    });
  });
  anglersSelect.addEventListener("change", updateEstimate);
  secondHasCardCheckbox.addEventListener("change", updateEstimate);

  /** 9) Submit на формата */
  form.addEventListener("submit", async function (e) {
    e.preventDefault();
    clearError();

    const selectedSectorEl = form.querySelector(".bush-sector.selected");
    if (!selectedSectorEl) return showError("Моля, изберете сектор.");

    // вземаме range от flatpickr инпута
    const raw = dateInput.value || "";
    const parts = raw.split(" to ");
    if (parts.length < 2) return showError("Моля, изберете начална и крайна дата.");

    const start = parts[0].trim();
    const end = parts[1].trim();

    const payload = {
      start,
      end,
      sector: selectedSectorEl.dataset.sector,
      anglers: parseInt(anglersSelect.value || "1", 10),
      secondHasCard: !!secondHasCardCheckbox.checked,
      client: {
        firstName: form.querySelector("input[name='firstName']").value.trim(),
        lastName: form.querySelector("input[name='lastName']").value.trim(),
        email: form.querySelector("input[name='email']").value.trim(),
        phone: form.querySelector("input[name='phone']").value.trim(),
      },
      notes: form.querySelector("textarea[name='notes']").value.trim(),
      payMethodId: paySelect ? paySelect.value : "",
    };

    // базова валидация
    if (!payload.client.firstName || !payload.client.lastName) return showError("Въведете име и фамилия.");
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(payload.client.email)) return showError("Невалиден имейл.");
    if (!/^[0-9+\-\s]{6,20}$/.test(payload.client.phone)) return showError("Невалиден телефон.");

    const btn = form.querySelector("button[type='submit']");
    if (btn) { btn.disabled = true; btn.textContent = bushlyaka.messages.loading; }

    try {
      const r = await fetch(bushlyaka.restUrl + "bookings", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-WP-Nonce": bushlyaka.nonce,
        },
        body: JSON.stringify(payload),
      });
      const res = await r.json();
      if (!r.ok || !res.ok) {
        throw new Error(res.message || bushlyaka.messages.error);
      }
      alert(bushlyaka.messages.success + (res.price ? (" Цена: " + res.price + " лв.") : ""));
      window.location.href = bushlyaka.redirectUrl;
    } catch (err) {
      showError(err.message || bushlyaka.messages.error);
    } finally {
      if (btn) { btn.disabled = false; btn.textContent = "Изпрати резервация"; }
    }
  });

  /** 🔄 Стартов bootstrap: цени → методи → blackout-и → календар */
  (async function bootstrap() {
    try {
      await loadPrices();
      updateEstimate();
      await loadPayMethods();
      await loadBlackouts();
      initCalendar();
    } catch (e) {
      // не спираме UI, просто логваме
      console.warn(e);
      initCalendar();
    }
  })();
});
