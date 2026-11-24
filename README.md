# Internet Programming Portal (PHP + MySQL)

Group project template: secure, database-driven web app with registration, login (sessions + cookie),
Contact Us form, full CRUD, search + pagination, CSV import/export, reports, SEO tags and activity logging.

## Quick start (XAMPP / WAMP / MAMP / Laragon)
1. Copy this folder to your web root, e.g. `C:\xampp\htdocs\ip_project`.
2. Start **Apache** and **MySQL** in the control panel.
3. Open **phpMyAdmin** (http://localhost/phpmyadmin) → *Import* → choose `sql/schema.sql` → *Go*.
   (This creates the `ip_portal` database, 4 tables and sample data.)
4. Check `config/db.php` (defaults: host `localhost`, user `root`, empty password, db `ip_portal`).
5. Visit http://localhost/ip_project/
6. Sample login: `alice` / `Password123!` (also `brian`, `cynthia`) – or register a new account.

Requirements: PHP 8.0+ with `pdo_mysql` and `mbstring`; MySQL 5.7+/MariaDB 10.3+.

## Folder structure
```
config/     config.php (constants, subject array), db.php (PDO connection)
includes/   init.php, functions.php, queries.php, header.php, nav.php, footer.php
pages/      dashboard, profile, contact, submissions (+ edit, export_csv), hub  (login required)
assets/     css/, img/
sql/        schema.sql (schema + sample data)
logs/       activity.log (file-handling requirement)
docs/       documentation.html / documentation.pdf
```
Public: `index.php`, `register.php`, `login.php`, `logout.php`, `robots.txt`, `sitemap.xml`.

## Deploying to InfinityFree (or similar)
1. Create a hosting account + a MySQL database in the control panel; note host, db name, user, password.
2. Import `sql/schema.sql` via the host's phpMyAdmin (remove the first two lines `CREATE DATABASE ...` / `USE ...`
   if the host forbids creating databases; select your database before importing).
3. Edit `config/db.php` with the host's credentials (or set environment variables if supported).
4. Upload all files to `htdocs/` with FTP/File Manager. Make sure `logs/` is writable.
5. Replace `YOUR-DOMAIN` in `robots.txt` and `sitemap.xml`.
6. Take screenshots of each step for the documentation.

## Team checklist
- [ ] Replace sample names/text, add your group names to `docs/documentation.html`
- [ ] Add real screenshots (hosting steps, app pages, phpMyAdmin) to the documentation
- [ ] Re-export `sql/schema.sql` from phpMyAdmin after any schema change
- [ ] Rebuild the PDF, zip everything, and upload to Moodle (each member uploads individually)
