

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
