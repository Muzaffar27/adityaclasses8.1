# Saved test papers — Phase 2

Implemented locally on 12 November 2026. This is a backend foundation: no student/tutor paper screens or answer-entry interface have been added. The existing Phase 1 samples remain read-only previews.

## What is implemented

- A staff member can issue a fixed 15-question paper from the current approved bank to a student with active access to that grade and subject.
- A stable batch key identifies one request. Reusing it with the same student/course/purpose/topics returns the original paper. Reusing it with changed topics returns HTTP 409. A fresh key intentionally represents another batch; automatic completion-event keys belong to Phase 3.
- Main, revision and follow-up purposes have separate identities. Their scheduling and topic-eligibility rules are not implemented yet.
- Question content, source IDs, assigned topics, blueprint version, approval revision/time and PDF/source/processor provenance are frozen at issue time.
- Question PNGs are retained as content-addressed private copies in SQL, so replacing/deleting source PDFs or extracted PNGs does not break old papers. Base64 storage keeps assets and paper creation in one atomic transaction, without a filesystem/transaction gap. Only a diagram referenced by an authorised paper can be requested.
- One attempt identity is created per issued paper. Repeated Start preserves its first timestamp; it does not accept answers or scores.
- Student lists include only that student's papers. Students can read their own existing history after course access expires; active access is required to issue or start a paper. Tutor/admin access is checked server-side.
- Student responses contain Question X content/diagram URLs, without topic labels, source metadata, answer keys or rubrics. Staff preview additionally includes source/topic provenance and the audit trail.
- Cancellation requires a reason and retains the old paper. Replacement cancels an unfinished paper and creates a linked version with a new attempt, without changing previous snapshots. Repeating the same replacement request returns the same replacement. Replacement failure rolls back cancellation.
- Available/started/cancelled operations enforce valid transitions. Submitted/awaiting_review/marked states and their timestamp/score fields are reserved for Phase 4; no endpoint can manufacture submission or marking now. Submitted or marked papers cannot be cancelled/replaced by these operations.
- Private API/diagram responses use no-store headers. The service worker bypasses these endpoints and removes matching old runtime-cache entries on activation. Installed-PWA acceptance is still pending.

## Marking limitation

Update — 12 November 2026: [reusable answer keys](QUESTION_ANSWER_KEYS.md) can now store approved marks/rules. New papers snapshot eligible approved keys and set marking_ready=true only when all selected questions have one. The real bank still has zero approved keys; the older limitations below describe that data state. Existing unmarked papers are never updated silently.

The current bank has no approved answer keys, per-question marks or correction rules. Therefore saved papers have `marking_ready=false`, nullable marks/marking snapshots and no scores. This records the missing information honestly; it does not establish the scored-paper snapshot criterion. Populate and approve a versioned marking contract before issuing scored papers in a later work unit. Already issued unmarked snapshots must remain unchanged; cancel/replace them explicitly if a scored version is needed.

## Local migration

One additive migration creates six new tables: assessment_batches, test_papers, test_paper_questions, test_paper_assets, test_paper_attempts and test_paper_events. Unique constraints cover student/course/purpose/batch identity, paper version/request identity, question positions/source IDs and one attempt per paper. Foreign keys restrict deletion of history.

```powershell
docker compose exec -T --user www-data app php artisan migrate --path=database/migrations/2026_11_12_000001_create_saved_test_papers.php
```

Applied locally only. Existing user counts, approval rows and bank hashes were checked unchanged; all 19 pre-existing table definitions remained unchanged (ignoring AUTO_INCREMENT counters). Verification baseline: private `storage/app/phase2-verification/before.json`. No papers were assigned to real local students. No production action is authorised; a later production migration must use the approved `/admin/migrate` process.

## API for the later interface

All routes require the existing Sanctum authentication. Use the app's existing authenticated API client; do not put credentials or tokens into URLs.

| Method | Path | Access |
|---|---|---|
| POST | `/api/admin/test-papers` | Tutor/admin: issue or return an existing paper |
| GET | `/api/test-papers` | Student: paginated own-paper list |
| GET | `/api/test-papers/{uuid}` | Owner or tutor/admin: saved-paper preview |
| POST | `/api/test-papers/{uuid}/start` | Owner student with active course access |
| POST | `/api/admin/test-papers/{uuid}/cancel` | Tutor/admin, with reason |
| POST | `/api/admin/test-papers/{uuid}/replace` | Tutor/admin, with reason and stable replacement request key |
| GET | `/api/test-papers/{uuid}/diagrams/{sha256}` | Owner or tutor/admin, for a retained paper diagram |

Example issue body (replace the student ID and generate one UUID per intended batch; reuse it on retries):

```json
{
  "student_id": 123,
  "grade_id": 2,
  "subject_id": 1,
  "purpose": "main",
  "batch_key": "11111111-1111-4111-8111-111111111111",
  "topics": ["2. Fractions", "4. BODMAS", "11. Indices"]
}
```

Grade/subject IDs above are local examples, not universal IDs. No completion history is inspected in Phase 2. Phase 3 will reserve the correct three topics and supply a stable completion-batch key automatically.

Replacement body:

```json
{"request_key":"22222222-2222-4222-8222-222222222222","reason":"Tutor requested a replacement"}
```

## Safe test demonstration

This test demonstrates the entire storage foundation using in-memory SQLite and fake private files. It does not assign a paper to an existing user:

```powershell
docker compose exec -T -e APP_ENV=testing -e CACHE_DRIVER=array app php artisan test tests/Feature/SavedTestPaperTest.php
```

It verifies repeated creation, permissions, private payloads, fixed content/retained diagrams after source removal, starting twice, expiry, cancellation/replacement, rollback, immutable snapshots, separate purposes and database uniqueness. Expected current result: 15 tests, 165 assertions.

## Real concurrent MariaDB verification

The checked-in script `scripts/assessments/verify-saved-paper-concurrency.php` requires an empty dedicated MySQL database named `assessment_phase2_check_<12 lowercase hex characters>` and APP_ENV=testing. It refuses the application database or any nonempty database. Provision that isolated database and grant the local app user access to that database only; no credentials belong in this document. The script applies fixture-only migrations, uses private `/tmp/<database>/` storage and starts two PHP processes together at a barrier.

```powershell
docker compose exec -T -e APP_ENV=testing -e CACHE_DRIVER=array -e DB_DATABASE=assessment_phase2_check_<12hex> --user www-data app php scripts/assessments/verify-saved-paper-concurrency.php
```

Do not rerun against a populated database: use a fresh dedicated name. Verification performs no reset, DROP or deletion. The tested fixture database `assessment_phase2_check_7b91e4c20a36` and `/tmp/assessment_phase2_check_7b91e4c20a36` are retained for inspection. Two simultaneous requests returned the same UUID and left one batch, one paper, one attempt and 15 questions. It contains synthetic fixture accounts/questions only; no application records or source files were used.

## Validation and remaining acceptance

- 78 targeted PHP tests / 698 assertions passed (saved papers, Phase 1 selector/readiness, question review, starter review and upload integration).
- Eight service-worker tests passed, including private/offline and activation cleanup cases. These are VM tests, not installed-PWA proof.
- MariaDB simultaneous assignment and local additive migration passed.
- Local HTTP checks returned 401 for unauthenticated list/detail/creation; served service worker has the new private-endpoint rules.
- PHP/JavaScript syntax and whitespace checks passed. No Vite build required: no application Vue/bundle change; public/sw.js is standalone and was copied to the local public volume.
- No new UI, real student assignment, paid AI call, commit, push or deployment. UI/mobile and installed-PWA acceptance remain pending. Student working/final-answer entry remains Phase 4, with no photo uploads.
