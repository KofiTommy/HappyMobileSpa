<?php
// Generate an isolated browser fixture: php tests/booking-navigation-browser.php > booking-test.html
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/bootstrap.php';
seed_availability_fixture();
$services = Catalog::activeServices();
$html = render_template_for_test('book', [
    'services'=>$services, 'selectedService'=>$services[0], 'staff'=>Catalog::activeStaff(1),
    'settings'=>Catalog::settings(), 'old'=>[], 'error'=>'',
]);
echo '<!doctype html><meta charset="utf-8"><title>Booking navigation test</title>';
echo '<script>window.fetch = async () => ({ok:true, json:async () => ({slots:[{start:"2099-01-06 10:00:00",label:"10:00",staff_name:"Test therapist"}]})});</script>';
echo str_replace('<script src="assets/js/booking.js" defer></script>', '<script>' . file_get_contents(APP_ROOT . '/assets/js/booking.js') . '</script>', $html);
?>
<pre id="test-result">RUNNING</pre>
<script>
(async () => {
  const check = (condition, message) => { if (!condition) throw new Error(message); };
  const form = document.querySelector('[data-booking-form]');
  const step = () => Number(form.querySelector('.booking-step.is-active').dataset.step);
  try {
    check(step() === 0, 'starts at treatment');
    form.querySelector('.therapist-card').click();
    check(step() === 1, 'therapist selection advances to time');
    form.querySelector('[data-step="1"] [data-next]').click();
    check(step() === 1, 'cannot advance without a time');
    const date = form.querySelector('[data-date-input]');
    date.value = '2099-01-06'; date.dispatchEvent(new Event('change'));
    await new Promise(resolve => setTimeout(resolve, 0));
    form.querySelector('[name="slot_choice"]').click();
    check(step() === 2, 'time selection advances to details');
    form.querySelector('[data-step="2"] [data-back]').click();
    check(step() === 1 && form.querySelector('[name="slot_choice"]').checked, 'back keeps time selection');
    form.querySelector('[data-step="1"] [data-next]').click();
    const enter = new KeyboardEvent('keydown', {key:'Enter', bubbles:true, cancelable:true});
    form.elements.name.dispatchEvent(enter);
    check(step() === 2 && enter.defaultPrevented, 'Enter cannot submit incomplete details');
    form.elements.name.value = 'Test Customer';
    form.elements.phone.value = '0244127788';
    form.elements.name.dispatchEvent(new KeyboardEvent('keydown', {key:'Enter', bubbles:true, cancelable:true}));
    check(step() === 3, 'Enter advances completed details to review');
    check(form.querySelector('[data-review]').textContent.includes('Test Customer'), 'review contains customer details');
    const submit = new Event('submit', {cancelable:true});
    form.dispatchEvent(submit);
    check(submit.defaultPrevented, 'policy must be accepted before confirming');
    check(!form.querySelector('[data-step="3"] [type="submit"]').disabled, 'failed validation allows correction');
    document.querySelector('#test-result').textContent = 'PASS: automatic navigation, back, validation, Enter, review and consent';
  } catch (error) { document.querySelector('#test-result').textContent = 'FAIL: ' + error.message; }
})();
</script>
