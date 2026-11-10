// Course registration: save first, then acknowledge by email.
const form = document.querySelector('#application-form');
const steps = [...document.querySelectorAll('.form-step')];
const indicators = [...document.querySelectorAll('.stepper li')];
const catalogue = JSON.parse(document.querySelector('#registration-catalogue').textContent);
const back = document.querySelector('#back');
const next = document.querySelector('#continue');
const drafts = new Map();
let renderedGrade = null;
let submitting = false;
let lastPayload = null;
let submissionId = null;
let currentStep = 0;

function element(tag, text, className) {
  const node = document.createElement(tag);
  if (text !== undefined) node.textContent = text;
  if (className) node.className = className;
  return node;
}

const money = amount => `Rs ${amount.toLocaleString('en-MU')}`;
const selectedGrade = () => catalogue[form.elements.grade.value];

function selectedCourses() {
  const grade = selectedGrade();
  if (!grade) return [];
  const data = new FormData(form);
  const selected = data.getAll('subjects');
  return Object.entries(grade.courses).filter(([, course]) => selected.includes(course.name)).map(([id, course]) => ({
    id, ...course, option: course.options.length === 1 ? course.options[0]
      : course.options.find(option => option.id === data.get(`batches[${id}]`)),
  }));
}

function details() {
  const data = new FormData(form);
  const courses = selectedCourses();
  return { student: data.get('studentName').trim(), grade: data.get('grade'), school: data.get('school').trim(),
    guardian: data.get('guardian').trim(), email: data.get('email').trim(), phone: data.get('phone').trim(),
    courses, total: courses.length * (selectedGrade()?.fee || 0) };
}

function renderCoursePicker() {
  const gradeName = form.elements.grade.value;
  if (renderedGrade === gradeName) return;
  if (renderedGrade) {
    const fields = new FormData(form);
    drafts.set(renderedGrade, {subjects: fields.getAll('subjects'), batches: Object.fromEntries(
      [...form.querySelectorAll('[data-batch-course]:checked')].map(input => [input.dataset.batchCourse, input.value]))});
  }
  renderedGrade = gradeName;
  const grade = selectedGrade();
  const picker = document.querySelector('#course-picker');
  picker.querySelectorAll('article').forEach(node => node.remove());
  document.querySelector('#selected-grade-title').textContent = grade ? `${gradeName} · ${grade.form}` : '';
  if (!grade) return;
  const draft = drafts.get(gradeName);
  for (const [id, course] of Object.entries(grade.courses)) {
    const card = element('article', undefined, 'class-card');
    const label = element('label', undefined, 'class-toggle');
    const checkbox = document.createElement('input');
    Object.assign(checkbox, {type: 'checkbox', name: 'subjects', value: course.name, checked: draft?.subjects.includes(course.name) || false});
    checkbox.dataset.course = id;
    const title = element('span');
    title.append(element('strong', course.name));
    title.append(element('small', course.options.length > 1 ? `Choose one of ${course.options.length} batches`
      : course.options[0].meetings.length > 1 ? `${course.options[0].meetings.length} weekly sessions`
      : `${course.options[0].meetings[0].day} · ${course.options[0].meetings[0].time}`));
    const price = element('span', money(grade.fee), 'class-price');
    price.append(element('small', '/ month'));
    label.append(checkbox, element('span', course.name.includes('Mathematics') ? '∑' : course.name.slice(0, 2), 'class-monogram'), title, price);
    card.append(label);
    if (course.options.length > 1) {
      const batches = element('fieldset', undefined, 'batch-picker');
      batches.dataset.batchPicker = id;
      batches.append(element('legend', 'Choose your batch'));
      const options = element('div', undefined, 'batch-options');
      for (const option of course.options) {
        const choice = element('label', undefined, 'batch-choice');
        const radio = document.createElement('input');
        Object.assign(radio, {type: 'radio', name: `batches[${id}]`, value: option.id, checked: draft?.batches[id] === option.id});
        radio.dataset.batchCourse = id;
        const content = element('span');
        const meeting = option.meetings[0];
        content.append(element('strong', `${option.label} · ${meeting.day}`), element('span', meeting.time), element('small', `Starts ${meeting.start}`));
        choice.append(radio, content);
        options.append(choice);
      }
      const error = element('small', '', 'error');
      error.id = `batch-${id}-error`;
      error.setAttribute('role', 'alert');
      batches.append(options, error);
      card.append(batches);
    } else {
      for (const meeting of course.options[0].meetings) {
        card.append(element('div', `${meeting.note ? meeting.note + ' · ' : ''}${meeting.day} ${meeting.time} · Starts ${meeting.start}`, 'class-start'));
      }
    }
    picker.append(card);
  }
  renderSelection();
}

function appendMeetings(target, course) {
  if (!course.option) {
    target.append(element('small', 'Choose a batch to complete this timetable.', 'hint'));
    return;
  }
  for (const meeting of course.option.meetings) {
    target.append(element('small', `${meeting.note ? meeting.note + ' · ' : ''}${meeting.day} · ${meeting.time} · Starts ${meeting.start}`));
  }
}

function renderSelection() {
  for (const checkbox of form.querySelectorAll('[data-course]')) {
    const picker = form.querySelector(`[data-batch-picker="${checkbox.dataset.course}"]`);
    if (picker) { picker.hidden = !checkbox.checked; picker.disabled = !checkbox.checked; }
  }
  const courses = selectedCourses();
  document.querySelector('#selection-summary').hidden = !courses.length;
  document.querySelector('#selection-count').textContent = `${courses.length} subject${courses.length === 1 ? '' : 's'}`;
  const timetable = document.querySelector('#selected-timetable');
  timetable.replaceChildren();
  for (const course of courses) {
    const row = element('div', undefined, 'selected-course');
    row.append(element('strong', `${course.name}${course.option?.label ? ' · ' + course.option.label : ''}`));
    appendMeetings(row, course);
    timetable.append(row);
  }
  document.querySelector('#monthly-total').textContent = money(courses.length * (selectedGrade()?.fee || 0));
  document.querySelector('#fee-breakdown').textContent = `${courses.length} subject${courses.length === 1 ? '' : 's'} × ${money(selectedGrade()?.fee || 0)} / month`;
}

function definitions(target, entries) {
  target.replaceChildren();
  for (const [label, value] of entries) {
    const row = element('div');
    row.append(element('dt', label), element('dd', value));
    target.append(row);
  }
}

function renderPayment() {
  const data = details();
  definitions(document.querySelector('#review-student'), [['Student', data.student], ['Grade', data.grade], ['School', data.school]]);
  definitions(document.querySelector('#review-contact'), [['Parent / guardian', data.guardian], ['Email', data.email], ['Phone', data.phone]]);
  const list = document.querySelector('#payment-courses');
  list.replaceChildren();
  for (const course of data.courses) {
    const row = element('li');
    const content = element('div');
    content.append(element('strong', `${course.name}${course.option?.label ? ' · ' + course.option.label : ''}`));
    appendMeetings(content, course);
    row.append(content, element('strong', money(selectedGrade().fee), 'course-amount'));
    list.append(row);
  }
  document.querySelector('#payment-total').textContent = money(data.total);
  document.querySelector('#payment-breakdown').textContent = `${data.courses.length} subject${data.courses.length === 1 ? '' : 's'} × ${money(selectedGrade()?.fee || 0)} per month`;
  document.querySelector('#payment-reference').textContent = `${data.student} + ${data.grade}`;
}

function showStep(index, focus = true) {
  currentStep = index;
  renderCoursePicker();
  steps.forEach((step, i) => { step.hidden = i !== index; });
  indicators.forEach((item, i) => {
    item.classList.toggle('complete', i < index);
    if (i === index) item.setAttribute('aria-current', 'step');
    else item.removeAttribute('aria-current');
    item.querySelector('span').textContent = i < index ? '✓' : String(i + 1);
  });
  back.hidden = index === 0;
  next.firstChild.textContent = index === steps.length - 1 ? 'Submit registration ' : index === 2 ? 'Review & payment ' : 'Continue ';
  document.querySelector('#required-note').hidden = index !== 0;
  document.querySelector('#submission-error').textContent = '';
  if (index === 3) renderPayment();
  if (focus) steps[index].querySelector('h3').focus();
}

function validateStep() {
  let firstInvalid;
  steps[currentStep].querySelectorAll('input[required], select[required]').forEach(input => {
    const validPhone = input.type !== 'tel' || (/^[+0-9 ()-]+$/.test(input.value) && input.value.replace(/\D/g, '').length >= 7);
    const valid = input.checkValidity() && validPhone && (input.type === 'checkbox' || input.value.trim() !== '');
    const error = document.getElementById(`${input.id}-error`);
    if (!valid) {
      input.setAttribute('aria-invalid', 'true');
      input.setAttribute('aria-describedby', error.id);
      error.textContent = input.type === 'email' ? 'Enter a valid email address.' : input.type === 'tel' ? 'Enter a valid contact number.' : input.type === 'checkbox' ? 'Please confirm before submitting.' : input.tagName === 'SELECT' ? 'Please choose a grade.' : 'Please fill in this field.';
      firstInvalid ||= input;
    } else { input.removeAttribute('aria-invalid'); input.removeAttribute('aria-describedby'); error.textContent = ''; }
  });
  if (currentStep === 1) {
    const courses = selectedCourses();
    document.querySelector('#subjects-error').textContent = courses.length ? '' : 'Choose at least one subject to continue.';
    if (!courses.length) firstInvalid ||= form.querySelector('[data-course]');
    for (const course of courses) {
      const error = document.querySelector(`#batch-${course.id}-error`);
      if (error) error.textContent = course.option ? '' : 'Choose a batch to continue.';
      if (!course.option) firstInvalid ||= form.querySelector(`[data-batch-course="${course.id}"]`);
    }
  }
  firstInvalid?.focus();
  return !firstInvalid;
}

form.addEventListener('submit', async event => {
  event.preventDefault();
  if (submitting || !validateStep()) return;
  if (currentStep < steps.length - 1) return showStep(currentStep + 1);
  const fields = new FormData(form);
  const data = details();
  const payload = {
    studentName: data.student, grade: data.grade, school: data.school, returning: fields.get('returning'),
    subjects: data.courses.map(course => course.name), batches: Object.fromEntries(data.courses.map(course => [course.id, course.option.id])),
    guardian: data.guardian, phone: data.phone, email: data.email,
    message: fields.get('message').trim(), consent: fields.get('consent') === 'on', updates: fields.get('updates') === 'on',
  };
  const serialized = JSON.stringify(payload);
  if (serialized !== lastPayload) { submissionId = crypto.randomUUID(); lastPayload = serialized; }
  const error = document.querySelector('#submission-error');
  error.textContent = '';
  submitting = true;
  form.inert = true;
  form.setAttribute('aria-busy', 'true');
  next.disabled = true;
  next.firstChild.textContent = 'Registering… ';
  try {
    const response = await fetch(form.action, {
      method: 'POST', credentials: 'same-origin',
      headers: {'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content},
      body: JSON.stringify({...payload, submission_id: submissionId}), signal: AbortSignal.timeout(25000),
    });
    const result = await response.json().catch(() => ({}));
    if (!response.ok) {
      if (response.status === 422) throw new Error(Object.values(result.errors || {}).flat().join(' ') || 'Please check your details and try again.');
      if (response.status === 419) throw new Error('Your session expired. Refresh this page before registering again.');
      if (response.status === 429) throw new Error('Too many attempts. Please wait a minute, then try again.');
      throw new Error(response.status === 409 ? result.message : 'We could not confirm your registration. Please try again.');
    }
    if (!result.reference || !['sent', 'pending'].includes(result.email_status)) throw new Error('We could not confirm your registration. Please try again.');
    document.querySelector('#success-copy').textContent = `Thank you, ${data.guardian}. The registration for ${data.student} has been saved.`;
    document.querySelector('#registration-reference').textContent = result.reference;
    const registered = document.querySelector('#registered-classes');
    registered.replaceChildren();
    for (const course of data.courses) {
      const row = element('div', undefined, 'selected-course');
      row.append(element('strong', `${course.name}${course.option.label ? ' · ' + course.option.label : ''}`));
      appendMeetings(row, course);
      registered.append(row);
    }
    document.querySelector('#success-total').textContent = money(data.total);
    document.querySelector('#email-status').textContent = result.email_enabled === false
      ? 'Your registration is saved. Email confirmations are not enabled yet; please keep your reference.'
      : result.email_status === 'sent'
      ? `Your acknowledgement has been sent to ${data.email}. Please check your inbox and spam folder.`
      : 'Your registration is saved. The acknowledgement email is delayed; please keep the reference above.';
    document.querySelector('#form-view').hidden = true;
    document.querySelector('#success-view').hidden = false;
    document.querySelector('#success-title').focus();
  } catch (failure) {
    error.textContent = failure.name === 'TimeoutError' || failure.name === 'TypeError'
      ? 'The connection was interrupted. Your details are still here. Try again to confirm your registration.' : failure.message;
  } finally {
    submitting = false;
    form.inert = false;
    form.removeAttribute('aria-busy');
    next.disabled = false;
    next.firstChild.textContent = 'Submit registration ';
    if (error.textContent) error.focus();
  }
});
back.addEventListener('click', () => showStep(currentStep - 1));
form.querySelectorAll('[data-edit-step]').forEach(button => button.addEventListener('click', () => showStep(Number(button.dataset.editStep))));
form.addEventListener('input', event => {
  const input = event.target;
  if (input.hasAttribute('aria-invalid')) {
    input.removeAttribute('aria-invalid'); input.removeAttribute('aria-describedby');
    const error = document.getElementById(`${input.id}-error`);
    if (error) error.textContent = '';
  }
  if (input.name === 'grade') renderCoursePicker();
  if (input.dataset.course || input.dataset.batchCourse) {
    document.querySelector('#subjects-error').textContent = '';
    form.querySelectorAll('[data-batch-picker] .error').forEach(error => { error.textContent = ''; });
    renderSelection();
  }
});
document.querySelector('#restart').addEventListener('click', () => {
  form.reset(); drafts.clear(); renderedGrade = null; lastPayload = null; submissionId = null;
  form.querySelectorAll('.error').forEach(error => { error.textContent = ''; });
  form.querySelectorAll('[aria-invalid]').forEach(input => { input.removeAttribute('aria-invalid'); input.removeAttribute('aria-describedby'); });
  document.querySelector('#form-view').hidden = false;
  document.querySelector('#success-view').hidden = true;
  showStep(0);
});
renderCoursePicker();
