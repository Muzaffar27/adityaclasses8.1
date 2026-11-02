# PDF extraction, review and manual correction

## Responsibilities

| Area | Entry point | Responsibility |
| --- | --- | --- |
| PDF sources | LessonQuestionBankService | Track lesson uploads, claim bounded extraction jobs, validate result revisions and execution retries. |
| PDF extraction | scripts/questions/app-worker.mjs | Run the Node/Poppler extractor outside HTTP requests, then import private results. |
| Question review | QuestionReviewService and StarterQuestionReviewService | Present current source-bound questions, select the starter bank and validate explicit approvals. |
| Manual corrections | QuestionCorrectionService | Save flags, validate source integrity and store immutable edits with explicit approvals. |

## Tutor workflow

1. Review questions in Starter bank or More variety. Compare against the original PDF.
2. Flag incorrect questions. They leave the approval queue and appear in Needs correction, ready for manual editing.
3. Correct text or maths, check the preview and choose Save & approve. The new version and its approval commit in one transaction; failure rolls back both. Original diagram attachments are retained; replacing a diagram requires a source PDF change.
4. Save only remains available for unfinished edits; these stay unapproved and can later use Approve correction or Save & approve. Approval removes the item from Open corrections, updates counts and focuses the next item. Resolved corrections remain in history.
5. Rejected regions and skipped pages retain explicit Retry extraction. This reprocesses the entire source PDF and may return the same result. It never approves questions.

## Original PDF comparison

Compare original PDF opens one docked side panel on desktop, leaving the correction editor usable beside it. Close, Escape or the same question's Close original PDF button dismisses the panel without discarding the draft. Keyboard focus returns to the compare control. Other questions can open their own source in the same panel; no duplicate PDF panels are created.

On smaller screens the PDF uses a compact lower panel, with the selected editor brought into view above it. The document scrolls independently and Open full PDF remains available. Loading, retry and Close remain accessible; closing while a request or worker is starting cannot reopen a stale source.

## Starter review limit

Starter bank covers five reviewed slots per grade/subject/topic. Approved questions, flagged questions and saved correction versions awaiting approval all occupy slots. Flags never count as approved and are not automatically replaced by more unreviewed candidates. Fix the existing Needs correction entries to reach the five-approved goal. The progress bar counts approvals only. More variety explicitly opens the remaining unreviewed bank.

## Editing words and maths

Simple extracted questions now open in Text & maths: one normal text box for words, numbers, spaces and maths symbols. Split extracted letters in the demonstrated 32/a/n/d/36 expression are prefilled as 32 and 36. The original extraction is not rewritten by opening the editor. Symbol buttons insert at the text cursor; the preview shows exactly what will be saved.

Questions with fractions, powers or roots initially retain Formula layout. Text & maths can show an editable text representation with grouping, denominators and root indices preserved; this is literal writing rather than an equation parser. Formula layout remains available for stacked notation. Switching views keeps their drafts; the selected preview determines what is saved. The old Advanced editor has been removed.

Save & approve is the primary action for the selected preview. Save only creates an unapproved version. Original content and diagrams remain preserved.

## Fast PDF review

Completed or already-empty PDFs advance automatically within the current selection. Flags hide the affected card immediately, and approval moves to the next PDF while saving continues. Only an explicit approval click queues approval, with the exact displayed question IDs.

The browser serializes saves so flags finish before the following approval uses their acknowledged source snapshot. The queue is shared across review tabs, bound to the current authenticated session and journaled in sessionStorage for same-tab refresh recovery. A replayed approval is only confirmed successful when those exact IDs are already approved; conflicts never adopt a newer snapshot automatically. Failed saves remain visible with Retry save and Review again. Review again reloads the affected PDF for a fresh explicit decision.

Keep the tab open until Saving clears. Leaving with outstanding or failed changes triggers the browser's unsaved-change warning. This is a browser save queue, not scheduled PDF extraction; no midnight automation is involved. Counts refresh after the queue settles to avoid a bank-wide refresh for every rapid click. Manual corrections use the explicit Save & approve action in Needs correction, or optional Save only.

## Background extraction

Use the existing local helper scripts/start-question-bank-worker.ps1, or run one local pass:

    node scripts/questions/app-worker.mjs --limit=5

The normal worker handles lesson uploads and tutor-requested extraction retries. Source execution failures retain bounded retries (maximum three attempts), row locks, revision checks and the private bank-file lock. A complete manifest preserves unrelated content; only claimed sources are processed. Do not run overlapping workers. Successful result files are removed after acknowledgement. Investigate stale structured/bank.lock only after confirming no worker remains active.

The --native option uses PHP CLI directly instead of Docker. Production CLI access requires QUESTION_BANK_WORKER_ENABLED=true; this is an access gate, not a schedule. The worker host needs PHP, Node, project dependencies, Poppler and access to private storage. Native production runtime remains unverified. No deployment or server configuration has been performed.

## Removed automation and retained history

The midnight schedule, nightly command, automatic flag-queue command and correction-only worker mode were removed on 2 November 2026. Old correction-only invocations fail explicitly instead of processing unrelated uploads. No cron should invoke the removed commands. Other application schedules must be preserved.

New flags go directly to needs_manual. Existing queued flags remain editable without a data migration. Previously claimed processing records can still be reconciled when the normal worker imports results; this compatibility path never queues another scan. Existing correction records, snapshots, attempts and earlier edited versions are preserved.

No correction schema change is needed for this cleanup. Production migrations, when needed separately, remain restricted to the approved /admin/migrate process. Installed-PWA acceptance, large-bank performance benchmarking and production worker validation remain pending.
