# Local cleanup - 9 November 2026

Archived 445 files (51 MB) outside the project, without deleting their contents:

`C:\Users\muzaf\Documents\Codex\adityaclasses-local-archive-20261109`

| Original location | Archive location |
| --- | --- |
| `.playwright-mcp/` | `browser-checks/` |
| `deliverables/student-registration-poc/` | `student-registration-poc/` |
| `deliverables/student-registration-poc.zip` | `student-registration-poc.zip` |
| `deliverables/quotation-work/` | `quotation-work/` |

The archive's `manifest.json` records original/archived paths, sizes and SHA-256
hashes. Every moved file was checked against its original hash. To restore an
item, copy it back to its original location after checking for newer files there.
Browser tools may create a new `.playwright-mcp/` folder for future checks.

The original tuition/timetable JPEGs and supplied registration PDFs are preserved
under the archived `student-registration-poc/Information/` directory.

## Kept in the project

- `public/registration-poc/`: despite its historical name, these JavaScript/CSS
  and sharing-image assets are used by the actual registration page.
- Registration controllers, views, email, configuration, models, migrations and
  tests; the compiled build and service worker.
- Client quotation DOCX files, project documentation and backlog.
- Question-review/visual-authoring source and the separate release worktree.
- `.env`, Git history, editor settings, dependencies, storage/uploads and database
  backups.

No code behaviour, database, production setting, commit, push or deployment was
changed as part of this cleanup.
