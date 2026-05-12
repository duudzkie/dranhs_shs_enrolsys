# Railway MySQL Setup Guide

This project now standardizes on MySQL for Railway deployments.
Use a Railway MySQL plugin and set the following environment variables:

- `MYSQLHOST`
- `MYSQLUSER`
- `MYSQLPASSWORD`
- `MYSQLDATABASE`
- `MYSQLPORT`

Railway may also provide `DATABASE_URL`, and the app can parse it if it is a MySQL URL.

## Automatic Setup

1. Railway will inject environment variables for MySQL automatically when the MySQL plugin is attached.
2. Run the setup script once via SSH or direct execution:
   ```bash
   php setup_db.php
   ```
   This will create the necessary tables and insert default admin users.

## Manual Setup (via Railway Dashboard)

1. Go to your Railway project.
2. Add or verify the MySQL plugin.
3. In the environment variables section, set the values above.
4. Deploy the app or restart the service.
5. Run `php setup_db.php` if the database schema is not already initialized.

## Connection Details

The app supports two ways to connect:

- Railway MySQL env vars: `MYSQLHOST`, `MYSQLUSER`, `MYSQLPASSWORD`, `MYSQLDATABASE`, `MYSQLPORT`
- `DATABASE_URL=mysql://user:pass@host:port/database`

## Default Users

All default users have password: `password123` (hashed with bcrypt)

| Username  | Role      |
|-----------|-----------|
| admin     | admin     |
| evaluator | evaluator |
| encoder   | encoder   |

## Update PHP Code to Use New Config

Existing code should use the centralized connection helpers in `config_db.php` or `EMS2/db.php` rather than hardcoded local credentials.

For example:

```php
require_once 'config_db.php';
$conn = getMySQLiConnection();
```

or in EMS2:

```php
require_once __DIR__ . '/db.php';
$conn = db_connect();
```

## Troubleshooting

- **"Connection refused"**: verify the MySQL plugin is running and the env vars are set
- **"Access denied"**: confirm your `MYSQLUSER` and `MYSQLPASSWORD` are correct
- **"Unknown database"**: create the database in Railway or use `setup_db.php`
