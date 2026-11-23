# Phase 1: approved test-paper previews

For the new reusable-answer workflow and expandable draft-answer sample, see [answer keys](../../docs/QUESTION_ANSWER_KEYS.md). Real AI/provider integration and tutor acceptance remain pending; locally calculated drafts are labelled accordingly.

These tools do not issue student papers, record attempts, mark answers or call AI. They reuse current source/diagram validation and require an explicit approved review. Run from the repository root with the local Docker app/database running.

## Check readiness

```powershell
docker compose exec -T --user www-data app php artisan assessments:bank-readiness
docker compose exec -T --user www-data app php artisan assessments:bank-readiness --json
```

The JSON includes source-lesson counts, active topics with zero approved questions, historical/unusable approvals and missing marking metadata. Counts are distinct within each topic; a shared question can appear in several topic counts. Preview a particular batch to prove that its quotas can be allocated without duplicates. A raw count of 15 is not sufficient.

## Generate a Grade 8 Mathematics sample

Local IDs verified on 12 November 2026: Grade 8 = 2, Mathematics = 1. Recheck IDs with readiness when using another environment.

```powershell
node scripts/assessments/preview.mjs 2 1 "2. Fractions" "4. BODMAS" "11. Indices"
node scripts/questions/serve-local.mjs storage/app/assessment-previews
```

Open the exact `http://127.0.0.1:8091/<preview-id>/index.html` printed by the generator. The server's generic startup message mentions `/tests/`; that prefix is not used for assessment previews. Port 8091 must be available. The server binds only to this computer, so this link cannot be opened from a phone on Wi-Fi. Native phone access/answering is a later phase, not supplied by this server.

Every run makes a private UUID folder under `storage/app/assessment-previews/`, containing `index.html`, `paper.json`, and any hash-checked diagram copies. The HTML is a read-only sample using the existing maths renderer. Its header states that answering/marking are not available. The existing sample renderer has no new runtime dependencies or application frontend bundle changes.

Student-facing question cards show only `Question X`, the original instruction and the question/diagrams. Topic names, syllabus numbers and per-topic counts stay in private preview metadata and are omitted from the HTML.

To inspect selection/shortages without creating files:

```powershell
docker compose exec -T --user www-data app php artisan assessments:preview --grade=2 --subject=1 --topic="2. Fractions" --topic="4. BODMAS" --topic="11. Indices"
```

Add `--json` for selected content, provenance and blueprint details. Failure returns a nonzero exit code and no partial paper. Use exact full topic names from readiness.

## Blueprint v1

- Exactly three distinct topics, one grade and subject, 15 unique canonical question IDs.
- Aim for 5/5/5. If necessary, choose the feasible allocation closest to five per topic, with a minimum of three. Examples: 3/6/6 or 4/4/7.
- Use cryptographic randomness to order eligible candidates and break equally fair quota ties. Deterministic mode exists only for pure-selector tests.
- Bipartite matching reassigns shared questions to satisfy quotas. It rejects overlapping subsets that cannot provide three distinct questions per topic, even if the overall union has 15.
- A canonical grouped question counts once, including all its subparts/instructions/diagrams. No splitting of source questions. Fifteen grouped questions are not a verified duration or marks allocation: tutor workload acceptance is pending.
- Selection returns only question-content fields and assigned-topic provenance; future keys/rubrics are excluded by an allowlist. The source-validation service is the required gateway; the pure selector cannot validate PDFs itself.

## Metadata contract and next storage boundary

No migration is needed in Phase 1. Extracted content remains in the private JSON bank; existing question_reviews and question_corrections remain authoritative for review/correction decisions. Preserve fingerprint IDs and correction ancestry. Do not create a second extraction pipeline.

Before scored papers are issued, approve versioned marking records per canonical question/replacement. The new question_answer_keys table stores final/accepted answers, working, marks, answer form, method/rubric and units; new papers can freeze approved versions. Numerical tolerance, symbolic equivalence, skill tags, difficulty and estimated time remain future work. Metadata must not be inferred as approved from transcription approval. Readiness now reports missing versus approved answer-key coverage; the real bank still needs tutor key preparation/approval.

Phase 2 should put indexed paper/batch/attempt identity and ownership in SQL, and freeze the selected question, diagram and marking versions in private snapshots. The precise additive schema belongs to that work unit. Source PDFs/JSON stay the extraction source, rather than being rewritten for assessment storage. Unmarked previews are possible now; trustworthy scored student attempts require the marking contract and later phases.

## Student answer interface requirement

User direction, 12 November 2026: answer directly on the website, including working and final answers; quick to use on phones; no photo/image uploads as answers. Phase 4 must provide an accessible answer field plus simple editable working steps and relevant maths shortcuts, with autosave/resume and minimum typing/navigation. Exact maths-entry controls need a mobile prototype and user acceptance. The current sample intentionally has no answer controls or persistence.

## Validation

```powershell
docker compose exec -T -e APP_ENV=testing -e CACHE_DRIVER=array app php artisan test tests/Unit/TestPaperSelectorTest.php tests/Feature/TestPaperReadinessTest.php tests/Feature/QuestionReviewTest.php tests/Unit/StarterQuestionReviewTest.php tests/Feature/LessonQuestionBankUploadTest.php
node --check scripts/assessments/preview.mjs
```

Existing feature fixtures use isolated in-memory SQLite and fake private storage. Explicit testing/cache overrides prevent Docker's local file cache from sharing API rate-limit counters across tests; no production throttling is disabled.
