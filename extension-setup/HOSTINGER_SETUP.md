# Hostinger MySQL logging and Render predictions

The six extensions (Bontoc, Hinunangan, Maasin, Main, SanJuan, and TO) now read
their prediction and logging URLs from their own `config.json` files. Their
campus identities and computer numbers are preserved.

```text
Extension -> Render /check_url -> phishing_url_bundle.joblib
Extension -> Hostinger /api/phishing-logs -> Laravel -> MySQL
```

## Extension configuration

Each extension has these additional fields:

```json
{
  "prediction_api_url": "https://phish-blocker-monitoring-system-8f1o.onrender.com/check_url?url=",
  "logging_api_url": ""
}
```

The prediction address comes from your Render deployment screenshot. Confirm it
against your service's actual URL. Set `logging_api_url` in **each of the six**
configs to `https://YOUR-HOSTINGER-DOMAIN/api/phishing-logs`. Include any subfolder
prefix if the Laravel app is deployed under a subfolder. It is intentionally
empty until the actual Hostinger address is known; predictions work while logs
are skipped with a console message. Reload each extension after editing.

The existing `<all_urls>` host permission already covers the Hostinger address;
the explicit Render permission has been updated. No database credentials belong
in these extension files. Configure each installed computer's own number.

## Hostinger backend

Deploy the updated Laravel application with its document root pointing to
`public/`. The new stateless route `POST /api/phishing-logs` does not require a
dashboard login or CSRF cookie. It validates input, limits submissions to 120 per
minute per IP, writes `phishing_logs`, registers the campus, updates distinct
computer counts, and creates alerts for suspicious/blocked results. The ingestion
route is public and accepts reported extension data; it does not authenticate
device identity. Dashboard access remains behind the existing login.

Set real MySQL credentials in **Hostinger's Laravel `.env`**, for example:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://YOUR-HOSTINGER-DOMAIN
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_hostinger_database
DB_USERNAME=your_hostinger_database_user
DB_PASSWORD=your_hostinger_database_password
```

Use the database host shown in your Hostinger panel if it differs. Clear old
`DB_URL` and PostgreSQL `DATABASE_URL` values if present. Keep your real `APP_KEY`
and other existing app settings. The API uses Laravel's configured database;
changing `.env.example` alone does not change your live database connection.

For a new database, run `php artisan migrate --force` after deployment, then
`php artisan optimize:clear` and `php artisan config:cache`. Back up an existing
database before applying migrations. No migrations or credential changes have
been run against your real database by this update. Existing manually created
tables must have a migration history compatible with the Laravel migrations;
the supplied `db_query_create.txt` was written for PostgreSQL, not Hostinger
MySQL, and should not be pasted into phpMyAdmin.

The existing migrations provide MySQL-compatible log, campus, and alert tables.
The old PostgreSQL trigger setup remains for existing PostgreSQL deployments;
MySQL campus/alert updates now run in the Laravel endpoint transaction instead.

## Render

Deploy the updated `extension-setup/api` files. The saved model and threshold are
unchanged. Render no longer provides `/logs` and no longer reads Supabase settings.
Remove `SUPABASE_URL` and `SUPABASE_KEY` from the Render environment if present.
Its `/health` endpoint continues to check model readiness.

If pushing to the separate `phishblock_api` GitHub repository, put the **contents**
of `extension-setup/api` at that repository's root. Use a blank Root Directory in
Render and remove `rootDir: extension-setup/api` from that repository's Blueprint.
For deployment from the full monitoring repository, keep the existing `rootDir`.

## Checks

```text
php artisan test --compact
node --test tests/extension-routing.test.cjs
python -m unittest discover -s extension-setup/api/tests -v
```

The Python command needs the API requirements and development dependency `httpx`.
PHP feature tests use an isolated SQLite database, and JavaScript tests stub all
network calls. After deploying, browse a URL with one configured extension and
verify the POST returns 201 and a matching record appears on the dashboard.
