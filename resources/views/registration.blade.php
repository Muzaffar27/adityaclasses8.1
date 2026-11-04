<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#0f172a">
  <meta name="description" content="Begin your learning journey with Aditya Classes. Student admissions for 2027.">
  <title>Student admissions 2027 · Aditya Classes</title>
  <link rel="icon" href="{{ asset('logo.png') }}">
  <link rel="stylesheet" href="{{ asset('registration-poc/styles.css') }}?v=registration-mail-off-20261104">
  <script type="application/json" id="registration-catalogue">@json(config('registration.catalogue'))</script>
  <script src="{{ asset('registration-poc/app.js') }}?v=registration-mail-off-20261104" defer></script>
</head>
<body>
  <header class="site-header">
    <a class="brand" href="{{ route('registration') }}" aria-label="Aditya Classes admissions home"><span class="brand-mark"><img src="{{ asset('logo.png') }}" width="40" height="34" alt=""></span><span>ADITYA CLASSES<small>A little guidance. A brighter future.</small></span></a>
    <a class="header-link" href="#how-it-works">How admissions work <span aria-hidden="true">↗</span></a>
  </header>

  <main id="main">
    <aside class="intro">
      <div class="intake"><span></span> 2027 STUDENT INTAKE</div>
      <h1>Big futures.<br><span class="headline-accent">Small beginnings.</span></h1>
      <p class="intro-copy">A new chapter starts with the right support. Let’s find the right class for your child.</p>
      <div class="learning-art" aria-hidden="true">
        <div class="art-grid"></div><span class="art-star">✳</span><span class="art-label">ROOM TO GROW</span>
        <div class="rise rise-one"><span>01</span></div><div class="rise rise-two"><span>02</span></div><div class="rise rise-three"><span>03</span><b>↗</b></div>
        <span class="art-caption">One step closer to what’s next.</span>
      </div>
      <div class="intro-details"><span><b>01—</b> Tell us about your child</span><span><b>02—</b> Choose their learning path</span><span><b>03—</b> We’ll take it from there</span></div>
      <div class="intro-bottom"><span class="mini-icon" aria-hidden="true">✓</span><p>No payment needed to apply.<br><strong>A simple first step. About 2 minutes.</strong></p></div>
    </aside>

    <section class="application" aria-labelledby="form-title">
      <div id="form-view">
        <div class="section-kicker">LET’S GET TO KNOW YOU</div>
        <h2 id="form-title">Register for classes</h2>
        <p class="section-copy">A few details today. A world of possibilities tomorrow.</p>
        <ol class="stepper" aria-label="Application progress">
          <li aria-current="step"><span>1</span><b>Student</b></li><li><span>2</span><b>Classes</b></li><li><span>3</span><b>Contact</b></li><li><span>4</span><b>Payment</b></li>
        </ol>
        <form id="application-form" action="{{ route('registration.store') }}" method="post" novalidate>
          <section class="form-step" data-step="0" aria-labelledby="student-title">
            <div class="step-heading"><h3 id="student-title" tabindex="-1">Meet the student</h3><span>STEP 01 / 04</span></div>
            <p class="step-copy">Tell us who we’ll be cheering on.</p>
            <div class="field"><label for="student-name">Student’s full name <span>*</span></label><input id="student-name" name="studentName" autocomplete="name" placeholder="e.g. Ayaan Ramdin" required maxlength="100"><small class="error" id="student-name-error"></small></div>
            <div class="field-row">
              <div class="field"><label for="grade">Grade in 2027 <span>*</span></label><select id="grade" name="grade" required><option value="">Select a grade</option><option>Grade 7</option><option>Grade 8</option><option>Grade 9</option><option>Grade 10</option><option>Grade 11</option><option>Grade 12</option><option>Grade 13</option></select><small class="error" id="grade-error"></small></div>
              <div class="field"><label for="school">Current school <span>*</span></label><input id="school" name="school" placeholder="School name" required maxlength="150"><small class="error" id="school-error"></small></div>
            </div>
            <fieldset class="student-type"><legend>Has your child attended Aditya Classes?</legend><label class="choice"><input type="radio" name="returning" value="New student" checked><span>New student</span><span class="choice-check" aria-hidden="true"></span></label><label class="choice"><input type="radio" name="returning" value="Returning student"><span>Returning student</span><span class="choice-check" aria-hidden="true"></span></label></fieldset>
          </section>
          <section class="form-step" data-step="1" aria-labelledby="classes-title" hidden>
            <div class="step-heading"><h3 id="classes-title" tabindex="-1">Their next chapter</h3><span>STEP 02 / 04</span></div><p class="step-copy">Choose what they’d like a little extra support with.</p>
            <div class="schedule-heading"><div><span class="section-kicker">YOUR 2027 TIMETABLE</span><h4 id="selected-grade-title"></h4></div><span class="schedule-location">Quartier Militaire</span></div>
            <p class="schedule-help">Choose your subjects and one batch where offered. All times shown are local.</p>
            <fieldset class="class-picker" id="course-picker"><legend class="sr-only">Available subjects</legend></fieldset>
            <small class="error" id="subjects-error" role="alert"></small>
            <section class="selection-summary" id="selection-summary" aria-labelledby="selection-title" hidden>
              <div class="summary-heading"><h4 id="selection-title">Your selected timetable</h4><span id="selection-count"></span></div>
              <div id="selected-timetable"></div>
              <div class="fee-summary" aria-live="polite"><span>Monthly tuition<strong id="monthly-total">Rs 0</strong></span><small id="fee-breakdown"></small></div>
            </section>
            <p class="schedule-footnote">Class places are subject to confirmation.</p>
          </section>
          <section class="form-step" data-step="2" aria-labelledby="contact-title" hidden>
            <div class="step-heading"><h3 id="contact-title" tabindex="-1">Keep in touch</h3><span>STEP 03 / 04</span></div><p class="step-copy">Where should we send the next steps?</p>
            <div class="field"><label for="guardian">Parent / guardian’s full name <span>*</span></label><input id="guardian" name="guardian" autocomplete="section-guardian name" placeholder="Your full name" required maxlength="100"><small class="error" id="guardian-error"></small></div>
            <div class="field-row contact-row"><div class="field"><label for="phone">Contact number <span>*</span></label><input id="phone" name="phone" type="tel" autocomplete="tel" placeholder="5123 4567" required maxlength="22"><small class="error" id="phone-error"></small></div><div class="field"><label for="email">Parent / guardian email <span>*</span></label><input id="email" name="email" type="email" autocomplete="email" placeholder="you@example.com" required maxlength="200"><small class="error" id="email-error"></small></div></div>
            <div class="field"><label for="message">Anything you’d like us to know? <span class="optional">Optional</span></label><textarea id="message" name="message" placeholder="Learning goals, questions, or anything else…" rows="2" maxlength="1000"></textarea></div>

            <label class="check-line"><input name="updates" type="checkbox"><span>I’d also like emails about new courses and registration openings. <span class="optional">Optional</span></span></label>
            <p class="privacy">Your details are used to register your course choices and contact you about enrolment. @if(config('registration.mail_enabled'))A registration acknowledgement will be sent by email.@else Keep your registration reference. Email confirmations are not enabled yet.@endif</p>
          </section>
          <section class="form-step" data-step="3" aria-labelledby="payment-title" hidden>
            <div class="step-heading"><h3 id="payment-title" tabindex="-1">Review &amp; payment</h3><span>STEP 04 / 04</span></div>
            <p class="step-copy">Check your details and monthly total before completing registration.</p>
            <section class="registration-review" aria-labelledby="review-student-title">
              <div class="review-heading"><h4 id="review-student-title">Student details</h4><button type="button" class="summary-edit" data-edit-step="0" aria-label="Edit student details">Edit</button></div>
              <dl id="review-student" class="review-details"></dl>
            </section>
            <section class="registration-review" aria-labelledby="review-contact-title">
              <div class="review-heading"><h4 id="review-contact-title">Contact details</h4><button type="button" class="summary-edit" data-edit-step="2" aria-label="Edit contact details">Edit</button></div>
              <dl id="review-contact" class="review-details"></dl>
            </section>
            <section class="registration-review" aria-labelledby="review-courses-title">
              <div class="review-heading"><h4 id="review-courses-title">Selected classes</h4><button type="button" class="summary-edit" data-edit-step="1" aria-label="Edit selected classes">Edit</button></div>
              <ul id="payment-courses" class="payment-courses"></ul>
              <div class="payment-total" aria-live="polite"><span>Monthly amount to pay<strong id="payment-total">Rs 0</strong></span><p id="payment-breakdown"></p><small>Tuition for one month. Registration does not charge your account.</small></div>
            </section>
            <details class="juice-payment" open><summary><span class="juice-wordmark">juice<span>by MCB</span></span><span>Payment details<small>Pay using Juice</small></span><span class="juice-chevron" aria-hidden="true">＋</span></summary><div class="juice-body"><p>Use these details to pay with Juice. Include the student’s name and grade as your payment reference.</p><div class="juice-recipient"><span>Recipient<strong>{{ config('registration.payment_recipient') }}</strong></span><span>Juice number<strong>{{ config('registration.payment_number') }}</strong></span></div><p class="juice-reference">Reference: student’s name + grade</p><small>Your application does not make a payment.</small></div></details>
            <p class="payment-reference">Payment reference: <strong id="payment-reference"></strong></p>
            <label class="check-line"><input id="consent" name="consent" type="checkbox" required><span>I agree to be contacted about this application and understand that a place is subject to confirmation. <span class="required">*</span></span></label><small class="error" id="consent-error"></small>
          </section>
          <p class="error" id="submission-error" role="alert" tabindex="-1"></p>
          <div class="form-actions"><button type="button" id="back" class="text-button" hidden>← Back</button><span id="required-note">* Required fields</span><button type="submit" class="primary" id="continue">Continue <span aria-hidden="true">→</span></button></div>
        </form>
        <div class="form-foot"><span aria-hidden="true">◇</span> A thoughtful start to their learning journey.</div>
      </div>
      <section id="success-view" aria-labelledby="success-title" hidden>
        <div class="success-icon" aria-hidden="true">✓</div><div class="section-kicker">REGISTRATION RECEIVED</div>
        <h2 id="success-title" tabindex="-1">Thank you for<br>registering.</h2>
        <p class="section-copy" id="success-copy"></p>
        <div class="reference">REGISTRATION REFERENCE <strong id="registration-reference"></strong></div>
        <p id="email-status" role="status"></p>
        <h3>Your selected classes</h3><div id="registered-classes"></div><p class="success-amount">Monthly tuition: <strong id="success-total"></strong></p>
        <details class="juice-payment"><summary><span class="juice-wordmark">juice<span>by MCB</span></span><span>Payment details<small>Pay using Juice</small></span><span class="juice-chevron" aria-hidden="true">＋</span></summary><div class="juice-body"><p>Use these details to pay with Juice. Include the student’s name and grade as your payment reference.</p><div class="juice-recipient"><span>Recipient<strong>{{ config('registration.payment_recipient') }}</strong></span><span>Juice number<strong>{{ config('registration.payment_number') }}</strong></span></div><p class="juice-reference">Reference: student’s name + grade</p><small>Your application does not make a payment.</small></div></details>
        <p class="privacy">This is a registration acknowledgement, not a payment receipt.</p>
        <button type="button" class="text-button restart" id="restart">Register another student</button>
      </section>
    </section>
  </main>
  <section id="how-it-works" class="how-it-works" aria-labelledby="how-title"><div><span class="section-kicker">A SIMPLE WAY FORWARD</span><h2 id="how-title">From hello to your first class.</h2></div><div class="how-item"><span>01</span><h3>Apply in minutes</h3><p>Share a few details about your child and their learning goals.</p></div><div class="how-item"><span>02</span><h3>Find the right fit</h3><p>We’ll get in touch to discuss classes, schedules and availability.</p></div><div class="how-item"><span>03</span><h3>Get ready to learn</h3><p>Keep your registration reference and selected class details for your first class.</p></div></section>
  <footer><span>© 2027 Aditya Classes</span><span>Every student. Every step.</span></footer>

</body>
</html>
