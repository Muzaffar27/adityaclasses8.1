

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


### Independent subject term prices and discounts - 19 November 2026

Status: Done locally

User clarified that Price Details must edit the selected subject rather than the whole package. This replaces package-wide authoring while retaining the compact store, independent access periods and isolated release branch.

- [x] Package Contents selects a subject; highlighted card and Price Details heading identify it. Each subject keeps its own three term prices and discounts. Switching subjects preserves edits; the top Update saves them together. Newly added subjects receive independent pricing fields; removing an item keeps the remaining selection correct.
- [x] The store shows each subject's own prices/discounts and sums selected subjects for equal or mixed durations. Server-calculated access-request amounts use the same independent prices. Package-list prices are sums, with no misleading shared percentage when discounts differ.
- [x] New price/discount values validate numeric ranges and two decimal places. Explicit zero prices remain zero. Integer cents/basis-point arithmetic keeps half-cent discount rounding consistent between browser and server. Staff authorization remains enforced.
- [x] Additive migration 2026_11_19_000002_add_subject_term_prices_to_package_items.php adds six nullable pricing columns to package_items. It populates only those new fields from existing effective subject prices/discounts, preserving payable amounts even with cent rounding. Existing package columns and lesson_access records are untouched. Older update payloads omitting these fields retain the saved subject pricing.

Agent log:
- Main files: PackageBuilder.vue, PackagePage.vue, PackageList.vue, utils/packagePricing.mjs, PackageController.php, Package.php, PackageItem.php, the additive migration, targeted tests and generated build assets.
- Validation: isolated SQLite backend suite passed 13 tests / 88 assertions; frontend pricing suite passed 6 tests; production build and git diff --check passed. Migration regression covers rounded amounts, zero weights/full discounts and independent prices after removing a subject. A backup of the isolated preview SQLite database was taken before applying the new migration there; all existing payable amounts across its two packages matched before/after. Production was not accessed or migrated.
- Playwright: real temporary preview package creation, independent field editing, switching/reopening without lost drafts, per-subject headings, validation, adding/removing subjects, saving and store totals verified. Example Math 1300 minus 15 percent = 1105; Physics 1800 minus 20 percent = 1440; mixed total 2545. Browser submission payload checked with a mocked success to avoid changing existing preview access requests; real backend request pricing is covered by isolated feature tests. Temporary verification packages were removed. Editor/store at 1440/390/320px had no horizontal overflow or page exceptions; screenshots inspected.
- Remaining verification: real MySQL migration, user acceptance and installed-PWA/device checks. Production migration must run through approved /admin/migrate after the earlier pricing migration, with source/assets shipped together. Legacy package/base-price fields remain hidden compatibility data; newly edited subject term prices determine store totals.
- Release: separate commit on hotfix/package-pricing-20261119, depending on preceding pricing commits only. No unrelated development, push or deployment.


### Edit package footer actions and blue total label - 19 November 2026

Status: Done locally

- Moved Cancel and Update below Price Details for edit mode in PackageBuilder.vue; Create remains at the top for creation. Matched the store Your total label to the amount's primary blue in PackagePage.vue. No pricing, backend, schema or dependency changes.
- Validation: production build and git diff --check passed. Playwright verified footer below prices, no header actions and no overflow at 1440/390/320px; Cancel closes the editor and Update sends the existing request (mocked success, no data save); Create remains at top. Computed colours of Your total and amount both rgb(79,70,229). No page exceptions in the interaction check.
- Remaining: user visual acceptance and installed-PWA verification. No push, deploy or production writes.


### Compact selection blocks - 19 November 2026

Status: Done locally

- Styled each selected subject as a separate compact block in PackagePage.vue, reusing the package-choice background, border and 8px corner radius. Subject, access duration and price remain grouped; desktop uses a grid and phones stack the blocks. Kept both the total label and amount blue and the summary below subjects.
- Validation: production build and git diff --check passed; Playwright verified separate blocks, mixed durations, unchanged blue total colours, no overflow at 1440/390/320px and zero page exceptions. Mobile summary screenshot inspected. No pricing, backend or migration changes. No push/deploy; installed-PWA check remains pending.
