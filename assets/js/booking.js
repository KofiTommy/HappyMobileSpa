const form = document.querySelector('[data-booking-form]');
if (form) {
  form.classList.add('booking-enhanced');
  const steps = [...form.querySelectorAll('[data-step]')];
  const progress = [...form.querySelectorAll('.booking-progress li')];
  const dateInput = form.querySelector('[data-date-input]');
  const startInput = form.querySelector('[data-start-input]');
  const slots = form.querySelector('[data-slots]');
  const slotStatus = form.querySelector('[data-slot-status]');
  const alert = form.querySelector('[data-form-alert]');
  const serviceInputs = [...form.querySelectorAll('[name="service_id"]')];
  const staffSelect = form.querySelector('[data-staff-select]');
  const profiles = JSON.parse(form.querySelector('[data-therapist-profiles]').textContent);
  const therapistCards = form.querySelector('[data-therapist-cards]');
  let current = 0;
  let controller;

  const showStep = (index, moveFocus = false) => {
    current = index;
    steps.forEach((step, i) => step.classList.toggle('is-active', i === index));
    progress.forEach((item, i) => {
      item.classList.toggle('is-active', i === index);
      item.classList.toggle('is-complete', i < index);
    });
    alert.textContent = '';
    if (index === 3) updateReview();
    form.scrollIntoView({ behavior: 'smooth', block: 'start' });
    if (moveFocus) { steps[index].querySelector('legend')?.focus({ preventScroll: true }); }
  };
  const validStep = () => {
    const wantsMobileMoney = form.querySelector('[name="payment_choice"]:checked')?.value === 'mobile_money';
    form.elements.email.required = wantsMobileMoney;
    if (current === 3 && wantsMobileMoney) {
      const email = form.elements.email;
      email.required = true;
      if (!email.checkValidity()) {
        showStep(2);
        alert.textContent = 'Enter an email address for your mobile money payment receipt.';
        email.focus(); email.reportValidity();
        return false;
      }
    }
    if (current === 1 && !startInput.value) {
      alert.textContent = 'Choose an available appointment time.';
      return false;
    }
    const fields = [...steps[current].querySelectorAll('input,select,textarea')];
    for (const field of fields) {
      if (!field.checkValidity()) { field.reportValidity(); return false; }
    }
    return true;
  };
  form.querySelectorAll('[data-next]').forEach(button => button.addEventListener('click', () => { if (validStep()) showStep(Math.min(3, current + 1), true); }));
  form.querySelectorAll('[data-back]').forEach(button => button.addEventListener('click', () => showStep(Math.max(0, current - 1), true)));

  const selectedService = () => serviceInputs.find(input => input.checked);
  const syncTherapistCards = () => {
    therapistCards.querySelectorAll('button').forEach(button => {
      button.setAttribute('aria-pressed', String(button.dataset.staffId === staffSelect.value));
    });
  };
  const refreshStaffOptions = () => {
    const selected = staffSelect.value;
    const therapists = profiles.filter(person => person.service_ids.includes(Number(selectedService().value)));
    staffSelect.replaceChildren(new Option('Any available therapist', ''));
    therapists.forEach(person => staffSelect.add(new Option(person.name, String(person.id))));
    staffSelect.value = therapists.some(person => String(person.id) === selected) ? selected : '';
    therapistCards.replaceChildren();
    [{ id: '', name: 'Any available therapist', biography: 'We will match you with an available therapist.' }, ...therapists].forEach(person => {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'therapist-card';
      button.dataset.staffId = String(person.id);
      if (person.photo) {
        const img = document.createElement('img');
        img.src = person.photo;
        img.alt = '';
        img.width = 80; img.height = 80;
        img.loading = 'lazy';
        img.addEventListener('error', () => img.replaceWith(placeholder(person.name)), { once: true });
        button.append(img);
      } else {
        button.append(placeholder(person.name));
      }
      const name = document.createElement('strong'); name.textContent = person.name;
      const bio = document.createElement('small'); bio.textContent = person.biography || 'Your spa therapist';
      button.append(name, bio);
      button.addEventListener('click', () => {
        staffSelect.value = String(person.id);
        staffSelect.dispatchEvent(new Event('change'));
      });
      therapistCards.append(button);
    });
    syncTherapistCards();
  };
  const placeholder = name => {
    const span = document.createElement('span');
    span.className = 'therapist-initials';
    span.setAttribute('aria-hidden', 'true');
    span.textContent = name === 'Any available therapist' ? '?' : name.trim().split(/\s+/).slice(0, 2).map(part => part[0]).join('').toUpperCase();
    return span;
  };
  const updateSummary = () => {
    const service = selectedService();
    const selectedSlot = slots.querySelector('input:checked');
    const rows = [
      ['Treatment', service?.dataset.serviceLabel || 'Choose a treatment'],
      ['Duration', service?.dataset.serviceDuration ? `${service.dataset.serviceDuration} min` : '—'],
      ['Price', service?.dataset.servicePrice || '—'],
      ['Therapist', staffSelect.options[staffSelect.selectedIndex]?.text || 'Any available therapist'],
      ['Date', dateInput.value || 'Choose a date'],
      ['Time', selectedSlot?.dataset.timeLabel || 'Choose a time'],
    ];
    const summary = form.querySelector('[data-summary]');
    summary.replaceChildren();
    rows.forEach(([term, value]) => {
      const row = document.createElement('div');
      const dt = document.createElement('dt'); dt.textContent = term;
      const dd = document.createElement('dd'); dd.textContent = value;
      row.append(dt, dd); summary.append(row);
    });
  };
  const appendSlot = (container, slot) => {
    const label = document.createElement('label');
    label.className = 'time-slot';
    const input = document.createElement('input');
    input.type = 'radio'; input.name = 'slot_choice'; input.value = slot.start;
    input.dataset.timeLabel = slot.label;
    const text = document.createElement('span');
    text.textContent = slot.label;
    const staff = document.createElement('small'); staff.textContent = slot.staff_name;
    text.append(staff); label.append(input, text); container.append(label);
    input.addEventListener('change', event => { startInput.value = event.target.value; updateSummary(); });
  };
  const loadSlots = async () => {
    controller?.abort();
    startInput.value = '';
    slots.replaceChildren();
    if (!dateInput.value || !selectedService()) { slotStatus.textContent = 'Select a date to see available times.'; return; }
    controller = new AbortController();
    startInput.value = '';
    slots.replaceChildren();
    slotStatus.textContent = 'Checking actual availability…';
    const query = new URLSearchParams({ service_id: selectedService().value, staff_id: staffSelect.value, date: dateInput.value });
    try {
      const response = await fetch(`api/availability.php?${query}`, { signal: controller.signal, headers: { Accept: 'application/json' } });
      const data = await response.json();
      if (!response.ok) throw new Error(data.error);
      if (!data.slots.length) { slotStatus.textContent = 'No times are available on this date. Try another day.'; return; }
      slotStatus.textContent = `${data.slots.length} available ${data.slots.length === 1 ? 'time' : 'times'} · Ghana time`;
      const periods = [
        { name: 'Morning', slots: data.slots.filter(slot => Number(slot.start.slice(11, 13)) < 12) },
        { name: 'Afternoon', slots: data.slots.filter(slot => Number(slot.start.slice(11, 13)) >= 12) },
      ];
      periods.filter(period => period.slots.length).forEach(period => {
        const group = document.createElement('section');
        group.setAttribute('data-slot-period', period.name.toLowerCase());
        const heading = document.createElement('h3'); heading.textContent = period.name;
        const grid = document.createElement('div'); grid.className = 'slot-grid';
        period.slots.forEach(slot => appendSlot(grid, slot));
        group.append(heading, grid); slots.append(group);
      });
    } catch (error) {
      if (error.name !== 'AbortError') {
        slotStatus.textContent = 'Times could not be loaded. Please try again.';
        const retry = document.createElement('button');
        retry.type = 'button'; retry.className = 'button-back'; retry.setAttribute('data-retry-slots', '');
        retry.textContent = 'Try loading times again'; retry.addEventListener('click', loadSlots);
        slotStatus.append(document.createTextNode(' '), retry);
      }
    }
  };
  dateInput.addEventListener('change', () => { loadSlots(); updateSummary(); });
  staffSelect.addEventListener('change', () => { syncTherapistCards(); loadSlots(); updateSummary(); });
  serviceInputs.forEach(input => input.addEventListener('change', () => {
    startInput.value = '';
    staffSelect.value = '';
    refreshStaffOptions();
    slots.replaceChildren();
    slotStatus.textContent = dateInput.value ? 'Checking actual availability…' : 'Select a date to see available times.';
    if (dateInput.value) loadSlots();
    updateSummary();
  }));

  const updateReview = () => {
    const service = selectedService();
    const date = dateInput.value ? new Date(`${dateInput.value}T12:00:00`) : null;
    const selectedSlot = slots.querySelector('input:checked')?.nextElementSibling;
    const values = [
      ['Treatment', service?.dataset.serviceLabel || ''],
      ['Duration', service?.dataset.serviceDuration ? `${service.dataset.serviceDuration} min` : ''],
      ['Price', service?.dataset.servicePrice || ''],
      ['Therapist', staffSelect.options[staffSelect.selectedIndex].text],
      ['Date', date?.toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }) || ''],
      ['Time', selectedSlot?.childNodes[0]?.textContent || ''],
      ['Name', form.elements.name.value],
      ['Phone', form.elements.phone.value],
    ];
    const review = form.querySelector('[data-review]');
    review.replaceChildren();
    values.forEach(([term, value]) => {
      const row = document.createElement('div');
      const dt = document.createElement('dt'); dt.textContent = term;
      const dd = document.createElement('dd'); dd.textContent = value;
      row.append(dt, dd); review.append(row);
    });
  };
  form.addEventListener('submit', event => {
    if (!validStep()) { event.preventDefault(); return; }
    const submit = form.querySelector('[data-step="3"] [type="submit"]');
    submit.disabled = true; submit.textContent = 'Confirming…';
  });
  refreshStaffOptions();
  if (dateInput.value) { loadSlots(); }
  updateSummary();
  showStep(0);
}
