# Review batches for 11 November 2026

Branch: `review/local-batches-20261110`.

These commits prepare review and incremental deployment; nothing has been deployed. The branch starts at `6d528c8` from `feat/question-review-workflow`, not the production registration branch. Do not deploy this branch wholesale or treat its Git ancestry as proof of the live server's state.

## Commit inventory

| Commit | Batch | Dependencies / deployment decision |
| --- | --- | --- |
| `ab9971e` | Bring the existing registration release into the review baseline, preserving the question-review work | Integration prerequisite, not a new registration launch. Registration source comes from `origin/release/registration-only-20261104` at `ecd395f`; shared routes/sidebar were integrated into the review baseline. Do not reapply already completed production registration migrations. |
| `c7f1cd4` | Group Tutor Space into Students, Academics, Communication and Website Content; Registrations opens first; priority review badges; create packages from Packages | Requires registration APIs/components **and** the earlier question-review/correction foundation. Red hides yellow while important work remains. No new migrations in this batch. |
| `d017bb9` | Per-account, per-video completion for students/tutors/admins with course access | Requires the earlier lesson-progress, video-source and learning-activity foundation from the review branch. Uses the signed-in user only; never another student's progress. Lesson/answer completion stay separate. No new migrations in this batch. |
| `66f2a6b` | Service-worker cold-cache request fix | The most isolated candidate for an initial release. Changes only `public/sw.js` and worker tests; retains registration privacy safeguards. Fixes the reproduced cache-only fetch failure, not every possible Vimeo playback problem. Verify the target worker first. No frontend build or database migration required for this commit alone. |
| `a6ea333` | Bounded localhost playback diagnostics | Optional local troubleshooting. Requires the completion player source immediately before it. Client is disabled on production hostnames; server accepts diagnostics only in local environment with a loopback Host. Keep this out of the initial production rollout unless there is an explicit reason to include it. |

Each frontend-changing commit includes its matching Vite manifest and generated assets. The worker-only commit retains the preceding frontend build unchanged.

## Proposed incremental order

1. Read the approved hosting deployment runbook and inspect the actual target commit, schema, public-root mapping and runtime. No production state was checked during this packaging task.
2. Consider the worker fix `66f2a6b` separately against the registration release baseline. Review its two-file diff and verify its existing privacy behavior matches the target. Take the normal backups before any release. Check normal reload, Resume, offline behavior and installed-PWA behavior before proceeding.
3. Review the older progress/question-review prerequisites already present before `6d528c8`. The registration release does not contain all of those source changes. Decide their release/migration order explicitly; do not attempt to solve missing dependencies by blindly cherry-picking today's UI or completion controller.
4. Once those foundations and registration are available, release Tutor Space and video completion as separate work units, with a smoke check after each.
5. Keep the diagnostics as local tooling while investigating the user's unresolved playback failure. The analytics requests shown as blocked in the browser do not by themselves prove a media-stream failure.

If transplanting a batch onto a different source baseline, resolve and review its prerequisites, then rebuild on that resulting target tree. The committed assets match this review branch's exact tree; they are not safe to copy over a different frontend source tree indiscriminately.

For a frontend release, publish new hashed assets first and the manifest last. Retain old hashed files during rollout so open tabs can finish loading their chunks; do not use a broad delete/sync that removes them. Roll back matching source and manifest together if the smoke check fails. Never overwrite the recipient's `.env` with `.env.example`. Any production migrations must use the approved `/admin/migrate` process, never a reset or `migrate:fresh`.

## Validation completed during packaging

- 88 selected PHP tests, 1,025 assertions passed: registration submission/management, question review, manual completion, local diagnostics and learning activity. Database tests used their existing private SQLite helpers; mail was faked. These checks ran against the primary local source, which matches the packaged final source except an irrelevant pre-existing whitespace-only registration CSS difference.
- 11 Node tests passed: five review-save queue tests and six service-worker tests. The six worker tests also passed in the review checkout.
- Registration integration, Tutor Space, video completion and diagnostics snapshots each built successfully. Existing Sass/import warnings remain.
- Every intermediate commit's manifest dependencies and referenced asset files were checked against that commit's Git tree. Source parity, credential-pattern scan and `git diff --check` were checked before push.
- Prior local Playwright checks covered Tutor Space/navigation/count states/mobile widths; independent tutor/student completion behavior; real Vimeo resume after reload; the cold-cache worker reproduction; and real local diagnostic delivery. Packaging did not change those final source implementations. Browser fixtures and real playback were reported separately in the local backlog.

Remaining acceptance: the user's actual intermittent playback failure, real installed-PWA behavior, human question-content acceptance, and all live release checks. Passing local tests is not a guarantee of production behavior.

## Workspace boundaries

The review checkout is `C:\Projects\AdityaBatches-20261110`. The original development checkout and its local testing build were left intact. Its uncommitted status is expected: delivery commits are on this isolated review branch.

Excluded from delivery: real `.env` files, tokens, local logs/backups/databases, screenshots, temporary verification accounts/helpers, local housekeeping notes and the intentionally ignored working backlog. No merge, force push, deployment, production migration or real email/WhatsApp send was performed.
