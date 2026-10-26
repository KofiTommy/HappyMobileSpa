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
  let current = 0;
  let controller;

  const showStep = index => {
    current = index;
    steps.forEach((step, i) => step.classList.toggle('is-active', i === index));
    progress.forEach((item, i) => {
      item.classList.toggle('is-active', i === index);
      item.classList.toggle('is-complete', i < index);
    });
    alert.textContent = '';
    if (index === 3) updateReview();
    form.scrollIntoView({ behavior: 'smooth', block: 'start' });
  };
  const validStep = () => {
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
  form.querySelectorAll('[data-next]').forEach(button => button.addEventListener('click', () => { if (validStep()) showStep(Math.min(3, current + 1)); }));
  form.querySelectorAll('[data-back]').forEach(button => button.addEventListener('click', () => showStep(Math.max(0, current - 1))));

  const selectedService = () => serviceInputs.find(input => input.checked);
  const loadSlots = async () => {
    if (!dateInput.value || !selectedService()) return;
    controller?.abort();
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
      data.slots.forEach(slot => {
        const label = document.createElement('label');
        label.className = 'time-slot';
        label.innerHTML = `<input type="radio" name="slot_choice" value="${slot.start}"><span>${slot.label}<small>${slot.staff_name}</small></span>`;
        label.querySelector('input').addEventListener('change', event => { startInput.value = event.target.value; });
        slots.append(label);
      });
    } catch (error) {
      if (error.name !== 'AbortError') slotStatus.textContent = 'Times could not be loaded. Please try again.';
    }
  };
  dateInput.addEventListener('change', loadSlots);
  staffSelect.addEventListener('change', loadSlots);
  serviceInputs.forEach(input => input.addEventListener('change', () => { startInput.value = ''; slots.replaceChildren(); slotStatus.textContent = 'Therapist options will refresh when you continue.'; }));

  const updateReview = () => {
    const service = selectedService()?.closest('.booking-service');
    const date = dateInput.value ? new Date(`${dateInput.value}T12:00:00`) : null;
    const selectedSlot = slots.querySelector('input:checked')?.nextElementSibling;
    const values = [
      ['Treatment', service?.querySelector('strong')?.textContent || ''],
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
    const submit = form.querySelector('[type="submit"]');
    submit.disabled = true; submit.textContent = 'Confirming…';
  });
  showStep(0);
}
