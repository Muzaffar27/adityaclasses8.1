

### Urgent package pricing hotfix - 19 November 2026

Status: Done locally

User-directed exception to the normal queue: only package pricing, its display and new-request duration. Isolated branch hotfix/package-pricing-20261119 starts at the live commit ecd395fd9d205e54f9de4a5a123cb7027e448bd3 (confirmed by read-only SSH). Unrelated local development is excluded.

- [x] Three-term option is 12 months in the editor, store and new access requests; approval expires it 12 months from approval. Existing enrolments are untouched.
- [x] Keep subject base prices, explain their proportional role, and replace the misleading single Total with three final term prices.
- [x] Independent 0-100% discount for each term, saved on create/update and shown in the package list and store.
- [x] Server calculates requested prices from saved package/item identities; client amounts cannot override discounts. Retain existing zero-term-price fallback. Subject shares allocate rounding cents to match the package total.
- [x] Tutor/admin authorization on price editing and access approval; validate prices and discounts on create and update.

Agent log:
- Main files: PackageController.php, LessonAccessController.php, Package.php, PackageBuilder.vue, PackageList.vue, PackagePage.vue, utils/packagePricing.mjs, targeted tests and rebuilt public/build assets.
- Migration: 2026_11_19_000001_restore_twelve_month_package_price.php adds the 12-month price and three discount columns; copies the existing three-term amount without dropping the old column or changing lesson_access records.
- Validation: isolated SQLite PackageDurationTest passed 8 tests / 52 assertions; node PackagePricing.test.mjs passed 3 tests; npm run build passed (existing Sass/import warnings); git diff --check passed. Playwright exercised real isolated package save/reopen, per-term totals, partial/full selection and access-request submission. Editor/store checked at 1440/768/390/320px without horizontal overflow; screenshots inspected. Metadata endpoints were fixtures because the preview uses SQLite and the existing grade query uses MySQL syntax. No pricing page exceptions; blocking service workers in the harness produces an existing registration warning, not an installed-PWA result.
- Remaining verification: real MySQL migration and tutor acceptance, installed PWA update, and production smoke checks. No push, deployment or production write performed.
- Release boundary: this single commit only, based on live ecd395f. Ship source and its generated assets together. Production migration must use the approved /admin/migrate process, never the older destructive duration migration or a rollback. Legacy cached store submissions are rejected with a reload instruction so old client-supplied prices cannot bypass server pricing. Existing pending/accepted nine-month records retain their dates and duration.
- Blocker: none for local completion; deployment needs separate authorization.


### Student store selection redesign - 19 November 2026

Status: Done locally

User-directed continuation of the isolated pricing hotfix; depends on de1ba3c. Scope is the student store only.

- [x] Open the first available subject/package automatically and select its subjects by default so the initial total reflects the package.
- [x] Show 1-, 2- and 3-term prices and discounts on every subject, with independent term choices and a combined itemised total. Keep an optional apply-to-all period selector.
- [x] Preserve dark/indigo colours, desktop navigation, touch-friendly choices and safe-area padding. No new dependencies, migrations or backend production-code changes.
- [x] Explicit loading, unavailable/retry, no-packages and no-subjects-selected states; no false Rs 0.00 for an empty selection. Preserve the reviewed selection after submitting and during request errors.

Agent log:
- Files: resources/js/components/PackagePage.vue, tests/Feature/PackageDurationTest.php, this log and generated build assets. New mixed-subject request regression verifies independent durations and server-calculated prices in one submission.
- Validation: targeted isolated SQLite suite passed 9 tests / 58 assertions; existing frontend pricing tests passed 3 tests; production build and git diff --check passed. Playwright confirmed automatic opening, default subject selection, per-subject 3/12-month mix, correct combined total and real preview HTTP200 submission; apply-to-all periods, all deselected/disabled request, package/subject switching, empty results, package load failure/retry and request failure/retry. Desktop/mobile screenshots inspected at 1440/768/390/320px with no horizontal overflow and zero page exceptions. Multi-package/empty/error cases used browser fixtures; normal mixed-term submission used the isolated local preview backend.
- Remaining verification: tutor/student acceptance and installed-PWA/device checks; responsive browser checks do not prove installed-PWA behaviour. Existing service-worker registration warning occurs when service workers are blocked by the test harness. Production and normal development checkout remain unchanged.
- Release: separate follow-up commit on hotfix/package-pricing-20261119; release after de1ba3c, with that commit's additive migration through /admin/migrate. No extra migration in this follow-up. No push or deploy.


### Compact student store revision - 19 November 2026

Status: Done locally

- User requested a less bulky layout: removed the optional shared-period control entirely; retained independent subject terms, prices and discounts. Removed duplicate hero/subject-total headings and reduced card spacing. Your selection is always below the subject list in normal flow on desktop and mobile.
- Files: PackagePage.vue and generated build assets. No backend, migration, dependency or other-feature changes.
- Validation: frontend production build, three existing pricing calculation tests and git diff --check passed. Playwright verified removal of the shared-period option; mixed 3/12-month total Rs 1,937.50 and HTTP200 submission with individual durations; deselect-all disables requests; summary is below subjects at 1440/768/390/320px, with no horizontal overflow or page exceptions. Desktop/mobile screenshots inspected.
- Remaining verification: user visual acceptance and installed-PWA/device check. No push, deployment or production writes. This revision supersedes the optional apply-to-all UI in the previous entry; backend pricing is unchanged.


### Pricing display polish - 19 November 2026

Status: Done locally

- Restored the store total to the original primary blue (#4f46e5) and enlarged it to 24px. Hid the Subject base price input in PackageBuilder while keeping its reactive/saved values and pricing calculations; adjusted its helper text to describe the visible term-price controls.
- Files: PackagePage.vue, PackageBuilder.vue and generated assets. No backend, schema or dependency changes.
- Validation: production build and git diff --check passed. Playwright confirmed the hidden input is absent; editing an existing subject retains its price in the form; computed total is blue/24px; no document overflow at 1440/390/320px and zero page exceptions. Mobile screenshot inspected. No database save was made during this UI check.
- Remaining: visual acceptance and installed-PWA/device verification. No push, deployment or production write.


### Hide Package Contents base-price display - 19 November 2026

Status: Done locally

- Removed only the base-price text from each Package Contents item in PackageBuilder.vue. Subject/grade labels and item editing remain available; saved values and calculation logic are unchanged. No migration.
- Validation: production build and git diff --check passed. Playwright confirmed no Rs/base-price text in Package Contents, item editing still opens, no overflow at 390px and zero page exceptions. No database save, push or deployment. Installed-PWA verification remains pending.
