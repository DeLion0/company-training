# SkillSpring — Unified Login (MySQL)

## Local setup (XAMPP)

1. Copy this project's contents into `C:\xampp\htdocs\company-training`, leaving any existing `.git` intact.
2. Start Apache and MySQL in XAMPP.
3. phpMyAdmin → **Import** → `database/schema.sql` (the script creates `company_training`).
4. Copy `.env.example` to `.env`. Set your local DB values; default XAMPP root user has an empty password (unless changed).
5. In PowerShell:
   ```powershell
   cd C:\xampp\htdocs\company-training
   & 'C:\xampp\php\php.exe' tools\seed-users.php
   ```
   Enter a unique HR email/password and a Department Head email/password. Choose Department ID for the head.
6. Visit `http://localhost/company-training/public/` and sign in with either account.

**Note:** The seeder echoes password characters. Use a private terminal, not screen sharing. Change demo passwords for any shared testing. The seeder is CLI-only and is not under the public web root.

## Railway readiness

- Same PHP codebase, different environment variables. Add a **Railway MySQL service**, import `database/schema.sql`, then run account setup against that DB by a trusted private connection. **Local XAMPP data/accounts do not automatically sync to Railway.**
- Under Railway PHP service → **Variables**, configure:
  - `DB_HOST` = `${{MySQL.MYSQLHOST}}`
  - `DB_PORT` = `${{MySQL.MYSQLPORT}}`
  - `DB_NAME` = `${{MySQL.MYSQLDATABASE}}`
  - `DB_USER` = `${{MySQL.MYSQLUSER}}`
  - `DB_PASSWORD` = `${{MySQL.MYSQLPASSWORD}}`
- Depending on the Railway DB service name, replace `MySQL` in these references with the actual service name. Verify connection to the private network before using.
- Root directory remains blank; deploy via included `Dockerfile`. It installs PDO MySQL, which is required by database authentication.
- Public networking target port `8080` unless `PORT` explicitly differs.
- Store credentials only in Railway Variables or untracked `.env`, **never in GitHub**.

## Current boundaries

- UI prototype plus database-backed HR and Department Head authentication; no training persistence yet.
- Employee and Organization Admin roles are reserved for future modules and cannot sign in yet.
- Role and account status are validated on authenticated requests. Department-specific data authorization must be implemented when department data pages are built.
- Current five-failure limit is session-scoped, not global. Before actual production, add centralized rate limiting, MFA/password recovery, audit logs, and production web server deployment.
