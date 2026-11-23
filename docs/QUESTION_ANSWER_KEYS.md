# Reusable question answer keys

Implemented locally on 12 November 2026. This work unit adds the answer-key prerequisite to the test-paper plan. It does not implement automatic topic batching, student answer entry, marking/submission, a tutor editing screen or an external AI provider.

## Behaviour

- A current tutor-approved question can have several immutable answer-key versions. Each stores the exact question ID/content hash and snapshot, final answer, accepted alternatives, worked steps, total marks, answer form, unit and marking method/rubric.
- Content approval and answer-key approval are separate. Creating/generating a key always makes a draft. Tutor/admin approval is an explicit second operation.
- The local exact-arithmetic generator can prepare drafts for supported numeric AST expressions. It handles bounded fractions, decimals, mixed numbers, parentheses, unary signs, operation precedence and integer powers. It uses no eval, executable expressions, external API or AI model.
- It refuses ambiguous layouts, diagrams, variables, roots, unsupported instructions and tasks requiring an index-form answer. Unsupported questions are reported as needing tutor or reviewed AI interpretation; no answer is invented.
- Generated drafts have no marks. A tutor must create a revised draft with marks before approval. Tutor-reviewed answers also need a rubric whose component marks equal the total.
- Supported exact-numeric answers are checked for agreement with local arithmetic, and all accepted alternatives must match the value and required form. Equivalent value does not by itself establish the requested form: for example 10/8 is not a lowest-terms fraction, and 1.25 is a decimal representation rather than a fraction.
- Methods/forms: exact_numeric with equivalent_numeric, lowest_terms_fraction or decimal; tutor_review for symbolic/diagram/open-ended answers with explicit rubric guidance. Unit conversion, tolerance/rounding rules and symbolic equivalence are not implemented; use tutor review when these are needed.
- Draft saves have stable request keys. Retries reuse the same version; changed content under the same request key returns 409. Automatic local generation reuses its existing draft or an eligible published key.
- Editing creates another version. An unapproved new draft does not displace the published version. Revoking the latest published key disables it for future papers without silently falling back to an older published version. Discarding an unpublished draft does not revoke the current published key.
- Changed question content, lost question approval or unavailable/stale sources prevent new use. Revoked keys and historical versions remain stored.
- The same approved key can be reused across students. New papers freeze its ID/version, answer/rules/marks and approval provenance inside private question snapshots. All 15 keys must be approved for marking_ready=true and a complete attempt total. Missing keys remain explicit null snapshots; old papers are never silently updated.
- Saved-paper responses keep these answers private, including before submission. Key endpoints are tutor/admin only, use no-store headers, and are excluded from the service-worker cache (with activation cleanup).

## Try the read-only local preview

```powershell
node scripts/assessments/answer-key-preview.mjs
```

Open the exact localhost URL printed by the command. If the existing preview server is stopped:

```powershell
node scripts/questions/serve-local.mjs storage/app/assessment-previews
```

Each of five sample questions has a native expandable **Show draft answer and working** section. This is a local tutor demonstration, not a student test. It explicitly labels output as locally calculated, not AI-generated or approved. It creates only a private preview folder with HTML/JSON; no actual key, approval or student record is saved. The server binds to localhost and is not a production feature.

Read-only JSON/coverage report:

```powershell
docker compose exec -T --user www-data app php artisan assessments:answer-key-preview --limit=5
docker compose exec -T --user www-data app php artisan assessments:bank-readiness --json
```

The real local bank currently has 65 usable approved questions. The bounded solver supports 15 and reports 50 needing interpretation. No real key has been saved or approved by this work unit; the real new table remains empty. The bank-readiness command now reports approved answer-key/marks/method coverage separately from content approval; skill classification is still missing.

## Staff API (existing Sanctum authentication)

| Method | Route suffix under `/api/admin/question-answer-keys` | Purpose |
|---|---|---|
| GET | `/` | Paginated current approved questions; optional grade_id, subject_id, topic filters; current published/latest key metadata |
| GET | `/questions/{64-character-question-id}` | Exact question/hash and paginated key history |
| POST | `/questions/{question-id}/generate` | Prepare/reuse a local exact-arithmetic draft, or explain why tutor input is needed |
| POST | `/questions/{question-id}` | Save an immutable tutor draft |
| POST | `/{key-uuid}/approve` | Explicit approval of a complete key for the expected question hash |
| POST | `/{key-uuid}/revoke` | Retain but disable a key, with a reason |

Example tutor draft body for a question whose correct answer is 5/4:

```json
{
  "request_key": "11111111-1111-4111-8111-111111111111",
  "question_content_hash": "<copy the current hash from the staff GET response>",
  "answer": {
    "final_answer": "5/4",
    "accepted_answers": ["1.25", "1 1/4"],
    "working": ["Use denominator 4: 3/4 + 2/4 = 5/4."],
    "marks": "2.00",
    "marking_method": "exact_numeric",
    "answer_form": "equivalent_numeric",
    "unit": null,
    "rubric": []
  }
}
```

For a lowest-terms-fraction requirement, choose answer_form=lowest_terms_fraction and omit decimal alternatives. Approval body contains only the matching question_content_hash. Revocation requires a reason. Supplying an answer or status from a student request never gives student access to these operations.

## Migration and validation

Additive migration `2026_11_12_000002_create_question_answer_keys.php` adds one table with per-question/version and per-question/request uniqueness plus restrictive historical foreign keys. Applied locally using its explicit path after SQL preview. All 25 existing table structures and user/review/bank/paper hashes remained unchanged. Baseline retained privately at `storage/app/answer-key-verification/before.json`. No production migration or deployment.

```powershell
docker compose exec -T -e APP_ENV=testing -e CACHE_DRIVER=array app php artisan test tests/Unit/ExactMathAnswerTest.php tests/Feature/QuestionAnswerKeyTest.php
```

Final combined regression: 93 PHP tests / 817 assertions passed. Ten Node service-worker tests passed. PHP/JavaScript syntax, route discovery, real HTTP 401 on the private key list, HTTP 200/sample structure and served worker privacy rules passed. A new Vite build was unnecessary: there are no application Vue/bundle changes; only standalone worker JS and generated local preview HTML. Only sw.js was synced to the local public volume.

The preview is verified at the HTML/HTTP level. Playwright MCP/browser host were unavailable earlier in this session; visual/mobile and installed-PWA acceptance remain explicitly pending. Fixture approvals and arithmetic tests are not real tutor acceptance of all bank answers.

## Next boundaries

- Select/configure an AI provider and budget before implementing real provider calls; no provider/model, API key or paid processing was selected or used here. The assistant requested the provider preference; no reply has been assumed.
- Add evaluated provider draft generation for the remaining questions, with question-only context, provider/prompt version, cost controls, validation and tutor review. Do not label this local solver as AI.
- Build the tutor key-editing/approval interface so this API workflow can be used by clicking, then obtain actual tutor acceptance and approve real keys.
- Continue Phase 3 only after its topic-completion/activation decisions are resolved under the project work-unit rules. Student working/final-answer entry remains Phase 4 and excludes photo uploads.
