# Hostinger Deployment Runbook

Use this procedure for deployments of Aditya Classes to Hostinger. Follow it in order and stop if any safety check fails.

## Production connection

- SSH host: `46.202.183.184`
- SSH port: `65002`
- SSH user: `u539496203`
- Local deployment key: `C:\Users\muzaf\.ssh\adityaclasses_hostinger`
- Git branch: `main`
- Git remote: `https://github.com/Muzaffar27/adityaclasses8.1.git`
- Laravel application: `/home/u539496203/domains/adityaclasses.mu/adityaclasses`
- Public document root: `/home/u539496203/domains/adityaclasses.mu/public_html`
- Deployment backups: `/home/u539496203/deploy-backups/adityaclasses/<timestamp>/`

Never store a password, private key, `.env` contents, or database credentials in this file or in chat.

## Non-negotiable safety rules

- Never run `migrate:fresh`, `migrate:refresh`, `db:wipe`, destructive rollbacks, or database seeders on production.
- Never run a test suite that uses `RefreshDatabase` against the configured database.
- Use only `php artisan migrate --pretend --force` followed by `php artisan migrate --force` after reviewing the SQL preview.
- Do not modify or replace production `.env`, anything under `config/`, `public_html/.htaccess`, or `public_html/index.php`.
- Do not overwrite `storage/` or uploaded lesson PDFs.
- Do not overwrite the existing `adityaclasses_old` or `public_html_old` directories.
- Do not clear configuration caches unless a deployment specifically requires it and the user approves it.
- Do not deploy from a dirty or diverged production Git worktree.
- Always create and validate a fresh production backup before pulling code.

## 1. Local preflight

1. Confirm the local working tree and branch:

   ```powershell
   git status --short --branch
   git log -3 --oneline --decorate
   ```

2. Build locally:

   ```powershell
   npm run build
   git diff --check
   ```

3. Commit and push the reviewed changes to `origin/main`.
4. Record the current production commit and the intended target commit.
5. Audit the complete production-to-target diff:

   ```powershell
   git diff --name-status <production-commit>..<target-commit>
   git diff --stat <production-commit>..<target-commit>
   git diff --quiet <production-commit>..<target-commit> -- .env config public/.htaccess
   ```

6. Stop if `.env`, `config/`, or `.htaccess` is included.

## 2. Read-only production audit

Use this SSH prefix:

```powershell
ssh -i C:\Users\muzaf\.ssh\adityaclasses_hostinger -p 65002 -o BatchMode=yes -o ConnectTimeout=20 -o IPQoS=none u539496203@46.202.183.184
```

Confirm:

```text
git -C /home/u539496203/domains/adityaclasses.mu/adityaclasses status --short --branch
git -C /home/u539496203/domains/adityaclasses.mu/adityaclasses remote -v
git -C /home/u539496203/domains/adityaclasses.mu/adityaclasses log -1 --oneline --decorate
php /home/u539496203/domains/adityaclasses.mu/adityaclasses/artisan migrate:status --no-ansi
```

Production must be clean and on `main`. Stop if there are uncommitted production changes.

## 3. Record protected-file checksums

Before deployment, record:

```text
sha256sum \
  /home/u539496203/domains/adityaclasses.mu/adityaclasses/.env \
  /home/u539496203/domains/adityaclasses.mu/public_html/.htaccess \
  /home/u539496203/domains/adityaclasses.mu/public_html/index.php
```

Compare these exact hashes after deployment.

## 4. Create a timestamped backup

Create a new directory outside the website root:

```text
/home/u539496203/deploy-backups/adityaclasses/YYYYMMDD-HHMMSS/
```

Create:

- `application.tar.gz` from the complete `adityaclasses` directory. This includes `.env`, `storage`, and uploaded PDFs.
- `public_html.tar.gz` from the complete `public_html` directory.
- `database.sql.gz` using `mysqldump --single-transaction --quick --skip-lock-tables` and Laravel's existing database configuration.

For the database dump, use a temporary private helper that boots Laravel, passes the password to `mysqldump` through the `MYSQL_PWD` process environment, writes a `.part` file, renames it only after success, applies mode `0600`, and removes itself. Never print the password or place it on the command line.

Validate all archives:

```text
gzip -t application.tar.gz
gzip -t public_html.tar.gz
gzip -t database.sql.gz
tar -tzf application.tar.gz adityaclasses/.env
```

Do not continue unless the dump exits successfully and all gzip checks pass.

## 5. Pull application code safely

Fast-forward only:

```text
git -C /home/u539496203/domains/adityaclasses.mu/adityaclasses pull --ff-only origin main
```

Stop if Git requests a merge, reports local changes, or does not land on the reviewed target commit.

Run `composer install --no-dev --optimize-autoloader` only when `composer.json` or `composer.lock` changed. Do not run it unnecessarily.

## 6. Preview and apply migrations

Preview first:

```text
php /home/u539496203/domains/adityaclasses.mu/adityaclasses/artisan migrate --pretend --force --no-ansi
```

Read every SQL statement. Stop if it drops, truncates, renames, or deletes anything that was not explicitly approved.

Apply only after the preview is correct:

```text
php /home/u539496203/domains/adityaclasses.mu/adityaclasses/artisan migrate --force --no-ansi
```

## 7. Publish frontend assets without touching server files

`public_html` is a separate physical directory. Copy only the compiled build directory:

```text
cp -a \
  /home/u539496203/domains/adityaclasses.mu/adityaclasses/public/build/. \
  /home/u539496203/domains/adityaclasses.mu/public_html/build/
```

Do not copy the whole `public/` directory. This protects `.htaccess`, `index.php`, images, PWA files, and server-specific files.

Leaving old hashed build assets is safe; the new `manifest.json` references only the current assets. Avoid deleting them during the deployment unless cleanup is separately reviewed and approved.

## 8. Verify the deployment

1. Confirm the source and live manifests match:

   ```text
   sha256sum \
     /home/u539496203/domains/adityaclasses.mu/adityaclasses/public/build/manifest.json \
     /home/u539496203/domains/adityaclasses.mu/public_html/build/manifest.json
   ```

2. Compare all current build files:

   ```text
   diff -qr \
     /home/u539496203/domains/adityaclasses.mu/adityaclasses/public/build \
     /home/u539496203/domains/adityaclasses.mu/public_html/build
   ```

   Only extra old hashed files in `public_html/build/assets` are acceptable. No current file may be missing or different.

3. Re-run the protected-file checksum command from step 3. All three hashes must match exactly.
4. Confirm Git is clean and synchronized:

   ```text
   git -C /home/u539496203/domains/adityaclasses.mu/adityaclasses status --short --branch
   ```

5. Confirm migrations are marked `Ran`:

   ```text
   php /home/u539496203/domains/adityaclasses.mu/adityaclasses/artisan migrate:status --no-ansi
   ```

6. Make a no-cache HTTPS request to `https://adityaclasses.mu/` and confirm HTTP 200.
7. Confirm the HTML references the new build filenames and that each referenced CSS/JavaScript URL returns HTTP 200.
8. Ask the user to perform an authenticated smoke test of the changed tutor/student workflow and use `Ctrl + Shift + R` if cached assets appear.

## Hostinger SSH reliability

Hostinger may intermittently reset port `65002` connections. When this happens:

- Do not assume a write command failed or repeat it blindly.
- First verify the resulting state, checksum, commit, or output file.
- Use `-o IPQoS=none`, `-o ConnectTimeout=20`, and short cooldowns of 10–30 seconds between retries.
- For long commands, add `-o ServerAliveInterval=5 -o ServerAliveCountMax=12`.
- Prefer one small, verifiable operation per SSH connection.

## Rollback rule

Rollback changes production and may overwrite data. Never perform it automatically. Obtain explicit user approval, identify the exact timestamped backup, and restore the database only when the schema/data rollback is actually required.
