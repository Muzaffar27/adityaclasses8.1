# Automatic questions from local lesson PDFs

## Starter bank review — 1 November 2026

Review questions defaults to **five reviewed slots per grade/subject/topic**. Approved questions and flags occupy slots; saved corrections awaiting approval keep their flagged slot. Flags stay unapproved in **Needs correction**, and the starter does not keep replacing them with more candidates. The progress bar still measures the goal of five approved questions per topic. Fix existing flags to finish that goal. Use **More variety** explicitly to access the rest of the pending bank; this is workload selection, not an accuracy score.

PDF approval submits an explicit list of displayed question IDs and validates them against the current source/review snapshot. Questions hidden by Starter bank are not approved by that action. Manual correction and recovered-result links open the full review scope so the requested result remains accessible. No AI or paid processing is involved.

## Saved flags and manual correction - 2 November 2026

Flags persist immediately in **Needs correction**. Tutors edit text or maths, check the preview and choose **Save & approve** in one request. **Save only** retains an unapproved draft for later approval. Original content, flags and earlier versions remain stored. New flags are marked for manual correction; existing queued flags remain editable.

Midnight automation and automatic flag retries have been removed. There is no question-bank scheduler entry, nightly command or correction-only worker mode. Upload extraction and explicit **Retry extraction** actions still use the normal background worker. The production worker CLI remains gated by QUESTION_BANK_WORKER_ENABLED; that setting does not schedule processing.

See [workflow boundaries and operations](../../docs/QUESTION_CORRECTION_RUNBOOK.md).

## Recovery actions and first parser repair — 27 October 2026

Rejected regions and skipped pages now offer **Retry extraction**. It queues the entire bound original PDF for forced local reprocessing, shows progress/results while this panel is open, then offers **Review extracted questions** for that lesson. A retry can return unchanged results; it is not an automatic correction of every unsupported layout. The local watcher must be running. Retry never approves questions or dismisses remaining issues.

The first recovery refinement removes only isolated bottom-right page-number lines matching the PDF page/cover offset and carries exercise context onto pages with increasing labels in matching columns. Changed instructions, reset labels and unknown boundaries remain unresolved. Continuation maths containing English list separators stays rejected until list-expression support exists.

Applied locally: all **415 original questions remain current**, plus **15 recovered candidates** (430 total). Skipped pages fell from 61 to 37. Rejected regions rose from 239 to 307 because previously unprocessed pages now expose additional unsupported regions. Two intermediate HCF candidates were archived, preserving history, after finding that their expression lists were not represented safely. Four user approvals were retained; new candidates remain unapproved.

`POST /api/admin/question-reviews/issues/{id}/retry` requires staff authorization and the current source revision. It rejects duplicate/stale requests and changed source bytes/bindings. Issue identities include the processor version. Progress uses the existing staff lesson-source status endpoint, now including skipped-page counts. `node scripts/questions/verify-extraction-recovery.mjs` checks real disposable PDF copies against the preserved private baseline and asserts the exact recovered Indices page 2 question 10 powers. Original PDFs are unchanged.

Remaining recovery needs additional parser/layout/OCR support or a manual transcription editor. That editor is **not implemented** in this work unit; Retry alone cannot resolve every remaining issue. This is partial extraction recovery, not complete coverage.

## Extraction coverage review — 27 October 2026

Review questions now includes **Rejected regions** and **Skipped pages** tabs, separate from extracted-question approval. Grade/subject/lesson filters apply to both, including lessons that produced no bank questions. Each record shows its source, reason and original PDF page; rejected regions have an orange outline. Raw text is explicitly unverified because mathematical layout can be lost. Skipped-page audit hints identify detected question labels/exercise headings, possible covers, examples/revision text and pages without readable text. Hints never dismiss a page or establish a missing-question total.

Current full local application coverage: **415 active questions, 239 rejected regions, 61 skipped pages across 20 PDF slots**. The 18 copied sample PDFs account for 55 of those skipped pages. The refreshed full-app audit detected question labels on 38 skipped pages, possible covers on 11, exercise headings on 2, example/revision text on 3, no readable text on 4, and 3 otherwise unclassified pages. These are automated observations, not completed human classification. Source SHA-256/binding/status checks exclude stale reports. Read-only coverage records cannot be approved as questions and are not marked resolved by viewing them.

Run `node scripts/questions/audit-extraction-coverage.mjs` after current-worker processing to refresh the private `storage/app/structured/extraction-coverage-audit.md` and `.json` reports. An optional first argument selects another bank root; old banks must be reprocessed to populate region coordinates and audit evidence. The script verifies original source hashes and checks evidence completeness. Worker processing versions now include coverage metadata. Existing question identities/approvals survive this metadata-only rescan.

Recovery/transcription of rejected regions, human classification of skipped pages, OCR and confirmation that every original question is represented remain pending. The UI/report make extraction gaps visible; they do not claim complete PDF coverage.

## Diagram support — 27 October 2026

Digital-PDF diagrams now enter the same extraction/review workflow. The local Pythagoras source produces **16 questions with 17 original figure PNGs**, including continuation pages, shared-diagram subparts, a rectangle and a ladder. Instructions/question prose remain text; original figure labels stay in the images. This preserves figures rather than interpreting or redrawing geometry. General scanned-page OCR and ambiguous layouts remain unsupported.

The bank stores `diagrams: [{ sha256, path, width, height }]` alongside text/structured maths. Images live privately at `structured/diagrams/<sha256>.png`, relative to the bank root. Diagram bytes participate in the canonical question fingerprint; changed figures require review again. Original PDF hashes, lesson/topic/page origins and historical questions/approvals are preserved.

The existing local Poppler `pdftoppm` executable renders detected figure bounds at 144 DPI. It must be available on the worker's PATH; no package/dependency was installed. Missing rendering support, unlocated figures and drawings crossing ambiguous question boundaries are recorded as rejected extraction regions. Image-only scans are not converted into text questions.

The review panel automatically loads authenticated, staff-only image requests through `GET /api/admin/question-reviews/{id}/diagrams/{index}`. Click an image to zoom, or compare against the full original PDF. Yes stays disabled until all figure images decode successfully; failed loads offer Retry. The server also refuses missing/changed image assets and stale PDF sources. These endpoints do not grant student access; actual student delivery remains pending.

Local test-preview snapshots copy their figure PNGs into their own `diagrams/` folder so later extraction does not alter existing previews. Use `node scripts/questions/verify-local-structured.mjs` for real-corpus assertions and `node --test tests/Frontend/DiagramQuestions.test.mjs` for targeted geometry/identity/renderer checks. Human acceptance of every transcription/figure and installed-PWA verification remain pending.

## One-time extraction review

Tutor Space → **Review questions**, or `http://localhost:8080/question-review`, opens the staff-only review queue. Filter by grade, subject or lesson and compare the extracted text/MathML with its original PDF page. The full PDF link supports browser zoom.

- **Yes, correct** approves that exact canonical content fingerprint once. An unchanged rescan or another source containing the same question retains its approval. Changed question content has a different fingerprint and needs its own review.
- **No, reprocess** stores the optional issue note and queues a forced extraction even when PDF bytes are unchanged. It is excluded from the approved pool and must be reviewed again. The current deterministic extractor does not interpret the note or guarantee an automatic correction. Identical output is explicitly marked **Review again**, still unapproved. A changed result is a new pending question; the missing old version becomes **Needs attention** and cannot be approved. Failed processing remains unapproved and follows worker retries.
- `QuestionReviewService::approvedQuestions(grade, subject)` supplies only approved questions with a valid current source, PDF hash and lesson/course binding. Pending/rejected/stale questions are excluded. Student test creation and completion triggers remain pending; standalone sample generators are explicitly unverified developer previews.

The additive `2026_10_19_000002_create_question_reviews_table.php` migration stores review snapshots, decisions, reviewer/time, revisions and retry tracking; it adds a forced-processing flag to the source outbox. It was applied only locally. Review APIs are `GET /api/admin/question-reviews` and `PUT /api/admin/question-reviews/{fingerprint}`, protected server-side for tutors/admins. Revision checks reject stale decisions; repeated Yes is idempotent.

Validation: 16 isolated PHP tests / 91 assertions for review, authorization, current-source eligibility and upload integration; 29 Node tests including forced same-byte extraction/deduplication. Frontend build/local sync passed with existing Sass/chunk warnings. Playwright used synthetic authenticated API fixtures for decisions, status transitions, PDF comparison and desktop/mobile overflow checks; it did not approve real bank content. Actual local bank: 399 pending, zero approved. Human transcription acceptance and installed-PWA verification remain pending. No production access or deployment.

## Local Laravel upload integration

Final review-unit regression check: **21 isolated PHP tests / 112 assertions** passed including the five recent-learning-resource tests. After the PDF zoom/sizing refinement, the final build and Playwright checks passed again, including filters, retry after a synthetic API failure, stale-decision rejection and no overflow at 1440/390/320px. All 18 original sample PDFs retain their manifest SHA-256 hashes.

Uploading/replacing either question PDF through the existing lesson form now creates a durable `lesson_question_sources` request. The processed marker and old question IDs clear immediately. Lesson/topic/grade/subject/active-state changes and removals also invalidate sources; the worker reconciles bulk operations that bypass model events. Older worker results cannot overwrite a newer PDF revision, and completion checks the actual current file hash.

An additive source/outbox migration is applied only to the local Docker database:

```powershell
docker compose exec -T app php artisan migrate --path=database/migrations/2026_10_19_000001_create_lesson_question_sources_table.php
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/start-question-bank-worker.ps1
```

The hidden local Node worker polls every five seconds, claims database requests using a local-only Artisan bridge, invokes the existing persistent bank and acknowledges source statuses/counts. This keeps scanning out of upload HTTP requests even with Laravel's existing `sync` queue setting. It reuses the host's installed Node/pdfjs runtime, so no Docker/runtime dependency was added. Docker must be running. The start helper avoids duplicate watchers; restart it after reboot when needed. It does not install a Windows startup task.

- Worker: `scripts/questions/app-worker.mjs` (`--watch`, or omit for one pass).
- Private application bank: `storage/app/structured/bank.json`.
- Logs: `storage/logs/question-bank-worker.log` and `question-bank-worker-errors.log`.
- Staff-only status: `GET /api/admin/lessons/{lesson}/question-bank`.
- Local sample setup: `docker compose exec -T --user www-data app php artisan question-bank:attach-local-samples` attaches validated copies to matching empty local lesson slots only. Originals and existing PDFs are preserved. Run as the web-server user because the local disk creates private directories/files; running as root makes the copied PDFs unreadable to HTTP requests and hides their questions from review.
- The sample-attachment command refuses production execution. The export/import bridge allows production only when `QUESTION_BANK_WORKER_ENABLED=true`. Worker result imports are limited to the private results directory. They are not public HTTP endpoints.

The database currently stores processing requests, revisions and question references. Canonical question content remains in the private persistent JSON bank; a full SQL question/assessment model and student completion trigger are not implemented by this upload work unit. Unsupported extraction remains explicitly `processed_with_issues`, not verified question content.

Local integration verification on 19 October 2026: attached the 18 copied PDFs; the worker reconciled 20 application PDF slots including two already-existing local slots. It stored 399 unique candidates and acknowledged all sources; the next pass had no pending work. An identical-content replacement through a real local model save automatically changed lesson 20 to pending revision 2, then the background worker acknowledged it as processed with 10 questions, zero PDF scans and zero additions. The source copy was preserved. Fifteen isolated PHP tests (65 assertions) and 28 Node tests passed; no production connection or migration was used.

## Current workflow: text and structured maths

The user replaced the image-question prototype with text-first extraction. The current default generator renders selectable question text and native MathML fractions, powers and roots. Supported original diagrams are separate PNG attachments; whole-question raster fallback is not used.

Run from the repository root:

```powershell
node scripts/questions/extract-structured.mjs
node scripts/questions/inspect-bank.mjs
node scripts/questions/verify-local-structured.mjs
node scripts/questions/verify-bank-cache.mjs
node --test tests/Frontend/PdfQuestions.test.mjs tests/Frontend/StructuredMaths.test.mjs tests/Frontend/LessonQuestionBank.test.mjs tests/Frontend/DiagramQuestions.test.mjs
node scripts/questions/generate-local-test.mjs
node scripts/questions/serve-local.mjs
```

The generator prints the new test directory. Open `http://127.0.0.1:8091/tests/<directory-name>/index.html`. It randomly selects 15 questions, five each from Fractions, BODMAS and Indices. Choose another three-topic batch and optional count (10–15):

```powershell
node scripts/questions/generate-local-test.mjs storage/app/ocr-samples/grade-8-mathematics "10. Percentage|2. Fractions|11. Indices" 12
```

`generate-structured-test.mjs` is also directly callable with the same arguments. The earlier image-based test snapshots remain available for comparison; new default previews use text, structured maths and separate original diagrams where present.

## Extraction and data

The persistent bank lives in `structured/bank.json`. Each logical lesson/PDF slot tracks its content hash, processor version, status, processing date, rejection details and question references. Questions are stored once by a fingerprint of normalised instructions plus text/structured maths, scoped to grade and subject. Each question retains links to every source lesson/page where it occurs.

Run extraction after adding/updating local PDFs or changing the manifest. Unchanged processed PDFs are skipped. A changed content hash, changed grade/subject/topic binding or changed extractor version invalidates processing. A byte-only change reprocesses the PDF but reuses identical question records. New content adds questions; removed content loses its source link. Questions with no remaining current source are archived, not deleted. Existing generated test snapshots keep their own copies of question data.

Statuses are `processing`, `processed`, `processed_with_issues`, `failed` and `removed`; `inspect-bank.mjs` also reports a changed file as `pending` immediately on its next status check. Unsupported content is recorded once rather than retried on every unchanged run. Failed files are retried on the next processing run. Atomic bank writes and a processing lock guard against partial/concurrent updates. Changed/unavailable/failed source versions cannot supply questions to new tests; a duplicate still linked to another current lesson remains eligible.

Test generation reads the stored bank and only performs lightweight hash checks on PDFs in the selected topics; it never imports PDF.js, parses PDF pages or runs OCR. Exact normalised duplicates are reused. Algebraically equivalent expressions or reworded questions are not automatically treated as duplicates; differing shared instructions, variable case and course scope remain distinct.

Verified with the original local collection: initial run processed **18 PDFs**, stored **399 unique candidate questions** and reused **2 duplicate occurrences**; a second run processed **zero PDFs** and skipped all 18. A disposable real-PDF check also verifies a changed file with the same questions adds zero records, and a replacement PDF adds new questions and archives removed ones. No original PDF is changed by that check.

- Reads the 18 copied PDFs and their manifest directly. It does not need the previous image-region index, Poppler, an OCR service, an API key or a production connection.
- Computes each source SHA-256 hash to identify its current version. The original copy manifest's hash remains provenance; intentional local PDF updates are accepted as new versions. Source PDFs are never changed by processing.
- Detects exercise/question boundaries and joins text runs using PDF glyph spacing, preserving whole words.
- Writes `structured/bank.json` as the authoritative persistent bank and `structured/questions.json` as a derived topic-selection view. Metadata links each question to its source lessons, pages, labels and PDF hashes.
- Supports positioned digital-PDF arithmetic, mixed/simple fractions, basic variables, superscripts and square/nth roots. Every parsed glyph must be accounted for; unsupported symbols, ambiguous layouts and unsupported multiline maths are rejected.
- Uses structured JSON nodes (`expression`, `number`, `variable`, `operator`, `fraction`, `power`, `root`). Native MathML renders them without new dependencies.
- Generates independent JSON/HTML snapshots with cryptographic random selection, balanced topic quotas, unique IDs and explicit insufficient-pool/mixed-course errors.

Local run on 19 October 2026: **401 extracted occurrences became 399 unique candidates**, with **248 rejected regions and 57 skipped pages**. Candidates are labelled `structured_unverified`; parsing success does not certify mathematical transcription. Corpus checks cover specific original mixed fractions, powers, cube roots and arithmetic. Node tests and Playwright desktop/mobile checks supplement those checks, but do not verify all 399 questions.

## Pending

Digital-diagram support is implemented for this local subset; broader layout/figure-boundary acceptance remains pending. Image-only/scanned pages still need maths-aware OCR; the receipt OCR service is not used as proof of maths recognition. Complex equations, negative/multitoken exponents and unusual layouts may be rejected. Eligibility depends on the supported pool rather than silently importing unrelated questions.

Still pending: complete transcription/diagram validation, scanned-PDF OCR, a full SQL question/assessment model, per-student three-topic completion batches, duplicate-trigger prevention, student authorization, attempts, marking and installed-PWA verification. The sample-collection CLI can still be run explicitly; existing Laravel lesson uploads are now handled automatically while the local worker runs. This is a local prototype; no deployment or production access is part of this workflow.

## Legacy diagnostic tools

`extract-local.mjs`, `render-local-test.mjs` and `refresh-local-test.mjs` belong to the earlier crop-based investigation. They are retained for debugging historical previews; they are not used by the default structured extractor or generator.
