(function(){
  if (!window.BushCfg) return;
  const el = document.getElementById('bush-booking'); if (!el) return;

  // ---------- State ----------
  const state = {
    start: null,           // ISO (12:00)
    end: null,             // ISO (12:00)
    sector: null,          // 1..19
    anglers: 1,            // 1 или 2
    secondHasCard: false,  // вторият има карта?
    pricing: null,         // от /pricing
    priceEstimate: 0
  };

  // ---------- DOM ----------
  const steps          = [...el.querySelectorAll('.bush-step')];
  const rangeInput     = document.getElementById('bush-date-range');
  const durationBox    = document.getElementById('bush-duration');
  const sectorsWrap    = document.getElementById('bush-sectors');
  const unavailableBox = document.getElementById('bush-unavailable');
  const anglersSel     = document.getElementById('bush-anglers');
  const secondCard     = document.getElementById('bush-second-card');
  const priceBox       = document.getElementById('bush-price');
  const priceMeta      = document.getElementById('bush-price-meta');
  const summary        = document.getElementById('bush-summary');
  const submitBtn      = document.getElementById('bush-submit');
  const resultBox      = document.getElementById('bush-result');
  const payMethodSelect= document.getElementById('bush-pay-method');
  const payInstrBox    = document.getElementById('bush-pay-instr');

  const first  = document.getElementById('bush-first');
  const last   = document.getElementById('bush-last');
  const email  = document.getElementById('bush-email');
  const phone  = document.getElementById('bush-phone');
  const notes  = document.getElementById('bush-notes');

  // ---------- Helpers ----------
  function fmt(date){
    const d = new Date(date);
    const dd = String(d.getDate()).padStart(2,'0');
    const mm = String(d.getMonth()+1).padStart(2,'0');
    const yyyy = d.getFullYear();
    return `${dd}.${mm}.${yyyy}, 12:00`;
  }
  function addDays(date, days){ const d = new Date(date); d.setDate(d.getDate()+days); return d; }
  function isFriday(date){ return new Date(date).getDay() === 5; } // 0=Sun, 5=Fri
  function hoursBetween(a,b){ return Math.round((new Date(b)-new Date(a))/36e5); }
  function setStep(target){ steps.forEach(s => { s.hidden = s.getAttribute('data-step') !== String(target); }); }

  // ---------- REST fetchers ----------
  async function fetchPricing(){
    try{
      const r = await fetch(BushCfg.rest.base + 'pricing');
      state.pricing = await r.json();
      updatePrice();
    } catch(e){ console.error('pricing error', e); }
  }
  async function fetchBlackouts(){
    try{
      const r = await fetch(BushCfg.rest.base + 'blackouts');
      const ranges = await r.json();
      const disable = (ranges || []).map(rg => ({ from: rg.start, to: rg.end }));
      try { fp.set('disable', disable); } catch(e){}
    } catch(e){ console.error('blackouts error', e); }
  }
  async function fetchPayMethods(){
    try {
      const r = await fetch(BushCfg.rest.base + 'payments/methods');
      const methods = await r.json();
      payMethodSelect.innerHTML = '';
      if (!methods.length){
        const opt = document.createElement('option');
        opt.value = '0'; opt.textContent = 'На място';
        payMethodSelect.appendChild(opt);
        payInstrBox.textContent = '';
        return;
      }
      methods.forEach(m => {
        const opt = document.createElement('option');
        opt.value = String(m.id);
        opt.textContent = m.name;
        opt.dataset.instructions = m.instructions || '';
        payMethodSelect.appendChild(opt);
      });
      function updateInstr(){
        const sel = payMethodSelect.options[payMethodSelect.selectedIndex];
        payInstrBox.innerHTML = sel && sel.dataset.instructions ? sel.dataset.instructions : '';
      }
      payMethodSelect.addEventListener('change', updateInstr);
      updateInstr();
    } catch(e){ console.error('paymethods error', e); }
  }

  async function checkAvailability(){
    if (!state.start || !state.end) return;
    unavailableBox.textContent = 'Проверка за налични сектори...';
    try {
      const r = await fetch(BushCfg.rest.base + 'availability', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ start: state.start, end: state.end })
      });
      const data = await r.json();
      const unavailable = data.unavailableSectors || [];
      unavailableBox.textContent = unavailable.length ? 'Недостъпни: ' + unavailable.join(', ') : 'Всички сектори са налични.';

      sectorsWrap.innerHTML = '';
      (BushCfg.sectors || []).forEach(n => {
        const b = document.createElement('button');
        b.type = 'button';
        b.className = 'bush-sector';
        const isBusy = unavailable.includes(n);
        b.innerHTML = `
          <span class="label">Сектор ${n}</span>
          <span class="chip ${isBusy ? 'chip-busy' : 'chip-free'}">${isBusy ? 'Зает' : 'Свободен'}</span>
          <small class="sub">12:00 → 12:00</small>
        `;
        if (isBusy) {
          b.classList.add('unavailable');
          b.disabled = true;
        }
        b.addEventListener('click', () => {
          [...sectorsWrap.querySelectorAll('.bush-sector')].forEach(x=>x.classList.remove('selected'));
          b.classList.add('selected');
          state.sector = n;
          el.querySelector('[data-step="2"] .bush-next]').disabled = false;
        });
        sectorsWrap.appendChild(b);
      });
    } catch(err){
      console.error('availability error', err);
      unavailableBox.textContent = 'Грешка при проверка. Показваме всички сектори като свободни.';
      sectorsWrap.innerHTML = '';
      (BushCfg.sectors || []).forEach(n => {
        const b = document.createElement('button');
        b.type = 'button';
        b.className = 'bush-sector';
        b.innerHTML = `
          <span class="label">Сектор ${n}</span>
          <span class="chip chip-free">Свободен</span>
          <small class="sub">12:00 → 12:00</small>
        `;
        b.addEventListener('click', () => {
          [...sectorsWrap.querySelectorAll('.bush-sector')].forEach(x=>x.classList.remove('selected'));
          b.classList.add('selected');
          state.sector = n;
          el.querySelector('[data-step="2"] .bush-next]').disabled = false;
        });
        sectorsWrap.appendChild(b);
      });
    }
  }

  // ---------- Price ----------
  function updatePrice(){
    if (!state.pricing) return;
    const hours = state.start && state.end ? hoursBetween(state.start, state.end) : 0;
    const blocks = Math.max(1, Math.round(hours/24));
    let base = state.pricing.base_per_24h * blocks;
    if (state.anglers === 2) {
      base += (state.secondHasCard ? state.pricing.second_angler_with_card_price
                                   : state.pricing.second_angler_price) * blocks;
    }
    state.priceEstimate = base;
    if (priceBox) priceBox.textContent = base.toFixed(2) + ' лв';
    if (priceMeta) priceMeta.textContent =
      `База: ${state.pricing.base_per_24h} лв / 24ч; 2-ри рибар: ${(state.secondHasCard?state.pricing.second_angler_with_card_price:state.pricing.second_angler_price)} лв / 24ч`;
  }

  function rebuildSummary(){
    if (!summary) return;
    const h = hoursBetween(state.start,state.end)/24;
    summary.innerHTML = `
      <div><strong>Период:</strong> ${fmt(state.start)} → ${fmt(state.end)} (${h} × 24ч)</div>
      <div><strong>Сектор:</strong> № ${state.sector}</div>
      <div><strong>Рибари:</strong> ${state.anglers}${state.anglers===2?` (2-ри с${state.secondHasCard?'':' без'} карта)`:''}</div>
      <div><strong>Ориентировъчна сума:</strong> ${state.priceEstimate.toFixed(2)} лв</div>
    `;
  }

  // ---------- Flatpickr (INLINE) ----------
  const fp = flatpickr(rangeInput, {
    inline: true,      // винаги отворен
    static: true,      // фиксиран в потока
    mode: 'range',
    showMonths: 2,
    disableMobile: true,
    dateFormat: 'Y-m-d',
    locale: {
      weekdays: { shorthand: ['Нд','Пн','Вт','Ср','Чт','Пт','Сб'], longhand: ['Неделя','Понеделник','Вторник','Сряда','Четвъртък','Петък','Събота'] },
      months:   { shorthand: ['Ян','Фев','Мар','Апр','Май','Юни','Юли','Авг','Сеп','Окт','Ное','Дек'], longhand: ['Януари','Февруари','Март','Април','Май','Юни','Юли','Август','Септември','Октомври','Ноември','Декември'] }
    },
    minDate: 'today',
    onChange: function(selectedDates){
      // 1 дата → автоматичен край +24ч (или +48ч ако е петък)
      if (selectedDates.length === 1){
        const start = new Date(selectedDates[0]); start.setHours(12,0,0,0);
        let end = addDays(start, 1);
        if (isFriday(start)) end = addDays(start, 2);
        state.start = start.toISOString(); state.end = end.toISOString();
        durationBox.textContent = `Продължителност: ${hoursBetween(state.start,state.end)/24} × 24 часа`;
        el.querySelector('[data-step="1"] .bush-next').disabled = false;
      }
      // 2 дати → валидиране на правилата
      if (selectedDates.length === 2){
        const start = new Date(selectedDates[0]); start.setHours(12,0,0,0);
        let end = new Date(selectedDates[1]); end.setHours(12,0,0,0);
        if (isFriday(start)){ const minEnd = addDays(start, 2); if (end < minEnd) end = minEnd; }
        else if (end <= start) { end = addDays(start, 1); }
        state.start = start.toISOString(); state.end = end.toISOString();
        durationBox.textContent = `Продължителност: ${hoursBetween(state.start,state.end)/24} × 24 часа`;
        el.querySelector('[data-step="1"] .bush-next').disabled = false;
      }
    },
    onClose: function(){ if (state.start && state.end) { checkAvailability(); updatePrice(); } }
  });

  // Мобилна адаптация: 1 месец под 640px, 2 месеца иначе
  function adaptCalendarForMobile(){
    if (window.matchMedia('(max-width: 640px)').matches) {
      try { fp.set('showMonths', 1); } catch(e){}
    } else {
      try { fp.set('showMonths', 2); } catch(e){}
    }
  }
  adaptCalendarForMobile();
  window.addEventListener('resize', adaptCalendarForMobile);

  // ---------- Nav ----------
  el.querySelectorAll('.bush-next').forEach(btn => btn.addEventListener('click', (e)=>{
    const to = parseInt(e.currentTarget.dataset.next,10);
    if (to === 2) { checkAvailability(); }
    if (to === 6) { rebuildSummary(); }
    setStep(to);
  }));
  el.querySelectorAll('.bush-prev').forEach(btn => btn.addEventListener('click', (e)=>{
    const to = parseInt(e.currentTarget.dataset.prev,10);
    setStep(to);
  }));

  // ---------- Anglers & card ----------
  if (anglersSel) anglersSel.addEventListener('change', (e)=>{
    state.anglers = parseInt(e.target.value,10);
    secondCard.disabled = state.anglers !== 2;
    if (state.anglers !== 2) { secondCard.checked = false; state.secondHasCard = false; }
    updatePrice();
  });
  if (secondCard) secondCard.addEventListener('change', ()=>{ state.secondHasCard = !!secondCard.checked; updatePrice(); });

  // ---------- Contact validation ----------
  function validateContact(){ 
    const ok = first.value && last.value && email.value.includes('@') && phone.value.length >= 6;
    el.querySelector('[data-step="4"] .bush-next').disabled = !ok;
  }
  [first,last,email,phone].forEach(inp => inp && inp.addEventListener('input', validateContact));

  // ---------- Submit ----------
  if (submitBtn) submitBtn.addEventListener('click', async ()=>{
    const consentEl = document.getElementById('bush-consent');
    if (consentEl && !consentEl.checked) { alert('Моля, потвърдете съгласието.'); return; }
    if (!state.sector) { alert('Моля, изберете сектор.'); return; }
    submitBtn.disabled = true; resultBox.className = 'bush-result'; resultBox.textContent = 'Изпращане...';

    const payload = {
      start: state.start, end: state.end, sector: state.sector,
      anglers: state.anglers, secondHasCard: state.secondHasCard,
      client: { firstName: first.value, lastName: last.value, email: email.value, phone: phone.value, notes: notes.value },
      priceEstimate: state.priceEstimate,
      payMethodId: (function(){ const v = payMethodSelect.value; return parseInt(v,10)||0; })()
    };

    try {
      const r = await fetch(BushCfg.rest.base + 'bookings', {
        method:'POST',
        headers:{'Content-Type':'application/json','X-WP-Nonce': BushCfg.rest.nonce},
        body: JSON.stringify(payload)
      });
      const data = await r.json();
      if (data && data.ok){
        resultBox.className = 'bush-result ok';
        resultBox.textContent = 'Заявката е изпратена. №: ' + data.bookingId;

        // Забраняваме повторно натискане и пренасочваме след 3s
        submitBtn.disabled = true;
        setTimeout(() => { window.location.href = "/thanks"; }, 3000);

      } else {
        throw new Error((data && data.message) ? data.message : 'Грешка при запис.');
      }
    } catch(err){
      resultBox.className = 'bush-result err';
      resultBox.textContent = 'Възникна проблем: ' + err.message;
      submitBtn.disabled = false;
    }
  });

  // ---------- Init ----------
  fetchPricing();
  fetchBlackouts();
  fetchPayMethods();
})();
