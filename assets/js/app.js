document.addEventListener("DOMContentLoaded", () => {
  const form = document.querySelector(".bushlyaka-booking-form");
  if (!form) return;

  // --- Състояние ---
  const state = {
    start: null,
    end: null,
    sector: null,
    anglers: 1,
    secondHasCard: false,
    pricing: null,
    payMethods: [],
    priceEstimate: 0,
  };

  // --- Помощни функции ---
  const showError = (msg) => {
    const errorBox = form.querySelector(".bush-error-global");
    if (errorBox) {
      errorBox.textContent = msg;
      errorBox.style.display = "block";
    } else {
      alert(msg);
    }
  };

  const clearError = () => {
    const errorBox = form.querySelector(".bush-error-global");
    if (errorBox) errorBox.style.display = "none";
  };

  const validateEmail = (email) =>
    /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);

  const validatePhone = (phone) =>
    /^[0-9+\-\s]{6,20}$/.test(phone);

  const updatePrice = () => {
    if (!state.pricing) return;
    let price = state.pricing.base;
    if (state.anglers > 1) {
      price += state.secondHasCard
        ? state.pricing.second_with_card
        : state.pricing.second;
    }
    state.priceEstimate = price;
    form.querySelector(".bush-price-estimate").textContent =
      price + " лв.";
  };

  const toggleLoading = (loading) => {
    const btn = form.querySelector("button[type=submit]");
    if (!btn) return;
    btn.disabled = loading;
    btn.textContent = loading ? "Изпращане..." : "Изпрати резервация";
  };

  // --- Fetch данни ---
  const apiFetch = async (endpoint, options = {}) => {
    try {
      const res = await fetch(
        bushlyaka.restUrl + endpoint,
        {
          headers: {
            "Content-Type": "application/json",
            "X-WP-Nonce": bushlyaka.nonce,
          },
          ...options,
        }
      );
      if (!res.ok) {
        const err = await res.json().catch(() => ({}));
        throw new Error(err.message || "Грешка при заявката");
      }
      return res.json();
    } catch (err) {
      showError(err.message);
      throw err;
    }
  };

  // --- Календар ---
  flatpickr(form.querySelector(".bush-date-range"), {
    mode: "range",
    dateFormat: "Y-m-d",
    minDate: "today",
    inline: true,
    showMonths: window.innerWidth < 640 ? 1 : 2,
    onChange: async (selectedDates) => {
      if (selectedDates.length === 2) {
        clearError();
        state.start = selectedDates[0].toISOString();
        state.end = selectedDates[1].toISOString();

        // Проверка за заети сектори
        try {
          const data = await apiFetch("availability", {
            method: "POST",
            body: JSON.stringify({
              start: state.start,
              end: state.end,
            }),
          });
          document
            .querySelectorAll(".bush-sector")
            .forEach((el) => {
              const sec = el.dataset.sector;
              if (data.unavailable.includes(sec)) {
                el.classList.add("unavailable");
                el.classList.remove("available", "selected");
              } else {
                el.classList.add("available");
                el.classList.remove("unavailable");
              }
            });
        } catch (err) {
          // handled by apiFetch
        }
      }
    },
  });

  // --- Сектори ---
  form.querySelectorAll(".bush-sector").forEach((btn) => {
    btn.addEventListener("click", () => {
      if (btn.classList.contains("unavailable")) return;
      form.querySelectorAll(".bush-sector").forEach((el) =>
        el.classList.remove("selected")
      );
      btn.classList.add("selected");
      state.sector = btn.dataset.sector;
    });
  });

  // --- Англери ---
  form
    .querySelector("[name=anglers]")
    .addEventListener("change", (e) => {
      state.anglers = parseInt(e.target.value, 10) || 1;
      updatePrice();
    });

  form
    .querySelector("[name=secondHasCard]")
    .addEventListener("change", (e) => {
      state.secondHasCard = e.target.checked;
      updatePrice();
    });

  // --- Submit ---
  form.addEventListener("submit", async (e) => {
    e.preventDefault();
    clearError();

    // Валидация
    if (!state.start || !state.end) {
      showError("Моля изберете дати.");
      return;
    }
    if (!state.sector) {
      showError("Моля изберете сектор.");
      return;
    }

    const firstName = form.querySelector("[name=firstName]").value.trim();
    const lastName = form.querySelector("[name=lastName]").value.trim();
    const email = form.querySelector("[name=email]").value.trim();
    const phone = form.querySelector("[name=phone]").value.trim();

    if (!firstName || !lastName) {
      showError("Моля въведете име и фамилия.");
      return;
    }
    if (!validateEmail(email)) {
      showError("Невалиден имейл адрес.");
      return;
    }
    if (!validatePhone(phone)) {
      showError("Невалиден телефон.");
      return;
    }

    // Изпращане
    toggleLoading(true);
    try {
      await apiFetch("bookings", {
        method: "POST",
        body: JSON.stringify({
          start: state.start,
          end: state.end,
          sector: state.sector,
          anglers: state.anglers,
          secondHasCard: state.secondHasCard,
          client: { firstName, lastName, email, phone },
          notes: form.querySelector("[name=notes]").value.trim(),
          payMethodId: form.querySelector("[name=payMethod]").value,
        }),
      });

      form.innerHTML = `<p class="success">Резервацията е изпратена успешно!</p>`;
      setTimeout(() => {
        window.location.href = bushlyaka.redirectUrl || "/";
      }, 2500);
    } catch (err) {
      toggleLoading(false);
    }
  });

  // --- Зареждане на данни ---
  (async () => {
    try {
      const pricing = await apiFetch("pricing");
      state.pricing = pricing;
      updatePrice();

      const payMethods = await apiFetch("payments/methods");
      state.payMethods = payMethods;
      const select = form.querySelector("[name=payMethod]");
      payMethods.forEach((pm) => {
        const opt = document.createElement("option");
        opt.value = pm.id;
        opt.textContent = pm.name;
        select.appendChild(opt);
      });
    } catch (err) {
      // handled by apiFetch
    }
  })();
});
