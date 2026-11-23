# Smart test papers implementation plan

Date: 12 November 2026
Status: Phases 1–3 and unmarked student answering in Phase 4 are implemented locally. Answer preparation/AI/marking are paused. Score-dependent learning/revision, physical-device/PWA, workload acceptance and deployment remain pending.

Current update — 12 November 2026: user paused provider/answer-key work and explicitly requested student answering with a compact, clearly labelled maths keypad. Phase 3 automatic batching and the unmarked student-answer subset of Phase 4 are implemented locally, with visible tutor/student pages, a practice demo and local scheduler/recovery. See [automatic papers and student answers](AUTOMATIC_PAPERS_AND_STUDENT_ANSWERS.md). AI/marking and score-dependent later phases stay pending; no deployment.

Update — 12 November 2026: reusable answer-key versioning/approval, exact local arithmetic drafts and approved-key snapshot integration are now implemented locally. Real tutor key approval, external AI provider integration, visible editing/answering interfaces and the remaining phases are still pending. See [answer-key guide](QUESTION_ANSWER_KEYS.md). This newer update supersedes earlier statements that answer metadata cannot yet be stored.

This plan expands US-4.1, US-5.2/5.3, US-6.1/6.2/6.4, analytics and US-9.1/9.2/9.3. It does not mark those stories complete or authorise production actions, paid AI calls or sending messages. Implement one bounded work unit at a time under AGENTS.md. Reuse existing extraction, approval, correction, rendering and lesson-progress code.

## Agreed direction

- Papers use current, tutor-approved questions from the question bank.
- Batch three newly completed topics for one student, grade and subject, in that student's completion order.
- The next main paper uses the next three topics; earlier batches stay out of new-topic papers.
- Target 15 distinct questions, at least three per topic, normally five per topic.
- Keep separate revision and follow-up practice so earlier topics can return intentionally.
- AI should provide useful assistance with visible provenance; ordinary selection and scheduling must not be labelled AI.
- Student answers and working must be entered directly on the website, with quick mobile-friendly controls. No photo/image uploads as answers (user direction, 12 November 2026).

## Answer-key prerequisite — current work unit

**Status: Done locally for backend versioning/approval/reuse and local arithmetic preview; real key review, external AI and visual acceptance pending.**

- [x] Add immutable key versions tied to the exact question ID and canonical content hash; retain earlier versions and approval/revocation provenance.
- [x] Store final/accepted answers, worked steps, marks, required answer form, units and method/rubric. Keep content approval separate from key approval.
- [x] Prepare bounded exact arithmetic drafts without paid calls or invented marks; unsupported questions remain explicitly unanswered.
- [x] Validate numerical equivalence and answer form, require marks before approval, and require rubric totals for tutor review.
- [x] Reuse approved keys across students and freeze them into new saved papers; never retrofit existing unmarked papers.
- [x] Staff-only no-store endpoints and service-worker cache bypass/cleanup for answer keys; no student answer disclosure.
- [x] Provide a read-only expandable local draft-answer sample and isolated tests.
- [ ] Configure and evaluate a real AI provider for unsupported algebra/diagram drafts; provider preference requested, no reply assumed and no paid call authorised/performed.
- [ ] Add the tutor editing/approval screen and obtain real tutor answer acceptance. No real keys have been saved or approved automatically.
- [ ] Browser/mobile visual and installed-PWA verification (browser tooling unavailable).

Agent log — 12 November 2026: added QuestionAnswerKey model/table, QuestionAnswerKeyService, ExactMathAnswerService, six private staff endpoints, read-only preview command/script, key coverage in readiness and approved-key snapshots/marks in newly issued papers. Existing papers remain immutable. Real-bank read-only check: 65 usable approved questions, 15 supported local arithmetic drafts, 50 needing interpretation. Applied one additive migration locally after SQL preview; all 25 existing schemas and existing users/reviews/bank/papers unchanged; real key table empty. Final regression: 93 PHP tests/817 assertions, ten service-worker tests, PHP/JS syntax, route/HTTP and generated preview checks passed. No Vite bundle change/build, no real student assignment, no approval of real answers, no external AI call, no commit/push/deployment. UI/mobile/PWA and human/provider acceptance remain pending. The expandable draft preview is for local tutor inspection, not a student answer screen.

## Decisions to confirm before the affected work unit

These are proposed defaults, not yet accepted business rules. Earlier proposals to use five questions per topic, topic-completion rules and revision behaviour need to be reflected in implementation acceptance criteria.

- [ ] Topic completion: require all active lesson videos in that topic to be completed; decide whether answer videos, PDFs or exercises count. PDF viewing alone does not establish completion.
- [ ] Existing history: choose whether to backfill already completed topics or start from a recorded activation point. Do not release a surprise backlog of tests.
- [ ] Topic changes: decide how adding/removing lessons affects eligibility; preserve already issued batches and historical results.
- [x] Assessment format: online answering with editable working and a final answer; no photo uploads. Printable/export delivery remains optional and outside the current implementation.
- [ ] Completion while a test is outstanding: proposed default is to keep accepting learning progress, retain later batches, and show one next test prominently. Agree how many papers can be issued at once.
- [ ] Marks and timing: confirm per-question/subpart marks, calculator policy, optional time limit, pass guidance and answer-release policy. Fifteen questions does not imply fifteen marks or equal workload.
- [ ] Revision: agree intervals, quiz length, whether optional/required, and how it appears alongside main tests. Start with transparent rules rather than an unvalidated mastery score.
- [ ] AI activation: select provider/model, allowed inputs, budget and tutor-review policy before real API use. Implementation may use fixtures while access is unavailable; this is not live AI acceptance.

## Phase 1 — Current bank readiness and paper blueprint

**Status: Done locally for backend readiness, selection and preview generation; browser/mobile and human workload acceptance pending.** A read-only readiness report plus a pure selector tested on approved-question fixtures. No student trigger or new assessment tables in this unit. Usage and metadata/storage contract: [assessment tools README](../scripts/assessments/README.md).

- [x] Report current usable approved counts by grade, subject, topic and source lesson using QuestionReviewService, including corrected replacements.
- [x] Distinguish raw historical approvals from approvals with current valid sources and available diagrams.
- [x] Identify missing answer keys, marks, skills and marking methods separately from content approval.
- [x] Define the minimum reusable question/answer metadata needed for papers; preserve current fingerprint IDs, correction ancestry and source provenance. Decide explicitly which content stays in JSON and which records need indexed SQL storage; do not rebuild extraction. The README records the Phase 2 storage boundary; no speculative migration added.
- [x] Define a versioned blueprint: 15 unique questions, three topics, minimum three each, target five each, one grade/subject.
- [x] Redistribute shortages fairly when a topic has three or four questions. Refuse generation when the minimum or total cannot be met.
- [x] Allocate questions shared by multiple topics once only, using reassignment to avoid false shortages.
- [x] Show a shortage report naming the affected topics and number of further approvals required.
- [x] Define counting unit: one canonical question, including its grouped subparts and original instructions/diagrams. No automatic splitting.
- [ ] Tutor validates expected workload for the grouped-question sample before scored student use; marks/time are not yet defined.
- [ ] Browser/mobile visual verification of preview (Playwright MCP not exposed; available browser host returned unavailable).

Acceptance: deterministic fixtures prove constraints and errors; a read-only real-bank report identifies viable batches. No unapproved or stale question can enter a paper.

Agent log — 12 November 2026: implemented TestPaperReadinessService, pure TestPaperSelector, assessments:bank-readiness and assessments:preview commands, local HTML preview script/README and isolated tests. Existing JSON extraction/review/correction services and maths renderer reused; no migration, dependency, new public API, student attempt, real approval, paid AI call or production action. Real local report: 430 current unique questions, 65 usable approvals, zero historical/unusable approvals; approved content is Grade 8 Mathematics across nine topics. Missing scoring metadata is explicitly reported. Generated two 15-question samples (Fractions/BODMAS/Indices and BODMAS/Equation/Pythagoras) with five per topic. HTTP 200, 15 unique question IDs, five-per-topic counts and six diagram occurrence hashes verified; no answer controls claimed. First broader regression used Docker's local file cache and hit HTTP 429; explicit APP_ENV=testing/CACHE_DRIVER=array isolated test counters without disabling authorisation or throttling. The combined initial suite passed 61 tests/524 assertions; final expanded Phase 1 suite passed 15 tests/97 assertions (including approved correction replacements and empty topics). PHP/Node syntax and whitespace checks passed. No application Vite build needed because application frontend/bundles were not changed. Browser/mobile, installed-PWA and tutor workload acceptance remain pending.

## Phase 2 — Saved papers and immutable question versions (US-6.1)

**Status: Partial — saved-paper backend Done locally; scored marking snapshots await approved bank metadata.** See [saved-paper guide](SAVED_TEST_PAPERS.md) for routes, local migration, safe demonstrations and verification limits. No student/tutor paper screen or answer entry in this phase.

Phase 1 presentation follow-up — 12 November 2026: removed the per-topic summary and topic labels from question headings in the preview generator. Cards now show Question X, original instructions and question/diagrams only; internal topic metadata is preserved. Updated all three previously shared local samples in place without changing selected questions or paper.json, and generated a fresh sample. Node syntax, generated/served HTML checks and whitespace checks passed. No application bundle, backend, database or student-answer behaviour changed. Browser/mobile visual verification remains pending because browser tools are unavailable. Answering with working/final answer remains Phase 4.

- [x] Add minimal additive assessment/batch, selected-question snapshot and attempt structures with required indexes.
- [x] Save source question IDs, content/diagram versions, topic assignment, blueprint version and approval/source provenance.
- [x] Support approved marks and versioned marking-rule snapshots in newly issued papers through reusable answer keys; freeze the approved key version and approval provenance. An incomplete set explicitly keeps marking_ready=false.
- [ ] Tutor prepares/approves real bank answer keys before scored use; no real answers or marks are fabricated or approved automatically. Older unmarked papers remain unchanged.
- [x] Freeze issued paper content; retain diagram assets so source replacement cannot break old papers.
- [x] Enforce student ownership and tutor/admin permissions server-side, including retained diagram access.
- [x] Define all lifecycle states, valid-transition map, audit records and future submission/marking timestamps. Generation/availability/start/cancellation are operational; submission/marking operations remain Phase 4.
- [x] Separate main, revision and follow-up purposes in storage and API responses; scheduling/recommendations remain later phases.
- [x] Make creation transactional and idempotent with a database uniqueness constraint for each student/course/batch/purpose. Verified with simultaneous MariaDB requests.
- [x] Provide staff API preview and explicit cancellation/replacement history without overwriting previous attempts. A visible tutor screen remains future interface work.
- [x] Bypass private saved-paper API/diagram responses in the service-worker cache and remove matching cached entries on activation; installed-PWA verification remains pending.

Acceptance: duplicate requests create one paper; source changes do not rewrite it; another student cannot read it or its answers. Answer keys never appear in pre-submission student payloads.

Agent log — 12 November 2026: added one additive six-table migration, saved-paper models/service/controller, seven authenticated API routes, immutable question/marking snapshots, retained content-addressed PNGs, attempt IDs/timestamps, staff cancellation/version replacement audit, and service-worker private-path exclusion/cache version. No student answering/score or completion-trigger implementation. Current missing marking metadata is represented as null snapshots and marking_ready=false; scored-paper criterion remains unchecked. Applied only the named migration locally after SQL preview; all 19 pre-existing schemas, user counts, approval records and bank hashes unchanged, all six new application tables empty. Concurrent verification in dedicated assessment_phase2_check_7b91e4c20a36 (synthetic fixtures/private temp storage only): two barrier-synchronised processes returned one paper, one attempt, 15 questions. Named fixture database/temp directory retained; no reset/deletion. Targeted combined PHP run: 78 passed/698 assertions; eight Node service-worker tests passed. PHP/JS syntax, route listing, local unauthenticated HTTP 401, served-worker version and whitespace checks passed. No Vue bundle changes/build required; copied only public/sw.js to local public volume. No real student paper assignment, production action, paid AI call, commit or deployment. Student/tutor UI, installed-PWA acceptance, marking metadata and tutor workload acceptance remain pending.

## Phase 3 — Topic completion and automatic batching (US-6.2)

**Status: Done locally for generation/activation, saved-paper pages and scheduler; real student activation, installed-PWA and human acceptance pending.** Rule selected under the user's feature-design delegation: all active primary lesson videos must be complete/current; no answer-video/PDF completion; explicit per-student/course activation with a historical baseline. Pause/resume preserves all evidence. Changed curriculum fails closed for tutor review. First/next three-topic groups are independent; used main-paper question IDs are excluded even across shared origins. New papers can remain unmarked while answer work is paused.

- [x] Implement the selected topic-completion rule using existing lesson completion records.
- [x] Save one stable completion event per student/course/topic, with a deterministic tie-breaker for simultaneous completion.
- [x] Record course/grade context and pause generation for lost course access without mixing histories.
- [x] Reserve the next three unassigned completion events as a batch; keep fourth/fifth topics waiting.
- [x] Prevent completed topics and previously issued main-paper question IDs from re-entering later main-paper batches.
- [x] Keep insufficient-bank batches in a durable waiting state with a staff-visible reason.
- [x] Re-evaluate waiting batches when questions are approved, using bounded work and idempotent retries.
- [x] Revalidate approval/source eligibility at generation with transaction/row locking and final PDF hash checks. Dedicated automatic-concurrency/large-load stress acceptance remains pending.
- [x] Add a reconciliation job for missed events and interrupted jobs, independent of browser sessions; local scheduler runtime verified.
- [x] Link dashboard Quick access to ready saved papers. Student question topic labels are intentionally omitted at user request; staff batch topics remain visible.

Acceptance: complete six topics in a fixture student account and receive exactly two disjoint batches in actual completion order; test concurrency, restarts, shortages and repeated events.

## Phase 4 — Student attempts and trustworthy marking

**Status: Partial — student capture/resume/submission and keypad Done locally; scoring/marking/feedback paused.** Students can enter final answers and optional working on the website, with labelled maths keys or their normal keyboard, one question at a time. Online autosave, optimistic revisions, error/retry, reload, confirmed immutable submission and tutor read-only responses are implemented. Submissions remain awaiting_review with score=null. No photos. Local keypad demo at `/test-papers/demo`; real paper/tutor controls at `/test-papers`. See the current work-unit guide for executed checks and remaining installed-PWA/device acceptance. Score-based learning improvement cannot proceed without marking evidence; do not fabricate mastery or results.

- [x] Build mobile-friendly instructions, paper view, question navigation and entered-answer progress summary using StructuredQuestion. Browser widths 320/390/1440 checked.
- [x] Add labelled maths keypad, normal keyboard option, final answer and editable working steps; no photo inputs/uploads. Physical-phone and installed-PWA acceptance still pending.
- [x] Autosave answers with conflict/version protection, saving/error/retry feedback and resume after reload.
- [x] Define online-only autosave behaviour: preserve unsaved drafts in page memory, warn on leaving, retry network failures, bypass private caches. Persistent offline editing across restarts is not supplied or claimed.
- [ ] Verify actual installed-PWA lifecycle/offline behaviour and physical-device ergonomics.
- [x] Make submission idempotent, lock submitted responses and record server timestamps/duration. No countdown/deadline is invented; timer policy remains a future decision.
- [ ] Mark supported objective/numerical answers with accepted answers, units and tolerances. Do not compare maths answers by raw text alone.
- [x] Keep all current submissions awaiting_review with null score, instead of inventing grades. Automatic/assisted grading remains paused.
- [ ] Provide a tutor marking queue, rubric, overrides and an audit trail.
- [ ] Show per-topic marks, unanswered items, pending reviews, feedback and links to relevant lessons.
- [ ] Keep provisional and final results distinct; never report pending answers as incorrect automatically.

Acceptance: a real local student flow completes start, save, reload, submit and results; authorisation, duplicate submission and tutor-review cases pass.

## Phase 5 — Revision and learning improvement

- [ ] Track per-student topic evidence: attempt date, marks, reviewed answer status and question exposure.
- [ ] Show topic-level evidence rather than claiming mastery from one short test or AI confidence.
- [ ] Recommend targeted lessons and short follow-up practice after mistakes.
- [ ] Schedule separate revision using agreed delays, giving weak and overdue topics priority.
- [ ] Prefer unseen question versions; repeat deliberately when the pool is small and explain this to staff.
- [ ] Track skill coverage where validated tags exist; include reasoning/application alongside routine calculation where available.
- [ ] Keep main-test, revision and retry scores separate while showing improvement over time.
- [ ] Give tutors a concise view of weak topics, repeated mistakes, outstanding papers and approval shortages.

Acceptance: earlier topics appear only in intentional revision/follow-up, with an explainable reason; one poor topic remains visible even if total marks are high.

## Phase 6 — Genuine AI assistance (US-9)

AI readiness infrastructure, then tutor assistance, then student-facing assistance. Core paper generation continues working without AI.

- [ ] Add a provider adapter, background jobs, structured output validation, timeouts, bounded retries and an off switch.
- [ ] Store model/prompt version, relevant input-version hashes, output provenance, actual token/cost usage and review status without secrets or unnecessary student identity.
- [ ] Cache by input/version, deduplicate jobs and enforce per-job/daily spending limits before provider calls. Do not silently substitute a provider.
- [ ] AI bank assistant: propose skill tags, difficulty, expected time, answer keys, worked solutions and marking rubrics as tutor-reviewable drafts.
- [ ] Validate proposed answers with deterministic checks where supported; disagreements enter a review queue. Tutor approval of question transcription does not automatically approve an AI answer key.
- [ ] AI variation assistant: draft additional questions for shortage topics, with solutions and duplicate/similarity checks. Keep drafts ineligible until tutor-approved.
- [ ] AI feedback assistant: suggest explanations of mistakes grounded in the actual question, student answer and approved solution; avoid revealing future test answers.
- [ ] AI marking assistant: propose rubric-aligned partial credit for eligible written answers, with an explanation and tutor escalation. Self-reported confidence alone cannot authorise final marks.
- [ ] AI tutor summary: draft a concise evidence-linked account of weak skills and suggested next practice; let the tutor inspect the supporting results.
- [ ] Treat uploaded content/student answers as untrusted data, constrain available tools, and reject prompt-injection attempts and unsupported output fields.
- [ ] Label actual outputs as AI-assisted and show tutor review where applicable. Do not claim that random selection or a fixed revision timetable uses AI.
- [ ] Create a tutor-reviewed evaluation set spanning correct, wrong, partly correct and ambiguous maths answers. Measure material errors, scoring disagreements, unsafe answer disclosure, cost and latency before activation.
- [ ] Require human acceptance with real permitted inputs before calling live AI reliable. A second model agreeing is not independent proof of mathematical correctness.

Acceptance: each AI feature has an evidenced benefit, an evaluation report, review/fallback behaviour and a budget. AI failure does not lose an attempt or silently change a final result.

## Phase 7 — Automation that saves tutor work

Current subset: assessments:reconcile (--dry-run/--limit), scheduler/helper, waiting-batch recovery and private staff health/status UI are implemented locally. Named tools below remain proposals unless covered by that reconciler; do not create duplicate retry frameworks. Further revision/AI tools await their inputs.

Proposed commands/jobs below do not exist yet. Match final names to existing Artisan conventions and use dry-run/report mode by default for maintenance.

| Proposed tool | Purpose | Dependency |
|---|---|---|
| `assessments:bank-readiness` | Report viable topic pools, missing marking metadata and approval shortages | Phase 1 |
| `assessments:preview` | Preview a blueprint using approved content without issuing a student paper | Phase 1 |
| `assessments:reconcile --dry-run` | Find eligible unbatched completions, waiting batches and missed triggers | Phase 3 |
| `assessments:retry-waiting --limit=...` | Retry bounded eligible batches without duplicating papers | Phase 3 |
| `assessments:integrity` | Detect broken snapshots/assets, invalid state transitions and duplicate assignments | Phase 2 onward |
| `revision:plan --dry-run` | Explain who is due revision and why, without sending messages | Phase 5 |
| `ai:bank-enrich --dry-run` | Preview missing metadata work and estimated cost before approved AI processing | Phase 6 |
| `ai:evaluate-assessments` | Run the fixed evaluation corpus and compare quality/cost against a baseline | Phase 6 |
| Staff health dashboard | Surface stuck jobs, failures, shortages and AI-review work with actionable recovery | Relevant phases |

- [ ] Keep dry-run output free of unnecessary student identifiers and answers.
- [ ] Document schedule, runtime dependencies, locking, job limits and recovery; do not assume an existing extraction worker also runs these jobs.
- [ ] Use existing queues/scheduler where sufficient; avoid introducing a parallel worker framework.
- [ ] Provide local synthetic fixture scripts so the complete flow can be demonstrated without modifying real students or approving real questions.

## Phase 8 — Acceptance and release

- [ ] Targeted backend tests: eligibility, batch ordering, quota allocation, shared questions, idempotency, concurrent requests, ownership, answer-key secrecy, marking and history.
- [ ] Failure recovery: worker restart, missing diagrams, source changes, shortage resolution, failed autosaves and AI outages.
- [ ] Frontend build and targeted frontend tests.
- [ ] Playwright MCP: student and tutor flows, loading/empty/error/retry states, mobile widths, no overflow and no runtime errors.
- [ ] Installed-PWA acceptance for resume, connectivity changes and private-data handling; normal browser checks do not replace this.
- [ ] Tutor validates curriculum coverage, answers, workload and feedback on an actual sample paper.
- [ ] Isolated student accounts validate the six-topic/two-paper scenario and later revision.
- [ ] Bound query counts, generation time, worker load and AI spend using realistic bank sizes.
- [ ] Review additive migrations, backups, retention rules, activation/backfill plan, monitoring and rollback before an explicitly authorised release.
- [ ] Apply production migrations only through the approved `/admin/migrate` process. No deployment, paid processing or real messaging is authorised by this plan.
- [ ] Update the main backlog per work unit with executed checks and remaining verification.

## Definition of complete

A student completes three eligible topics, receives one valid paper drawn solely from their current approved course material, answers and resumes it reliably, and receives trustworthy topic-level results. The next batch covers new topics. Older topics return through planned revision. Tutors can resolve shortages and uncertain marking, inspect learning evidence and use evaluated AI assistance with clear review and spending controls. Local, installed-PWA, human and production acceptance remain separately recorded.
