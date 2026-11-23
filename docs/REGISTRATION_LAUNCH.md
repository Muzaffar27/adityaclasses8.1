# Registration-only launch

Scope: public `/registration`, saved student/contact/course choices, Juice number
59473797, and an email acknowledging receipt of registration from
`info@adityaclasses.mu`. This creates no user account and performs no automatic payment verification.
The form has four steps: Student, Classes, Contact, then Review & payment.
The final step itemises monthly fees, shows the selected timetable and payment
number, and allows editing student/contact/course details before submission.

The Grade 7–13 courses, batch options, days, times and first-class dates are
transcribed from the supplied 2027 timetable JPEGs. Grade 13 Maths Main keeps
Statistics, Mechanics (starting 12 April 2027), and Pure Maths revision together
as one subject. Monthly rates per subject from `Tuition fees.jpeg` are Rs
400/500/600/700/800/1,000/1,100 for Grades 7/8/9/10/11/12/13 respectively, using
the existing per-subject/month calculation. `config/registration.php` is the
shared catalogue for the UI and server validation. Saved registrations snapshot
the selected timetable and authoritative fees in the existing JSON details;
client-supplied prices are ignored. Emails include those saved values.

## Release files

The feature also includes the tutor/admin Registrations panel at `/registrations`
and Tutor Space → Registrations. Its default Pending filter includes New and
Contacted; Enrolled remains searchable separately. Staff can view all saved
student/guardian/course/timetable/fee/consent details, add internal notes, update
registration status and manually verify payment. Verification requires an
explicit acknowledgement and records the staff user and time. Enrolment labels
do not create accounts or grant access. Stale saves return HTTP 409 and retain
the browser draft. CSV export follows the active filters and escapes spreadsheet
formula prefixes. Guests and students cannot list/read/update/export records.

The inbox uses compact desktop rows and mobile cards, live search, clickable
status totals, grade/subject/payment/email filters, newest/oldest/student sorting,
25/50/100 rows per page, numbered pages and direct page entry. Only the selected
page is fetched. Record details open in a modal side panel; Previous/Next reviews
records on the current page. Closing or switching protects unsaved notes and
closing restores keyboard focus to the originating row. CSV includes all matches
in the selected sort order, not just the current page.

To retry an unsent acknowledgement, set **Email → Pending — not sent**, open the
registration and choose **Resend acknowledgement**. Only tutors/admins can call
`POST /api/admin/registrations/{id}/resend-acknowledgement`; the retry is throttled
and uses the existing send lock. Already acknowledged records are not sent again.
The UI preserves unsaved follow-up notes and reports sent versus still-pending
truthfully. A retry does not configure SMTP: actual delivery remains blocked
until the mailbox credentials/host settings are corrected.

Deploy the registration files only; the current working branch also contains
unrelated question-review work and must not be deployed wholesale.

- `app/Http/Controllers/CourseRegistrationController.php`
- `app/Models/CourseRegistration.php`
- `app/Http/Controllers/RegistrationManagementController.php`
- `app/Mail/RegistrationAcknowledgement.php`
- `app/Services/RegistrationAcknowledgementService.php`
- `app/Console/Commands/SendRegistrationAcknowledgements.php`
- `config/registration.php`
- `database/migrations/2026_11_04_000001_create_course_registrations_table.php`
- `database/migrations/2026_11_04_000002_add_staff_workflow_to_course_registrations.php`
- `resources/views/registration.blade.php`
- `resources/views/emails/registration-acknowledgement.blade.php`
- `public/registration-poc/app.js` and `styles.css`
- `resources/js/components/tutor/RegistrationPanel.vue` and `RegistrationPage.vue`
- Registration-only additions to `TutorSpace.vue`, `resources/js/router/index.js`
  and `routes/api.php`, plus rebuilt Vite assets/manifest from the live baseline
- Registration-specific network-only/cache-purge additions to `public/sw.js`

The staff API is protected by `auth:sanctum` and controller role checks. The
service worker must never cache `/api/admin/registrations` or its subpaths,
including CSV; activation purges old cached registration responses. Other
existing offline behavior is unchanged. The build produced locally includes
the current feature branch; do not deploy that entire bundle. Rebuild the
registration additions against the verified live baseline for the eventual
registration-only release.

Apply only the two registration route definitions in `routes/web.php`, before
the existing catch-all. Keep existing live routes. The POST uses the web CSRF
middleware and `throttle:6,1`.

The SMTP timeout in `config/mail.php` should be
`'timeout' => env('MAIL_TIMEOUT', 15)`. Preserve other live mail configuration.
The page uses the existing public `/logo.png`; verify it exists.

## Mail configuration and launch blockers

Use an existing, working Hostinger mailbox. Do not paste passwords in chat or
commit environment files. Enter the mailbox password privately in the server
environment configuration.

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=smtp.hostinger.com
MAIL_PORT=465
MAIL_ENCRYPTION=ssl
MAIL_USERNAME=info@adityaclasses.mu
MAIL_TIMEOUT=15
REGISTRATION_MAIL_FROM_ADDRESS=info@adityaclasses.mu
REGISTRATION_PAYMENT_NUMBER=59473797
REGISTRATION_PAYMENT_RECIPIENT="Aditya Classes"
```

Set `MAIL_PASSWORD` privately. Check that a stale `MAIL_URL` does not override
these settings. Confirm the payment recipient name for public launch.

Read-only checks on 4 November 2026: domain MX records point to Hostinger.
The local sender/authentication user was `contact@adityaclasses.mu`.
`mail.adityaclasses.mu` failed certificate verification. Using
`smtp.hostinger.com` passed connection/TLS but the saved credentials failed
authentication. No email was sent. The Hostinger Mail API returned no orders
for the domain, so mailbox existence/status is not verified. The requested
`info@adityaclasses.mu` credentials and real inbox delivery remain pending.

## Production procedure

1. Read the current live files/schema and back up only the files being changed.
   Prepare a registration-only release from the live baseline; do not copy the
   entire feature branch, local `.env`, tests or `deliverables/` onto production.
2. Upload the reviewed registration release and configure the sender privately.
   Confirm storage/cache directories are writable and `APP_DEBUG=false`.
3. Apply the additive migration only through the approved `/admin/migrate`
   process. First inspect all pending production migrations: that existing
   endpoint runs every pending migration. If unrelated migrations are pending,
   stop and resolve the release scope before invoking it.
4. Refresh application configuration/route caches through the existing approved
   deployment procedure. Verify HTTPS page/CSS/JS/logo, real save, unique
   reference, subjects/batch, payment details and actual acknowledgement receipt
   in the chosen test inbox. Do not claim delivery based on a fake transport.
5. Configure the host's existing scheduler/cron to run
   `php artisan registration:send-acknowledgements` every five minutes if
   automatic retry is required. The form sends immediately; this command retries
   saved, unsent acknowledgements without requiring a queue worker. Manual retry:
   `php artisan registration:send-acknowledgements --reference=AC-2027-...`.
6. Confirm no unrelated release files were changed. Keep saved registrations if
   rolling back the UI; do not drop the table or roll back its migration.

Registration data is private database data with staff-only list/read endpoints.
An interrupted browser request can be retried with the same submission UUID
while the page remains open. Reloading clears the in-memory form and UUID.
Already acknowledged submissions are not emailed again. SMTP has no universal
exactly-once guarantee: if the provider accepts a message and then the process
fails before saving `acknowledged_at`, an operator retry may deliver it again.

## Local verification

Run local PHP tests as the web user, for example
`docker compose exec -T -u www-data app php artisan test --filter='RegistrationManagementTest|CourseRegistrationTest'`.
On 4 November a real main-app submission exposed root-owned 0755 cache
subdirectories left by CLI execution: the web user could not create the rate
limiter file and the POST returned HTTP 500 before saving. Corrected ownership
of the verified local `storage/framework/cache/data` volume to `www-data`;
did not disable throttling or CSRF. Subsequent real main-app saves returned
201, and a dropped-response retry returned 200 for the same submission UUID.
The full main-app retest covered all grade totals, validation, back/forward
retention, the Pending inbox, notes, manual payment verification and reset,
reload, grade/subject filters, CSV export and 320/390/768/1440px layouts.
Two clearly labelled `LOCAL TEST` registrations were retained for review;
payment is unverified and registration status New. SMTP remained pending and
no email was delivered. The temporary staff test token was revoked afterward.

Targeted feature tests use a private SQLite in-memory connection and fake mail;
they do not migrate/reset the application's database. Browser verification uses
an isolated temporary SQLite database and array mail transport in a separate
local Docker container on port 8091. It sends no real emails. Both additive
registration migrations were also applied, by explicit paths only, to the main
local Docker MariaDB after verifying APP_ENV=local, DB_HOST=db and the database
name. A completed 757,726-byte database dump was verified beforehand at
`storage/app/backups/registration-feature-before-20261104.sql` (Git-ignored).
No existing registration rows were present. No production migration was applied.

Main local review: open `http://localhost:8080/registrations` with the existing
tutor/admin login, or use Tutor Space → Registrations. The main local inbox is
initially empty; public submissions at `/registration` populate it. Real SMTP
delivery is still blocked by the mailbox configuration described above. The
isolated preview at port 8091 contains synthetic test registrations only.

Scale/retry verification used 250 additional synthetic registrations only in the
isolated SQLite preview, never the main local database. Tests covered the last
page, 25/50/100 page sizes, sorting, filters, modal navigation, dirty-note protection,
keyboard focus restoration and full sorted exports across multiple chunks. Email
retry success was tested with fake/array mail; retry failure was also verified
against the main local app's unconfigured SMTP using an existing labelled test
registration. No real email was delivered. Targeted checks: 23 PHP tests / 243
assertions; frontend build/local sync; 320/390/768/1440px list/drawer checks with
zero unexpected browser errors. No new migration or dependency for this subset.

Staff panel validation: targeted backend authorization/filter/update/export and
public registration regression tests; frontend build/local asset sync; browser
form-to-inbox save, filters, payment verification, notes persistence, stale-save
protection, CSV export, navigation, mobile/desktop and failure/retry states.
Failure-state browser checks use intercepted HTTP 500s in a separate context;
normal saves and authorization use real local endpoints. Browser-controlled
service-worker checks verified cached registration entries were purged and an
offline request could not read an intentionally cached private test response.
Physical-device and installed-PWA acceptance remain pending.

Temporary browser-test authentication tokens were revoked after verification,
and their local storage-state files were removed. Main local review uses the
existing tutor/admin accounts, without synthetic accounts or registrations in
that database.

The obsolete portable presentation and its source tutor materials were archived
outside the project on 9 November 2026; see [the cleanup record](LOCAL_CLEANUP_20261109.md)
for their location and restoration details. Deploy the application-served
Blade/public files listed above. The active `public/registration-poc/` assets
remain required by the live registration page.
