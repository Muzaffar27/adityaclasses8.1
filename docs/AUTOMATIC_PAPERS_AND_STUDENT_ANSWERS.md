# Automatic papers and student answers

Local implementation: 12 November 2026. AI/provider selection, generated answer preparation and marking are paused at the user's request. This work implements automatic batching, visible tutor/student paper pages and unmarked answer capture. No deployment is authorised or performed.

## Try the maths keypad

While signed in locally, open **http://localhost:8080/test-papers/demo**. The same editor is used for real papers.

- Three demonstration questions, one at a time; entries remain in page memory only.
- Five organised keypad rows with digits, decimal, delete, fraction /, power ^, multiply ×, divide ÷, add/subtract, brackets, root √, x, equals and space, plus a short percent/pi/degrees row. Each symbol has a visible name, including at mobile widths.
- Tap Final answer or Your working to select the destination. Show/hide working preserves its value. The new-line button adds a working step.
- Letters/ABC switches to the normal device keyboard; Use maths keypad switches back. Fractions/powers are entered as readable notation such as 3/4 and 2^3; this is not a symbolic solving engine.
- Submit demo freezes that demonstration until Try again. No student paper, answer, score or real approval is saved by the demo.

## Tutor workflow

### Dashboard list and pink announcement-style reminder - 17 November 2026

The dashboard now lists generated papers instead of showing one placeholder card. It displays five per page with Previous/Next navigation through the full history. Available and started papers come first, oldest by available_at; submitted/reviewed/cancelled papers follow. Each row shows its subject, grade, preparation date, status and Start/Resume/View link. The Up next label matches the reminder's destination. This ordering guides students without locking them out of choosing another paper.

The notification selects the oldest actionable unfinished paper across the account's courses, even when a newer paper is already started. After submission/cancellation it moves on to the next paper when refreshed. Student course-access checks and account privacy remain enforced. The full-width notice now follows the announcement's simpler icon/title/body layout in the dashboard's pink test-paper palette. The private personal list endpoint accepts per_page=1–20, and the dashboard requests five; no paper data was added to the generic cached dashboard API. List loading, empty, retry and account-change request cancellation are handled separately.

Validation: 28 saved-paper PHP tests/384 assertions passed in private SQLite; Vite build/local sync and dedicated Playwright dashboard fixtures passed across 320/390/768/1440, covering actions/statuses, pagination, refresh/advance, retry, empty state and no overflow/page errors. Playwright MCP separately confirmed the pink two-paper mobile view and correct reminder text. Screenshot inspected. No migration, application-data modification or deployment. Physical-device/installed-PWA acceptance remains pending.

### Student reminder and dashboard - 13 November 2026

For a tutor-enabled student/course, finishing three new topics queues the next paper. Once enough approved questions are available, the minutely background worker issues the saved paper. The student sees **Your test paper is ready** with **Open test paper** while using the website, and a dashboard Test papers card shows the number still to do plus the same direct link. After starting, the reminder says **Finish your test paper** with **Resume test paper**. It clears after submission or cancellation. On the paper itself the reminder is hidden to leave room for answering.

The reminder checks every 30 seconds while the page is visible and online, as well as on window focus/reconnection. Starting and submitting refresh it immediately. This is a website reminder; device push delivery is not implemented. If a check fails, the dashboard offers Try again. Previously fetched details remain confined to the current signed-in student and clear on account changes. Available papers require active course access to appear; started work remains resumable under the existing access-expiry policy.

`GET /api/test-papers/attention` is student-only and returns own counts and one next-paper ID/status/grade/subject with private no-store headers. Started papers take priority. No questions, solutions or drafts are added to the generic dashboard response. The existing private-API service-worker rule already bypasses and purges this path. No migration is added.

To test locally, enable future topic tests from the tutor Test papers page before the student completes three new topics. Use Check now or wait for the scheduler, then open the student dashboard. Start the paper, return to the dashboard to see Resume, and submit the paper after entering all final answers to clear the notice. A question shortage remains visible to staff and does not generate a misleading student reminder. Existing completed topics at activation are still baselined, so replaying them does not trigger a paper. The local scheduler helper must be restarted after restarting its container.

Validation: 36 isolated saved-paper/automatic-assessment PHP tests with 448 assertions and 11 service-worker tests passed. Final build/local Docker sync and extended Chrome browser fixtures passed, including Open/Resume, counts, clearing on submission, retry and 320/390/1440 layouts; screenshots inspected, no page errors. Playwright MCP verified delayed readiness appears on the polling cycle without reload and without mobile overflow. Physical-device/installed-PWA acceptance remains pending. Nothing deployed.

Open **http://localhost:8080/test-papers** while signed in as tutor/admin. Choose a student, grade and subject, then explicitly enable future topic tests. The student must have active course access.

Activation is idempotent for that student/course. Already completed topics are captured in a baseline and excluded; it never backfills a surprise backlog. The course can be paused/resumed without erasing history or resetting its baseline. Resume records the current responsible tutor, allowing another authorised tutor to recover a course whose previous tutor role changed.

Check now runs one bounded reconciliation for that course. The page displays waiting topics, question shortages, curriculum changes and issued batches. Waiting batches also have a staff API cancellation operation requiring a reason; cancelling retains their reserved topics/history, rather than silently recycling them. Issued papers use the existing paper-cancellation/replacement operations.

## Completion and batching rules

- All active lessons in a topic must have their main video completed against its current video-source hash. A missing primary video blocks completion; answer videos and PDF viewing do not count.
- Previously completed topics at activation remain excluded even if replayed. Partially completed topics can qualify once their last required lesson is completed after activation.
- Three distinct newly completed topics, in each student's actual completion order, form one batch within one grade/subject. Same-time ties use a stable topic hash.
- Fourth/fifth topics wait; the sixth forms another batch. Already assigned topics do not re-enter later main batches.
- Main-paper question IDs already issued to that student/course are excluded, including shared questions with origins in an old and a new topic. An unseen-question shortage waits for more approved content rather than reusing old main-paper questions. Explicit manual replacements and revision/follow-up papers have separate purposes and can reuse material intentionally.
- The existing 15-question selector still enforces at least three per topic, normally five, with no duplicates within a paper. No answer-key approval is required to issue an unmarked paper.
- Completion records and each batch's evidence/topic selection are retained. Renaming/splitting a completed topic cannot silently retest its old lessons. Changed lesson sets or video sources before issuance produce waiting_curriculum for tutor review.
- Insufficient approved questions produce a durable waiting batch. Later approval is picked up by reconciliation. Retry counts rotate waiting batches so one shortage does not starve another ready batch.
- A batch UUID is the saved-paper idempotency key. Transactions, student-row locks and database uniqueness protect repeated/concurrent processing. Source/approval/key locks are acquired before the automatic transaction's consistent read, preserving snapshot validity when calling the saved-paper service inside reconciliation.

## Scheduler and recovery

Progress writes only a durable reconciliation flag in their existing database transaction. They do not generate a PDF or scan the bank in the playback request. A Laravel scheduler command checks a bounded rotation of activated courses every minute (20 per pass by default); no enrolled courses means no papers are generated.

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/start-assessment-worker.ps1
docker compose exec -T --user www-data app php artisan assessments:reconcile --dry-run
docker compose exec -T --user www-data app php artisan assessments:reconcile --limit=20
```

The helper refuses a non-local application container, starts hidden inside Docker, and uses flock to prevent duplicate scheduler processes. It is running locally; it is not a Windows startup task or production cron. Restart it after restarting the app container. Log: `storage/logs/assessment-scheduler.log`. Installed host scheduling/deployment is outside this task. A new paper normally appears on a later minute's check; larger queues remain bounded and rotate. Staff can use Check now for a particular course.

## Real student answering

Current interface — 13 November 2026. A single response sheet has Working and Final answer tabs. Final answer uses the maths keypad. Working offers Keypad / Handwrite controls. Choose a section, then enter the response in the sheet. The keypad stays open while switching sections. Undo stroke and Clear appear within the handwriting surface. Older dated UI notes below describe earlier versions.

Students open Test papers from their dashboard Quick access, select a paper and choose Start answering. A paper shows only Question X, instructions and question/diagrams, followed by answer controls. Topic labels are not shown on student questions.

- One question at a time, with Previous/Next and a question picker showing where final answers were entered.
- Working and final answer are separate text fields. No photo/file inputs or upload routes; answer-photo fields are rejected server-side.
- Online autosave is debounced by 1.5 seconds and serialised. Navigating between questions flushes pending changes first. Reload restores saved responses and their revision.
- Stale-tab saves/submissions return 409. The current draft stays visible; Retry using this draft explicitly fetches the latest revision before resaving only the locally dirty questions.
- Network errors preserve the in-memory draft and show Retry save. Navigation/unload warns if unsaved work would be discarded. No claim of persistent offline editing across app/browser restarts: keep the page open until saving succeeds. Installed-PWA/offline acceptance remains pending.
- Submit answers appears above the questions. It lists any question numbers still missing final answers. Once all final answers are entered, the reader saves pending work and asks the student to confirm. The server also checks every final answer before accepting submission.
- Submitted answers are immutable through the API. The paper becomes awaiting_review, with server timestamps/duration and score=null. Repeated submission is idempotent; late autosaves cannot overwrite it. No automatic answer release, scoring or AI marking occurs.
- Tutors can read student responses but cannot submit or edit them as the student. Ownership is enforced server-side for questions, diagrams and answers. Previously started papers can be finished after course access expires; starting a new attempt still requires active access. History stays readable by its owner.
- Paper, response and automatic-enrolment APIs use private no-store responses; the service worker bypasses and purges private endpoint caches. The shared dashboard data endpoint was not expanded with private paper contents or answer drafts.

## Schema and API

Two additive migrations, applied locally only:

- `2026_11_12_000003_create_automatic_assessment_tracking.php`: student_assessment_enrolments, automatic_assessment_batches, student_topic_completions.
- `2026_11_12_000004_create_test_paper_responses.php`: test_paper_response_versions, test_paper_answers.

No existing columns/data were reset or deleted. All 26 original table definitions and original users/reviews/papers/keys/bank hashes stayed unchanged; the later pre-response baseline also verified all 29 preceding table definitions unchanged. All five new application tables are empty: no real student was activated, assigned a paper or given a fabricated completion/answer. Synthetic verification is separate.

New authenticated operations:

| Method | Route | Role |
|---|---|---|
| POST | `/api/admin/students/{student}/automatic-test-papers` | Tutor/admin; grade_id and subject_id |
| GET | `/api/admin/automatic-test-papers?student_id=…` | Tutor/admin; activation/batch status |
| PUT | `/api/admin/automatic-test-papers/{enrolment}` | Tutor/admin; enabled boolean |
| POST | `/api/admin/automatic-test-papers/{enrolment}/reconcile` | Tutor/admin; bounded Check now |
| POST | `/api/admin/automatic-test-papers/batches/{uuid}/cancel` | Tutor/admin; reason required, unissued batches only |
| GET | `/api/admin/test-papers?student_id=…` | Tutor/admin saved-paper list |
| PUT | `/api/test-papers/{uuid}/answers` | Owner student; revision and numbered answer/working patches |
| POST | `/api/test-papers/{uuid}/submit` | Owner student; expected response revision |

## Verification evidence

- Targeted PHP suites cover six-topic/two-paper ordering, historical baselines, shortage recovery/fairness, source/lesson completeness, renamed curriculum, course/role permissions, pause/resume, observer outbox, dry-run, old-question exclusion, answer persistence, stale tabs, immutable unmarked submission, limits and prohibited photo fields.
- Combined targeted run passed 111 PHP tests / 1007 assertions before the final dashboard-count check; the final saved-paper subset passed 19 tests / 222 assertions including actual submitted-count/null-average dashboard behaviour. Submission counts now reflect real saved submissions; scores/averages remain unmarked.
- Nearby dashboard/learning regression passed 17 tests / 234 assertions. Fourteen Node tests passed (three keypad and eleven service-worker tests). Final local build and cached-Playwright browser fixtures passed after the visible labels/contrast fixes and percent/pi/degrees keys; screenshots were inspected. Browser checks used fixtures and blocked service workers; installed-PWA/device and full automatic-generation concurrency/load acceptance remain pending.
- Node tests cover cursor insertion/selection/delete/Unicode/limits and visible symbol labels; service-worker tests cover privacy and existing nearby cache behaviour.
- Vite production build/local Docker sync passed with existing Sass/dynamic-import warnings.
- Playwright MCP was unavailable; used the already cached Playwright package and local headless Chrome, with no new project dependency. `scripts/assessments/check-browser.mjs` used isolated auth/API fixtures to verify the actual built UI: fraction/power/root entry, working, normal-keyboard switching, navigation, save/reload, failed-save recovery, stale-tab recovery, unmarked submission, tutor activation/check/pause, and zero horizontal overflow at 320/390/1440 pixels. No page runtime errors. Screenshots were visually inspected; dark-theme contrast and mobile keypad layout were corrected and retested. Fixtures are not proof of production/PWA behaviour.
- Real MariaDB smoke in the retained isolated synthetic database `assessment_phase2_check_7b91e4c20a36`: three completed topics generated one paper; one answer saved/resumed and submitted awaiting review with no score; retries created no duplicates. `scripts/assessments/verify-automatic-flow.php` uses only the controlled concurrency fixture and refuses the application database. Its initial attempt correctly encountered a shortage because the earlier fixture student had already seen every question; the verifier was corrected to use a fresh synthetic learner, without weakening exposure checks or resetting data. Synthetic records and marker file remain for inspection.
- Local scheduler lock and scheduled minute executions were observed. The real application has no activated student courses.
- Physical phone, installed-PWA lifecycle/offline work retention, human design/curriculum acceptance and scale/load benchmarking remain pending. No commit, push, deployment, production migration, real message or paid processing.

## Remaining scope

Answer keys/AI and marking stay paused. Consequently no score-based weaknesses, mastery claims, adaptive correction or score-driven revision recommendations are invented. Phase 5's marking-dependent work and Phase 6's provider integration remain pending. The bounded reconciliation/scheduler/recovery tools implement the relevant part of Phase 7; further integrity/revision tools await their inputs. Phase 8 has local automated/browser evidence, while physical-device/PWA and release acceptance remain pending. Student keypad design is ready for user feedback through the demo above.

## Mobile keypad refinement — 13 November 2026

The answer keypad opens when a student taps final answer or working. On screens up to 640 px it docks at the bottom, with safe-area padding and space at the end of the document so page controls can scroll above it. Done, tapping outside and changing questions dismiss it. Desktop keeps the keypad beside the editor in normal page flow.

Numbers and maths symbols each use two rows of six keys. Tabs select 123, Maths or More, preserving the selected answer field and cursor. Space has a wide permanent button, ABC opens the normal keyboard, and working includes a Line button. Power displays x with a superscript box and the label Power. Answer text continues using the existing ^ notation, such as 2^3.

Validation passed locally with the Vite build/local Docker sync, three keypad unit tests, browser fixtures for 320/390/1440 widths (two-row layout, minimum 44 px key widths, bottom docking, no horizontal overflow), field switching, symbols, normal keyboard, dismissal, save/reload/network retry/conflict/submission and staff controls. Playwright MCP also verified the 320 px layout, power entry, dismissal and no page runtime errors. Screenshots inspected. Physical-phone and installed-PWA acceptance remain pending. No migration or deployment.

## Variables and fraction key — 13 November 2026

Removed ABC and normal-keyboard switching following phone feedback. Renamed More to Vars with π, x, y, a, b, c, m, n, p, q, r and t in two rows. Degrees now appears under Maths. Fraction displays two boxes separated by a horizontal fraction bar and keeps its visible Fraction label. Entered fractions still use the existing text notation, such as 3/4. Working supports spaces and new lines through the keypad.

Vite build/local Docker sync and complete browser fixtures passed, including every variable, no ABC control, fraction bar, working newlines, 320/390/1440 layouts, save/reload/network retry/conflict/submission and staff controls. Playwright MCP verified πxyab entry, the fraction icon, zero page runtime errors and no phone-width overflow. Screenshots inspected. Physical-phone/PWA acceptance remains pending.

## Editable fractions and powers — 13 November 2026

Final answers and working now use structured maths fields. Fraction creates editable numerator/denominator slots, including wrapping a preceding value. Power creates a raised exponent slot, or base/exponent slots when starting with an empty field. Students can tap slots directly, use Next slot to reach an empty slot and Out to leave a fraction or power. Fraction/Power return the keypad to its number keys automatically. Working keeps its Line button for multiple steps. The fraction and power icons use blue inset boxes and refined typography.

Added pinned MathLive 0.111.4 because the existing MathML question renderer displays finished expressions and supplies no structured cursor/selection editing. The editor and local fonts are bundled in the assessment route chunk, approximately 231 kB gzip for its JavaScript. The main app imports assessment pages lazily. A Playwright MCP check observed only localhost resource requests while entering a fraction and power.

New entries save standard LaTeX inside inline delimiters, for example `\(\frac34+2^3\)`, through the existing answer/working strings. Empty slots and multiline gathered working retain their structure on reopening. The backend preserves these strings exactly. Older plain-text responses render through the legacy conversion path, preserve prose as text, and keep their stored value until edited. Future marking must recognise these delimiters and decode the LaTeX. Marking remains a pending work unit. There is no schema migration.

Validation passed with the final local Vite build/sync, 19 saved-paper backend tests / 224 assertions, three existing keypad utility tests, JS syntax/diff checks and complete browser fixtures. Browser coverage includes variable keys, no ABC, fraction/power entry, incomplete-fraction save/reload/completion, legacy text/prose preservation, character-limit rejection preserving the prior answer, multiline working, space-only entries, save/reload/network/conflict/retry/submission, staff controls, 320/390/1440 widths, minimum key sizes and no horizontal overflow/runtime errors. Playwright MCP verified numerator/denominator slot navigation, powers, local resources and phone layout. Screenshots inspected. Physical-phone and installed-PWA acceptance remain pending.

Backend test environment required the existing root-owned fake-storage fixture and an uncached testing configuration with array cache. The initial www-data run stopped on fixture-directory permissions, and the cached-config run hit the shared rate limiter. The successful run used the isolated in-memory database, array test cache and `APP_CONFIG_CACHE=/tmp/aditya-keypad-testing-config.php`. Authentication and throttling middleware ran normally. No app database reset or cache clear was performed.

## Finger and stylus handwriting — 13 November 2026

Students can choose Write by hand under the final answer or working field. The ruled drawing area accepts finger, stylus and mouse strokes. Undo stroke reverses recent drawing actions, including Clear. Done writing restores page scrolling over the drawing. Keypad expressions and handwriting can coexist. Handwriting-only responses count as entered answers and can be submitted. Submitted and tutor views display the retained strokes with editing controls hidden. The practice demo keeps entries in page memory, while real papers use the existing autosave/revision/submit flow.

Added HandwritingPad.vue using native canvas and pointer capture, normalized coordinates, a fixed 4:3 surface, high-DPI rendering and resize redraw. Pointer cancellation retains the partial stroke. Secondary contacts during an active stroke are ignored. Immutable point snapshots are published while drawing so a newer stroke remains dirty when an earlier save returns. Both answer and working ink participate in conflict retry and unsaved-draft handling.

The additive local migration `2026_11_13_000001_add_handwriting_to_test_paper_answers.php` adds nullable JSON columns `answer_ink` and `working_ink`. Storage uses `{ "v": 1, "strokes": [[[0.1, 0.2], [0.3, 0.4]]] }`. Coordinates are numeric pairs in [0,1]. Each field accepts up to 200 strokes, 500 points per stroke and 5,000 points total. The Handwriting validation rule rejects invalid structure, coordinates and excessive data. Empty drawings normalize to SQL null. Existing text-only clients preserve ink when their patch omits the ink fields. Missing migration hides handwriting entry and returns 503 for attempted ink saves, while text entry remains available. Existing ownership, revision locks, private no-store headers and immutable submission apply to the ink fields.

Local SQL preview showed only the two new JSON columns. The existing answer-row baseline contained zero rows and remained unchanged after migration. The application database received the additive migration only. No real student answers were created by these checks.

Validation passed with 22 saved-paper backend tests / 277 assertions, nine automatic-assessment tests / 99 assertions, PHP/JS syntax, whitespace checks and the final Vite build/local Docker sync. Full browser fixtures passed with emulated touch and pen, undo/clear/undo-clear, working ink, handwriting-only demo submission, navigation and resize preservation, real-paper fixture autosave/reload, drawing during an in-flight save, locked submitted views, existing maths input and error/retry flows, and 320/390/1440 layouts. Playwright MCP checked a phone-width touch stroke, enabled submission and restored scroll touch-action, with no overflow or page runtime errors. Screenshots inspected. Physical-phone, real-stylus/palm behavior and installed-PWA acceptance remain pending. The feature saves handwriting as written. Automatic handwriting recognition and marking have no implementation in this work unit.

## Space and mixed numbers — 13 November 2026

Fixed Space inserting a visible backslash because MathLive inferred the input format. Keypad insertions now explicitly use LaTeX, and Space inserts a visible mathematical gap. Space-only input stays empty for answer/submission purposes. Fraction after a gap creates empty numerator/denominator slots beside the whole number. Ordinary Fraction continues to wrap an immediately preceding value.

Added a Mixed button in the keypad footer. For 1 1/4, students can enter 1, Mixed, 1, Next slot, 4. Mixed can also start from an empty field and supplies whole-number/numerator/denominator slots. It handles an existing gap after the whole number. The working Line button moved to the header so the footer stays usable at 320 px.

Changed MathsEntryField.vue and MathsAnswerEditor.vue. Extended the existing browser verifier with five mixed-entry sequences, corrected Space output, footer target sizes, and mixed-number save/reload alongside handwriting. Final Vite build/local Docker sync, JS syntax/diff checks and full browser fixtures passed across 320/390/1440 widths, including existing fractions/powers, handwriting, save/reload/conflicts/retry/submission and staff controls. Playwright MCP verified the displayed mixed number at 320 px with no overflow or page runtime errors. Screenshot inspected. Physical-phone/PWA acceptance remains pending. No migration or deployment.

Follow-up verification — the answer field now scrolls above the docked keypad as entries grow or students move between slots. MathsAnswerEditor checks the field's current viewport bounds after the next paint on insertion/navigation, keeping the denominator visible. Final build/local sync and full browser fixtures passed with a specific 320 px denominator-visibility check. Playwright MCP confirmed the complete mixed fraction remains visible above the keypad, without overflow or page runtime errors.

## Unified academic response sheet — 13 November 2026

Replaced the repeated Write by hand and Show working controls with one bordered response sheet. Working / Final answer tabs select the response section. Shared Keypad / Handwrite controls select how to enter it. The active section is the only visible panel. Thin section tabs, restrained colours, integrated maths entry and a ruled handwriting surface provide a consistent exam-paper appearance. Existing keypad expressions and ink remain visible within their selected section. An open keypad stays available when changing sections.

HandwritingPad now receives its editing state from the shared toolbar and presents Undo stroke / Clear inside the sheet. Working and final-answer components remain mounted so their selection, drafts and undo history survive section/method switches. Section tabs support left/right arrow navigation and associated tab-panel labels. Read-only submitted/tutor views retain section navigation and omit entry-method/editing controls.

Main source changes are MathsAnswerEditor.vue and HandwritingPad.vue, with updated existing browser verification. Final local build/Docker sync, JS syntax/diff checks and complete browser fixtures passed. Coverage includes one visible panel, removed old controls, accessible tab navigation, retained keypad on section switching, saved maths and pen undo history, mixed numbers, handwriting, save/reload/conflict/retry/submission and 320/390/1440 widths. Playwright MCP at 320 px confirmed one visible panel, retained mixed-number content and ink undo state, with no overflow/runtime errors. Screenshots inspected. Physical-phone/stylus/PWA acceptance remains pending. This UI work added no database migration or deployment.

## Keypad-only final answers and clearer controls — 13 November 2026

Final answer accepts maths-keypad entry. Handwriting entry is offered under Working, which remembers its selected method when changing sections. Returning to Final answer always enables its editable maths field. The server rejects new nonempty answer_ink values, and the response service enforces the same restriction. Final-answer counts and question completion labels use the typed answer. Submission eligibility ignores legacy final-answer ink, while the existing allowance for incomplete papers and working-only responses continues. Earlier final-answer drawings remain viewable as labelled read-only history and survive text edits. New saves omit the answer_ink field.

Renamed Mixed to a visible 1 ¼ example with Whole + fraction, Next slot to Next box, and Out to Back to main line. The four action buttons now occupy two clear rows, with larger readable labels. The number/symbol keys keep their two-row layout. The response sheet explains that students can tap a number or empty box to edit it. Student paper instructions reflect the keypad-only final-answer workflow.

Main changes are MathsAnswerEditor.vue, TestPapersPage.vue, TestPaperDemo.vue, TestPaperController.php and TestPaperResponseService.php, with updated feature/browser regressions. Final build/local Docker sync passed. Twenty-three saved-paper tests / 291 assertions passed, covering rejected final-answer ink, retained legacy history excluded from entered responses, working ink quotas/ownership and submission locks. Complete browser fixtures passed with the new labels, keypad-only finals, working touch/pen input, method switching, mixed fractions, autosave/reload/concurrent save/conflicts/retry/submission and 320/390/1440 layouts. Playwright MCP at 320 px confirmed handwriting appears for Working, Final answer stays editable through its keypad, and all labels fit without overflow/runtime errors. Screenshot inspected. Physical-device/PWA verification and automatic marking remain pending. Implementation is local.

## Calculator-style cursor controls — 13 November 2026

Replaced Whole + fraction, Next box and Back to main line with one footer row containing Space, ← Left and Right →, following the user's reviewed proposal. Left/Right use normal MathLive character navigation to enter/leave fractions and powers or edit digits. Fraction and Power keep their visual symbols. Entry follows writing order, such as 3, Fraction, 4, Right, +, 2 for 3/4 + 2, or 1, Space, 1, Fraction, 4 for a mixed number. The direct-editing hint now explains tapping where to write or using the arrows. Removed the unused mixed-template and placeholder-navigation handlers.

Main source changes are MathsAnswerEditor.vue and MathsEntryField.vue, with updated existing browser verification. Final local build/Docker sync, JS syntax/diff checks and complete browser fixtures passed. Added coverage for the exact three-button footer, fraction/power continuation with Right, digit insertion with Left, mixed numbers and returning into an incomplete fraction with Left after reopening. Existing final-answer policy, handwriting, save/reload/retry/conflict/submission and 320/390/1440 layouts passed. Playwright MCP at 320 px confirmed 3/4 + 2 and the requested footer, with no overflow or runtime errors. Screenshot inspected. Physical-device/PWA acceptance remains pending. Implementation is local.
