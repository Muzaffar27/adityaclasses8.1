# Aditya Classes — User Stories from the Quotation

Source: [Aditya_Classes_Complete_Quotation_Reorganised.md](Aditya_Classes_Complete_Quotation_Reorganised.md).

Scope decision — 16 October 2026: account-status display, management and login restrictions are removed from scope; older requirements and pending decisions for that feature below are superseded. Exercise/assessment activity tracking and further video-resume precision/abrupt-close hardening are deferred. Topic-completion highlighting already exists and derives from completed main lesson videos in the displayed group; separate historical chapter records can be revisited with later assessments/reports. The existing dashboard is the accepted foundation for this phase. No unperformed actual-account/installed-PWA checks or full analytics-module criteria are marked complete by this decision.

This is a fresh translation of the quotation into user stories. The existing implementation backlog was not used. No application implementation or runtime verification was performed for this document; these stories do not assert that a feature is missing or already implemented.

Stories are **Pending** unless an individual status is recorded below. Before implementing a story, inspect the existing feature and identify only the unfinished work. Acceptance criteria below are proposed testable interpretations of the quotation. Open business rules are listed at the end and must be resolved where they affect implementation. **Done locally** records the executed local checks; deployment and real-account/installed-PWA acceptance remain separate where noted.

Dependencies identify shared capabilities required by a story, whether already present or implemented later. IDs follow the quotation's module order and are not a mandatory implementation queue.

## 1. Student Profile, Video Progress & Learning Activity

### US-01 — View student profile
**Status: Done locally — existing implementation reviewed and locally verified on 4 October 2026.**

As a student, I want to view my personal and academic information so that I can understand my account and enrolment.

Acceptance criteria:
- A dedicated profile page displays my name, grade and subjects.
- The displayed information belongs to the authenticated student.
- Restricted academic/account fields are distinguishable from information I may edit.

Dependencies: shared authorization (F-02).

Scope decision — 4 October 2026: account-status display/checks are not required for US-01.

Agent log — 4 October 2026:
- [x] Existing protected profile displays name, assigned grade and active subjects; academic fields, saved guardian phone and consent are labelled tutor managed. No profile UI was rebuilt.
- [x] Isolated feature tests verify authenticated ownership, unauthenticated rejection and accepted/unexpired subject filtering. Playwright MCP verified the populated profile at 390px/1440px without horizontal overflow or page runtime errors; unauthenticated `/profile` redirects to `/login`.
- Browser checks used mocked API fixtures with service workers blocked. Real-account and installed-PWA/cache acceptance remain verification pending for user testing; no deployment performed.

### US-02 — Update permitted account information
**Status: Done locally — restricted-field rejection completed on 4 October 2026; awaiting user acceptance testing.**

As a student, I want to update permitted account information so that my profile stays accurate.

Acceptance criteria:
- I can edit and save only the agreed student-editable fields.
- Invalid values produce useful validation messages and do not overwrite saved information.
- Server-side checks reject changes to restricted fields and other students' profiles.

Dependencies: US-01; agreement on editable fields.

Agent log — 4 October 2026:
- [x] Reused existing editing/saving, validation, guardian-phone lock and authenticated self-update behavior. Editable fields: name, email, student phone, academic year, guardian name and relationship; guardian phone may be supplied when blank, but students cannot subsequently replace or clear it.
- [x] Completed the missing explicit rejection of student-supplied role, subject/access fields, guardian-report consent, forced-password-change flag and password through the personal-information endpoint. Existing grade rejection remains. Password changes use the separate current-password-validated endpoint. Rejected requests leave all saved personal/account/access data unchanged, including when restricted values are null.
- [x] `StudentProfilePermissionsTest`: 11 tests passed, 234 assertions, using private SQLite in memory; no application database reset. Controller/test PHP syntax checks and `git diff --check` passed.
- [x] Playwright MCP with mocked APIs verified field-level error feedback, successful retry, permitted-only save payload, no document reload or subject refetch, and no page runtime errors. Profile layouts passed at 390px/1440px without horizontal overflow.
- Files: `app/Http/Controllers/UserController.php`, `tests/Feature/StudentProfilePermissionsTest.php`, this story file and the implementation backlog. No frontend source changes, build, dependencies or migrations were needed.
- Remaining verification: user testing with an actual account and installed PWA. No commit, push, deployment or external messages. Stopped after this one implementation work unit; US-03 has not been started.

### US-03 — Change password securely
**Status: Done locally — password input validation hardened and locally verified on 4 October 2026; awaiting user acceptance testing.**

As a student, I want to change my password securely so that I can protect my account.

Acceptance criteria:
- The password-change flow verifies the account holder and validates the new password using the platform's security policy.
- A successful change allows authentication with the new password and rejects the old password.
- Passwords are stored securely and are never exposed in responses or logs.

Dependencies: existing authentication and password policy.

Agent log — 4 October 2026:
- [x] Reviewed and reused the existing authenticated password-change endpoint and profile security form. Current-password verification, minimum eight characters, matching confirmation, secure hashing and clearing the forced-change flag already existed.
- [x] Added string validation for current/new passwords so numeric or array payloads receive field-level validation errors before password checking or saving. Existing password policy remains unchanged.
- [x] `StudentPasswordChangeTest` passed six tests (71 assertions) on private SQLite in memory: wrong current password, missing/short/malformed inputs, confirmation mismatch, hashed storage, response secrecy, forced-change flag, successful new-password login, rejected old-password login, student isolation and unauthenticated rejection. Existing `StudentProfilePermissionsTest` passed 11 tests (234 assertions).
- [x] Controller/new test PHP syntax checks and `git diff --check` passed. Playwright MCP with mocked APIs and service workers blocked verified current-password/length/confirmation feedback, failure/retry, successful field clearing, removal of forced-change notice, no document reload, and 390px/1440px layouts without horizontal overflow or page runtime errors. The first browser run used an incorrect expected error label; corrected the check and reran successfully.
- Code inspection: user serialization hides passwords/hashes; password-change code does not log password values; the exception handler excludes all three password fields from session flashing. No real account password or production data was changed during validation.
- Files: `app/Http/Controllers/UserController.php`, `tests/Feature/StudentPasswordChangeTest.php` and both tracking Markdown files. No frontend source changes, build, dependencies or migrations needed.
- Remaining verification: actual-account and installed-PWA acceptance by the user. No commit, push or deployment. Stopped after US-03; US-04 has not been started.

Manual acceptance test:
1. Log in with a student test account and open My profile → Change password.
2. Try an incorrect current password, a new password shorter than eight characters, and mismatching confirmation separately. Each must show an appropriate error and leave the saved password unchanged.
3. Enter the correct current password and a new password of at least eight characters twice. Expect success without a page reload and all password fields cleared. If a forced-change notice was present, it should disappear.
4. Log out. The old password must fail; the new password must log in successfully.
5. Repeat on mobile/the installed PWA where available. You may use the same flow to restore the test account's original password afterward.

### US-04 — Manage restricted student information
**Status: Partial — existing grade and course-access controls audited on 4 October 2026; authorization hardening and account-status implementation remain.**

As a tutor/admin, I want to update a student's grade, subjects and account status so that access reflects their enrolment.

Acceptance criteria:
- Authorized staff can update the restricted fields for the selected student.
- Invalid grade, subject and status values are rejected.
- Student access follows the saved grade, subject and account restrictions, enforced server-side.

Dependencies: F-02; agreement on staff permissions and account-status behavior.

Implementation audit — 4 October 2026:
- [x] Tutor/admin can assign, change or clear the selected student's grade through `PUT /api/students/{student}/profile`. The endpoint checks staff role, student target and grade existence. Student self-updates reject `grade_id`.
- [x] Existing student-management UI includes profile editing and course-access acceptance/removal. Subjects are managed through grade/subject access records, rather than a subject field in the profile form. This checkbox records code presence only.
- [x] Existing active-course filtering uses the authenticated student, accepted status and unexpired access. Isolated profile tests verify course filtering and ownership.
- [ ] Enforce tutor/admin authorization on all course-access administration endpoints. `LessonAccessController` acceptance, refusal, removal and request-list methods currently have authenticated routes but no staff-role check; a hidden UI control does not enforce authorization.
- [ ] Validate course-access administration payloads and referenced records explicitly. Existing methods use IDs directly; the request normalizer checks positive grade/subject IDs but does not validate their existence.
- [ ] Implement agreed account/login statuses, staff editing, invalid-status rejection and server-side restrictions. No account-status control or login-status check exists in the audited profile/authentication flow. Course statuses (`pending`, `accepted`, `refused`) are separate from account status.
- [ ] Resolve whether changing the assigned profile grade should change existing course access. Current course access is independent and may include multiple grades; changing the profile grade does not revoke those grants.
- [ ] Complete actual-account browser and installed-PWA acceptance testing.

Agent log — 4 October 2026 (audit only):
- Reviewed `UserController`, `LessonAccessController`, `LessonController`, `Api/AuthController`, API middleware/routes, `StudentManagement.vue`, `StudentAccessEditor.vue` and existing profile permission tests. Preserved pending local code changes; no application implementation or migrations added.
- Validation run: `docker compose exec -T -w /var/www/html/adityaclasses app php artisan test --filter=StudentProfilePermissionsTest` passed 11 tests (234 assertions), using the test's private SQLite in-memory connection; no application database reset. These tests establish existing profile permissions and active-subject filtering, not safe course-access administration or account-status behavior.
- Documentation whitespace validation: `git diff --check` passed. No build, browser, installed-PWA or production checks performed for this audit.
- Files changed for this audit: this story file and `ADDITIONAL_FEATURES_IMPLEMENTATION_BACKLOG.md` only.
- Next implementation candidate within US-04: staff-only course-access authorization and input-validation hardening. Account-status work requires allowed statuses and effects on new logins/existing sessions to be specified first. No next story started, commit, push or deployment.

Manual acceptance test for existing controls (local test accounts only):
1. Open `http://localhost:8080/students` as a tutor/admin. Select a test student, open their profile editor, change Class and save. Reopen the editor and confirm the value persists and another student's profile is unchanged. Repeat clearing/reassigning Class; preserve the test account's original values for restoration.
2. Sign in as that student and open My profile. Confirm the new class appears read-only. A student request to `PUT /api/updateUserInfo` containing `grade_id` must return 422 without changing stored information; a student request to `PUT /api/students/{student}/profile` must return 403.
3. As staff, submit an otherwise valid profile update with a nonexistent `grade_id`. Expect a 422 field error and no saved changes. This invalid-grade scenario remains manual verification pending in this audit.
4. With a disposable pending course request, use the selected student's Course Access Details to Accept Request. Sign in as the student and check My courses for the matching grade/subject. As staff, remove only that disposable access record and confirm it disappears after the student refreshes. Ensure another student's access is unchanged. Acceptance/removal is code-inspected only in this audit.
5. Security regression to verify after hardening: using a student session, acceptance/refusal/bulk/removal/request-list administration requests must return 403 and leave access unchanged. Current code lacks these checks; do not treat this as a passing criterion or test against live records.
6. Repeat the grade/profile and course navigation checks on phone, desktop and an installed PWA. Account-status tests will be added once the business rules and implementation exist. No account-status acceptance is claimed now.

### US-05 — Resume video playback
As a student, I want videos to resume where I stopped so that I can continue learning without searching for my place.

Acceptance criteria:
- Playback progress stores the student's last playback position for each video.
- Reopening that video resumes from the saved position within the agreed precision.
- Progress remains separate for different students and videos.
- Save or playback failures do not silently present unsaved progress as saved.

Dependencies: existing video player; agreement on precision and save timing.

### US-06 — View video progress and recently viewed content
As a student, I want to see video progress and recent lessons so that I can find and continue my learning.

Acceptance criteria:
- Videos show not started, in progress or completed status and completion percentage.
- The last viewed video and recently viewed videos/lessons are available in recency order.
- Status and percentage follow agreed completion and viewing rules.

Dependencies: US-05; completion rules.

### US-07 — Complete lessons and chapters
As a student, I want lessons and chapters to be marked completed according to clear rules so that my learning progress is accurate.

Acceptance criteria:
- Lesson and chapter completion derives from the agreed completion rules.
- Completion records identify the student and relevant lesson/chapter.
- Repeated progress updates do not create duplicate completion events or inflate totals.

Dependencies: US-06; lesson/chapter completion rules.

### US-08 — Record learning activity
As a student, I want my learning activity recorded so that dashboards and reports reflect my work over time.

Acceptance criteria:
- Records include last login, recent activity and active learning days.
- Completed videos, lessons, chapters, exercises and assessments contribute to the relevant activity history.
- History supports student-specific and period-specific summaries without counting the same event twice.

Dependencies: F-01; completion and submission events from the relevant modules; definition of an active learning day.

## 2. Online Exercises, Question Bank & Automatic Correction

### US-09 — Manage a reusable question bank
**Status: Partial — visual authoring-form POC only, 17 October 2026.**

Agent log — 17 October 2026, preview-door correction:
- Tutor tools now remain visible at their existing width. The arrow slides a locally clipped preview door over the question editor only; placement and tutor navigation remain available. Closing restores the unchanged draft, and preview-only confirmation closes the door for the next question while retaining placement. Hidden editor controls are inert; reduced-motion preferences are respected.
- Build/local asset sync and whitespace checks pass. Playwright MCP: 59 assertions at 1440px/390px/320px pass, plus reduced-motion, Escape/focus and rapid-toggle checks. Desktop/mobile screenshots inspected. No page runtime errors, horizontal document overflow or API writes. Synthetic tutor fixtures used; actual-account/design and installed-PWA acceptance remain pending. No backend, migration, dependency or deployment changes.
- This user-requested correction supersedes the sidebar replacement behavior recorded in the preceding iteration below. Full question-bank functionality remains incomplete.

Agent log — 17 October 2026, sidebar preview iteration:
- Added a centered arrow tab on the tool sidebar while Questions is active. It toggles the left panel between tutor tools and a live question preview; the editor and topic placement remain interactive. Removed the question review modal. The Review question action opens the same inline panel after validation.
- Confirmation remains preview-only, clears question-specific content and retains placement; the live panel stays open for the next question with confirmation disabled until the draft is complete. Returning to tools preserves the draft. The arrow and preview controls fit mobile widths, with bounded internal preview scrolling and visible confirmation actions.
- Build/local asset sync and whitespace checks pass. Playwright MCP: 63 assertions pass at 1440px/390px/320px, including centered/in-bounds arrow, inline placement, no modal, live updates, toggling, draft retention, confirmation, disabled incomplete state, existing tool navigation and no horizontal document overflow. No page runtime errors or API writes occurred. Screenshots inspected.
- Synthetic tutor/browser fixtures were used with service workers unavailable. Actual-account/design and installed-PWA acceptance remain pending. No backend, database, dependency or deployment changes.

Agent log — 17 October 2026, compact authoring iteration:
- Moved the question editor to the top/main column and course/topic placement to a collapsible side panel. Placement starts collapsed on narrower screens. Removed the extra tutor-page title only for this tool; other tutor tools keep their existing title.
- Replaced the always-visible preview with Review question → Back to editing or Confirm & next question. Confirmation clears question-specific content, focuses the next prompt, retains grade/subject/topics/type/difficulty, and increments an in-memory preview counter. Nothing is persisted or sent to a question API.
- Build/local sync and whitespace checks pass. Playwright MCP: 65 assertions pass at 1440px/390px/320px, with no page runtime errors, question API writes, horizontal document overflow or clipped placement controls. Review action is visible without scrolling; review/cancel/confirm, empty-input guards, preserved placement and Escape behavior are checked.
- Before/after at a 900px viewport height: desktop document height 1794 → 900px, editor top 945 → 216px; 390px mobile height 2720 → 1300px, editor top 1163 → 457px. Screenshots inspected. Browser checks still use synthetic tutor fixtures with service workers unavailable; actual-account/design and installed-PWA acceptance remain pending.
- Scope remains visual POC only. No backend, migration, dependency, deployment or real saved question.

Agent log — 17 October 2026:
- User redirected the task to a visual POC only. Added a lazy-loaded Questions tool in the existing tutor panel with sample grade/subject selection, searchable multi-topic checkboxes/chips, editable question/answer mockups, mathematical-symbol shortcuts and a live preview.
- Uses sample data and temporary component state only. The create action explicitly simulates creation; it makes no question API request and stores nothing. Backend scaffolding started before clarification was removed; no migration was applied and no backend route/model/controller change remains.
- Validation: frontend build/local Docker asset synchronization and whitespace checks pass. Playwright MCP checks desktop 1440px and mobile 390px/320px: 66 interaction assertions, no horizontal overflow and no page runtime errors. Existing tutor tools remain navigable. Screenshots were visually inspected.
- Browser verification uses an isolated tutor fixture with service workers unavailable, so installed-PWA acceptance and actual-account user acceptance remain verification pending. Real question creation/editing, persistence, authorization endpoints, exercises and correction are not implemented by this POC.
- Files: `resources/js/components/tutor/QuestionBuilderPreview.vue`, `resources/js/components/TutorSpace.vue`, generated build assets and tracking notes. No dependencies, database changes or deployment.

As a tutor, I want one structured question bank so that questions can be reused across exercises and assessments.

Acceptance criteria:
- Questions can be created and edited with grade, subject, chapter, lesson/topic and question type classifications.
- Difficulty can be recorded where applicable.
- The bank supports multiple choice, true/false, short answer, numerical, mathematical and written/open-ended questions.
- Existing submitted answers and results remain meaningful when source questions are edited.

Dependencies: F-01 and F-02; content classification and historical-record strategy.

### US-10 — Manage exercises and homework
As a tutor, I want a dedicated Exercises / Homework section so that I can create, edit and manage student work.

Acceptance criteria:
- Authorized tutors can create and edit exercises using questions from the shared bank.
- Exercises retain their relevant grade, subject and content context.
- Students can access only exercises permitted by their grade and subject restrictions.

Dependencies: US-09 and F-02; agreement on how exercises become available to students.

### US-11 — Answer exercises and save work in progress
As a student, I want to answer exercises on the website and save my work so that I can continue before submission.

Acceptance criteria:
- Supported question types provide suitable answer controls on the website.
- Answers are saved while I work and restored when I reopen the exercise.
- Loading, saving and failure states are visible, with a way to retry failed saves.
- Another student cannot read or change my saved answers.

Dependencies: US-10 and F-02; draft-save behavior.

### US-12 — Submit exercises and view history
As a student, I want to submit completed work and view past exercises so that I can track my practice and results.

Acceptance criteria:
- Submission stores the submitted answers and their exercise/attempt association.
- Repeated submission requests do not unintentionally create duplicate submissions.
- Exercise history shows submitted work and available results belonging to the student.
- Questions awaiting correction are distinguishable from questions with final results.

Dependencies: US-11 and F-01; resubmission/attempt policy.

### US-13 — Automatically correct supported answers
As a student, I want supported answers corrected automatically so that I receive consistent results promptly.

Acceptance criteria:
- Objective/predefined answers are evaluated using their configured correction rules.
- Scores and correct/incorrect outcomes are stored for future analytics.
- Results are shown at the agreed feedback stage.
- Answers without reliable predefined correction are routed to the agreed correction workflow rather than treated as automatically correctable.

Dependencies: US-09 and US-12; scoring, matching, numerical tolerance and feedback rules.

### US-14 — Enter mathematical answers
As a student, I want accessible mathematical input so that I can enter mathematical answers easily.

Acceptance criteria:
- Input supports powers, fractions, square roots, brackets, pi, multiplication/division, algebraic expressions and common equations/symbols.
- Entered expressions remain intact through saving, reopening and submission.
- Mathematical input is usable on mobile and desktop without horizontal overflow.
- Answers reach correction in a representation supported by the selected correction method.

Dependencies: US-11; agreement on supported mathematical notation and correction scope.

### US-15 — Upload handwritten working
As a student, I want to upload a photo or image of working where appropriate so that I can answer questions that are difficult to enter digitally.

Acceptance criteria:
- Eligible questions allow an image/photo to be attached to the student's answer.
- Uploads validate agreed file types and size limits and display useful error states.
- Saved attachments remain associated with the correct answer and are accessible only to authorized users.
- Uploading working does not by itself imply that image-based automatic or AI correction is available.

Dependencies: US-11 and F-02; eligible questions, upload limits and review workflow.

## 3. Automated Assessments & Lower-Grade Self-Assessment

### US-16 — Trigger a test after three completed chapters
As a student, I want an assessment after completing three eligible chapters so that I can check my understanding.

Acceptance criteria:
- A trigger detects three eligible completed chapters belonging to the same grade and subject.
- The generated assessment draws from those chapters and is linked to the student.
- Repeated processing of the same qualifying completion group does not create unintended duplicate tests.
- Insufficient eligible content is handled explicitly according to the agreed policy.

Dependencies: US-07 and US-17; chapter eligibility, grouping and repeat-trigger policy.

### US-17 — Configure and generate randomized assessments
As a tutor, I want configurable randomized assessments from the shared question bank so that students receive suitable tests.

Acceptance criteria:
- The assessment question count is configurable by the agreed authorized role.
- Selection uses questions in the relevant grade, subject and chapter scope.
- Selection avoids unnecessary repeated questions within a test and permits different combinations when sufficient questions exist.
- A generated test retains its selected questions for the student's attempt/history.

Dependencies: US-09 and F-01; question availability and selection rules.

### US-18 — Take a lower-grade self-assessment
As a student, I want to assess myself on eligible lower-grade material in the same subject so that I can identify gaps in foundational knowledge.

Acceptance criteria:
- The platform detects my current grade and offers only eligible lower grades.
- Grade/subject restrictions are enforced server-side, including the permitted lower-grade exception.
- Questions come from the selected lower-grade content in the same subject.
- Test length is configurable and results are retained in my assessment history.

Dependencies: US-17 and F-02; lower-grade eligibility and test-length controls.

### US-19 — Complete assessments and view results
As a student, I want stored assessment attempts and results so that I can review my performance and revision needs.

Acceptance criteria:
- Each test and attempt is stored in the student's history.
- Results include overall score and chapter/topic performance using the shared correction system.
- Results feed the central analytics system, including lower-grade knowledge-gap analysis.
- Access to attempts and results is restricted to the student and authorized staff.

Dependencies: US-13, US-17, F-01 and F-02; assessment attempt and scoring policies.

## 4. Student Dashboard, Statistics & Learning Analytics

### US-20 — View learning progress on the dashboard
As a student, I want a dashboard of my learning progress so that I know where to continue.

Acceptance criteria:
- The dashboard shows current grade/subjects, recent lessons and current lesson/chapter in progress.
- It shows course/content completion percentage and recent learning activity.
- It shows pending or recommended assessments where available.
- Loading, empty and error states remain usable on mobile and desktop.

Dependencies: US-06, US-07, US-08, US-16 and F-03; completion denominator and recommendation rules.

### US-21 — View assessment statistics
As a student, I want a summary of my assessment performance so that I can see how I am progressing.

Acceptance criteria:
- The dashboard shows latest results, average score and completed assessment count.
- Performance trends are shown over time using shared analytics.
- Missing results are distinguished from genuine zero scores and sufficient-history requirements are respected.

Dependencies: US-19 and F-03; averages, attempt selection and trend definitions.

### US-22 — Identify strengths, weaknesses and revision needs
As a student, I want topic-level feedback so that I can focus revision on areas that need attention.

Acceptance criteria:
- Analytics calculate performance by subject, chapter and topic.
- Agreed rules identify strong/weak areas, repeated mistakes, repeated low performance and improving/declining performance.
- Lower-grade self-assessment results contribute to identifying foundational knowledge gaps.
- The dashboard displays strongest/weakest topics and revision recommendations supported by available results.

Dependencies: US-19, US-24 and F-03; thresholds, minimum evidence and recommendation rules.

### US-23 — Review student learning as a tutor
As a tutor, I want student learning summaries and filters so that I can identify students needing additional attention.

Acceptance criteria:
- Authorized tutors can view student progress and performance summaries.
- The student view supports filtering by grade or subject.
- Students needing attention are identified using agreed analytics rules.
- Tutor summaries use the same calculations as student dashboards and reports.

Dependencies: US-20, US-21, US-22, F-02 and F-03; staff access scope.

### US-24 — Manage statistics rules
As an authorized tutor/admin, I want to manage statistics rules so that student statistics reflect the agreed educational criteria.

Acceptance criteria:
- Rules can be created, edited, reordered, hidden and archived.
- Rules influence calculations/presentation according to an agreed distinction between hiding and archiving.
- Applicable statistics are reused consistently in dashboards, reports and parent communications.
- Unauthorized users cannot change rules.

Dependencies: F-02 and F-03; rule capabilities and historical recalculation policy.

## 5. AI-Assisted Learning

### US-25 — Generate practice questions with AI
As a tutor, I want AI to generate additional questions and variations so that I can expand practice material efficiently.

Acceptance criteria:
- Generation accepts existing questions or lesson/topic/chapter context with grade and subject.
- Generated questions can vary difficulty where appropriate.
- Accepted questions are classified in the shared bank and reusable by exercises and assessments.
- Provider failure is reported without claiming questions were successfully generated.

Dependencies: US-09; AI provider/account, usage authorization and agreement on review before reuse.

### US-26 — Assist correction of open-ended answers with AI
As a tutor, I want AI-assisted correction for answers without reliable predefined rules so that I can evaluate varied written responses.

Acceptance criteria:
- Eligible written/open-ended answers can receive a correct/partially correct/incorrect indication, suggested score and short feedback.
- Correction can work without one exact predefined answer, using the available question context and agreed guidance.
- AI output is identified as assistive; failures or unavailable output do not become fabricated grades.
- Handling of ambiguous output and final score approval follows the agreed review policy.

Dependencies: US-12 or US-19; AI provider/account, usage authorization, grading guidance and finalization policy.

## 6. Parent Communication, Progress Reports & WhatsApp

### US-27 — Manage parent/guardian contacts
As an authorized tutor/admin, I want parent/guardian WhatsApp details linked to students so that progress updates reach the correct person.

Acceptance criteria:
- Parent/guardian contact details are linked to the intended student.
- Communication/consent status is stored where required.
- Contact information is protected and changes require authorized access.
- Contact validation and student-recipient mapping are checked before sending.

Dependencies: F-02; consent, contact validation and multiple-guardian policy.

### US-28 — Generate progress reports
As a tutor, I want student progress reports from central analytics so that I can communicate learning outcomes consistently.

Acceptance criteria:
- Reports include content progress, completed chapters, recent exercise/test scores and average performance.
- Reports include trends, strong/weak areas, revision recommendations and recent learning activity.
- Attendance is included where available; unavailable attendance is not reported as zero attendance.
- Reports use shared analytics for the selected student and agreed reporting period.

Dependencies: US-08, US-19, US-22 and F-03; reporting period/format and optional attendance source.

### US-29 — Configure WhatsApp integration and templates
As an authorized tutor/admin, I want the official WhatsApp Business API and standard templates configured so that I can send approved parent summaries.

Acceptance criteria:
- Integration uses an authorized official WhatsApp Business API connection.
- A standard message template supports the agreed statistics and recipient mapping.
- Setup accounts for the provider's required business/template approvals.
- Credentials are protected and configuration is restricted to authorized staff.

Dependencies: external account/access, provider selection and required approvals. External charges are outside the quotation's development price.

### US-30 — Select statistics and preview parent messages
As a tutor, I want to select relevant statistics and preview a WhatsApp summary so that I can check it before sending.

Acceptance criteria:
- I can select supported statistics from central analytics for the intended student.
- Preview displays the intended guardian/recipient and rendered message content.
- The rendered message follows the configured template format.
- Previewing does not send a message.

Dependencies: US-27, US-28 and US-29.

### US-31 — Send parent summaries and track delivery
As an authorized tutor, I want to send the reviewed summary to the correct guardian and see its delivery state so that I can track parent communication.

Acceptance criteria:
- Sending validates authorization, recipient mapping and applicable communication/consent status.
- The reviewed summary is sent through the official integration to the intended guardian.
- Available sent/delivered/failed states are retained against the correct student and recipient.
- Failures are visible, and retry behavior avoids unintended duplicate sends.

Dependencies: US-30; provider access, sending authorization and retry policy. Real sending requires explicit approval.

### US-32 — Include a report with a parent message, where supported
As a tutor, I want to include a report document or secure report link when supported so that parents can review more detail.

Acceptance criteria:
- Supported delivery options allow the intended student's report to be included in the message.
- Secure links/documents do not expose another student's information.
- Unsupported attachment/link options are made clear before sending.

Dependencies: US-28 and US-31; optional delivery format and guardian-access/link-expiry rules.

## 7. Annual Student Summary & Controlled Data Management

### US-33 — Calculate annual student statistics
As a tutor, I want annual learning and performance statistics so that I can review the student's year.

Acceptance criteria:
- Statistics cover the selected student's agreed annual period.
- The summary includes learning progress and annual assessment/performance results.
- Attendance totals are included where attendance data exists.
- Calculations reuse the central analytics system.

Dependencies: US-08, US-19 and F-03; annual-period definition and optional attendance source.

### US-34 — Generate, review and retain an annual summary
As a tutor, I want to review a standard annual summary before finalizing it so that an accurate record is retained for future reference.

Acceptance criteria:
- A standard annual summary can be generated in the agreed PDF or DOCX format.
- The tutor can review it before finalization.
- The final summary is retained and can be retrieved by authorized users.
- Retained summaries remain available after any permitted cleanup of detailed annual records.

Dependencies: US-33 and F-02; document format/template, finalization and retention policy.

### US-35 — Confirm deletion of completed-year details
As an authorized tutor, I want controlled deletion of completed-year detailed records so that I can manage retained data while preserving the final annual summary.

Acceptance criteria:
- Only agreed detailed records for an eligible completed year can be selected for deletion.
- Confirmation clearly identifies the student, year and records affected before deletion.
- The final annual summary and required retained history are preserved according to the agreed policy.
- An audit record retains who performed the deletion and when.

Dependencies: US-34; explicit retention/deletion scope and authorization. Implementation must not delete real data as validation.

## Shared Foundation — Included Once

These are enabling requirements and delivery tasks rather than additional priced feature modules.

### F-01 — Shared historical data
Provide reusable student activity history, question classifications, exercise/assessment attempts, scores, topic performance, generated reports and communication history. Relationships must preserve the correct student/content context and historical results needed by reports and analytics.

### F-02 — Permissions and data protection
Enforce student ownership and tutor/admin permissions server-side. Restrict content and assessments by grade and subject, with explicit lower-grade self-assessment eligibility. Protect student results, parent contacts and report access. UI restrictions alone do not satisfy this requirement.

### F-03 — Reusable analytics
Use one calculation system for student dashboards, tutor summaries, automated-assessment feedback, lower-grade feedback, progress reports, annual summaries and WhatsApp statistics. Agree definitions for reporting periods, averages, trends, completion and evidence thresholds before relying on those measures.

### F-04 — Workflow and regression validation
Validate student and tutor/admin workflows, video resume/completion, exercise creation/saving/submission, mathematical input, automatic correction, AI correction, generated assessments, lower-grade assessments, statistics/weakness detection, dashboards, reports and parent-recipient mapping/sending. Verify existing platform behavior remains functional. Mocked provider checks do not establish real AI or WhatsApp integration behavior.

Apply the repository's mobile, authorization and PWA requirements to relevant stories. Use browser verification for frontend changes; verify installed PWA behavior separately when explicitly required. Record actual executed checks and leave unavailable external/runtime checks visible as verification pending.

### F-05 — Deployment and release verification
Deploy the completed functionality through the approved release process and verify existing and new workflows in the target environment. Production deployment and migrations require explicit authorization; creating this document authorizes neither action.

## Decisions and External Dependencies to Resolve

| Area | Clarification required before affected implementation |
|---|---|
| Profile | Student-editable fields, staff permission boundaries, account-status effects. |
| Video progress | Save timing, resume precision, viewing-percentage calculation, completion thresholds and lesson/chapter aggregation rules. |
| Activity | What qualifies as an active learning day and which timezone governs day boundaries. |
| Exercises | Student availability/assignment, draft saving, permitted attempts/resubmissions and feedback timing. |
| Correction | Marks/weighting, short-answer matching, numerical tolerance, mathematical equivalence and manual correction workflow. |
| Uploads | Eligible questions, image types/limits and who reviews handwritten working. Image OCR/AI marking is not expressly promised. |
| Automatic tests | Eligible chapters, how groups of three are formed, chapter reuse, question mix and insufficient-bank behavior. |
| Self-assessment | Eligible lower grades/subjects and who configures test length. |
| Analytics | Average/trend definitions, attempt inclusion, strength/weakness thresholds, minimum evidence, revision rules and rule-edit effects on historical reports. |
| AI | Provider/account, costs, permitted data sharing, generation-review policy, grading guidance and whether tutor approval is required before final scores. |
| Parents/WhatsApp | Guardian multiplicity, contact validation, consent rules, provider/business access, approved templates, sending/retry policy and optional secure-report access. |
| Reports/attendance | Progress-report period/format, attendance source if available, annual-period boundaries and annual PDF or DOCX selection. |
| Retention/deletion | Records eligible for deletion, completed-year eligibility, preservation of required history, summary retention and deletion permissions. |
| Release | Target environment, external verification access and production approval. |

These questions are not additional features. They identify details the quotation does not fully specify; no policy choice is silently made here.

## Scope and Commercial Reference

| Quotation module | Stories | Quoted development price |
|---|---|---:|
| 1. Student Profile, Video Progress & Learning Activity | US-01–US-08 | Rs 12,000 |
| 2. Online Exercises, Question Bank & Automatic Correction | US-09–US-15 | Rs 24,000 |
| 3. Automated Assessments & Lower-Grade Self-Assessment | US-16–US-19 | Rs 18,000 |
| 4. Student Dashboard, Statistics & Learning Analytics | US-20–US-24 | Rs 12,000 |
| 5. AI-Assisted Learning | US-25–US-26 | Rs 8,000 |
| 6. Parent Communication, Progress Reports & WhatsApp | US-27–US-32 | Rs 22,000 |
| 7. Annual Student Summary & Controlled Data Management | US-33–US-35 | Rs 9,000 |
| Shared foundation | F-01–F-05 | Included above |
| **Total** | **35 user stories + 5 shared requirements** | **Rs 105,000** |

The quotation estimates approximately 8–12 weeks, subject to workflow validation, available question/content quality and third-party approvals. This document does not provide a new estimate or per-story pricing.

AI usage, WhatsApp/provider charges, other third-party subscriptions/hosting, approval waiting time, bulk educational content creation/manual entry and unlisted major features remain outside the quoted scope. Provider cost examples in the quotation have not been independently checked here.

## Document Validation

- Source reviewed: all seven feature modules, shared foundation, testing/deployment, scope notes and exclusions in the quotation.
- Story coverage: 35 uniquely identified stories with acceptance criteria and dependencies, plus five shared requirements.
- Application tests, browser checks, migrations and deployment: not applicable to this documentation-only task; none performed.
- Remaining review: confirm the listed business rules and external dependencies before implementing affected stories.
