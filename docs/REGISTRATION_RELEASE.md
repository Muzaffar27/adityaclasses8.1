# Registration-only release - 4 November 2026

Target: adityaclasses.mu. Based directly on verified production commit
3c2cb0679e6d10db6c03b164d59fabca25a33d1e. Branch:
release/registration-only-20261104. Excludes later question-review work.

## Behaviour

Public /registration saves applications, selected timetable, monthly fee and
payment instructions (Juice 59473797). Tutor/admin /registrations provides
search, filters, paging, CSV export, details and follow-up/payment tracking.
No student account is created. Payment is manually verified by staff.

Email delivery defaults OFF, independent of existing SMTP settings. A successful
registration displays its reference and remains email-pending in the staff panel.
The resend action stays disabled until email is enabled. Failed delivery never
undoes a saved registration.

## Enable email later on the server

Configure the private server .env; never commit mailbox credentials:

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=smtp.hostinger.com
MAIL_PORT=465
MAIL_ENCRYPTION=ssl
MAIL_USERNAME=info@adityaclasses.mu
MAIL_PASSWORD=<mailbox password entered privately>
REGISTRATION_MAIL_FROM_ADDRESS=info@adityaclasses.mu
REGISTRATION_MAIL_ENABLED=true
```

Verify these SMTP settings with the mailbox provider before use. If Laravel's
configuration is cached, refresh it through the approved server process. Test
one acknowledgement first. Staff can then resend pending acknowledgements from
the details panel. The optional registration:send-acknowledgements command sends
all pending registrations; use --reference for one reference. Do not run a bulk
send until actual delivery has been verified. No WhatsApp setup is required.

## Deployment scope and required runbook exceptions

Use this isolated release branch rather than origin/main: main contains unrelated
changes beyond the live baseline. Requires explicit approval to switch production
from main to this release branch and add the new config/registration.php. Existing
configuration files, .env, public_html/index.php and .htaccess remain unchanged.

Create and validate fresh application, public_html and database backups. Preview
the two new additive registration migrations. Apply them ONLY through the approved
/admin/migrate process (AGENTS.md), not CLI migrate. Publish public/build plus
public/registration-poc/app.js, styles.css and public/sw.js to the separate public
root. Preserve all other public-root files, uploads and old hashed assets.

## Validation

On the isolated release: 25 PHP tests, 253 assertions passed; 4 service-worker
Node tests passed; Vite production build passed. Browser submission saved with
email_enabled=false and pending email status. Staff could review the saved entry;
resend was disabled. Public and staff pages had no horizontal overflow at
320, 390, 768 and 1440 px. Production deployment and authenticated live acceptance
remain pending. Existing build tooling emits Node-engine/Sass warnings.
