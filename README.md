# DRANHS SMARTENROLL (EMS2)

Senior High School enrollment system for Daniel R. Aguinaldo National High School.

## Local development (XAMPP)

1. Place this project under your web root (e.g. `htdocs/dranhs-portal/EMS2`).
2. Start Apache and MySQL.
3. Import `scripts/setup_schema.sql` and `scripts/setup_users.sql`.
4. Configure database via environment variables or defaults in `db.php`.
5. Open `http://localhost/.../EMS2/` in your browser.

## Railway deployment

1. Connect this repository to Railway.
2. Set **Root Directory** to `/` (repository root).
3. Railway uses the `Dockerfile` (FrankenPHP).
4. Set MySQL variables: `MYSQLHOST`, `MYSQLPORT`, `MYSQLUSER`, `MYSQLPASSWORD`, `MYSQLDATABASE`.

## Stack

- PHP 8.x, MySQL, Tailwind CSS (CDN), JavaScript
