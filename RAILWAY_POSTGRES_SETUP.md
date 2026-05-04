# Railway PostgreSQL Setup Guide

## Automatic Setup

1. **Railway will inject `DATABASE_URL`** as an environment variable automatically
2. **Run the setup script** once via SSH or direct execution:
   ```bash
   php setup_db.php
   ```
   This will create the `users` table and insert default admin users

## Manual Setup (via Railway CLI or Dashboard)

### Option 1: Using Railway CLI
```bash
railway connect postgres
psql < EMS2/scripts/setup_users_postgres.sql
```

### Option 2: Using Railway Dashboard
1. Go to your Railway project
2. Click on **PostgreSQL** plugin
3. Click **PostgreSQL GUI** tab (if available) or use the connection string
4. Run the SQL from `EMS2/scripts/setup_users_postgres.sql`

## Connection Details

Railway provides the PostgreSQL connection string as:
```
DATABASE_URL=postgres://user:password@host:port/database
```

The app automatically:
- Detects `DATABASE_URL` and uses PostgreSQL
- Falls back to MySQL if `DATABASE_URL` is not set (local dev)

## Default Users

All default users have password: `password123` (hashed with bcrypt)

| Username  | Role     |
|-----------|----------|
| admin     | admin    |
| evaluator | evaluator|
| encoder   | encoder  |

## Update PHP Code to Use New Config

If you have existing code using hardcoded MySQL connections, update them to use:

```php
require_once 'config_db.php';
$conn = getDBConnection(); // For PDO (recommended)
// OR
$conn = getMySQLiConnection(); // For mysqli (legacy)
```

## Troubleshooting

- **"Connection refused"**: Check that PostgreSQL is running and `DATABASE_URL` is set
- **"relation does not exist"**: Run `setup_db.php` to create tables
- **"database does not exist"**: Create it first via Railway dashboard or CLI

## Files Added

- `config_db.php` - Database configuration for Railway/local
- `setup_db.php` - One-time setup script
- `EMS2/scripts/setup_users_postgres.sql` - PostgreSQL schema
