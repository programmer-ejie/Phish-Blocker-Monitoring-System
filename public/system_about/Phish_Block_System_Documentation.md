# Phish Block Monitoring System: Complete Technical Documentation

Source snapshot: 29 October 2026, Asia/Manila. Prepared from the local project repository for documentation, maintenance, deployment planning, and academic reference. Text only: architecture, operational flows, interfaces, database, source responsibilities, training, configuration, tests, and a file inventory. Sections flow continuously to use the page area efficiently.

# 1. Scope, evidence, and reading guide

This document describes implementation present in the repository. Configured addresses are configuration evidence, not proof of live deployment or service availability. Database structures are derived from migrations and SQL, not a connection to a production database. Test descriptions describe existing assertions; they do not claim a fresh passing test run. No live website, prediction endpoint, training job, migration, seeder, or external service was invoked to produce this documentation.

The principal implementation is Laravel for the website and ingestion endpoints, Manifest V3 JavaScript for browser protection, and Python FastAPI for prediction. The following chapters explain their relationship. Later chapters extract source declarations, migration fields, test intent, existing documentation, dataset headers, and file purposes. Installed dependency internals, caches, private runtime data, model binary contents, and raw dataset rows are outside the prose extraction. Their roles and inventory categories are recorded separately. Secret values and seeded login credentials are intentionally excluded from this publicly located PDF.

# 2. Purpose, users, and boundaries

PhishBlock screens browser destination URLs and centralizes reported results across Southern Leyte State University campuses. Browser users receive quiet checks, warnings, or a blocking intervention. Administrators authenticate to review scans, risk, campus activity, computer identities, alerts, and analytics. Maintainers configure hosting and extension endpoints. Researchers prepare historical URL datasets, train candidate models, evaluate decisions, and package approved releases.

The six canonical campus identifiers are SLSU-Bontoc, SLSU-Hinunangan, SLSU-Maasin, SLSU-Main, SLSU-SanJuan, and SLSU-TO. Each campus has an extension distribution folder. Identity belongs to a browser profile installation; it is not the Windows computer name. One physical computer using several profiles may register several devices.

The prediction service assesses URL text, optional verified PhishTank entries, and optional Google Safe Browsing threat matches. It does not fetch submitted target pages, inspect page contents, follow redirects, query domain age, or use a universal trusted-domain bypass. Demonstration seed reasons mentioning these properties do not establish corresponding runtime detection capabilities.

# 3. Architecture and responsibility split

A content script runs inside an eligible browser page and requests a check from the extension background service worker. The worker reads campus configuration and calls the configured prediction API. FastAPI loads an approved local model bundle, normalizes the URL, computes a phishing score, applies the model decision policy and optional reputation checks, and returns structured risk information. The content script presents the result. Independently, the background worker registers the device and submits the result to Laravel. Laravel validates it, creates a phishing log, updates campus/computer statistics, and produces an alert when necessary. The admin website reads those database records on page requests.

Prediction and logging are separate network operations. Render is the documented prediction host. Hostinger is the documented Laravel/MySQL deployment target. PostgreSQL/Supabase migration support remains in the repository for earlier deployments. Browser extensions communicate with Laravel over HTTP; they never contain database credentials or connect directly to MySQL. Render no longer provides a logs endpoint or writes monitoring records to Supabase.

# 4. Full browsing and decision flow

Step 1: Chrome loads the campus extension manifest. It registers background.js as its service worker, popup.html as the toolbar interface, and content.js at document_start for matching URLs. Step 2: background.js loads config.json once through a shared configuration promise. If configuration cannot be read, it retains a default prediction endpoint and blank campus/logging settings. Step 3: content.js skips localhost, 127.0.0.1, blank hosts, and browser pages; a recognizable brand substring never skips a check.

Step 4: content.js creates a hidden overlay and sends fetch_phish_api with the current page URL. Step 5: the worker URL-encodes the destination, appends it to prediction_api_url, and performs a GET request with a nine-second timeout. Step 6: the API validates and classifies the URL and returns URL, score, engine, thresholds, model decision, final status, risk, and reason. Step 7: the worker starts asynchronous logging and immediately returns the prediction to the content script. Logging success is not required before showing the assessment.

Step 8: Block displays an intervention overlay containing risk, score, and reason. Leave unsafe page attempts to close the window and then navigates to about:blank. Continue at my own risk removes the overlay. Suspicious or Medium shows a dismissible warning banner and leaves the page visible. Proceed removes the hidden overlay and keeps the page visible. Successful results are saved per URL and as lastPhishResult. Failed checks remove the overlay and leave the page available; service failure is not a safe verdict.

This is a page-level intervention after navigation has begun, not a network firewall or guaranteed pre-request cancellation. The manifest does not define a declarative network blocking ruleset. A user may override a displayed intervention. The content script begins with the current URL; no general single-page-application URL observer or navigation history hook is implemented in this file.

# 5. Device registration and identity allocation

On the first successful assessment needing logging, the worker reads phishblockDeviceUuid from chrome.storage.local. If absent it creates crypto.randomUUID and saves it before requesting registration. The registration endpoint is derived from logging_api_url by replacing the final /phishing-logs path segment with /devices/register, preserving an application subfolder prefix. A logging URL that does not end with the expected path is rejected for registration.

Laravel accepts a valid UUID and one of the six canonical campus names. It lowercases the UUID, upserts the campus, obtains a row lock on the campus, and finds an existing device for the campus/UUID pair. If new, it assigns one greater than the maximum computer number among devices and legacy phishing logs for that campus. The generated display name follows campus-PC-001, with at least three digits. The transaction allows five attempts. Registration refreshes last_seen_at and recalculates the distinct union of registered and logged computer numbers.

The response contains device_uuid, campus_name, computer_number, and name. The extension verifies returned UUID, campus, and name, then saves phishblockDevice. A shared registrationPromise prevents concurrent checks from initiating duplicate registrations within the service-worker lifetime. Failed registration clears the promise so a later assessment can retry. A worker restart reuses the saved UUID and registers idempotently. Removing extension storage or uninstalling creates a new identity; cloning profile storage can duplicate an identity.

# 6. Logging transaction and alerts

The extension captures detected_at as an ISO UTC timestamp at logging initiation, before waiting for registration. It posts URL, score, risk_level, status, reason, device_uuid, campus_name, and approved model metadata. Requests for registration and logging use twelve-second timeouts. Blank logging_api_url disables registration/logging while prediction continues. Failed log submissions are written to the extension console and are not durably queued or automatically replayed.

Laravel checks registered UUID against the submitted campus, rejects unknown or mismatched registrations, links device_id, and replaces any supplied computer number with the registered number. Legacy callers can omit device_uuid and supply computer_number. Only ml_engine, model_threshold, model_block_threshold, and model_decision metadata keys are persisted. Caller-selected database IDs are not accepted through the validated data. A transaction keeps the log, campus updates, and application-created alert consistent.

On MySQL/SQLite the controller upserts and locks a campus and attaches campus_id before inserting. On PostgreSQL it expects supplied database triggers to register/link campuses and create alerts. In both paths the controller recomputes computer_count from the distinct union of log and device computer numbers. Proceed does not create an alert. Critical risk creates a Critical/Open alert. Block status or High risk creates a High/Open alert. Other non-Proceed results create a Medium/Monitoring alert. Automated alerts use owner Automated Detection and corresponding detection titles. Repeated suspicious events can create separate alerts; no aggregation/deduplication key is defined.

# 7. Website routes, screens, authentication, and metrics

GET / renders the public landing page through AdminController.home. It uses live database summaries, five recent monitoring records, capability descriptions, workflow text, campus coverage, and previews. Because this route is public and includes monitoring information, its current data exposure should be considered when choosing a deployment audience. The stock welcome.blade.php exists but is not the home route target.

GET /login shows the login form; POST /login validates email/password and attempts authentication for an active account. Successful authentication regenerates the session and redirects to admin.dashboard. Failure returns an email-field error and preserves only the email input. POST /logout logs out, invalidates the session, regenerates the CSRF token, and redirects to login. All six admin GET routes require authentication plus an active user with role admin; a signed-in non-admin receives HTTP 403. No public user-registration or password-reset controller route is defined here.

GET /admin/dashboard displays summary totals, campus health, recent records, priority alerts, hourly activity, and status mix. GET /admin/logs displays newest logs in pages of six, including Philippine time, score to four decimals, risk, status, reason, assigned device display name, campus, and URL. GET /admin/analytics displays hourly traffic, risk distribution, campus decision percentages, and campus health/threat exposure. GET /admin/alerts displays summary cards and five alerts per page. GET /admin/campuses displays counts, sync times, stored status, and uptime. GET /admin/settings displays settings grouped and sorted from the database.

Total scans counts every phishing log. Blocked today counts Block records by created_at on the application current date. Review queue counts Suspicious records across the table. Active campuses counts is_active=true. Safe rate is rounded 100 times Safe-or-Low records divided by all records. Critical incidents counts Critical alerts whose status is neither Resolved nor Closed. Priority alerts count High and Critical; active alerts exclude Resolved/Closed. Alert campus count uses distinct non-null campus IDs.

Campus threat rate is 100 times Block-plus-Suspicious divided by campus scans. Health score is rounded uptime_percentage minus 0.35 times threat rate, clamped from zero to 100. Scores at least 95 are Healthy; at least 85 are Watch; lower scores are At Risk. This computed dashboard health can differ from the stored campus status displayed on the campuses screen. Uptime is a stored value; the inspected code does not measure network uptime. Active campuses and recent sync values do not establish a continuous heartbeat service.

Hourly traffic selects records since the start of the hour six hours ago and creates seven hourly buckets including the current hour. Bar height has a minimum four percent even for a zero-count bucket. Aggregations use created_at, while row display prefers detected_at and falls back to created_at, formatted in Asia/Manila. Latest ordering uses record creation time. monitoringRecords loads at most 100 newest records. Website views are server-rendered; no live polling or WebSocket feed is implemented in the inspected controllers/views. Shared layouts provide navigation, profile/logout, responsive menus, footer, and dark-mode preference under localStorage key phish-block-theme.

# 8. Laravel API contract

POST /api/devices/register: JSON device_uuid is required and must be a UUID; campus_name is required and must match Device.CAMPUSES. A successful response is HTTP 200 with identity fields. POST /api/phishing-logs: a successful insert is HTTP 201 with logged=true and the new id. Both routes use throttle:120,1, described in project setup as 120 requests per minute per IP. They are stateless public routes without dashboard login or browser CSRF-cookie requirements; registration is an identity allocation mechanism, not strong device authentication.

Log validation: url is a required HTTP(S) URL string with at most 16,384 characters. score is nullable numeric from zero through one. risk_level must be Safe, Low, Medium, High, or Critical. status must be Proceed, Suspicious, or Block. reason is nullable with at most 8,192 characters. detected_at is nullable and must use the precise UTC millisecond shape YYYY-MM-DDTHH:mm:ss.sssZ. device_uuid is an optional-but-required-when-present UUID. computer_number is required without device_uuid and is an integer from one through 4,294,967,295. campus_name is required text up to 30 characters; unlike registration, this legacy log field is not restricted to the six-name enumeration.

metadata is an optional array. ml_engine is nullable text up to 100 characters; warning/block thresholds are nullable numerics from zero through one; model_decision is nullable Proceed, Suspicious, or Block. Invalid JSON API data normally yields Laravel validation errors with HTTP 422. Throttled requests can yield HTTP 429. The controller validates field membership, not the semantic consistency of every score/risk/status combination; it trusts the submitted assessment and does not call FastAPI to independently re-score it.

config/cors.php allows POST and OPTIONS, any origin, Content-Type and Accept headers, no credentials, and a 600-second max age for api/phishing-logs only. The registration route is not listed in that CORS path configuration. Extension worker host permissions cover remote calls, but ordinary website cross-origin callers should not assume the registration route has the same CORS policy. GET /up is Laravel framework health routing, distinct from FastAPI /health.

# 9. Prediction API contract and hybrid decision policy

GET / returns service information: message, model_name, pipeline_loaded, metadata_fallback_loaded=false, model_threshold, model_block_threshold, phishtank_loaded, and logging_backend=hostinger-laravel. GET /health returns healthy, model_loaded, and model_name with HTTP 200 when loaded and 503 when unavailable. GET /check_url requires query parameter url, length one through 16,384. FastAPI also provides generated /docs, /redoc, and /openapi.json documentation under its default configuration.

The prediction response fields are url, score, ml_engine=phishing_url_bundle, model_threshold, model_block_threshold, ml_decision, risk_level, status, and reason. Note the mapping: API ml_decision is sent to Laravel as metadata.model_decision. The final status can differ from the ML-only decision because a verified reputation match or heuristic combination can raise final risk.

Normalization trims input, accepts protocol-relative URLs and missing schemes by supplying HTTP, restricts schemes to HTTP/HTTPS, rejects whitespace/control characters, validates ports and hostnames, lowercases and IDNA-encodes hosts, strips a trailing host dot, supplies a slash for empty paths, and removes fragments. Path/query case and encoding remain intact. The normalized representation is scheme-independent; equivalent HTTP and HTTPS addresses can match the same normalized entry. Username information is retained when present. No target URL is fetched.

predict_urls invokes the saved pipeline predict_proba and selects class 1 as phishing. Model classes must be ordered [0,1]. The warning threshold comes from bundle.threshold. A block threshold is optional and only accepted with validated policy and an ordered finite threshold between warning and one. Scores at or above a validated block threshold produce ML Block; at or above warning produce Suspicious; otherwise Proceed. Older warning-only bundles do not acquire an automatic blocking rule just because a score is high.

The hybrid layer checks exact normalized membership in the loaded PhishTank set and optional Google Safe Browsing v4 threats for malware, social engineering, and potentially harmful applications. A positive reputation match or ML Block sets High/Block. Otherwise an ML warning or at least two pattern flags sets Medium/Suspicious. Remaining results are Low/Proceed. Pattern flags are IP hostname, @ in authority, at least three host dashes, and at least three host dots. The normal current hybrid path emits Low, Medium, and High; database/ingestion support Safe and Critical for other or older producers.

PhishTank CSV requires a url column. If a verified column exists, only yes rows are loaded. Optional download by PHISHTANK_FILE_ID occurs only when the local file is absent. Missing/malformed optional PhishTank data leaves model detection available. Google requests use a six-second timeout; request/JSON failures are logged without the key and return no match. Invalid target URLs yield HTTP 422; missing model or unexpected classification errors yield HTTP 503. Absence of optional matches is not independent proof of safety.

# 10. Model loading, feature families, and release integrity

At startup the API loads dataset/phishing_url_bundle.joblib or MODEL_PATH. A missing file, wrong dictionary shape, invalid threshold, wrong classes, incompatible recorded scikit-learn version, or failed smoke prediction prevents readiness. The required bundle keys include pipeline and threshold. A test inference for example.com must return a finite probability from zero through one. The service never falls back to old numeric-feature metadata coefficients or old random-forest/pickle assets.

Version-four-or-newer preprocessing bundles require deployment_approved. Version-five-or-newer bundles additionally require strict release proof, strict profile, matching warning/block thresholds and block-test proof when applicable, and a matching inference-helper source hash. The source hash policy tolerates checkout line-ending differences for text but not substantive source changes; binary file hashes remain exact. Serialization is only appropriate for trusted training artifacts; this documentation does not unpickle or execute model files.

phishing_url_utils supports normalized raw URL baselines, leading-www-invariant text, separate hostname and path/query text, sparse and dense lexical structures, and a weighted probability ensemble. Removing a leading www label is a representation choice, not a domain allowlist. Lexical structure includes logged URL/host/path/query lengths, host dots/dashes, @ and percent counts, path depth, query separators, digit ratios, punycode marker, IP-host indicator, character entropy, homepage indicator, and account/login/security vocabulary. Not every research feature family is necessarily in the deployed binary; the actual saved pipeline determines runtime behavior.

# 11. Database structure and relationships

Domain tables are users, campuses, devices, phishing_logs, alerts, and system_settings. Framework support tables include password_reset_tokens, sessions, cache, cache_locks, jobs, job_batches, failed_jobs, and the migration repository. The following field register is extracted directly from Blueprint declarations and SQL. It records declared type, options, indexes, and constraints. Conditional migrations and database drivers mean not every declaration appears in every installed database; inspect the deployed schema before migration planning.

# Migration: 0001_01_01_000000_create_users_table.php

Table operation: Schema::create('users',

Declared field or constraint: id()

Declared field or constraint: string('name')

Declared field or constraint: string('email')->unique()

Declared field or constraint: timestamp('email_verified_at')->nullable()

Declared field or constraint: string('password')

Declared field or constraint: rememberToken()

Declared field or constraint: timestamps()

Table operation: Schema::create('password_reset_tokens',

Declared field or constraint: string('email')->primary()

Declared field or constraint: string('token')

Declared field or constraint: timestamp('created_at')->nullable()

Table operation: Schema::create('sessions',

Declared field or constraint: string('id')->primary()

Declared field or constraint: foreignId('user_id')->nullable()->index()

Declared field or constraint: string('ip_address', 45)->nullable()

Declared field or constraint: text('user_agent')->nullable()

Declared field or constraint: longText('payload')

Declared field or constraint: integer('last_activity')->index()

# Migration: 0001_01_01_000001_create_cache_table.php

Table operation: Schema::create('cache',

Declared field or constraint: string('key')->primary()

Declared field or constraint: mediumText('value')

Declared field or constraint: bigInteger('expiration')->index()

Table operation: Schema::create('cache_locks',

Declared field or constraint: string('key')->primary()

Declared field or constraint: string('owner')

Declared field or constraint: bigInteger('expiration')->index()

# Migration: 0001_01_01_000002_create_jobs_table.php

Table operation: Schema::create('jobs',

Declared field or constraint: id()

Declared field or constraint: string('queue')->index()

Declared field or constraint: longText('payload')

Declared field or constraint: unsignedSmallInteger('attempts')

Declared field or constraint: unsignedInteger('reserved_at')->nullable()

Declared field or constraint: unsignedInteger('available_at')

Declared field or constraint: unsignedInteger('created_at')

Table operation: Schema::create('job_batches',

Declared field or constraint: string('id')->primary()

Declared field or constraint: string('name')

Declared field or constraint: integer('total_jobs')

Declared field or constraint: integer('pending_jobs')

Declared field or constraint: integer('failed_jobs')

Declared field or constraint: longText('failed_job_ids')

Declared field or constraint: mediumText('options')->nullable()

Declared field or constraint: integer('cancelled_at')->nullable()

Declared field or constraint: integer('created_at')

Declared field or constraint: integer('finished_at')->nullable()

Table operation: Schema::create('failed_jobs',

Declared field or constraint: id()

Declared field or constraint: string('uuid')->unique()

Declared field or constraint: text('connection')

Declared field or constraint: text('queue')

Declared field or constraint: longText('payload')

Declared field or constraint: longText('exception')

Declared field or constraint: timestamp('failed_at')->useCurrent()

# Migration: 2026_07_17_000000_ensure_phishing_logs_table.php

Table operation: Schema::create('phishing_logs',

Declared field or constraint: id()

Declared field or constraint: foreignId('campus_id')->nullable()

Declared field or constraint: text('url')

Declared field or constraint: double('score')->nullable()

Declared field or constraint: string('risk_level')

Declared field or constraint: string('status', 50)

Declared field or constraint: text('reason')->nullable()

Declared field or constraint: unsignedInteger('computer_number')->nullable()

Declared field or constraint: string('campus_name', 100)->nullable()

Declared field or constraint: json('metadata')->default('{}')

Declared field or constraint: timestamps()

# Migration: 2026_07_17_000001_create_monitoring_support_tables.php

Table operation: Schema::table('users',

Declared field or constraint: string('role', 30)->default('user')->index()

Declared field or constraint: boolean('is_active')->default(true)->index()

Table operation: Schema::create('campuses',

Declared field or constraint: id()

Declared field or constraint: string('code', 30)->unique()

Declared field or constraint: string('name')

Declared field or constraint: unsignedInteger('computer_count')->default(0)

Declared field or constraint: decimal('uptime_percentage', 5, 2)->default(100)

Declared field or constraint: string('status', 30)->default('Healthy')

Declared field or constraint: boolean('is_active')->default(true)->index()

Declared field or constraint: timestampTz('last_sync_at')->nullable()

Declared field or constraint: timestampsTz()

Table operation: Schema::create('alerts',

Declared field or constraint: id()

Declared field or constraint: foreignId('campus_id')->nullable()->constrained()->nullOnDelete()

Declared field or constraint: string('title')

Declared field or constraint: string('severity', 20)->index()

Declared field or constraint: string('owner', 100)

Declared field or constraint: string('status', 40)->index()

Declared field or constraint: timestampsTz()

Table operation: Schema::create('system_settings',

Declared field or constraint: id()

Declared field or constraint: string('group', 60)->index()

Declared field or constraint: string('label')

Declared field or constraint: text('value')

Declared field or constraint: unsignedInteger('sort_order')->default(0)

Declared field or constraint: timestampsTz()

Declared field or constraint: unique(['group', 'label'])

# Migration: 2026_07_17_000002_remove_action_from_phishing_logs.php

Table operation: Schema::table('phishing_logs',

Declared field or constraint: dropColumn('action')

Table operation: Schema::table('phishing_logs',

Declared field or constraint: string('action', 50)->nullable()

# Migration: 2026_07_17_000003_automate_monitoring_records.php

# Migration: 2026_10_28_000000_add_detected_at_to_phishing_logs.php

Table operation: Schema::table('phishing_logs',

Declared field or constraint: timestampTz('detected_at', 3)->nullable()

Table operation: Schema::table('phishing_logs',

Declared field or constraint: dropColumn('detected_at')

# Migration: 2026_10_28_000001_create_devices_table.php

Table operation: Schema::create('devices',

Declared field or constraint: id()

Declared field or constraint: uuid('device_uuid')

Declared field or constraint: string('campus_name', 30)

Declared field or constraint: unsignedInteger('computer_number')

Declared field or constraint: string('name', 80)

Declared field or constraint: timestampTz('last_seen_at')->nullable()

Declared field or constraint: timestamps()

Declared field or constraint: unique(['campus_name', 'device_uuid'])

Declared field or constraint: unique(['campus_name', 'computer_number'])

Additional SQL operation: DB::statement('ALTER TABLE devices ENABLE ROW LEVEL SECURITY');

Table operation: Schema::table('phishing_logs',

Declared field or constraint: foreignId('device_id')->nullable()->constrained('devices')

Table operation: Schema::table('phishing_logs',

Declared field or constraint: dropConstrainedForeignId('device_id')

PhishingLog belongs to Device and Campus; Alert belongs to Campus; Campus has many PhishingLog records. devices stores campus_name text rather than a campus foreign key. UUID and computer_number are each unique within campus_name. phishing_logs.device_id is nullable and constrained to devices. alerts.campus_id is nullable and becomes null on campus deletion. phishing_logs.campus_id is initially a nullable ID without a Blueprint foreign-key constraint in the supplied portable migration. The initial PostgreSQL SQL does not declare campus_id or updated_at although the later trigger/model path expects them; historical PostgreSQL installations therefore need schema compatibility review. MySQL-compatible migrations explicitly include timestamps and campus_id.

PhishingLog casts score to float and metadata to array. detected_at is a custom immutable UTC accessor/mutator; PostgreSQL writes include an explicit offset, other drivers use a microsecond timestamp string. Campus casts last_sync_at to datetime, uptime_percentage to one decimal in the model, and is_active to boolean. Device casts last_seen_at to datetime. User hides password and remember_token, hashes assigned passwords, casts active state to boolean, and casts email verification time to datetime.

The initial PostgreSQL log script enables row-level security and defines insert grants/policy for anon and authenticated roles, with URL length one through 4,096, valid risk, optional score bounds, and positive computer number. service_role receives broader rights. This is not equivalent to the Laravel maximum URL length of 16,384. devices migration enables PostgreSQL row-level security without defining a public device policy in that migration. The actual database role and installed policies determine direct database access.

PostgreSQL BEFORE INSERT register_monitoring_campus upserts the campus and sets NEW.campus_id. AFTER INSERT create_monitoring_alert skips Proceed, creates the mapped alert, and updates campus counts from logs. Laravel controller recalculation also includes registered devices. The old action column is removed by a migration that first rewrites existing statuses by risk. detected_at is added separately and remains nullable for older logs. These are migration-time behavior changes, not proof of already-applied production changes.

# 12. Seeding and configuration semantics

DatabaseSeeder creates or updates an administrator using hardcoded seed credentials, canonicalizes legacy campus aliases SLSU-HC, SLSU-LU, and SLSU-BR, inserts five demonstration campus rows, twelve illustrative logs, four illustrative alerts, and nine settings. The seed omits a SanJuan demonstration row even though registration supports it. Seeded uptime, computer counts, times, reasons, thresholds, and notifications are demonstration content. Running this seeder can create or change existing records and reset the seeded administrator password; this documentation did not run it.

The displayed settings include historical thresholds 0.85, 0.70, and 0.30, critical email alerts, escalation/digest schedules, a role preset, and session timeout text. Current API decisions use thresholds stored in the model bundle; no controller connects displayed settings to Python inference. The inspected web routes offer read screens, without update endpoints for settings, alert assignment/resolution, or campus editing. Email alert/digest labels alone do not implement dispatch jobs or a scheduler.

config/database.php defaults to DB_CONNECTION, otherwise PostgreSQL when DATABASE_URL exists, otherwise SQLite. It contains SQLite, MySQL, MariaDB, PostgreSQL, SQL Server, and Redis connection configurations. DB_URL can override connection details, and PostgreSQL supports DATABASE_URL. Templates and source show intended configuration; private .env contents were not read. Website configuration covers application name/environment/key/debug/URL, database, sessions, cache, queue, filesystem, logging, mail, and services. Session and date behavior should be checked in the deployed configuration, especially blocked-today boundaries versus explicitly Philippine row display.

# 13. Campus extension distributions and permissions

# phishblockv1_slsu-bontoc

Campus: SLSU-Bontoc. Manifest name: PhishBlock. Version: 1.2. Prediction endpoint: https://phish-blocker-monitoring-system-8f1o.onrender.com/check_url?url=. Logging endpoint: https://phish-block-monitoring.dissertationsystem.online/api/phishing-logs.

Permissions: scripting, activeTab, storage. Host permissions: https://phish-blocker-monitoring-system-8f1o.onrender.com/*, <all_urls>. Config and the branding logo are web-accessible resources for matching pages. Icons provide toolbar and extension branding. config.json assigns campus and endpoints; background.js handles remote requests, registration and logging; content.js handles page checks and interventions; popup.html defines the toolbar view; popup.js renders cached results and runs manual checks. backup.txt is retained reference material, not a manifest-loaded runtime script.

# phishblockv1_slsu-hinunangan

Campus: SLSU-Hinunangan. Manifest name: PhishBlock. Version: 1.2. Prediction endpoint: https://phish-blocker-monitoring-system-8f1o.onrender.com/check_url?url=. Logging endpoint: https://phish-block-monitoring.dissertationsystem.online/api/phishing-logs.

Permissions: scripting, activeTab, storage. Host permissions: https://phish-blocker-monitoring-system-8f1o.onrender.com/*, <all_urls>. Config and the branding logo are web-accessible resources for matching pages. Icons provide toolbar and extension branding. config.json assigns campus and endpoints; background.js handles remote requests, registration and logging; content.js handles page checks and interventions; popup.html defines the toolbar view; popup.js renders cached results and runs manual checks. backup.txt is retained reference material, not a manifest-loaded runtime script.

# phishblockv1_slsu-maasin

Campus: SLSU-Maasin. Manifest name: PhishBlock. Version: 1.2. Prediction endpoint: https://phish-blocker-monitoring-system-8f1o.onrender.com/check_url?url=. Logging endpoint: https://phish-block-monitoring.dissertationsystem.online/api/phishing-logs.

Permissions: scripting, activeTab, storage. Host permissions: https://phish-blocker-monitoring-system-8f1o.onrender.com/*, <all_urls>. Config and the branding logo are web-accessible resources for matching pages. Icons provide toolbar and extension branding. config.json assigns campus and endpoints; background.js handles remote requests, registration and logging; content.js handles page checks and interventions; popup.html defines the toolbar view; popup.js renders cached results and runs manual checks. backup.txt is retained reference material, not a manifest-loaded runtime script.

# phishblockv1_slsu-main

Campus: SLSU-Main. Manifest name: PhishBlock. Version: 1.2. Prediction endpoint: https://phish-blocker-monitoring-system-8f1o.onrender.com/check_url?url=. Logging endpoint: https://phish-block-monitoring.dissertationsystem.online/api/phishing-logs.

Permissions: scripting, activeTab, storage. Host permissions: https://phish-blocker-monitoring-system-8f1o.onrender.com/*, <all_urls>. Config and the branding logo are web-accessible resources for matching pages. Icons provide toolbar and extension branding. config.json assigns campus and endpoints; background.js handles remote requests, registration and logging; content.js handles page checks and interventions; popup.html defines the toolbar view; popup.js renders cached results and runs manual checks. backup.txt is retained reference material, not a manifest-loaded runtime script.

# phishblockv1_slsu-sanjuan

Campus: SLSU-SanJuan. Manifest name: PhishBlock. Version: 1.2. Prediction endpoint: https://phish-blocker-monitoring-system-8f1o.onrender.com/check_url?url=. Logging endpoint: https://phish-block-monitoring.dissertationsystem.online/api/phishing-logs.

Permissions: scripting, activeTab, storage. Host permissions: https://phish-blocker-monitoring-system-8f1o.onrender.com/*, <all_urls>. Config and the branding logo are web-accessible resources for matching pages. Icons provide toolbar and extension branding. config.json assigns campus and endpoints; background.js handles remote requests, registration and logging; content.js handles page checks and interventions; popup.html defines the toolbar view; popup.js renders cached results and runs manual checks. backup.txt is retained reference material, not a manifest-loaded runtime script.

# phishblockv1_slsu-to

Campus: SLSU-TO. Manifest name: PhishBlock. Version: 1.2. Prediction endpoint: https://phish-blocker-monitoring-system-8f1o.onrender.com/check_url?url=. Logging endpoint: https://phish-block-monitoring.dissertationsystem.online/api/phishing-logs.

Permissions: scripting, activeTab, storage. Host permissions: https://phish-blocker-monitoring-system-8f1o.onrender.com/*, <all_urls>. Config and the branding logo are web-accessible resources for matching pages. Icons provide toolbar and extension branding. config.json assigns campus and endpoints; background.js handles remote requests, registration and logging; content.js handles page checks and interventions; popup.html defines the toolbar view; popup.js renders cached results and runs manual checks. backup.txt is retained reference material, not a manifest-loaded runtime script.

The popup queries the active tab, displays its URL, looks up the exact URL in phishResultsByUrl, and runs a check when no cached result exists. Manual checks accept only HTTP(S), disable the button while waiting, and display unavailable state on failure. Scores are clamped to zero through one for percentage presentation. Popup checks update storage and the popup, but do not themselves invoke the content-script overlay path. The background also supports recheck_url forwarding to run_check in a specified tab. Cache entries have no explicit expiry or size-pruning mechanism in these scripts.

# 14. Training, provenance, evaluation, and deployment workflow

The notebook is the source of the research workflow. train_model.py executes its code cells in order without a Jupyter kernel, skips pip-install cells, uses a noninteractive plotting backend, tees output into an artifacts training log, and accepts --no-promote and --max-train-rows-per-source. It does not install dependencies, push Git, or deploy Render. Exit zero means promotion; exit two means a completed run without promotion; exceptions mean failed execution.

Historical training inputs are retained separately from runtime dataset assets. data4 and verified PhishTank data are the documented default sources; the active experiment mechanism can replace the base data/split with a verified derived experiment. Other data files are optional or skipped when labels/provenance or raw URL fields are unsuitable. Reviewed homepage corrections and reviewed_training_urls require independent evidence; they are not trusted-domain bypass lists. Review additions require URL, explicit zero/one label, evidence, reviewer, and date. Evaluation-domain reviews are excluded from training.

Frozen splits preserve domain assignments and dataset fingerprints. Reordering data reuses the same split; silently changing labels, data membership, seed, or reviewed-domain assignments is rejected rather than rebuilding an easier holdout. Training-only sampling leaves evaluation partitions unchanged. New base labels require a named experiment. Validation selects model/ensemble and warning/block thresholds; test evaluates the frozen selections. Raw-text diagnostics are not eligible as the production winner in the documented workflow.

Strict warning acceptance requires precision at least 90 percent, phishing recall at least 80 percent, and benign warning rate at most 3 percent on validation and test, sufficient per-class support, and passing homepage regressions. Separate optional ML blocking requires precision at least 98 percent, benign block rate at most 0.5 percent, and at least 50 block predictions with corresponding test support. No qualifying block threshold disables ML-only blocking. A failed gate preserves the runtime model; --no-promote preserves it even when checks pass.

The source-restored experiment manifest identifies phishstorm_restored_v1, 92,005 restored labels, and 4,023 quarantined reference rows. It describes a user-supplied publisher archive with matching extracted/member hashes and download-origin evidence; no publisher-signed checksum is claimed. This is historical source-label restoration, not verification of current website safety. Older notes about inaccessible source evidence are explicitly superseded by the newer verification manifest. The active experiment file points to training_data/experiments/phishstorm_restored_v1/experiment.json.

A passing strict v5 workflow can generate github_api_release with the approved bundle, exact inference helper, requirements, verifier, model card, standalone Render configuration, and hash manifest. READY_FOR_GITHUB must be true. Failed runs do not create a production package. verify_release.py rechecks package integrity. Monorepo Render rootDir is extension-setup/api; a standalone API repository requires blank Root Directory and no monorepo rootDir. Approval gates and historical metrics do not guarantee future production accuracy. No model accuracy claim is made here from an unexecuted binary or old candidate report.

# 15. Installation, deployment, maintenance, and troubleshooting

The repository declares PHP ^8.3 and Laravel ^13.0. Frontend development declares Vite ^8, Tailwind ^4, Laravel Vite plugin ^3, and concurrently ^9. composer setup installs dependencies, creates an environment file if absent, generates a key, runs migrations, installs frontend packages, and builds assets. composer dev runs Laravel server, queue listener, and Vite concurrently. These scripts describe available operations; they were not executed for documentation generation.

For Laravel hosting, use public as the web document root, production environment/debug settings, valid APP_KEY, intended APP_URL, and host-provided database credentials in private environment configuration. Use compatible migration history, back up existing data, apply migrations, and refresh configuration caches as described in HOSTINGER_SETUP.md. PostgreSQL SQL must not be pasted into MySQL phpMyAdmin. No credentials belong in extension configuration. Protect environment files and private research/runtime folders from direct web access.

For FastAPI, the supplied Render configuration pins Python 3.12.10, installs requirements.txt, launches uvicorn app:app on 0.0.0.0 with $PORT and one worker, and checks /health. One worker avoids multiple model copies. Runtime dependency pins must match training. Optional variables are GOOGLE_API_KEY, PHISHTANK_FILE_ID, MODEL_PATH, and PHISHTANK_PATH. Relative override paths resolve from the API directory. A local environment can run uvicorn on loopback port 8000. /health model readiness and / response model information should be verified after actual deployment.

Install or reload the appropriate campus folder using Chrome Load unpacked, configure its endpoints, and ensure Laravel device migrations exist before version 1.2 registration. A healthy prediction with an empty dashboard usually requires checking logging_api_url, campus identity, registration response, failed POSTs, rate limits, and backend/database logs. An unavailable prediction requires checking /health, bundle presence, dependencies, strict proof/helper hash, and the configured prediction address. Changing reviewed CSV rows alone never changes a running model; a passing train/package/deploy cycle is required.

Maintenance should distinguish event detection time from receipt time, back up database and reproducibility inputs, preserve named split manifests, review failed candidates without overwriting runtime artifacts, and reload extensions after updates. There is no durable client log queue, device authentication secret, continuous online-device heartbeat, alert-resolution endpoint, or retention/archival job in the inspected implementation. These are current boundaries rather than completed features.

# 16. Existing test coverage register

# extension-setup/api/tests/test_api.py

Checks v2 text removes www consistently and preserves paths.

Checks v2 text does not whitelist lookalike domains.

Checks health and model identity.

Checks inference matches training helper without network.

Checks legacy saved threshold controls warning not blocking.

Checks blocklist can override benign model prediction.

Checks invalid urls are rejected.

Checks model unavailable is not reported as safe.

Checks missing model fails loading.

Checks invalid threshold fails loading.

Checks blocklist preserves path case and filters unverified.

Checks optional google failure leaves model available.

Checks validated block threshold is distinct from warning.

Checks high legacy score is not automatic proof of phishing.

Checks unapproved v4 candidate cannot be served.

Checks v5 missing strict proof is rejected.

Checks v5 threshold cannot differ from approved evaluation.

Checks v5 helper must match evaluated source.

Checks logging is not handled by prediction server.

# extension-setup/api/tests/test_dataset_provenance.py

Checks conflicting reference labels are not used.

Checks unquoted url comma requires valid numeric suffix.

Checks pending source cannot change labels or activate.

Checks verified source creates separate data and split location.

Checks changed verified dataset is rejected.

Checks wrong reference checksum is rejected.

# extension-setup/api/tests/test_release.py

Checks crlf training proof accepts lf checkout but not code changes.

Checks binary hashes never normalize line endings.

Checks unresolved source labels block even perfect metrics.

Checks lowered notebook policy cannot approve 66 percent recall.

Checks failed report produces no release folder.

Checks passed package is standalone verified and secret free.

Checks changed package file fails verification.

Checks wrong runtime pin prevents packaging.

Checks missing homepage proof prevents release.

# extension-setup/api/tests/test_retraining.py

Checks /.

Checks reordering reuses exact assignments and does not rewrite file.

Checks changed label or new data cannot silently rebuild holdout.

Checks changing seed or reviewed domains is rejected.

Checks training only sampling leaves full holdout identical.

Checks infeasible recall fallback respects false positive limit.

Checks strict block policy can disable blocking.

Checks component features preserve real host and path case.

Checks model warning is not a block without validation.

Checks feasible threshold and infeasible policy are explicit.

Checks homepage success cannot override bad phishing recall.

Checks failed or disabled promotion preserves runtime and passing promotion backs up.

Checks lexical features keep structure and are finite.

Checks queue balances both error types and limits one domain.

Checks explicit reviews never add an evaluation domain to training.

Checks missing review evidence is rejected.

Checks review queue does not assign labels as truth.

# tests/extension-decisions.test.cjs

Test bootstrap, framework configuration, fixtures, or shared test base.

# tests/extension-routing.test.cjs

Checks device UUID persists across service worker restarts and concurrent logs share registration.

Checks registration failure preserves UUID and allows retry without sending unregistered logs.

# tests/Feature/AuthenticationTest.php

Checks guests are redirected away from the admin dashboard.

Checks an active administrator can log in and see the database dashboard.

Checks invalid credentials do not create an authenticated session.

# tests/Feature/DeviceRegistrationTest.php

Checks registration is stable and numbers are independent per campus.

Checks new devices avoid computer numbers in legacy logs.

Checks registered identity overrides client computer numbers and links the log.

Checks registered devices do not need manual computer numbers.

Checks unknown devices and campus mismatches cannot submit logs.

Checks invalid registrations create no records.

# tests/Feature/ExampleTest.php

Checks the application returns a successful response.

# tests/Feature/PhishingLogIngestionTest.php

Checks an extension can submit a log without a browser session.

Checks safe logs update campus computers without creating alerts or duplicate campuses.

Checks all six campuses are recorded separately.

Checks invalid extension logs are rejected before database writes.

Checks extension callers cannot choose database ids or unexpected metadata.

Checks detection time preserves UTC and displays Philippine time while receipt time is independent.

Checks detection time is optional for older extensions.

Checks detection time requires an explicit UTC timestamp.

# tests/Pest.php

Test bootstrap, framework configuration, fixtures, or shared test base.

# tests/TestCase.php

Test bootstrap, framework configuration, fixtures, or shared test base.

# tests/Unit/ExampleTest.php

Checks that true is true.

Project-documented verification commands include php artisan test --compact; node --test tests/extension-routing.test.cjs; node --test tests/extension-decisions.test.cjs; and python -m unittest discover -s extension-setup/api/tests -v with the required Python environment and httpx. Existing PHP feature tests use isolated SQLite and JavaScript tests stub network calls. This documentation-only change does not rerun application test suites or train a model.

# 17. Source function and responsibility index

# extension-setup/api/app.py

Render-ready phishing detection API using the retrained URL-text bundle.

Function configured_path: Implements configured path within this module; its callers determine runtime use.

Function load_model_bundle: Implements load model bundle within this module; its callers determine runtime use.

Function load_phishtank_blocklist: Implements load phishtank blocklist within this module; its callers determine runtime use.

Function lifespan: Implements lifespan within this module; its callers determine runtime use.

Function check_with_google_safebrowsing: Implements check with google safebrowsing within this module; its callers determine runtime use.

Function pattern_based_check: Implements pattern based check within this module; its callers determine runtime use.

Function hybrid_check: Implements hybrid check within this module; its callers determine runtime use.

Function home: Implements home within this module; its callers determine runtime use.

Function check_url: Implements check url within this module; its callers determine runtime use.

Function health: Implements health within this module; its callers determine runtime use.

# extension-setup/api/audit_dataset_provenance.py

Audit source-label disagreements without changing training or evaluation labels.

Run in the PhishBlock environment: python audit_dataset_provenance.py
The local PhishStorm-format reference is NOT claimed to be byte-authenticated
against the original publisher archive. Proposed corrections remain pending.

Function sha256: Implements sha256 within this module; its callers determine runtime use.

Function parse_local_reference: Validate every physical record; quarantine malformed/ambiguous encodings.

A URL containing an unquoted comma can be reconstructed only when all final
13 feature/label fields are finite numbers and the label is exactly 0 or 1.
Non-ASCII URLs are excluded because this local file's encoding is unconfirmed.

Function audit: Implements audit within this module; its callers determine runtime use.

Function group: Implements group within this module; its callers determine runtime use.

# extension-setup/api/dataset_experiments.py

Create source-verified label-restoration experiments without rewriting old data.

Function _contained: Implements contained within this module; its callers determine runtime use.

Function prepare_verified_experiment: Implements prepare verified experiment within this module; its callers determine runtime use.

Function resolve_active_experiment: Implements resolve active experiment within this module; its callers determine runtime use.

# extension-setup/api/phishing_url_utils.py

Shared offline URL normalization and inference for the retraining notebook.

Function normalize_url: Return a scheme-independent URL string, or None for an invalid input.

Hostnames are case insensitive; path/query case and encoding are preserved.
No URLs are requested and no redirects are followed.

Function predict_urls: Apply the exported model and its validation-selected decision threshold.

Function model_text_urls: Version 2 training text: ignore a leading www label for both classes.

This is a model representation, not a blocklist/domain trust rule. Preserve
the original hostname for blocklist matching and all path/query information.

Function normalized_model_urls: Raw-URL baseline representation, refitted on the same frozen partitions.

Function url_lexical_features: Offline URL structure features; no allowlists, DNS, or page requests.

Function host_text_urls: Separate hostname vocabulary from path/query vocabulary.

Function path_query_text_urls: Implements path query text urls within this module; its callers determine runtime use.

Function dense_url_features: Implements dense url features within this module; its callers determine runtime use.

Class UrlProbabilityEnsemble: Soft vote of already fitted models; weights are selected on validation.

Function decision_thresholds: Legacy/uncertified models warn only. Never infer trust from score size.

Function model_decision: Implements model decision within this module; its callers determine runtime use.

Function __init__: Implements init within this module; its callers determine runtime use.

Function predict_proba: Implements predict proba within this module; its callers determine runtime use.

Function predict: Implements predict within this module; its callers determine runtime use.

# extension-setup/api/prepare_verified_dataset.py

Prepare a NEW research experiment only after authenticating the original source.

# extension-setup/api/refresh_training_helpers.py

Run with %run ./refresh_training_helpers.py to refresh a notebook kernel.

Load exact sibling source files without consulting an old module or .pyc cache.
Register their real module names so exported sklearn pipelines remain picklable.

Function load_helper_source: Implements load helper source within this module; its callers determine runtime use.

# extension-setup/api/release_utils.py

Strict release proof and standalone GitHub packaging. No automatic publishing.

Function file_hash: Implements file hash within this module; its callers determine runtime use.

Function source_hash: Hash source with Git/platform-independent LF line endings.

Function source_hash_matches: Also accept legacy proofs hashed before Git converted CRLF to LF.

Only the line-ending representation varies; source text, whitespace, and
comments must still match exactly. Binary artifact hashes stay byte-exact.

Function _is_text_release_file: Implements is text release file within this module; its callers determine runtime use.

Function artifact_hash: Implements artifact hash within this module; its callers determine runtime use.

Function artifact_hash_matches: Implements artifact hash matches within this module; its callers determine runtime use.

Function strict_report_failures: Recompute fixed strict limits, independently of editable notebook settings.

Function runtime_dependency_failures: Implements runtime dependency failures within this module; its callers determine runtime use.

Function verify_release_package: Implements verify release package within this module; its callers determine runtime use.

Function build_release_package: Build only a passing strict release; exclude credentials, corpora, and old models.

# extension-setup/api/retraining_utils.py

Training-only split, evaluation, and local promotion controls.

Function sha256_file: Implements sha256 file within this module; its callers determine runtime use.

Function freeze_splits: Persist domain assignments BEFORE training subsampling; fail on data drift.

Changing row order or training sample size cannot change the holdout. A new
dataset needs a separately named experiment; this function never silently
rebuilds an existing experiment's partitions.

Function metric_summary: Implements metric summary within this module; its callers determine runtime use.

Function select_threshold: Select by validation only; explicitly mark an infeasible policy.

Function select_block_threshold: A second, stricter threshold; no feasible validation point means warnings only.

Function blocking_failures: Implements blocking failures within this module; its callers determine runtime use.

Function apply_training_reviews: Apply only evidence-backed training reviews; never move held-out domains.

Function validation_review_queue: Queue disagreements for HUMAN review, never silently assign replacement labels.

Function promotion_failures: Implements promotion failures within this module; its callers determine runtime use.

Function promote_candidate: Back up and atomically replace the local artifact only when every gate passes.

# extension-setup/api/train_model.py

Run the notebook's training code once without a Jupyter kernel.

Use the PhishBlock Python environment: python train_model.py
No pip installation, Git push, or Render deployment occurs in this runner.

Class Tee: Implements Tee within this module; its callers determine runtime use.

Function main: Implements main within this module; its callers determine runtime use.

Function __init__: Implements init within this module; its callers determine runtime use.

Function write: Implements write within this module; its callers determine runtime use.

Function flush: Implements flush within this module; its callers determine runtime use.

# extension-setup/api/verify_release.py

Run inside a generated github_api_release folder before pushing.

# app/Http/Controllers/AdminController.php

Public method home: home. See the behavior chapters for the associated screen, relation, cast, or transaction.

Public method dashboard: dashboard. See the behavior chapters for the associated screen, relation, cast, or transaction.

Public method logs: logs. See the behavior chapters for the associated screen, relation, cast, or transaction.

Public method analytics: analytics. See the behavior chapters for the associated screen, relation, cast, or transaction.

Public method alerts: alerts. See the behavior chapters for the associated screen, relation, cast, or transaction.

Public method campuses: campuses. See the behavior chapters for the associated screen, relation, cast, or transaction.

Public method settings: settings. See the behavior chapters for the associated screen, relation, cast, or transaction.

Private method adminViewData: admin view data. See the behavior chapters for the associated screen, relation, cast, or transaction.

Private method monitoringRecords: monitoring records. See the behavior chapters for the associated screen, relation, cast, or transaction.

Private method buildSummary: build summary. See the behavior chapters for the associated screen, relation, cast, or transaction.

Private method campusBreakdown: campus breakdown. See the behavior chapters for the associated screen, relation, cast, or transaction.

Private method hourlyTraffic: hourly traffic. See the behavior chapters for the associated screen, relation, cast, or transaction.

Private method statusDistribution: status distribution. See the behavior chapters for the associated screen, relation, cast, or transaction.

Private method riskDistribution: risk distribution. See the behavior chapters for the associated screen, relation, cast, or transaction.

Private method alertQueue: alert queue. See the behavior chapters for the associated screen, relation, cast, or transaction.

Private method notifications: notifications. See the behavior chapters for the associated screen, relation, cast, or transaction.

Private method alertSummary: alert summary. See the behavior chapters for the associated screen, relation, cast, or transaction.

Private method campusOperations: campus operations. See the behavior chapters for the associated screen, relation, cast, or transaction.

Private method settingsGroups: settings groups. See the behavior chapters for the associated screen, relation, cast, or transaction.

# app/Http/Controllers/AuthController.php

Public method showLogin: show login. See the behavior chapters for the associated screen, relation, cast, or transaction.

Public method login: login. See the behavior chapters for the associated screen, relation, cast, or transaction.

Public method logout: logout. See the behavior chapters for the associated screen, relation, cast, or transaction.

# app/Http/Controllers/Controller.php

# app/Http/Controllers/DeviceController.php

Public method register: register. See the behavior chapters for the associated screen, relation, cast, or transaction.

# app/Http/Controllers/PhishingLogController.php

Public method store: store. See the behavior chapters for the associated screen, relation, cast, or transaction.

# app/Http/Middleware/EnsureStaticAdminAuthenticated.php

Public method handle: handle. See the behavior chapters for the associated screen, relation, cast, or transaction.

# app/Models/Alert.php

Public method campus: campus. See the behavior chapters for the associated screen, relation, cast, or transaction.

# app/Models/Campus.php

Protected method casts: casts. See the behavior chapters for the associated screen, relation, cast, or transaction.

Public method phishingLogs: phishing logs. See the behavior chapters for the associated screen, relation, cast, or transaction.

# app/Models/Device.php

Protected method casts: casts. See the behavior chapters for the associated screen, relation, cast, or transaction.

# app/Models/PhishingLog.php

Protected method casts: casts. See the behavior chapters for the associated screen, relation, cast, or transaction.

Protected method detectedAt: detected at. See the behavior chapters for the associated screen, relation, cast, or transaction.

Public method device: device. See the behavior chapters for the associated screen, relation, cast, or transaction.

Public method campus: campus. See the behavior chapters for the associated screen, relation, cast, or transaction.

# app/Models/SystemSetting.php

# app/Models/User.php

Protected method casts: casts. See the behavior chapters for the associated screen, relation, cast, or transaction.

# app/Providers/AppServiceProvider.php

Public method register: register. See the behavior chapters for the associated screen, relation, cast, or transaction.

Public method boot: boot. See the behavior chapters for the associated screen, relation, cast, or transaction.

# 18. Dependency and build declarations

# composer.json

require / php: ^8.3

require / laravel/framework: ^13.0

require / laravel/tinker: ^3.0

require-dev / fakerphp/faker: ^1.23

require-dev / laravel/pail: ^1.2.5

require-dev / laravel/pint: ^1.27

require-dev / mockery/mockery: ^1.6

require-dev / nunomaduro/collision: ^8.6

require-dev / pestphp/pest: ^4.6

require-dev / pestphp/pest-plugin-laravel: ^4.1

scripts / setup: composer install; @php -r "file_exists('.env') || copy('.env.example', '.env');"; @php artisan key:generate; @php artisan migrate --force; npm install --ignore-scripts; npm run build

scripts / dev: Composer\Config::disableProcessTimeout; npx concurrently -c "#93c5fd,#c4b5fd,#fdba74" "php artisan serve" "php artisan queue:listen --tries=1" "npm run dev" --names='server,queue,vite'

scripts / test: @php artisan config:clear --ansi; @php artisan test

scripts / post-autoload-dump: Illuminate\Foundation\ComposerScripts::postAutoloadDump; @php artisan package:discover --ansi

scripts / post-update-cmd: @php artisan vendor:publish --tag=laravel-assets --ansi --force; @php artisan boost:update --ansi

scripts / post-root-package-install: @php -r "file_exists('.env') || copy('.env.example', '.env');"

scripts / post-create-project-cmd: @php artisan key:generate --ansi; @php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');"; @php artisan migrate --graceful --ansi

scripts / pre-package-uninstall: Illuminate\Foundation\ComposerScripts::prePackageUninstall

# package.json

devDependencies / @tailwindcss/vite: ^4.0.0

devDependencies / concurrently: ^9.0.1

devDependencies / laravel-vite-plugin: ^3.0.0

devDependencies / tailwindcss: ^4.0.0

devDependencies / vite: ^8.0.0

scripts / build: vite build

scripts / dev: vite

# public/home/package.json

dependencies / @fortawesome/fontawesome-free: ^5.15.3

dependencies / @popperjs/core: ^2.6.0

dependencies / bootstrap: 5.0.1

dependencies / bootstrap-icons: ^1.5.0

dependencies / feather-icons: ^4.28.0

dependencies / is_js: ^0.9.0

devDependencies / @babel/core: ^7.10.4

devDependencies / @babel/plugin-transform-strict-mode: ^7.10.4

devDependencies / @babel/preset-env: ^7.10.4

devDependencies / ansi: ^0.3.1

devDependencies / browser-sync: ^2.26.10

devDependencies / cross-env: ^7.0.2

devDependencies / del: ^5.1.0

devDependencies / dotenv: ^8.2.0

devDependencies / eslint-config-airbnb: ^18.2.0

devDependencies / eslint-plugin-import: ^2.22.0

devDependencies / eslint-plugin-prettier: ^3.1.4

devDependencies / gh-pages: ^3.1.0

devDependencies / gulp: ^4.0.2

devDependencies / gulp-autoprefixer: ^7.0.1

devDependencies / gulp-babel: ^8.0.0

devDependencies / gulp-clone: ^2.0.1

devDependencies / gulp-concat: ^2.6.1

devDependencies / gulp-eslint: ^6.0.0

devDependencies / gulp-if: ^3.0.0

devDependencies / gulp-jsbeautifier: ^3.0.1

devDependencies / gulp-pug: ^4.0.1

devDependencies / gulp-rename: ^2.0.0

devDependencies / gulp-replace: ^1.0.0

devDependencies / gulp-rtlcss: ^1.4.1

devDependencies / gulp-sass: ^4.1.0

devDependencies / gulp-sourcemaps: ^2.6.5

devDependencies / gulp-uglify: ^3.0.2

devDependencies / gulp-w3cjs: ^1.3.2

devDependencies / merge-stream: ^2.0.0

devDependencies / pretty: ^2.0.0

devDependencies / require-dir: ^1.2.0

devDependencies / through2: ^4.0.2

devDependencies / webpack-stream: ^6.1.0

devDependencies / yargs: ^15.4.0

scripts / start: gulp

scripts / build: cross-env MODE=PROD gulp build

scripts / build:test: cross-env MODE=PROD gulp build:test

scripts / live: gulp compile:all && cross-env MODE=PROD gulp live

scripts / publish: git subtree push --prefix live origin gh-pages

scripts / localhost: ssh -R 80:localhost:3000 ssh.localhost.run

scripts / deploy: gh-pages -d live

scripts / deploy:debug: NODE_DEBUG=gh-pages npm run deploy

# public/template/package.json

dependencies / @popperjs/core: ^2.11.8

dependencies / @tabler/icons: ^3.35.0

dependencies / @tabler/icons-webfont: ^3.35.0

dependencies / apexcharts: ^5.3.6

dependencies / bootstrap: ^5.3.8

dependencies / bootstrap-icons: ^1.13.1

dependencies / sass: 1.77.6

dependencies / vite: ^6.4.1

devDependencies / fast-glob: ^3.3.3

scripts / dev: vite

scripts / build: vite build

scripts / preview: vite preview

# extension-setup/api/requirements.txt

Declared dependency: fastapi==0.115.0

Declared dependency: uvicorn==0.30.6

Declared dependency: numpy==2.5.4

Declared dependency: scipy==1.18.1

Declared dependency: pandas==2.3.3

Declared dependency: scikit-learn==1.9.1

Declared dependency: joblib==1.6.0

Declared dependency: requests==2.32.3

Declared dependency: python-dotenv==1.0.1

Declared dependency: gdown==5.2.0

# extension-setup/api/requirements-training.txt

Declared dependency: -r requirements.txt

Declared dependency: matplotlib==3.11.2

Declared dependency: tldextract==5.4.0

Declared dependency: ipykernel>=6

Declared dependency: nbformat>=5

Declared dependency: nbclient>=0.10

Version strings above are repository declarations, not verification that those versions are available or installed. composer.lock and package-lock.json freeze dependency resolution. public/home uses its own Gulp/Pug/SCSS theme source and compiled output; public/template contains the administration template assets and its own build metadata. resources/views contains the Laravel page templates actually selected by application routes.

# 19. Configuration variable name register

config/app.php: APP_DEBUG, APP_ENV, APP_FAKER_LOCALE, APP_FALLBACK_LOCALE, APP_KEY, APP_LOCALE, APP_MAINTENANCE_DRIVER, APP_MAINTENANCE_STORE, APP_NAME, APP_PREVIOUS_KEYS, APP_TIMEZONE, APP_URL. Values come from environment or source defaults; private environment values are omitted.

config/auth.php: AUTH_GUARD, AUTH_MODEL, AUTH_PASSWORD_BROKER, AUTH_PASSWORD_RESET_TOKEN_TABLE, AUTH_PASSWORD_TIMEOUT. Values come from environment or source defaults; private environment values are omitted.

config/cache.php: APP_NAME, AWS_ACCESS_KEY_ID, AWS_DEFAULT_REGION, AWS_SECRET_ACCESS_KEY, CACHE_PREFIX, CACHE_STORE, DB_CACHE_CONNECTION, DB_CACHE_LOCK_CONNECTION, DB_CACHE_LOCK_TABLE, DB_CACHE_TABLE, DYNAMODB_CACHE_TABLE, DYNAMODB_ENDPOINT, MEMCACHED_HOST, MEMCACHED_PASSWORD, MEMCACHED_PERSISTENT_ID, MEMCACHED_PORT, MEMCACHED_USERNAME, REDIS_CACHE_CONNECTION, REDIS_CACHE_LOCK_CONNECTION. Values come from environment or source defaults; private environment values are omitted.

config/database.php: APP_NAME, DATABASE_URL, DB_CHARSET, DB_COLLATION, DB_CONNECTION, DB_DATABASE, DB_ENCRYPT, DB_FOREIGN_KEYS, DB_HOST, DB_PASSWORD, DB_PORT, DB_SOCKET, DB_SSLMODE, DB_TRUST_SERVER_CERTIFICATE, DB_URL, DB_USERNAME, MYSQL_ATTR_SSL_CA, REDIS_BACKOFF_ALGORITHM, REDIS_BACKOFF_BASE, REDIS_BACKOFF_CAP, REDIS_CACHE_DB, REDIS_CLIENT, REDIS_CLUSTER, REDIS_DB, REDIS_HOST, REDIS_MAX_RETRIES, REDIS_PASSWORD, REDIS_PERSISTENT, REDIS_PORT, REDIS_PREFIX, REDIS_URL, REDIS_USERNAME. Values come from environment or source defaults; private environment values are omitted.

config/filesystems.php: APP_URL, AWS_ACCESS_KEY_ID, AWS_BUCKET, AWS_DEFAULT_REGION, AWS_ENDPOINT, AWS_SECRET_ACCESS_KEY, AWS_URL, AWS_USE_PATH_STYLE_ENDPOINT, FILESYSTEM_DISK. Values come from environment or source defaults; private environment values are omitted.

config/logging.php: APP_NAME, LOG_CHANNEL, LOG_DAILY_DAYS, LOG_DEPRECATIONS_CHANNEL, LOG_DEPRECATIONS_TRACE, LOG_LEVEL, LOG_PAPERTRAIL_HANDLER, LOG_SLACK_EMOJI, LOG_SLACK_USERNAME, LOG_SLACK_WEBHOOK_URL, LOG_STACK, LOG_STDERR_FORMATTER, LOG_SYSLOG_FACILITY, PAPERTRAIL_PORT, PAPERTRAIL_URL. Values come from environment or source defaults; private environment values are omitted.

config/mail.php: APP_NAME, APP_URL, MAIL_EHLO_DOMAIN, MAIL_FROM_ADDRESS, MAIL_FROM_NAME, MAIL_HOST, MAIL_LOG_CHANNEL, MAIL_MAILER, MAIL_PASSWORD, MAIL_PORT, MAIL_SCHEME, MAIL_SENDMAIL_PATH, MAIL_URL, MAIL_USERNAME, POSTMARK_MESSAGE_STREAM_ID. Values come from environment or source defaults; private environment values are omitted.

config/queue.php: AWS_ACCESS_KEY_ID, AWS_DEFAULT_REGION, AWS_SECRET_ACCESS_KEY, BEANSTALKD_QUEUE, BEANSTALKD_QUEUE_HOST, BEANSTALKD_QUEUE_RETRY_AFTER, DB_CONNECTION, DB_QUEUE, DB_QUEUE_CONNECTION, DB_QUEUE_RETRY_AFTER, DB_QUEUE_TABLE, QUEUE_CONNECTION, QUEUE_FAILED_DRIVER, REDIS_QUEUE, REDIS_QUEUE_CONNECTION, REDIS_QUEUE_RETRY_AFTER, SQS_PREFIX, SQS_QUEUE, SQS_SUFFIX. Values come from environment or source defaults; private environment values are omitted.

config/services.php: AWS_ACCESS_KEY_ID, AWS_DEFAULT_REGION, AWS_SECRET_ACCESS_KEY, POSTMARK_API_KEY, RESEND_API_KEY, SLACK_BOT_USER_DEFAULT_CHANNEL, SLACK_BOT_USER_OAUTH_TOKEN. Values come from environment or source defaults; private environment values are omitted.

config/session.php: APP_NAME, SESSION_CONNECTION, SESSION_DOMAIN, SESSION_DRIVER, SESSION_ENCRYPT, SESSION_EXPIRE_ON_CLOSE, SESSION_HTTP_ONLY, SESSION_LIFETIME, SESSION_PARTITIONED_COOKIE, SESSION_PATH, SESSION_SAME_SITE, SESSION_SECURE_COOKIE, SESSION_STORE, SESSION_TABLE. Values come from environment or source defaults; private environment values are omitted.

extension-setup/api/app.py: GOOGLE_API_KEY, PHISHTANK_FILE_ID. Values come from environment or source defaults; private environment values are omitted.

# 20. Dataset and research artifact register

Raw dataset rows and historical browsing URLs are not reproduced. CSV schemas, file sizes, and names below explain available data without publishing their contents. Artifacts are research evidence, not automatic production deployment. Serialized bundles are inventoried as files only, never executed for extraction.

dataset: 10 files; 72068987 bytes. Extensions: .csv=2, .joblib=4, .json=1, .pkl=2, .zip=1.

extension-setup/api/dataset/phishtank.csv column headings: phish_id, url, phish_detail_url, submission_time, verified, verification_time, online, target.

extension-setup/api/dataset/urlset.csv/urlset.csv column headings: domain, ranking, mld_res, mld.ps_res, card_rem, ratio_Rrem, ratio_Arem, jaccard_RR, jaccard_RA, jaccard_AR, jaccard_AA, jaccard_ARrd, jaccard_ARrem, label.

training_data: 26 files; 211051718 bytes. Extensions: .csv=14, .json=8, .log=1, .md=3.

extension-setup/api/training_data/data2.csv column headings: URL, Label.

extension-setup/api/training_data/data3.csv column headings: domain, ranking, isIp, valid, activeDuration, urlLen, is@, isredirect, haveDash, domainLen, nosOfSubdomain, label.

extension-setup/api/training_data/data4.csv column headings: url, type.

extension-setup/api/training_data/exclude.csv column headings: Index, UsingIP, LongURL, ShortURL, Symbol@, Redirecting//, PrefixSuffix-, SubDomains, HTTPS, DomainRegLen, Favicon, NonStdPort, HTTPSDomainURL, RequestURL, AnchorURL, LinksInScriptTags, ServerFormHandler, InfoEmail, AbnormalURL, WebsiteForwarding, StatusBarCust, DisableRightClick, UsingPopupWindow, IframeRedirection, AgeofDomain, DNSRecording, WebsiteTraffic, PageRank, GoogleIndex, LinksPointingToPage, StatsReport, class.

extension-setup/api/training_data/exclude1.csv column headings: domain, ranking, mld_res, mld.ps_res, card_rem, ratio_Rrem, ratio_Arem, jaccard_RR, jaccard_RA, jaccard_AR, jaccard_AA, jaccard_ARrd, jaccard_ARrem, label.

extension-setup/api/training_data/experiments/phishstorm_restored_v1/contradictory_reference_labels.csv column headings: reference_line, reference_url, url, reference_label.

extension-setup/api/training_data/experiments/phishstorm_restored_v1/data4.csv column headings: url, type.

extension-setup/api/training_data/experiments/phishstorm_restored_v1/reference_quarantine.csv column headings: reference_line, reason.

extension-setup/api/training_data/experiments/phishstorm_restored_v1/source_label_changes.csv column headings: url, original_type, restored_type, reference_sha256.

extension-setup/api/training_data/phishstorm_original.csv column headings: domain, ranking, mld_res, mld.ps_res, card_rem, ratio_Rrem, ratio_Arem, jaccard_RR, jaccard_RA, jaccard_AR, jaccard_AA, jaccard_ARrd, jaccard_ARrem, label.

extension-setup/api/training_data/phishtank.csv column headings: phish_id, url, phish_detail_url, submission_time, verified, verification_time, online, target.

extension-setup/api/training_data/reviewed_training_urls.csv column headings: url, label, evidence, reviewed_by, reviewed_at.

extension-setup/api/training_data/reviewed_urls.csv column headings: url, label, notes.

artifacts: 362 files; 888210029 bytes. Extensions: (none)=15, .csv=181, .ipynb=8, .joblib=24, .json=36, .log=5, .md=7, .py=61, .txt=21, .yaml=4.

# 21. Existing written documentation, extracted as text

The following preserves explanatory prose from project-maintained notes, including historical status. Earlier chapters resolve material differences in favor of current source. Historical statements, commands, metrics, or deployment observations below are attributed to their source file and are not new verification. Code fences are omitted because this is a prose documentation PDF; source declarations and contracts are covered elsewhere. Links and paths remain text references.

# Source notes: README.md

<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center"> <a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a> <a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a> <a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a> <a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a> </p>

About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

Simple, fast routing engine (https://laravel.com/docs/routing).

Powerful dependency injection container (https://laravel.com/docs/container).

Multiple back-ends for session (https://laravel.com/docs/session) and cache (https://laravel.com/docs/cache) storage.

Expressive, intuitive database ORM (https://laravel.com/docs/eloquent).

Database agnostic schema migrations (https://laravel.com/docs/migrations).

Robust background job processing (https://laravel.com/docs/queues).

Real-time event broadcasting (https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

Learning Laravel

Laravel has the most extensive and thorough documentation (https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, Laracasts (https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on Laravel Learn (https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install Laravel Boost (https://laravel.com/docs/ai) to supercharge your AI workflow:

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the Laravel documentation (https://laravel.com/docs/contributions).

Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the Code of Conduct (https://laravel.com/docs/contributions#code-of-conduct).

Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via taylor@laravel.com (mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

License

The Laravel framework is open-sourced software licensed under the MIT license (https://opensource.org/licenses/MIT).

# Source notes: extension-setup/api/README.md

Phishing Detection API

FastAPI backend for the browser extension. Inference uses the retrained character TF-IDF + Logistic Regression bundle at dataset/phishing_url_bundle.joblib, with its saved warning threshold and optional validated blocking threshold. There is no fallback to the old numeric-feature model or metadata coefficients.

Deploy to Render

Commit and push the API changes, including the model binary and phishing_url_utils.py. Keep .env out of Git. Do not include the old .pkl or Random Forest files when creating a fresh API-only repository.

For a Web Service connected to the full monitoring-system repository:

| Setting | Value | | --- | --- | | Runtime | Python 3 | | Root Directory | extension-setup/api | | Build Command | pip install -r requirements.txt | | Start Command | uvicorn app:app --host 0.0.0.0 --port $PORT --workers 1 | | Health Check Path | /health | | Environment | PYTHON_VERSION=3.12.10 |

Alternatively create a Blueprint using extension-setup/api/render.yaml as its Blueprint path. That file assumes the full repository layout. If deploying an API-only repository, clear Root Directory / remove rootDir from the Blueprint.

The pinned scikit-learn, NumPy, SciPy, pandas, and joblib versions match the training environment. Keep them synchronized with any replacement model. Python 3.12 is specified in .python-version; the Blueprint pins 3.12.10. runtime.txt is retained for older tooling; Render uses PYTHON_VERSION or .python-version.

Set these environment variables as needed:

GOOGLE_API_KEY: optional Google Safe Browsing checks.

PHISHTANK_FILE_ID: optional public Google Drive CSV ID, downloaded at startup

only when the local CSV is absent. Existing dataset/phishtank.csv is ignored by Git; set this ID if you want a blocklist on Render without committing it.

MODEL_PATH / PHISHTANK_PATH: optional path overrides, relative to the API

directory unless absolute. Model default: dataset/phishing_url_bundle.joblib.

The model is loaded and smoke-tested at startup. A missing or incompatible model fails startup instead of returning misleading safe results. /health returns 200 only while the model is loaded. Optional PhishTank/Google failures leave ML detection enabled. One worker avoids loading multiple copies of the model.

Verify after deployment

1. Visit https://YOUR-SERVICE.onrender.com/health and check healthy: true.

2. Visit / and check model_name and model_threshold.

3. Call /check_url?url=https%3A%2F%2Fexample.com%2F. Scores are predictions,

not independently verified threat verdicts.

4. Configure logging_api_url in each extension config to your Hostinger website's /api/phishing-logs endpoint. Logging is handled by Laravel/MySQL, not Render.

5. Set prediction_api_url in each extension's config.json to the Render

/check_url?url= address. Reload the extension after changing its configuration.

/check_url preserves url, score, ml_engine, risk_level, status, and reason, adding model_threshold. Model scores at or above the saved threshold produce a non-blocking Medium / Suspicious warning unless a separate validated block threshold is present. Verified blocklist or Google matches can still block. Below threshold, multiple heuristic patterns can produce Medium / Suspicious. This intentionally replaces the old fixed 0.70/0.85 score rules. Path/query case is preserved to match training preprocessing. Submitted target URLs are not fetched.

Run locally

Copy .envexample to .env for local settings. /docs provides the interactive API documentation. In a development environment install httpx, then run python -m unittest discover -s tests -v for the offline API checks.

Render references: FastAPI deployment (https://render.com/docs/deploy-fastapi), Python version configuration (https://render.com/docs/python-version), and Blueprint specification (https://render.com/docs/blueprint-spec).

Logging architecture

Render only serves predictions. /logs and Supabase configuration have been removed. The extensions submit logs directly to the Laravel /api/phishing-logs endpoint. See ../HOSTINGER_SETUP.md for MySQL deployment and all six extensions.

One-run quality workflow (v4)

Use retrain_phishing_urls.ipynb with the registered PhishBlock kernel, or execute train_model.py in that same Python environment. It audits coverage and labels, accepts evidence-backed training reviews, compares component/structure/ensemble features on frozen splits, and exports a validation review queue. Runtime promotion is guarded by warning, block, homepage, and reload checks.

Older models now warn instead of blocking solely from their score. New models can block only with an approved two-stage decision policy. PhishTank/Google matches remain blocking signals. The six extensions display warnings without covering the page. Failed v4 candidates are rejected by API startup.

Read RETRAIN_AND_DEPLOY.md (RETRAIN_AND_DEPLOY.md) for the one-command runner, reviewed CSV format, exact quality gates, report locations, and push instructions.

Final strict release

Strict mode is the explicit selected profile; recall >=80% remains mandatory. A passing v5 notebook run generates github_api_release/ under its run directory, with an approved model, exact helper, release_utils.py, verification script, standalone Render configuration, model report, and file hashes. Copy the folder contents into the standalone API repo only when READY_FOR_GITHUB is True. Failed runs preserve the runtime model and generate no production package. See RETRAIN_AND_DEPLOY.md for verification and push steps. No automatic push occurs.

# Source notes: extension-setup/api/RETRAIN_AND_DEPLOY.md

Ready to retrain the source-restored experiment

The original PhishStorm ZIP has now been supplied and checked. The active experiment is phishstorm_restored_v1, with 92,005 restored source labels in a separate derived dataset. The notebook selects it automatically. Use the PhishBlock kernel, restart, and Run All. Original datasets and old splits remain unchanged. Strict >=80% recall and all other gates still apply. No replacement model has been trained or deployed by this dataset repair.

Earlier source-access warnings below are historical and are superseded by training_data/phishstorm_reference_verification.json and training_data/source_restored_experiment_manifest.json.

---

Data provenance work required before the next training run

Read training_data/PROVENANCE_FINDINGS.md. A full source-label audit identified 92,005 disagreements against a local PhishStorm-format reference. The original publisher archive is currently inaccessible (HTTP 403), so none was automatically applied. phishstorm_reference_verification.json remains pending. Do not repeatedly rerun unchanged data or treat proposed corrections as verified ground truth.

After authenticating the original snapshot, prepare_verified_dataset.py creates and optionally activates a NEW experiment without overwriting the old labels or split. The notebook/runner automatically uses that verified active experiment. The strict deployment requirements are unchanged.

---

Final profile: STRICT

You selected strict deployment. The 80% recall requirement remains mandatory; no assistive/relaxed acceptance profile is enabled.

After a real retraining run, look for READY_FOR_GITHUB: True. Only then does artifacts/url_retraining_v5_*/github_api_release/ contain an approved standalone API. Copy that folder's contents into the root of your phishblock_api repository, install its requirements, run python verify_release.py, review changes, then push. Its Render Root Directory is blank; the generated Blueprint removes the monorepo rootDir. The package excludes your .env, raw corpora, old models, and private logs. It includes the evaluated bundle, exact inference helper, strict verifier, pinned dependencies, MODEL_CARD.md, and SHA-256 file manifest.

The acceptance limits are rechecked independently of editable notebook settings: precision >=90%, recall >=80%, and benign warning rate <=3% on validation/test; homepage regressions must pass. When ML blocking is enabled it also needs >=98% precision, <=0.5% benign block rate, and sufficient validation/test support. Runtime dependency pins must match the training environment. New v5 bundles carry strict metric proof, thresholds, and helper hash checked by API startup.

A failing run prints READY_FOR_GITHUB: False, preserves the runtime model, and creates no release package. Editing PROMOTE_MODEL=True cannot bypass the gates. Changing code or repackaging cannot make a 66% recall model satisfy 80% recall. The latest failed candidate remains research-only. Do not repeatedly rerun unchanged data expecting different accuracy; improve verified training evidence.

---

One-run retraining and deployment (v4)

Execute

In VS Code select PhishBlock Training (Python 3.12), restart the kernel, then Run All in retrain_phishing_urls.ipynb. The setup reads the exact helper source to avoid stale imports. The Matplotlib dependency is healthy in this registered environment; do not select an unrelated project's .venv.

You can also run the identical notebook code from a terminal, without Jupyter:

On a new machine, create a Python 3.12 environment, install requirements-training.txt, then run python train_model.py from this folder. The runner installs nothing, logs to artifacts/training_*.log, and never pushes GitHub or deploys Render. Exit code 0 means a model was promoted; code 2 means a completed research run that did not promote. An exception means execution failed. --no-promote preserves the runtime model even if every check passes.

What Run All does

1. Audits source labels and removes contradictory equivalent URLs.

2. Reuses the permanent training_data/frozen_splits_v3.json assignments. This

filename remains v3 deliberately: v4 features do not redefine evaluation rows.

3. Reads the optional reviewed-training CSV. Only reviewed rows with an explicit

0/1 label, evidence, reviewer, and date are accepted. Frozen validation/test domains are excluded from training, even if someone submits a review for them.

4. Fits raw-text diagnostics, www-invariant text, separate hostname/path/query

features, nonlinear structure features, and validation-selected soft voting. All compared approaches receive the same training rows and sample weights.

5. Selects models and warning/block thresholds on validation only, then evaluates

the frozen choices on test. The raw-URL baseline cannot be a production winner.

6. Exports review queues, coverage, metrics, a reload-verified bundle, and a push

decision. It backs up and replaces the runtime model only when all gates pass.

No known domain is automatically trusted. A substring such as google. no longer bypasses the extension check. Suspicious results show a dismissible banner; blocking results show the existing intervention overlay in all six extensions.

Warning and automatic-block policy

The warning/detection threshold keeps the existing acceptance requirements on both validation and test: precision >=90%, phishing recall >=80%, and benign warning rate <=3%, plus enough test data per class and all homepage regressions. These targets were not reduced to make a weak model pass.

Automatic ML blocking is separate: validation precision >=98%, benign block rate <=0.5%, and at least 50 block predictions. If no point qualifies, ML blocking is DISABLED before test evaluation. If a blocking threshold is selected, test must also satisfy its precision/FPR/support limits or promotion is blocked. Warnings still work without model-only blocking. These historical metrics are acceptance checks, not guarantees on future traffic.

The API treats older bundles as warning-only; a large uncalibrated score alone is insufficient to block. A new bundle must carry validated decision thresholds and deployment_approved: true. Failed v4 research candidates cannot be served. Verified PhishTank and configured Google threat matches can still block.

Reading the result

The final LOCAL_MODEL_UPDATED value is decisive:

True: every required gate passed. The evaluated model now exists at

dataset/phishing_url_bundle.joblib; the previous runtime model is backed up in the run folder. Review the metrics and Git diff before pushing.

False: the runtime model was preserved. Read gate_failures and the review

reports. Do not rename/copy an unapproved candidate into the deployment folder or lower the requirements merely to obtain a passing message.

Add better training evidence without code edits

Fill training_data/reviewed_training_urls.csv with:

Use 0 for independently reviewed benign URLs and 1 for independently verified phishing. Include the evidence/source, actual reviewer, and ISO date. An empty file containing only the header is valid and Run All works without manual edits. No new URLs are automatically labeled safe from their domain or current score.

Each run creates validation_review_queue.csv. Review labels are deliberately blank. A disagreement does not prove the source label is wrong. Use the queue to investigate data quality and collect independent TRAINING examples, not blindly copy predictions into the training CSV. Reviews from frozen validation/test domains are recorded as excluded; changing their labels would need a new named experiment rather than silently contaminating the current holdout.

Other outputs include training_coverage.csv, training_review_decisions.csv, validation_comparison.csv, paired_test_comparison.csv, test_by_source.csv, homepage_regression_checks.csv, and metrics_and_manifest.json under artifacts/url_retraining_v4_*/. Test mistakes are for reporting, not auto-training. The initial validation-only audit is in artifacts/quality_audit_*/.

Push and deploy

For a passing run, include the promoted runtime model, phishing_url_utils.py, app.py, pinned requirements.txt, and current deployment configuration. For reproducibility include the notebook, retraining_utils.py, refresh_training_helpers.py, train_model.py, requirements-training.txt, frozen split JSON, and reviewed CSVs. Keep raw corpora, artifacts, and .env local. Deploy the updated extension background/content/popup scripts for all six campuses and the Laravel controller if logging the new decision metadata.

For the separate phishblock_api repository put the API contents at its root; Render Root Directory must be blank and its Blueprint must omit the monorepo rootDir. For the full monitoring repository retain extension-setup/api. The notebook/runner never pushes either repository automatically.

After deployment verify /health, the warning/block thresholds in /, and the Facebook/Instagram homepage decisions. Reload all six extensions to clear old verdicts. Review the non-blocking warning and strong-signal blocking behavior.

The source data are historical and the test set has been inspected repeatedly. Keep it as development evidence; use a recently collected independent final set before making strong production-accuracy claims. Better data quality requires real review evidence; rerunning unchanged data cannot guarantee a better model.

# Source notes: extension-setup/api/training_data/PROVENANCE_FINDINGS.md

Source identity resolved ? 2026-10-25

The user supplied the Aalto publisher archive at dataset/urlset.csv.zip. Its Windows download-origin metadata identifies research.aalto.fi, and the ZIP member, extracted CSV, and existing local reference share the exact SHA-256: f7229c4f5f105a74f5b72df71ed8154f9fca10b6535b488192434fd988c848d3. This is a documented user-supplied download verification, not a publisher-signed checksum claim. The canonical copy is training_data/phishstorm_original.csv.

The phishstorm_restored_v1 derived experiment is now active. It restores 92,005 historical source labels in both directions, with a full change log, immutable CSV hash, source evidence, and a separate frozen split path. Malformed or ambiguous reference records were quarantined. Original data4.csv, the old frozen split, and the runtime model were not overwritten. Other threat categories were kept.

The notebook automatically selects the new experiment. Use the PhishBlock kernel, restart, and Run All. Do not directly compare these metrics with the previous incorrect-label experiment. The strict release limits are unchanged; historical source restoration is not proof of current live URL status or a fresh final test.

The report below is the historical audit that led to this repair. Its original HTTP-403/source-identity blocker has now been resolved by the supplied archive.

---

Dataset provenance findings (2026-10-25)

Confirmed facts

The local data4.csv contains all 651,191 records from the original Kaggle v1 malicious_phish.csv, plus one extra local record. The original was downloaded from the publisher API and compared as URL/type multisets, not just file size. Different line endings/file bytes do not imply altered labels. Both hashes and record counts are in DATASET_PROVENANCE.json.

The uploader declares PhishStorm as an upstream source used to expand phishing examples. Aalto's original record explicitly defines label 0 as legitimate and label 1 as phishing, and describes 48,009 examples of each class from 2014.

Primary sources:

https://www.kaggle.com/datasets/sid321axn/malicious-urls-dataset

https://research.aalto.fi/en/datasets/phishstorm-phishing-legitimate-url-dataset/

https://doi.org/10.24342/f49465b2-c68a-4182-9171-075f0ed797d5

Strong evidence of an aggregation/label-convention problem

The local exclude1.csv has the PhishStorm feature layout. After conservative parsing, exact normalized-URL joins found 96,062 matching aggregate records, with 92,005 disagreements:

47,673 aggregate phishing records match a local reference label of legitimate.

44,332 aggregate benign records match a local reference label of phishing.

This is consistent with inverted upstream label handling, not merely a shortage of familiar homepage examples. It can make meaningful recall/precision targets unreliable. The disagreement count is evidence for review, NOT a count of already confirmed corrections or current live phishing sites.

Important unresolved identity check

The local reference's identity is inferred; its original publisher bytes have not been authenticated. The official Aalto archive returns HTTP 403 from this environment, and the legacy university CSV endpoint returned no data. The parser quarantined 4,023 malformed/ambiguous records, excluded contradictory reference labels, and did not invent replacements. No model predictions, domain popularity, or majority votes were used as truth.

Accordingly, all proposed corrections remain pending. Raw data, existing train/validation/test labels, frozen split file, and runtime model are unchanged.

Prepared workflow

1. Obtain the original urlset.csv from Aalto's urlset.csv.zip (or identify an

existing original download). Save it as training_data/phishstorm_original.csv.

2. Authenticate that source and record the exact file checksum, reviewer, and

retrieval evidence in phishstorm_reference_verification.json. Its current status is pending. Do not mark the existing local copy verified merely because its schema looks similar or its labels improve the model's metrics.

3. Once verified, run:

python prepare_verified_dataset.py --verification training_data/phishstorm_reference_verification.json --activate

This creates a NEW source-restored dataset under training_data/experiments, with a complete change log and its own frozen split path. It never overwrites raw data4 or the old frozen split. Malware/defacement categories stay intact.

4. The notebook automatically discovers the verified active experiment. Restart

the PhishBlock kernel and Run All. Old and new label-set scores must not be compared as if the evaluation tasks were identical.

5. Strict >=80% recall and the other release gates remain unchanged. Historical

label restoration is not current threat verification; a fresh independent final dataset is still needed for strong production claims.

Until step 1/2 is complete, do NOT rerun the old dataset expecting a different result. The blocker is original-reference access, not a request to relax gates.

Local evidence

Full source comparison, quarantined reference rows, and proposed corrections: artifacts/source_label_audit_20261025T013618706250Z. The raw source snapshot and URL tables stay under ignored artifacts; no Git push or production deployment was performed.

# Source notes: extension-setup/api/training_data/README.md

Local training data

These CSVs were copied from the existing research workspace; they are historical snapshots, not freshly collected threat feeds. source_manifest.json records their original locations, sizes, and SHA-256 hashes. The notebook uses data4.csv and verified rows from phishtank.csv by default. It does not overwrite the API's runtime dataset/phishtank.csv or dataset/phishing_url_bundle.joblib.

data2.csv and data3.csv are optional because their label definitions must be confirmed before mixing them into a phishing-specific task. data1.csv and exclude.csv have no raw URL column; exclude1.csv has unconfirmed label provenance. These files are retained for research but skipped by this URL-text notebook. exclude1.csv also contains non-UTF-8 bytes; do not silently replace characters if you later import it.

reviewed_urls.csv records exact known homepage false positives as explicit training feedback. It is not a list of trusted domains, and it never bypasses inference or threat checks. Its domain groups are assigned to training, so results on those URLs are regression checks, not independent accuracy measurements. Only add independently reviewed labels; never label arbitrary URLs based solely on the model's current prediction.

Raw CSVs are ignored by Git to avoid shipping research corpora on every Render build. The notebook, reviewed corrections, and source manifest can be committed. Keep local backups of the raw CSVs for reproducibility.

Frozen v3 experiment

frozen_splits_v3.json is the permanent domain assignment and dataset fingerprint for the v3 experiment. Commit and retain it. Never delete or edit it to chase an accuracy score. Training-only sampling does not change the holdout. New data, labels, or correction-domain changes require a new experiment filename; results across different experiments are not a fair comparison. Only freshly refitted models are included in the v3 comparison, not the old deployed bundle.

Reviewed training additions (v4)

reviewed_training_urls.csv is a separate evidence-backed training input with url, label, evidence, reviewed_by, and reviewed_at columns. Its header-only default is intentional. New/reviewed training-domain URLs can be added without changing the base frozen dataset. Validation/test domain reviews are exported as excluded and never moved into training. Actual applied training rows/labels/weights and the reviewed CSV are fingerprinted in each run's manifest. Source-label changes to the BASE corpus still require a new named experiment.

Monitoring application feedback (2026-10-28)

The application owner supplied the exact host phish-block-monitoring.dissertationsystem.online as a benign example. Eight explicit application URLs, including /admin/dashboard, are recorded in reviewed_training_urls.csv with owner provenance. The dashboard was observed redirecting to the expected login page. These are training labels, not a blanket trust rule for this domain, other subdomains, or URLs containing this hostname. The live API initially returned Block (score 0.9650002434) for the dashboard. Retraining and deployment are required to change live predictions; adding CSV rows alone does not modify the running Render model. The standard frozen holdout and release acceptance gates still apply.

# Source notes: extension-setup/api/training_data/REVIEW_BATCH_2026-10-25.md

Reviewed-data audit: 2026-10-25

The 500-entry queue now has 250 false-positive disagreements and 250 missed-phishing disagreements under the existing labels, spanning 409 domain groups. The old raw-distance ranking returned only missed-phishing cases. The new queue normalizes confidence distance, balances error types, and caps one domain at three entries. Queue review labels remain blank; no prediction was imported as truth.

There are 272 cases whose original label differs from what data2.csv good/bad terminology suggests. This is an investigation flag, not proof: its target taxonomy/provenance may differ, snapshots are old, and legitimate websites can host compromised content. Existing labels and held-out domains have NOT been changed based on those flags.

Added 30 benign training feedback rows across 9 domain groups. Each records a published page URL, observed content purpose, reviewer identity, and review date. The reviewed pages include public documentation, homepages, support pages, government information, and a legitimate article about phishing. Representative sources include https://support.microsoft.com/en-us/windows/ and https://www.nasa.gov/news/recently-published/ . Only the exact reviewed URLs are labeled; their whole domains are not trusted or bypassed.

All 30 additions passed the training-only domain check. None entered validation or test. Frozen base dataset fingerprint remains 7916b005a3869d206de312b75bfdfecd04df2039048d907657f583ccbdb551ce. The new CSV is training feedback, not independent test evidence. More independently verified phishing and representative benign samples are still needed; these 30 examples are a starter batch and do not guarantee an accuracy improvement.

Local audit files (under artifacts/reviewed_data_audit_20261024T234709):

balanced_validation_review_queue.csv: actual disagreements + raw data2 labels.

reviewed_training_decisions.csv: 30 applied rows, zero held-out additions.

training_coverage.csv: updated training representation.

summary.json: counts and fingerprint.

Next: restart the PhishBlock kernel and Run All. The notebook automatically reads training_data/reviewed_training_urls.csv and uses the balanced queue logic. Quality gates and runtime model remain unchanged. A passed training run must still be reviewed before deployment; no Git push or Render deployment was performed.

# Source notes: extension-setup/extensions-version/README.md

Automatic campus computer names

Deploy the Laravel changes and run the devices migration before using extension version 1.2. For each campus folder, set logging_api_url in config.json to your deployed Laravel endpoint, for example https://your-monitoring-domain.example/api/phishing-logs. Keep campus_name set to that folder's campus. No computer number is needed. The registration endpoint is derived automatically from the logging URL.

Install the appropriate campus folder through Chrome's Load unpacked option, or reload an existing installation. On its first successful URL assessment, the extension registers with Laravel and receives a name such as SLSU-TO-PC-001. The UUID and assigned identity are stored in chrome.storage.local (phishblockDeviceUuid and phishblockDevice). The dashboard shows this assigned name. Detection timestamps remain UTC in transit and are displayed in Philippine time.

The UUID survives browser and PC restarts and extension updates. Each browser profile has its own identity. Uninstalling the extension or clearing its local storage creates a new identity; cloning browser profile storage can copy an identity. This does not read the Windows hostname or rename the extension in Chrome. Failed registration retries on the next assessment; failed log submissions are not queued. Blank logging URLs disable registration and logging while URL assessments continue.

Existing logs and older extensions with manual computer numbers remain supported. Automatic allocation skips existing log numbers; retire manual installations before rolling out automatic registration to avoid future overlap with manually chosen numbers.

# Source notes: extension-setup/HOSTINGER_SETUP.md

Hostinger MySQL logging and Render predictions

The six extensions (Bontoc, Hinunangan, Maasin, Main, SanJuan, and TO) now read their prediction and logging URLs from their own config.json files. Their campus identities and computer numbers are preserved.

Extension configuration

Each extension has these additional fields:

The prediction address comes from your Render deployment screenshot. Confirm it against your service's actual URL. Set logging_api_url in each of the six configs to https://YOUR-HOSTINGER-DOMAIN/api/phishing-logs. Include any subfolder prefix if the Laravel app is deployed under a subfolder. It is intentionally empty until the actual Hostinger address is known; predictions work while logs are skipped with a console message. Reload each extension after editing.

The existing <all_urls> host permission already covers the Hostinger address; the explicit Render permission has been updated. No database credentials belong in these extension files. Configure each installed computer's own number.

Hostinger backend

Deploy the updated Laravel application with its document root pointing to public/. The new stateless route POST /api/phishing-logs does not require a dashboard login or CSRF cookie. It validates input, limits submissions to 120 per minute per IP, writes phishing_logs, registers the campus, updates distinct computer counts, and creates alerts for suspicious/blocked results. The ingestion route is public and accepts reported extension data; it does not authenticate device identity. Dashboard access remains behind the existing login.

Set real MySQL credentials in Hostinger's Laravel .env, for example:

Use the database host shown in your Hostinger panel if it differs. Clear old DB_URL and PostgreSQL DATABASE_URL values if present. Keep your real APP_KEY and other existing app settings. The API uses Laravel's configured database; changing .env.example alone does not change your live database connection.

For a new database, run php artisan migrate --force after deployment, then php artisan optimize:clear and php artisan config:cache. Back up an existing database before applying migrations. No migrations or credential changes have been run against your real database by this update. Existing manually created tables must have a migration history compatible with the Laravel migrations; the supplied db_query_create.txt was written for PostgreSQL, not Hostinger MySQL, and should not be pasted into phpMyAdmin.

The existing migrations provide MySQL-compatible log, campus, and alert tables. The old PostgreSQL trigger setup remains for existing PostgreSQL deployments; MySQL campus/alert updates now run in the Laravel endpoint transaction instead.

Render

Deploy the updated extension-setup/api files. The saved model and threshold are unchanged. Render no longer provides /logs and no longer reads Supabase settings. Remove SUPABASE_URL and SUPABASE_KEY from the Render environment if present. Its /health endpoint continues to check model readiness.

If pushing to the separate phishblock_api GitHub repository, put the contents of extension-setup/api at that repository's root. Use a blank Root Directory in Render and remove rootDir: extension-setup/api from that repository's Blueprint. For deployment from the full monitoring repository, keep the existing rootDir.

Checks

The Python command needs the API requirements and development dependency httpx. PHP feature tests use an isolated SQLite database, and JavaScript tests stub all network calls. After deploying, browse a URL with one configured extension and verify the POST returns 201 and a matching record appears on the dashboard.

# 22. Full project file-purpose inventory

Each project file discovered outside installed dependencies, Git internals, caches, private environment files, and runtime storage is listed below with its role and size. The inventory includes static assets, historical artifacts, training corpora, compiled bundles, and retained backups as well as application code. No source file is implied to be loaded merely because it exists. Large installed third-party trees are summarized after the project inventory.

.editorconfig (275 bytes). Project configuration, build input, metadata, or retained supporting resource; purpose is indicated by path and filename.

.env.example (1283 bytes). Environment variable template; real private environment files are excluded.

.gitattributes (186 bytes). Project configuration, build input, metadata, or retained supporting resource; purpose is indicated by path and filename.

.gitignore (329 bytes). Project configuration, build input, metadata, or retained supporting resource; purpose is indicated by path and filename.

.npmrc (31 bytes). Project configuration, build input, metadata, or retained supporting resource; purpose is indicated by path and filename.

README.md (3700 bytes). Human-readable project/setup/research documentation; selected notes extracted earlier.

artisan (425 bytes). Laravel command-line entry point.

composer.json (2869 bytes). Project configuration, build input, metadata, or retained supporting resource; purpose is indicated by path and filename.

composer.lock (335203 bytes). Dependency resolution lock file for reproducible installation.

package-lock.json (64310 bytes). Dependency resolution lock file for reproducible installation.

package.json (386 bytes). Project configuration, build input, metadata, or retained supporting resource; purpose is indicated by path and filename.

phpunit.xml (1322 bytes). Project configuration, build input, metadata, or retained supporting resource; purpose is indicated by path and filename.

vite.config.js (436 bytes). Project configuration, build input, metadata, or retained supporting resource; purpose is indicated by path and filename.

app/Http/Controllers/AdminController.php (12291 bytes). HTTP request/controller implementation; contracts and flow documented in preceding chapters.

app/Http/Controllers/AuthController.php (1611 bytes). HTTP request/controller implementation; contracts and flow documented in preceding chapters.

app/Http/Controllers/Controller.php (77 bytes). HTTP request/controller implementation; contracts and flow documented in preceding chapters.

app/Http/Controllers/DeviceController.php (2421 bytes). HTTP request/controller implementation; contracts and flow documented in preceding chapters.

app/Http/Controllers/PhishingLogController.php (5029 bytes). HTTP request/controller implementation; contracts and flow documented in preceding chapters.

app/Http/Middleware/EnsureStaticAdminAuthenticated.php (563 bytes). Request access-control middleware for active administrators.

app/Models/Alert.php (337 bytes). Eloquent domain model defining persistence attributes, casts, or relationships.

app/Models/Campus.php (544 bytes). Eloquent domain model defining persistence attributes, casts, or relationships.

app/Models/Device.php (421 bytes). Eloquent domain model defining persistence attributes, casts, or relationships.

app/Models/PhishingLog.php (1241 bytes). Eloquent domain model defining persistence attributes, casts, or relationships.

app/Models/SystemSetting.php (178 bytes). Eloquent domain model defining persistence attributes, casts, or relationships.

app/Models/User.php (899 bytes). Eloquent domain model defining persistence attributes, casts, or relationships.

app/Providers/AppServiceProvider.php (426 bytes). Project configuration, build input, metadata, or retained supporting resource; purpose is indicated by path and filename.

bootstrap/app.php (587 bytes). Laravel application bootstrapping or provider registration.

bootstrap/providers.php (89 bytes). Laravel application bootstrapping or provider registration.

config/app.php (4301 bytes). Laravel app service configuration and environment-default declarations.

config/auth.php (4040 bytes). Laravel auth service configuration and environment-default declarations.

config/cache.php (4155 bytes). Laravel cache service configuration and environment-default declarations.

config/cors.php (314 bytes). Laravel cors service configuration and environment-default declarations.

config/database.php (7035 bytes). Laravel database service configuration and environment-default declarations.

config/filesystems.php (2532 bytes). Laravel filesystems service configuration and environment-default declarations.

config/logging.php (4340 bytes). Laravel logging service configuration and environment-default declarations.

config/mail.php (3631 bytes). Laravel mail service configuration and environment-default declarations.

config/queue.php (4199 bytes). Laravel queue service configuration and environment-default declarations.

config/services.php (1039 bytes). Laravel services service configuration and environment-default declarations.

config/session.php (8423 bytes). Laravel session service configuration and environment-default declarations.

database/.gitignore (10 bytes). Project configuration, build input, metadata, or retained supporting resource; purpose is indicated by path and filename.

database/factories/UserFactory.php (1044 bytes). Synthetic model fixture factory for tests/development.

database/migrations/0001_01_01_000000_create_users_table.php (1739 bytes). Ordered schema/data migration; field declarations listed in the database chapter.

database/migrations/0001_01_01_000001_create_cache_table.php (873 bytes). Ordered schema/data migration; field declarations listed in the database chapter.

database/migrations/0001_01_01_000002_create_jobs_table.php (1813 bytes). Ordered schema/data migration; field declarations listed in the database chapter.

database/migrations/2026_07_17_000000_ensure_phishing_logs_table.php (1175 bytes). Ordered schema/data migration; field declarations listed in the database chapter.

database/migrations/2026_07_17_000001_create_monitoring_support_tables.php (2406 bytes). Ordered schema/data migration; field declarations listed in the database chapter.

database/migrations/2026_07_17_000002_remove_action_from_phishing_logs.php (951 bytes). Ordered schema/data migration; field declarations listed in the database chapter.

database/migrations/2026_07_17_000003_automate_monitoring_records.php (904 bytes). Ordered schema/data migration; field declarations listed in the database chapter.

database/migrations/2026_10_28_000000_add_detected_at_to_phishing_logs.php (548 bytes). Ordered schema/data migration; field declarations listed in the database chapter.

database/migrations/2026_10_28_000001_create_devices_table.php (1297 bytes). Ordered schema/data migration; field declarations listed in the database chapter.

database/seeders/DatabaseSeeder.php (6770 bytes). Administrator and demonstration-data initialization; may modify existing records.

extension-setup/HOSTINGER_SETUP.md (4418 bytes). Human-readable project/setup/research documentation; selected notes extracted earlier.

extension-setup/api/.envexample (276 bytes). Environment variable template; real private environment files are excluded.

extension-setup/api/.gitignore (287 bytes). Project configuration, build input, metadata, or retained supporting resource; purpose is indicated by path and filename.

extension-setup/api/.python-version (6 bytes). Prediction-service deployment/runtime process configuration.

extension-setup/api/Procfile (62 bytes). Prediction-service deployment/runtime process configuration.

extension-setup/api/README.md (6014 bytes). Human-readable project/setup/research documentation; selected notes extracted earlier.

extension-setup/api/RETRAIN_AND_DEPLOY.md (10073 bytes). Human-readable project/setup/research documentation; selected notes extracted earlier.

extension-setup/api/app.py (10452 bytes). Python prediction, preprocessing, training, provenance, or release helper; function index lists declarations.

extension-setup/api/audit_dataset_provenance.py (7670 bytes). Python prediction, preprocessing, training, provenance, or release helper; function index lists declarations.

extension-setup/api/dataset_experiments.py (5786 bytes). Python prediction, preprocessing, training, provenance, or release helper; function index lists declarations.

extension-setup/api/db_query_create.txt (10811 bytes). Retained historical PostgreSQL database setup reference; not MySQL installation SQL.

extension-setup/api/phishing_url_utils.py (7064 bytes). Python prediction, preprocessing, training, provenance, or release helper; function index lists declarations.

extension-setup/api/prepare_verified_dataset.py (970 bytes). Python prediction, preprocessing, training, provenance, or release helper; function index lists declarations.

extension-setup/api/refresh_training_helpers.py (2840 bytes). Python prediction, preprocessing, training, provenance, or release helper; function index lists declarations.

extension-setup/api/release_utils.py (10519 bytes). Python prediction, preprocessing, training, provenance, or release helper; function index lists declarations.

extension-setup/api/render.yaml (404 bytes). Prediction-service deployment/runtime process configuration.

extension-setup/api/requirements-training.txt (97 bytes). Project configuration, build input, metadata, or retained supporting resource; purpose is indicated by path and filename.

extension-setup/api/requirements.txt (169 bytes). Project configuration, build input, metadata, or retained supporting resource; purpose is indicated by path and filename.

extension-setup/api/retrain_phishing_urls.ipynb (169623 bytes). Research notebook defining data preparation, fitting, validation, reporting, and guarded promotion.

extension-setup/api/retraining_utils.py (13697 bytes). Python prediction, preprocessing, training, provenance, or release helper; function index lists declarations.

extension-setup/api/runtime.txt (16 bytes). Prediction-service deployment/runtime process configuration.

extension-setup/api/train_model.py (2845 bytes). Python prediction, preprocessing, training, provenance, or release helper; function index lists declarations.

extension-setup/api/verify_release.py (520 bytes). Python prediction, preprocessing, training, provenance, or release helper; function index lists declarations.

extension-setup/api/artifacts/frozen_split_preparation.log (1203 bytes). Training execution transcript; contents not copied into this public document.

extension-setup/api/artifacts/training_20261028T145222701949Z.log (12389 bytes). Training execution transcript; contents not copied into this public document.

extension-setup/api/artifacts/notebook_backups/before_strict_release_20261025T002857.ipynb (170132 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/notebook_backups/before_v3_20261024T144419.ipynb (136958 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/notebook_backups/before_v4_20261024T204616.ipynb (135024 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/notebook_backups/legacy_v2_executed_20261024T144846.ipynb (128499 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/provenance_audit_20261025T013442/sources/kaggle_malicious_phish.csv (45664439 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/quality_audit_20261024T205038/audit.log (1779 bytes). Training execution transcript; contents not copied into this public document.

extension-setup/api/artifacts/quality_audit_20261024T205038/review_summary.csv (80 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/quality_audit_20261024T205038/training_coverage.csv (161 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/quality_audit_20261024T205038/validation_review_queue.csv (73370 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/reviewed_data_audit_20261024T234709/AUDIT_FINDINGS.md (2364 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/reviewed_data_audit_20261024T234709/audit.log (4793 bytes). Training execution transcript; contents not copied into this public document.

extension-setup/api/artifacts/reviewed_data_audit_20261024T234709/balanced_validation_review_queue.csv (97306 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/reviewed_data_audit_20261024T234709/reviewed_training_decisions.csv (10763 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/reviewed_data_audit_20261024T234709/summary.json (383 bytes). Research manifest, evaluation metrics, or release proof identified by the filename.

extension-setup/api/artifacts/reviewed_data_audit_20261024T234709/training_coverage.csv (199 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/source_experiment_smoke_20261025T014335/audit_dataset_provenance.py (7670 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/source_experiment_smoke_20261025T014335/dataset_experiments.py (5786 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/source_experiment_smoke_20261025T014335/phishing_url_utils.py (7064 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/source_experiment_smoke_20261025T014335/refresh_training_helpers.py (2825 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/source_experiment_smoke_20261025T014335/release_utils.py (9145 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/source_experiment_smoke_20261025T014335/retraining_utils.py (13697 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/source_experiment_smoke_20261025T014335/training_data/active_experiment.json (86 bytes). Research manifest, evaluation metrics, or release proof identified by the filename.

extension-setup/api/artifacts/source_experiment_smoke_20261025T014335/training_data/data4.csv (35290 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/source_experiment_smoke_20261025T014335/training_data/phishtank.csv (48 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/source_experiment_smoke_20261025T014335/training_data/reference.csv (230 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/source_experiment_smoke_20261025T014335/training_data/reviewed_training_urls.csv (9419 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/source_experiment_smoke_20261025T014335/training_data/reviewed_urls.csv (460 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/source_experiment_smoke_20261025T014335/training_data/verification.json (609 bytes). Research manifest, evaluation metrics, or release proof identified by the filename.

extension-setup/api/artifacts/source_experiment_smoke_20261025T014335/training_data/experiments/phishstorm_restored_v1/contradictory_reference_labels.csv (50 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/source_experiment_smoke_20261025T014335/training_data/experiments/phishstorm_restored_v1/data4.csv (35290 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/source_experiment_smoke_20261025T014335/training_data/experiments/phishstorm_restored_v1/experiment.json (1521 bytes). Research manifest, evaluation metrics, or release proof identified by the filename.

extension-setup/api/artifacts/source_experiment_smoke_20261025T014335/training_data/experiments/phishstorm_restored_v1/frozen_splits.json (83772 bytes). Research manifest, evaluation metrics, or release proof identified by the filename.

extension-setup/api/artifacts/source_experiment_smoke_20261025T014335/training_data/experiments/phishstorm_restored_v1/reference_quarantine.csv (23 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/source_experiment_smoke_20261025T014335/training_data/experiments/phishstorm_restored_v1/source_label_changes.csv (50 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/source_label_audit_20261025T013618706250Z/contradictory_reference_labels.csv (50 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/source_label_audit_20261025T013618706250Z/parsed_local_reference.csv (11733948 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/source_label_audit_20261025T013618706250Z/proposed_source_label_corrections.csv (32695930 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/source_label_audit_20261025T013618706250Z/quarantined_reference_rows.csv (77331 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/source_label_audit_20261025T013618706250Z/reference_parse_repairs.csv (23 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/source_label_audit_20261025T013618706250Z/summary.json (1561 bytes). Research manifest, evaluation metrics, or release proof identified by the filename.

extension-setup/api/artifacts/url_retraining_v2_20261024T141402487619Z/conflicting_labels.csv (627927 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v2_20261024T141402487619Z/homepage_regression_checks.csv (564 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v2_20261024T141402487619Z/metrics_and_manifest.json (1832 bytes). Research manifest, evaluation metrics, or release proof identified by the filename.

extension-setup/api/artifacts/url_retraining_v2_20261024T141402487619Z/paired_test_comparison.csv (360 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v2_20261024T141402487619Z/phishing_url_bundle.joblib (3299079 bytes). Saved research candidate or release bundle; approval status depends on its corresponding report.

extension-setup/api/artifacts/url_retraining_v2_20261024T141402487619Z/phishing_url_utils.py (3040 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/url_retraining_v2_20261024T141402487619Z/requirements-lock.txt (104 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/url_retraining_v2_20261024T141402487619Z/reviewed_urls.csv (460 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v2_20261024T141402487619Z/source_audit.csv (136 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v2_20261024T141402487619Z/split_manifest.csv (30346737 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v2_20261024T141402487619Z/test_by_source.csv (191 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v2_20261024T141402487619Z/test_mistakes.csv (243957 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v2_20261024T141402487619Z/validation_comparison.csv (898 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v2_20261024T142219116449Z/conflicting_labels.csv (627927 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v2_20261024T142219116449Z/homepage_regression_checks.csv (564 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v2_20261024T142219116449Z/metrics_and_manifest.json (1832 bytes). Research manifest, evaluation metrics, or release proof identified by the filename.

extension-setup/api/artifacts/url_retraining_v2_20261024T142219116449Z/paired_test_comparison.csv (530 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v2_20261024T142219116449Z/phishing_url_bundle.joblib (3299079 bytes). Saved research candidate or release bundle; approval status depends on its corresponding report.

extension-setup/api/artifacts/url_retraining_v2_20261024T142219116449Z/phishing_url_utils.py (3040 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/url_retraining_v2_20261024T142219116449Z/requirements-lock.txt (104 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/url_retraining_v2_20261024T142219116449Z/reviewed_urls.csv (460 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v2_20261024T142219116449Z/source_audit.csv (136 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v2_20261024T142219116449Z/split_manifest.csv (30346737 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v2_20261024T142219116449Z/test_by_source.csv (191 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v2_20261024T142219116449Z/test_mistakes.csv (243957 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v2_20261024T142219116449Z/validation_comparison.csv (900 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v2_20261024T143046434535Z/conflicting_labels.csv (627927 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v2_20261024T143046434535Z/homepage_regression_checks.csv (560 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v2_20261024T143046434535Z/metrics_and_manifest.json (1830 bytes). Research manifest, evaluation metrics, or release proof identified by the filename.

extension-setup/api/artifacts/url_retraining_v2_20261024T143046434535Z/paired_test_comparison.csv (532 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v2_20261024T143046434535Z/phishing_url_bundle.joblib (3390549 bytes). Saved research candidate or release bundle; approval status depends on its corresponding report.

extension-setup/api/artifacts/url_retraining_v2_20261024T143046434535Z/phishing_url_utils.py (3040 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/url_retraining_v2_20261024T143046434535Z/requirements-lock.txt (104 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/url_retraining_v2_20261024T143046434535Z/reviewed_urls.csv (460 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v2_20261024T143046434535Z/source_audit.csv (136 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v2_20261024T143046434535Z/split_manifest.csv (84047927 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v2_20261024T143046434535Z/test_by_source.csv (193 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v2_20261024T143046434535Z/test_mistakes.csv (908257 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v2_20261024T143046434535Z/validation_comparison.csv (906 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v3_20261024T151132463192Z/conflicting_labels.csv (1157743 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v3_20261024T151132463192Z/frozen_splits_v3.json (14055904 bytes). Research manifest, evaluation metrics, or release proof identified by the filename.

extension-setup/api/artifacts/url_retraining_v3_20261024T151132463192Z/homepage_regression_checks.csv (434 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v3_20261024T151132463192Z/metrics_and_manifest.json (2832 bytes). Research manifest, evaluation metrics, or release proof identified by the filename.

extension-setup/api/artifacts/url_retraining_v3_20261024T151132463192Z/paired_test_comparison.csv (923 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v3_20261024T151132463192Z/phishing_url_bundle.joblib (3370018 bytes). Saved research candidate or release bundle; approval status depends on its corresponding report.

extension-setup/api/artifacts/url_retraining_v3_20261024T151132463192Z/phishing_url_utils.py (4649 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/url_retraining_v3_20261024T151132463192Z/requirements-lock.txt (119 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/url_retraining_v3_20261024T151132463192Z/retraining_utils.py (7754 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/url_retraining_v3_20261024T151132463192Z/reviewed_urls.csv (460 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v3_20261024T151132463192Z/source_audit.csv (103 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v3_20261024T151132463192Z/split_manifest.csv (84113118 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v3_20261024T151132463192Z/test_by_source.csv (144 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v3_20261024T151132463192Z/test_mistakes.csv (923524 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v3_20261024T151132463192Z/validation_comparison.csv (2514 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v4_20261024T230832374525Z/conflicting_labels.csv (1157743 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v4_20261024T230832374525Z/frozen_splits_v3.json (14055904 bytes). Research manifest, evaluation metrics, or release proof identified by the filename.

extension-setup/api/artifacts/url_retraining_v4_20261024T230832374525Z/homepage_regression_checks.csv (488 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v4_20261024T230832374525Z/metrics_and_manifest.json (4010 bytes). Research manifest, evaluation metrics, or release proof identified by the filename.

extension-setup/api/artifacts/url_retraining_v4_20261024T230832374525Z/paired_test_comparison.csv (1378 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v4_20261024T230832374525Z/phishing_url_bundle.joblib (3370302 bytes). Saved research candidate or release bundle; approval status depends on its corresponding report.

extension-setup/api/artifacts/url_retraining_v4_20261024T230832374525Z/phishing_url_utils.py (7064 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/url_retraining_v4_20261024T230832374525Z/refresh_training_helpers.py (2228 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/url_retraining_v4_20261024T230832374525Z/requirements-lock.txt (119 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/url_retraining_v4_20261024T230832374525Z/retraining_utils.py (12703 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/url_retraining_v4_20261024T230832374525Z/reviewed_training_urls.csv (44 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v4_20261024T230832374525Z/reviewed_urls.csv (460 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v4_20261024T230832374525Z/source_audit.csv (103 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v4_20261024T230832374525Z/split_manifest.csv (84113118 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v4_20261024T230832374525Z/test_by_source.csv (146 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v4_20261024T230832374525Z/test_mistakes.csv (786658 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v4_20261024T230832374525Z/training_coverage.csv (161 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v4_20261024T230832374525Z/training_review_decisions.csv (59 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v4_20261024T230832374525Z/validation_comparison.csv (3734 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v4_20261024T230832374525Z/validation_review_queue.csv (76261 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v4_20261024T235133085035Z/conflicting_labels.csv (1157743 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v4_20261024T235133085035Z/frozen_splits_v3.json (14055904 bytes). Research manifest, evaluation metrics, or release proof identified by the filename.

extension-setup/api/artifacts/url_retraining_v4_20261024T235133085035Z/homepage_regression_checks.csv (488 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v4_20261024T235133085035Z/metrics_and_manifest.json (4016 bytes). Research manifest, evaluation metrics, or release proof identified by the filename.

extension-setup/api/artifacts/url_retraining_v4_20261024T235133085035Z/paired_test_comparison.csv (1377 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v4_20261024T235133085035Z/phishing_url_bundle.joblib (3370987 bytes). Saved research candidate or release bundle; approval status depends on its corresponding report.

extension-setup/api/artifacts/url_retraining_v4_20261024T235133085035Z/phishing_url_utils.py (7064 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/url_retraining_v4_20261024T235133085035Z/refresh_training_helpers.py (2228 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/url_retraining_v4_20261024T235133085035Z/requirements-lock.txt (119 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/url_retraining_v4_20261024T235133085035Z/retraining_utils.py (13697 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/url_retraining_v4_20261024T235133085035Z/reviewed_training_urls.csv (9419 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v4_20261024T235133085035Z/reviewed_urls.csv (460 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v4_20261024T235133085035Z/source_audit.csv (103 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v4_20261024T235133085035Z/split_manifest.csv (84118098 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v4_20261024T235133085035Z/test_by_source.csv (146 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v4_20261024T235133085035Z/test_mistakes.csv (787535 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v4_20261024T235133085035Z/training_coverage.csv (199 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v4_20261024T235133085035Z/training_review_decisions.csv (10763 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v4_20261024T235133085035Z/validation_comparison.csv (3728 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v4_20261024T235133085035Z/validation_review_queue.csv (92559 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v5_20261025T003349560635Z/conflicting_labels.csv (1157743 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v5_20261025T003349560635Z/frozen_splits_v3.json (14055904 bytes). Research manifest, evaluation metrics, or release proof identified by the filename.

extension-setup/api/artifacts/url_retraining_v5_20261025T003349560635Z/homepage_regression_checks.csv (488 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v5_20261025T003349560635Z/metrics_and_manifest.json (4295 bytes). Research manifest, evaluation metrics, or release proof identified by the filename.

extension-setup/api/artifacts/url_retraining_v5_20261025T003349560635Z/paired_test_comparison.csv (1377 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v5_20261025T003349560635Z/phishing_url_bundle.joblib (3371808 bytes). Saved research candidate or release bundle; approval status depends on its corresponding report.

extension-setup/api/artifacts/url_retraining_v5_20261025T003349560635Z/phishing_url_utils.py (7064 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/url_retraining_v5_20261025T003349560635Z/refresh_training_helpers.py (2518 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/url_retraining_v5_20261025T003349560635Z/requirements-lock.txt (119 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/url_retraining_v5_20261025T003349560635Z/retraining_utils.py (13697 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/url_retraining_v5_20261025T003349560635Z/reviewed_training_urls.csv (9419 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v5_20261025T003349560635Z/reviewed_urls.csv (460 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v5_20261025T003349560635Z/source_audit.csv (103 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v5_20261025T003349560635Z/split_manifest.csv (84118098 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v5_20261025T003349560635Z/test_by_source.csv (146 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v5_20261025T003349560635Z/test_mistakes.csv (787535 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v5_20261025T003349560635Z/training_coverage.csv (199 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v5_20261025T003349560635Z/training_review_decisions.csv (10763 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v5_20261025T003349560635Z/validation_comparison.csv (3728 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v5_20261025T003349560635Z/validation_review_queue.csv (92559 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v5_20261025T023045464582Z/conflicting_labels.csv (28 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v5_20261025T023045464582Z/frozen_splits_v3.json (14152741 bytes). Research manifest, evaluation metrics, or release proof identified by the filename.

extension-setup/api/artifacts/url_retraining_v5_20261025T023045464582Z/homepage_regression_checks.csv (492 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v5_20261025T023045464582Z/metrics_and_manifest.json (6187 bytes). Research manifest, evaluation metrics, or release proof identified by the filename.

extension-setup/api/artifacts/url_retraining_v5_20261025T023045464582Z/paired_test_comparison.csv (1376 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v5_20261025T023045464582Z/phishing_url_bundle.joblib (3370417 bytes). Saved research candidate or release bundle; approval status depends on its corresponding report.

extension-setup/api/artifacts/url_retraining_v5_20261025T023045464582Z/phishing_url_utils.py (7064 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/url_retraining_v5_20261025T023045464582Z/previous_deployed_bundle.joblib (3289618 bytes). Saved research candidate or release bundle; approval status depends on its corresponding report.

extension-setup/api/artifacts/url_retraining_v5_20261025T023045464582Z/refresh_training_helpers.py (2825 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/url_retraining_v5_20261025T023045464582Z/requirements-lock.txt (119 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/url_retraining_v5_20261025T023045464582Z/retraining_utils.py (13697 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/url_retraining_v5_20261025T023045464582Z/reviewed_training_urls.csv (9419 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v5_20261025T023045464582Z/reviewed_urls.csv (460 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v5_20261025T023045464582Z/source_audit.csv (103 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v5_20261025T023045464582Z/split_manifest.csv (84745993 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v5_20261025T023045464582Z/test_by_source.csv (146 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v5_20261025T023045464582Z/test_mistakes.csv (361516 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v5_20261025T023045464582Z/training_coverage.csv (200 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v5_20261025T023045464582Z/training_review_decisions.csv (10763 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v5_20261025T023045464582Z/validation_comparison.csv (3702 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v5_20261025T023045464582Z/validation_review_queue.csv (86799 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v5_20261025T023045464582Z/github_api_release/.envexample (276 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/url_retraining_v5_20261025T023045464582Z/github_api_release/.gitignore (65 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/url_retraining_v5_20261025T023045464582Z/github_api_release/.python-version (6 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/url_retraining_v5_20261025T023045464582Z/github_api_release/MODEL_CARD.md (543 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/url_retraining_v5_20261025T023045464582Z/github_api_release/Procfile (62 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/url_retraining_v5_20261025T023045464582Z/github_api_release/README.md (520 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/url_retraining_v5_20261025T023045464582Z/github_api_release/app.py (10430 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/url_retraining_v5_20261025T023045464582Z/github_api_release/phishing_url_utils.py (7064 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/url_retraining_v5_20261025T023045464582Z/github_api_release/release_manifest.json (7595 bytes). Research manifest, evaluation metrics, or release proof identified by the filename.

extension-setup/api/artifacts/url_retraining_v5_20261025T023045464582Z/github_api_release/release_utils.py (9332 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/url_retraining_v5_20261025T023045464582Z/github_api_release/render.yaml (370 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/url_retraining_v5_20261025T023045464582Z/github_api_release/requirements.txt (169 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/url_retraining_v5_20261025T023045464582Z/github_api_release/runtime.txt (16 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/url_retraining_v5_20261025T023045464582Z/github_api_release/verify_release.py (520 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/url_retraining_v5_20261025T023045464582Z/github_api_release/dataset/phishing_url_bundle.joblib (3370417 bytes). Saved research candidate or release bundle; approval status depends on its corresponding report.

extension-setup/api/artifacts/url_retraining_v5_20261028T145239905774Z/conflicting_labels.csv (28 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v5_20261028T145239905774Z/frozen_splits_v3.json (14152741 bytes). Research manifest, evaluation metrics, or release proof identified by the filename.

extension-setup/api/artifacts/url_retraining_v5_20261028T145239905774Z/homepage_regression_checks.csv (492 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v5_20261028T145239905774Z/metrics_and_manifest.json (6189 bytes). Research manifest, evaluation metrics, or release proof identified by the filename.

extension-setup/api/artifacts/url_retraining_v5_20261028T145239905774Z/monitoring_site_regression.json (1421 bytes). Research manifest, evaluation metrics, or release proof identified by the filename.

extension-setup/api/artifacts/url_retraining_v5_20261028T145239905774Z/paired_test_comparison.csv (1383 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v5_20261028T145239905774Z/phishing_url_bundle.joblib (3370500 bytes). Saved research candidate or release bundle; approval status depends on its corresponding report.

extension-setup/api/artifacts/url_retraining_v5_20261028T145239905774Z/phishing_url_utils.py (7064 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/url_retraining_v5_20261028T145239905774Z/previous_deployed_bundle.joblib (3370417 bytes). Saved research candidate or release bundle; approval status depends on its corresponding report.

extension-setup/api/artifacts/url_retraining_v5_20261028T145239905774Z/refresh_training_helpers.py (2840 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/url_retraining_v5_20261028T145239905774Z/requirements-lock.txt (119 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/url_retraining_v5_20261028T145239905774Z/retraining_utils.py (13697 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/url_retraining_v5_20261028T145239905774Z/reviewed_training_urls.csv (12648 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v5_20261028T145239905774Z/reviewed_urls.csv (460 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v5_20261028T145239905774Z/source_audit.csv (103 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v5_20261028T145239905774Z/split_manifest.csv (84747321 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v5_20261028T145239905774Z/test_by_source.csv (145 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v5_20261028T145239905774Z/test_mistakes.csv (361918 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v5_20261028T145239905774Z/training_coverage.csv (200 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v5_20261028T145239905774Z/training_review_decisions.csv (14448 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v5_20261028T145239905774Z/validation_comparison.csv (3720 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v5_20261028T145239905774Z/validation_review_queue.csv (86826 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/url_retraining_v5_20261028T145239905774Z/github_api_release/.envexample (276 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/url_retraining_v5_20261028T145239905774Z/github_api_release/.gitignore (65 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/url_retraining_v5_20261028T145239905774Z/github_api_release/.python-version (6 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/url_retraining_v5_20261028T145239905774Z/github_api_release/MODEL_CARD.md (543 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/url_retraining_v5_20261028T145239905774Z/github_api_release/Procfile (62 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/url_retraining_v5_20261028T145239905774Z/github_api_release/README.md (520 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/url_retraining_v5_20261028T145239905774Z/github_api_release/app.py (10452 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/url_retraining_v5_20261028T145239905774Z/github_api_release/phishing_url_utils.py (7064 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/url_retraining_v5_20261028T145239905774Z/github_api_release/release_manifest.json (7597 bytes). Research manifest, evaluation metrics, or release proof identified by the filename.

extension-setup/api/artifacts/url_retraining_v5_20261028T145239905774Z/github_api_release/release_utils.py (10519 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/url_retraining_v5_20261028T145239905774Z/github_api_release/render.yaml (370 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/url_retraining_v5_20261028T145239905774Z/github_api_release/requirements.txt (169 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/url_retraining_v5_20261028T145239905774Z/github_api_release/runtime.txt (16 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/url_retraining_v5_20261028T145239905774Z/github_api_release/verify_release.py (520 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/url_retraining_v5_20261028T145239905774Z/github_api_release/dataset/phishing_url_bundle.joblib (3370500 bytes). Saved research candidate or release bundle; approval status depends on its corresponding report.

extension-setup/api/artifacts/v3_synthetic_smoke_20261024T144731/phishing_url_utils.py (4649 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/v3_synthetic_smoke_20261024T144731/retraining_utils.py (7754 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/v3_synthetic_smoke_20261024T144731/synthetic_execution.ipynb (111797 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/v3_synthetic_smoke_20261024T144731/artifacts/url_retraining_v3_20261024T144738790692Z/conflicting_labels.csv (28 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/v3_synthetic_smoke_20261024T144731/artifacts/url_retraining_v3_20261024T144738790692Z/frozen_splits_v3.json (274123 bytes). Research manifest, evaluation metrics, or release proof identified by the filename.

extension-setup/api/artifacts/v3_synthetic_smoke_20261024T144731/artifacts/url_retraining_v3_20261024T144738790692Z/homepage_regression_checks.csv (435 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/v3_synthetic_smoke_20261024T144731/artifacts/url_retraining_v3_20261024T144738790692Z/metrics_and_manifest.json (2185 bytes). Research manifest, evaluation metrics, or release proof identified by the filename.

extension-setup/api/artifacts/v3_synthetic_smoke_20261024T144731/artifacts/url_retraining_v3_20261024T144738790692Z/paired_test_comparison.csv (458 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/v3_synthetic_smoke_20261024T144731/artifacts/url_retraining_v3_20261024T144738790692Z/phishing_url_bundle.joblib (29320 bytes). Saved research candidate or release bundle; approval status depends on its corresponding report.

extension-setup/api/artifacts/v3_synthetic_smoke_20261024T144731/artifacts/url_retraining_v3_20261024T144738790692Z/phishing_url_utils.py (4649 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/v3_synthetic_smoke_20261024T144731/artifacts/url_retraining_v3_20261024T144738790692Z/previous_deployed_bundle.joblib (32 bytes). Saved research candidate or release bundle; approval status depends on its corresponding report.

extension-setup/api/artifacts/v3_synthetic_smoke_20261024T144731/artifacts/url_retraining_v3_20261024T144738790692Z/requirements-lock.txt (119 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/v3_synthetic_smoke_20261024T144731/artifacts/url_retraining_v3_20261024T144738790692Z/retraining_utils.py (7754 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/v3_synthetic_smoke_20261024T144731/artifacts/url_retraining_v3_20261024T144738790692Z/reviewed_urls.csv (460 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/v3_synthetic_smoke_20261024T144731/artifacts/url_retraining_v3_20261024T144738790692Z/source_audit.csv (92 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/v3_synthetic_smoke_20261024T144731/artifacts/url_retraining_v3_20261024T144738790692Z/split_manifest.csv (495791 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/v3_synthetic_smoke_20261024T144731/artifacts/url_retraining_v3_20261024T144738790692Z/test_by_source.csv (95 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/v3_synthetic_smoke_20261024T144731/artifacts/url_retraining_v3_20261024T144738790692Z/test_mistakes.csv (33 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/v3_synthetic_smoke_20261024T144731/artifacts/url_retraining_v3_20261024T144738790692Z/validation_comparison.csv (1192 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/v3_synthetic_smoke_20261024T144731/dataset/phishing_url_bundle.joblib (29320 bytes). Saved research candidate or release bundle; approval status depends on its corresponding report.

extension-setup/api/artifacts/v3_synthetic_smoke_20261024T144731/training_data/data4.csv (177970 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/v3_synthetic_smoke_20261024T144731/training_data/frozen_splits_v3.json (274123 bytes). Research manifest, evaluation metrics, or release proof identified by the filename.

extension-setup/api/artifacts/v3_synthetic_smoke_20261024T144731/training_data/phishtank.csv (4504 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/v3_synthetic_smoke_20261024T144731/training_data/reviewed_urls.csv (460 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/v4_synthetic_smoke_20261024T204927/phishing_url_utils.py (7064 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/v4_synthetic_smoke_20261024T204927/refresh_training_helpers.py (2228 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/v4_synthetic_smoke_20261024T204927/retrain_phishing_urls.ipynb (131657 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/v4_synthetic_smoke_20261024T204927/retraining_utils.py (12703 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/v4_synthetic_smoke_20261024T204927/synthetic_execution.ipynb (131657 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/v4_synthetic_smoke_20261024T204927/train_model.py (2845 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/v4_synthetic_smoke_20261024T204927/artifacts/training_20261024T205302419554Z.log (7016 bytes). Training execution transcript; contents not copied into this public document.

extension-setup/api/artifacts/v4_synthetic_smoke_20261024T204927/artifacts/url_retraining_v4_20261024T204935845220Z/conflicting_labels.csv (28 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/v4_synthetic_smoke_20261024T204927/artifacts/url_retraining_v4_20261024T204935845220Z/frozen_splits_v3.json (274123 bytes). Research manifest, evaluation metrics, or release proof identified by the filename.

extension-setup/api/artifacts/v4_synthetic_smoke_20261024T204927/artifacts/url_retraining_v4_20261024T204935845220Z/homepage_regression_checks.csv (498 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/v4_synthetic_smoke_20261024T204927/artifacts/url_retraining_v4_20261024T204935845220Z/metrics_and_manifest.json (3199 bytes). Research manifest, evaluation metrics, or release proof identified by the filename.

extension-setup/api/artifacts/v4_synthetic_smoke_20261024T204927/artifacts/url_retraining_v4_20261024T204935845220Z/paired_test_comparison.csv (647 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/v4_synthetic_smoke_20261024T204927/artifacts/url_retraining_v4_20261024T204935845220Z/phishing_url_bundle.joblib (29183 bytes). Saved research candidate or release bundle; approval status depends on its corresponding report.

extension-setup/api/artifacts/v4_synthetic_smoke_20261024T204927/artifacts/url_retraining_v4_20261024T204935845220Z/phishing_url_utils.py (7064 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/v4_synthetic_smoke_20261024T204927/artifacts/url_retraining_v4_20261024T204935845220Z/previous_deployed_bundle.joblib (27 bytes). Saved research candidate or release bundle; approval status depends on its corresponding report.

extension-setup/api/artifacts/v4_synthetic_smoke_20261024T204927/artifacts/url_retraining_v4_20261024T204935845220Z/refresh_training_helpers.py (2228 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/v4_synthetic_smoke_20261024T204927/artifacts/url_retraining_v4_20261024T204935845220Z/requirements-lock.txt (119 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/v4_synthetic_smoke_20261024T204927/artifacts/url_retraining_v4_20261024T204935845220Z/retraining_utils.py (12703 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/v4_synthetic_smoke_20261024T204927/artifacts/url_retraining_v4_20261024T204935845220Z/reviewed_training_urls.csv (44 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/v4_synthetic_smoke_20261024T204927/artifacts/url_retraining_v4_20261024T204935845220Z/reviewed_urls.csv (460 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/v4_synthetic_smoke_20261024T204927/artifacts/url_retraining_v4_20261024T204935845220Z/source_audit.csv (92 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/v4_synthetic_smoke_20261024T204927/artifacts/url_retraining_v4_20261024T204935845220Z/split_manifest.csv (495791 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/v4_synthetic_smoke_20261024T204927/artifacts/url_retraining_v4_20261024T204935845220Z/test_by_source.csv (95 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/v4_synthetic_smoke_20261024T204927/artifacts/url_retraining_v4_20261024T204935845220Z/test_mistakes.csv (33 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/v4_synthetic_smoke_20261024T204927/artifacts/url_retraining_v4_20261024T204935845220Z/training_coverage.csv (135 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/v4_synthetic_smoke_20261024T204927/artifacts/url_retraining_v4_20261024T204935845220Z/training_review_decisions.csv (59 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/v4_synthetic_smoke_20261024T204927/artifacts/url_retraining_v4_20261024T204935845220Z/validation_comparison.csv (1970 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/v4_synthetic_smoke_20261024T204927/artifacts/url_retraining_v4_20261024T204935845220Z/validation_review_queue.csv (113 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/v4_synthetic_smoke_20261024T204927/artifacts/url_retraining_v4_20261024T205306291768Z/conflicting_labels.csv (28 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/v4_synthetic_smoke_20261024T204927/artifacts/url_retraining_v4_20261024T205306291768Z/frozen_splits_v3.json (274123 bytes). Research manifest, evaluation metrics, or release proof identified by the filename.

extension-setup/api/artifacts/v4_synthetic_smoke_20261024T204927/artifacts/url_retraining_v4_20261024T205306291768Z/homepage_regression_checks.csv (498 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/v4_synthetic_smoke_20261024T204927/artifacts/url_retraining_v4_20261024T205306291768Z/metrics_and_manifest.json (3200 bytes). Research manifest, evaluation metrics, or release proof identified by the filename.

extension-setup/api/artifacts/v4_synthetic_smoke_20261024T204927/artifacts/url_retraining_v4_20261024T205306291768Z/paired_test_comparison.csv (647 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/v4_synthetic_smoke_20261024T204927/artifacts/url_retraining_v4_20261024T205306291768Z/phishing_url_bundle.joblib (29183 bytes). Saved research candidate or release bundle; approval status depends on its corresponding report.

extension-setup/api/artifacts/v4_synthetic_smoke_20261024T204927/artifacts/url_retraining_v4_20261024T205306291768Z/phishing_url_utils.py (7064 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/v4_synthetic_smoke_20261024T204927/artifacts/url_retraining_v4_20261024T205306291768Z/refresh_training_helpers.py (2228 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/v4_synthetic_smoke_20261024T204927/artifacts/url_retraining_v4_20261024T205306291768Z/requirements-lock.txt (119 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/v4_synthetic_smoke_20261024T204927/artifacts/url_retraining_v4_20261024T205306291768Z/retraining_utils.py (12703 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/v4_synthetic_smoke_20261024T204927/artifacts/url_retraining_v4_20261024T205306291768Z/reviewed_training_urls.csv (44 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/v4_synthetic_smoke_20261024T204927/artifacts/url_retraining_v4_20261024T205306291768Z/reviewed_urls.csv (460 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/v4_synthetic_smoke_20261024T204927/artifacts/url_retraining_v4_20261024T205306291768Z/source_audit.csv (92 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/v4_synthetic_smoke_20261024T204927/artifacts/url_retraining_v4_20261024T205306291768Z/split_manifest.csv (495791 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/v4_synthetic_smoke_20261024T204927/artifacts/url_retraining_v4_20261024T205306291768Z/test_by_source.csv (95 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/v4_synthetic_smoke_20261024T204927/artifacts/url_retraining_v4_20261024T205306291768Z/test_mistakes.csv (33 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/v4_synthetic_smoke_20261024T204927/artifacts/url_retraining_v4_20261024T205306291768Z/training_coverage.csv (135 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/v4_synthetic_smoke_20261024T204927/artifacts/url_retraining_v4_20261024T205306291768Z/training_review_decisions.csv (59 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/v4_synthetic_smoke_20261024T204927/artifacts/url_retraining_v4_20261024T205306291768Z/validation_comparison.csv (1970 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/v4_synthetic_smoke_20261024T204927/artifacts/url_retraining_v4_20261024T205306291768Z/validation_review_queue.csv (113 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/v4_synthetic_smoke_20261024T204927/dataset/phishing_url_bundle.joblib (29183 bytes). Saved research candidate or release bundle; approval status depends on its corresponding report.

extension-setup/api/artifacts/v4_synthetic_smoke_20261024T204927/training_data/data4.csv (177970 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/v4_synthetic_smoke_20261024T204927/training_data/frozen_splits_v3.json (274123 bytes). Research manifest, evaluation metrics, or release proof identified by the filename.

extension-setup/api/artifacts/v4_synthetic_smoke_20261024T204927/training_data/phishtank.csv (4504 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/v4_synthetic_smoke_20261024T204927/training_data/reviewed_training_urls.csv (44 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/v4_synthetic_smoke_20261024T204927/training_data/reviewed_urls.csv (460 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/v5_synthetic_release_20261025T003043/.envexample (276 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/v5_synthetic_release_20261025T003043/.python-version (6 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/v5_synthetic_release_20261025T003043/Procfile (62 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/v5_synthetic_release_20261025T003043/app.py (10430 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/v5_synthetic_release_20261025T003043/phishing_url_utils.py (7064 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/v5_synthetic_release_20261025T003043/refresh_training_helpers.py (2518 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/v5_synthetic_release_20261025T003043/release_utils.py (9145 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/v5_synthetic_release_20261025T003043/render.yaml (404 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/v5_synthetic_release_20261025T003043/requirements.txt (169 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/v5_synthetic_release_20261025T003043/retraining_utils.py (13697 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/v5_synthetic_release_20261025T003043/runtime.txt (16 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/v5_synthetic_release_20261025T003043/synthetic_execution.ipynb (146985 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/v5_synthetic_release_20261025T003043/verify_release.py (520 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/v5_synthetic_release_20261025T003043/artifacts/url_retraining_v5_20261025T003050686713Z/conflicting_labels.csv (28 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/v5_synthetic_release_20261025T003043/artifacts/url_retraining_v5_20261025T003050686713Z/frozen_splits_v3.json (274123 bytes). Research manifest, evaluation metrics, or release proof identified by the filename.

extension-setup/api/artifacts/v5_synthetic_release_20261025T003043/artifacts/url_retraining_v5_20261025T003050686713Z/homepage_regression_checks.csv (492 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/v5_synthetic_release_20261025T003043/artifacts/url_retraining_v5_20261025T003050686713Z/metrics_and_manifest.json (3672 bytes). Research manifest, evaluation metrics, or release proof identified by the filename.

extension-setup/api/artifacts/v5_synthetic_release_20261025T003043/artifacts/url_retraining_v5_20261025T003050686713Z/paired_test_comparison.csv (796 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/v5_synthetic_release_20261025T003043/artifacts/url_retraining_v5_20261025T003050686713Z/phishing_url_bundle.joblib (29585 bytes). Saved research candidate or release bundle; approval status depends on its corresponding report.

extension-setup/api/artifacts/v5_synthetic_release_20261025T003043/artifacts/url_retraining_v5_20261025T003050686713Z/phishing_url_utils.py (7064 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/v5_synthetic_release_20261025T003043/artifacts/url_retraining_v5_20261025T003050686713Z/previous_deployed_bundle.joblib (27 bytes). Saved research candidate or release bundle; approval status depends on its corresponding report.

extension-setup/api/artifacts/v5_synthetic_release_20261025T003043/artifacts/url_retraining_v5_20261025T003050686713Z/refresh_training_helpers.py (2518 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/v5_synthetic_release_20261025T003043/artifacts/url_retraining_v5_20261025T003050686713Z/requirements-lock.txt (119 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/v5_synthetic_release_20261025T003043/artifacts/url_retraining_v5_20261025T003050686713Z/retraining_utils.py (13697 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/v5_synthetic_release_20261025T003043/artifacts/url_retraining_v5_20261025T003050686713Z/reviewed_training_urls.csv (9419 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/v5_synthetic_release_20261025T003043/artifacts/url_retraining_v5_20261025T003050686713Z/reviewed_urls.csv (460 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/v5_synthetic_release_20261025T003043/artifacts/url_retraining_v5_20261025T003050686713Z/source_audit.csv (92 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/v5_synthetic_release_20261025T003043/artifacts/url_retraining_v5_20261025T003050686713Z/split_manifest.csv (500771 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/v5_synthetic_release_20261025T003043/artifacts/url_retraining_v5_20261025T003050686713Z/test_by_source.csv (98 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/v5_synthetic_release_20261025T003043/artifacts/url_retraining_v5_20261025T003050686713Z/test_mistakes.csv (101 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/v5_synthetic_release_20261025T003043/artifacts/url_retraining_v5_20261025T003050686713Z/training_coverage.csv (173 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/v5_synthetic_release_20261025T003043/artifacts/url_retraining_v5_20261025T003050686713Z/training_review_decisions.csv (10763 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/v5_synthetic_release_20261025T003043/artifacts/url_retraining_v5_20261025T003050686713Z/validation_comparison.csv (1964 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/v5_synthetic_release_20261025T003043/artifacts/url_retraining_v5_20261025T003050686713Z/validation_review_queue.csv (124 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/v5_synthetic_release_20261025T003043/artifacts/url_retraining_v5_20261025T003050686713Z/github_api_release/.envexample (276 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/v5_synthetic_release_20261025T003043/artifacts/url_retraining_v5_20261025T003050686713Z/github_api_release/.gitignore (65 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/v5_synthetic_release_20261025T003043/artifacts/url_retraining_v5_20261025T003050686713Z/github_api_release/.python-version (6 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/v5_synthetic_release_20261025T003043/artifacts/url_retraining_v5_20261025T003050686713Z/github_api_release/MODEL_CARD.md (544 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/v5_synthetic_release_20261025T003043/artifacts/url_retraining_v5_20261025T003050686713Z/github_api_release/Procfile (62 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/v5_synthetic_release_20261025T003043/artifacts/url_retraining_v5_20261025T003050686713Z/github_api_release/README.md (520 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/v5_synthetic_release_20261025T003043/artifacts/url_retraining_v5_20261025T003050686713Z/github_api_release/app.py (10430 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/v5_synthetic_release_20261025T003043/artifacts/url_retraining_v5_20261025T003050686713Z/github_api_release/phishing_url_utils.py (7064 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/v5_synthetic_release_20261025T003043/artifacts/url_retraining_v5_20261025T003050686713Z/github_api_release/release_manifest.json (4955 bytes). Research manifest, evaluation metrics, or release proof identified by the filename.

extension-setup/api/artifacts/v5_synthetic_release_20261025T003043/artifacts/url_retraining_v5_20261025T003050686713Z/github_api_release/release_utils.py (9145 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/v5_synthetic_release_20261025T003043/artifacts/url_retraining_v5_20261025T003050686713Z/github_api_release/render.yaml (370 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/v5_synthetic_release_20261025T003043/artifacts/url_retraining_v5_20261025T003050686713Z/github_api_release/requirements.txt (169 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/v5_synthetic_release_20261025T003043/artifacts/url_retraining_v5_20261025T003050686713Z/github_api_release/runtime.txt (16 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/v5_synthetic_release_20261025T003043/artifacts/url_retraining_v5_20261025T003050686713Z/github_api_release/verify_release.py (520 bytes). Generated research/release artifact; filename and parent run identify its experiment.

extension-setup/api/artifacts/v5_synthetic_release_20261025T003043/artifacts/url_retraining_v5_20261025T003050686713Z/github_api_release/dataset/phishing_url_bundle.joblib (29585 bytes). Saved research candidate or release bundle; approval status depends on its corresponding report.

extension-setup/api/artifacts/v5_synthetic_release_20261025T003043/dataset/phishing_url_bundle.joblib (29585 bytes). Saved research candidate or release bundle; approval status depends on its corresponding report.

extension-setup/api/artifacts/v5_synthetic_release_20261025T003043/training_data/data4.csv (177970 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/v5_synthetic_release_20261025T003043/training_data/frozen_splits_v3.json (274123 bytes). Research manifest, evaluation metrics, or release proof identified by the filename.

extension-setup/api/artifacts/v5_synthetic_release_20261025T003043/training_data/phishtank.csv (4504 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/v5_synthetic_release_20261025T003043/training_data/reviewed_training_urls.csv (9419 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/artifacts/v5_synthetic_release_20261025T003043/training_data/reviewed_urls.csv (460 bytes). Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.

extension-setup/api/dataset/feature_columns.pkl (1418 bytes). Serialized model asset; only the configured approved joblib bundle is runtime authority.

extension-setup/api/dataset/model_meta.json (912 bytes). Legacy numeric-feature model metadata; not a runtime inference fallback.

extension-setup/api/dataset/phishing_url_bundle.joblib (3370500 bytes). Serialized model asset; only the configured approved joblib bundle is runtime authority.

extension-setup/api/dataset/phishtank.csv (10278141 bytes). Dataset, reviewed input, historical snapshot, or source archive; consult data provenance and schema register.

extension-setup/api/dataset/rf_model.joblib (21363945 bytes). Serialized model asset; only the configured approved joblib bundle is runtime authority.

extension-setup/api/dataset/rf_model_8features.joblib (21363945 bytes). Serialized model asset; only the configured approved joblib bundle is runtime authority.

extension-setup/api/dataset/scaler.pkl (4775 bytes). Serialized model asset; only the configured approved joblib bundle is runtime authority.

extension-setup/api/dataset/sklearn_pipeline.joblib (2089 bytes). Serialized model asset; only the configured approved joblib bundle is runtime authority.

extension-setup/api/dataset/urlset.csv.zip (3400239 bytes). Dataset, reviewed input, historical snapshot, or source archive; consult data provenance and schema register.

extension-setup/api/dataset/urlset.csv/urlset.csv (12283023 bytes). Dataset, reviewed input, historical snapshot, or source archive; consult data provenance and schema register.

extension-setup/api/tests/test_api.py (9687 bytes). Automated test or shared test setup; intended assertions indexed in test coverage.

extension-setup/api/tests/test_dataset_provenance.py (5259 bytes). Automated test or shared test setup; intended assertions indexed in test coverage.

extension-setup/api/tests/test_release.py (6158 bytes). Automated test or shared test setup; intended assertions indexed in test coverage.

extension-setup/api/tests/test_retraining.py (9747 bytes). Automated test or shared test setup; intended assertions indexed in test coverage.

extension-setup/api/training_data/DATASET_PROVENANCE.json (2972 bytes). Training provenance, frozen split assignment, review evidence, or experiment-selection metadata.

extension-setup/api/training_data/PROVENANCE_FINDINGS.md (5475 bytes). Human-readable project/setup/research documentation; selected notes extracted earlier.

extension-setup/api/training_data/README.md (3395 bytes). Human-readable project/setup/research documentation; selected notes extracted earlier.

extension-setup/api/training_data/REVIEW_BATCH_2026-10-25.md (2430 bytes). Human-readable project/setup/research documentation; selected notes extracted earlier.

extension-setup/api/training_data/active_experiment.json (86 bytes). Training provenance, frozen split assignment, review evidence, or experiment-selection metadata.

extension-setup/api/training_data/data1.csv (1406307 bytes). Dataset, reviewed input, historical snapshot, or source archive; consult data provenance and schema register.

extension-setup/api/training_data/data2.csv (31567326 bytes). Dataset, reviewed input, historical snapshot, or source archive; consult data provenance and schema register.

extension-setup/api/training_data/data3.csv (9771799 bytes). Dataset, reviewed input, historical snapshot, or source archive; consult data provenance and schema register.

extension-setup/api/training_data/data4.csv (45664500 bytes). Dataset, reviewed input, historical snapshot, or source archive; consult data provenance and schema register.

extension-setup/api/training_data/exclude.csv (855304 bytes). Dataset, reviewed input, historical snapshot, or source archive; consult data provenance and schema register.

extension-setup/api/training_data/exclude1.csv (12283023 bytes). Dataset, reviewed input, historical snapshot, or source archive; consult data provenance and schema register.

extension-setup/api/training_data/frozen_splits_v3.json (14055904 bytes). Training provenance, frozen split assignment, review evidence, or experiment-selection metadata.

extension-setup/api/training_data/phishstorm_original.csv (12283023 bytes). Dataset, reviewed input, historical snapshot, or source archive; consult data provenance and schema register.

extension-setup/api/training_data/phishstorm_reference_verification.json (1090 bytes). Training provenance, frozen split assignment, review evidence, or experiment-selection metadata.

extension-setup/api/training_data/phishtank.csv (10226420 bytes). Dataset, reviewed input, historical snapshot, or source archive; consult data provenance and schema register.

extension-setup/api/training_data/reviewed_training_urls.csv (12648 bytes). Dataset, reviewed input, historical snapshot, or source archive; consult data provenance and schema register.

extension-setup/api/training_data/reviewed_urls.csv (460 bytes). Dataset, reviewed input, historical snapshot, or source archive; consult data provenance and schema register.

extension-setup/api/training_data/source_manifest.json (1599 bytes). Training provenance, frozen split assignment, review evidence, or experiment-selection metadata.

extension-setup/api/training_data/source_restored_experiment_manifest.json (1985 bytes). Training provenance, frozen split assignment, review evidence, or experiment-selection metadata.

extension-setup/api/training_data/experiments/phishstorm_restored_v1/contradictory_reference_labels.csv (50 bytes). Dataset, reviewed input, historical snapshot, or source archive; consult data provenance and schema register.

extension-setup/api/training_data/experiments/phishstorm_restored_v1/data4.csv (45657798 bytes). Dataset, reviewed input, historical snapshot, or source archive; consult data provenance and schema register.

extension-setup/api/training_data/experiments/phishstorm_restored_v1/experiment.json (1985 bytes). Training provenance, frozen split assignment, review evidence, or experiment-selection metadata.

extension-setup/api/training_data/experiments/phishstorm_restored_v1/frozen_splits.json (14152741 bytes). Training provenance, frozen split assignment, review evidence, or experiment-selection metadata.

extension-setup/api/training_data/experiments/phishstorm_restored_v1/notebook_preflight.log (4976 bytes). Training provenance, frozen split assignment, review evidence, or experiment-selection metadata.

extension-setup/api/training_data/experiments/phishstorm_restored_v1/reference_quarantine.csv (77331 bytes). Dataset, reviewed input, historical snapshot, or source archive; consult data provenance and schema register.

extension-setup/api/training_data/experiments/phishstorm_restored_v1/source_label_changes.csv (13011091 bytes). Dataset, reviewed input, historical snapshot, or source archive; consult data provenance and schema register.

extension-setup/extensions-version/README.md (1695 bytes). Human-readable project/setup/research documentation; selected notes extracted earlier.

extension-setup/extensions-version/phishblockv1_slsu-bontoc/background.js (6176 bytes). Extension worker: configuration, fetch timeouts, device identity, registration, logging, and message routing.

extension-setup/extensions-version/phishblockv1_slsu-bontoc/backup.txt (2742 bytes). Retained extension backup/reference text; not loaded by the manifest.

extension-setup/extensions-version/phishblockv1_slsu-bontoc/config.json (239 bytes). Campus identifier and prediction/logging endpoint configuration for this distribution.

extension-setup/extensions-version/phishblockv1_slsu-bontoc/content.js (10076 bytes). Browser-page assessment, result storage, warning banner, intervention overlay, and user actions.

extension-setup/extensions-version/phishblockv1_slsu-bontoc/manifest.json (1259 bytes). Chrome Manifest V3 version, permissions, entry points, icons, and resource exposure.

extension-setup/extensions-version/phishblockv1_slsu-bontoc/popup.html (3687 bytes). Extension toolbar popup markup and presentation styles.

extension-setup/extensions-version/phishblockv1_slsu-bontoc/popup.js (3254 bytes). Active-tab cached result rendering and manual prediction request handling.

extension-setup/extensions-version/phishblockv1_slsu-bontoc/icons/128.jpeg (7238 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

extension-setup/extensions-version/phishblockv1_slsu-bontoc/icons/16.jpeg (7238 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

extension-setup/extensions-version/phishblockv1_slsu-bontoc/icons/48.jpeg (7238 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

extension-setup/extensions-version/phishblockv1_slsu-bontoc/icons/icon128.png (16359 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

extension-setup/extensions-version/phishblockv1_slsu-bontoc/icons/icon16.png (799 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

extension-setup/extensions-version/phishblockv1_slsu-bontoc/icons/icon32.png (2138 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

extension-setup/extensions-version/phishblockv1_slsu-bontoc/icons/icon48.png (3896 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

extension-setup/extensions-version/phishblockv1_slsu-bontoc/icons/phish-block-logo.png (139641 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

extension-setup/extensions-version/phishblockv1_slsu-hinunangan/background.js (6176 bytes). Extension worker: configuration, fetch timeouts, device identity, registration, logging, and message routing.

extension-setup/extensions-version/phishblockv1_slsu-hinunangan/backup.txt (2742 bytes). Retained extension backup/reference text; not loaded by the manifest.

extension-setup/extensions-version/phishblockv1_slsu-hinunangan/config.json (243 bytes). Campus identifier and prediction/logging endpoint configuration for this distribution.

extension-setup/extensions-version/phishblockv1_slsu-hinunangan/content.js (10076 bytes). Browser-page assessment, result storage, warning banner, intervention overlay, and user actions.

extension-setup/extensions-version/phishblockv1_slsu-hinunangan/manifest.json (1259 bytes). Chrome Manifest V3 version, permissions, entry points, icons, and resource exposure.

extension-setup/extensions-version/phishblockv1_slsu-hinunangan/popup.html (3687 bytes). Extension toolbar popup markup and presentation styles.

extension-setup/extensions-version/phishblockv1_slsu-hinunangan/popup.js (3254 bytes). Active-tab cached result rendering and manual prediction request handling.

extension-setup/extensions-version/phishblockv1_slsu-hinunangan/icons/128.jpeg (7238 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

extension-setup/extensions-version/phishblockv1_slsu-hinunangan/icons/16.jpeg (7238 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

extension-setup/extensions-version/phishblockv1_slsu-hinunangan/icons/48.jpeg (7238 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

extension-setup/extensions-version/phishblockv1_slsu-hinunangan/icons/icon128.png (16359 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

extension-setup/extensions-version/phishblockv1_slsu-hinunangan/icons/icon16.png (799 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

extension-setup/extensions-version/phishblockv1_slsu-hinunangan/icons/icon32.png (2138 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

extension-setup/extensions-version/phishblockv1_slsu-hinunangan/icons/icon48.png (3896 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

extension-setup/extensions-version/phishblockv1_slsu-hinunangan/icons/phish-block-logo.png (139641 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

extension-setup/extensions-version/phishblockv1_slsu-maasin/background.js (6176 bytes). Extension worker: configuration, fetch timeouts, device identity, registration, logging, and message routing.

extension-setup/extensions-version/phishblockv1_slsu-maasin/backup.txt (2742 bytes). Retained extension backup/reference text; not loaded by the manifest.

extension-setup/extensions-version/phishblockv1_slsu-maasin/config.json (239 bytes). Campus identifier and prediction/logging endpoint configuration for this distribution.

extension-setup/extensions-version/phishblockv1_slsu-maasin/content.js (10076 bytes). Browser-page assessment, result storage, warning banner, intervention overlay, and user actions.

extension-setup/extensions-version/phishblockv1_slsu-maasin/manifest.json (1259 bytes). Chrome Manifest V3 version, permissions, entry points, icons, and resource exposure.

extension-setup/extensions-version/phishblockv1_slsu-maasin/popup.html (3687 bytes). Extension toolbar popup markup and presentation styles.

extension-setup/extensions-version/phishblockv1_slsu-maasin/popup.js (3254 bytes). Active-tab cached result rendering and manual prediction request handling.

extension-setup/extensions-version/phishblockv1_slsu-maasin/icons/128.jpeg (7238 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

extension-setup/extensions-version/phishblockv1_slsu-maasin/icons/16.jpeg (7238 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

extension-setup/extensions-version/phishblockv1_slsu-maasin/icons/48.jpeg (7238 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

extension-setup/extensions-version/phishblockv1_slsu-maasin/icons/icon128.png (16359 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

extension-setup/extensions-version/phishblockv1_slsu-maasin/icons/icon16.png (799 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

extension-setup/extensions-version/phishblockv1_slsu-maasin/icons/icon32.png (2138 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

extension-setup/extensions-version/phishblockv1_slsu-maasin/icons/icon48.png (3896 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

extension-setup/extensions-version/phishblockv1_slsu-maasin/icons/phish-block-logo.png (139641 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

extension-setup/extensions-version/phishblockv1_slsu-main/background.js (6176 bytes). Extension worker: configuration, fetch timeouts, device identity, registration, logging, and message routing.

extension-setup/extensions-version/phishblockv1_slsu-main/backup.txt (2742 bytes). Retained extension backup/reference text; not loaded by the manifest.

extension-setup/extensions-version/phishblockv1_slsu-main/config.json (237 bytes). Campus identifier and prediction/logging endpoint configuration for this distribution.

extension-setup/extensions-version/phishblockv1_slsu-main/content.js (10076 bytes). Browser-page assessment, result storage, warning banner, intervention overlay, and user actions.

extension-setup/extensions-version/phishblockv1_slsu-main/manifest.json (1259 bytes). Chrome Manifest V3 version, permissions, entry points, icons, and resource exposure.

extension-setup/extensions-version/phishblockv1_slsu-main/popup.html (3687 bytes). Extension toolbar popup markup and presentation styles.

extension-setup/extensions-version/phishblockv1_slsu-main/popup.js (3254 bytes). Active-tab cached result rendering and manual prediction request handling.

extension-setup/extensions-version/phishblockv1_slsu-main/icons/128.jpeg (7238 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

extension-setup/extensions-version/phishblockv1_slsu-main/icons/16.jpeg (7238 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

extension-setup/extensions-version/phishblockv1_slsu-main/icons/48.jpeg (7238 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

extension-setup/extensions-version/phishblockv1_slsu-main/icons/icon128.png (16359 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

extension-setup/extensions-version/phishblockv1_slsu-main/icons/icon16.png (799 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

extension-setup/extensions-version/phishblockv1_slsu-main/icons/icon32.png (2138 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

extension-setup/extensions-version/phishblockv1_slsu-main/icons/icon48.png (3896 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

extension-setup/extensions-version/phishblockv1_slsu-main/icons/phish-block-logo.png (139641 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

extension-setup/extensions-version/phishblockv1_slsu-sanjuan/background.js (6176 bytes). Extension worker: configuration, fetch timeouts, device identity, registration, logging, and message routing.

extension-setup/extensions-version/phishblockv1_slsu-sanjuan/backup.txt (2742 bytes). Retained extension backup/reference text; not loaded by the manifest.

extension-setup/extensions-version/phishblockv1_slsu-sanjuan/config.json (240 bytes). Campus identifier and prediction/logging endpoint configuration for this distribution.

extension-setup/extensions-version/phishblockv1_slsu-sanjuan/content.js (10076 bytes). Browser-page assessment, result storage, warning banner, intervention overlay, and user actions.

extension-setup/extensions-version/phishblockv1_slsu-sanjuan/manifest.json (1259 bytes). Chrome Manifest V3 version, permissions, entry points, icons, and resource exposure.

extension-setup/extensions-version/phishblockv1_slsu-sanjuan/popup.html (3687 bytes). Extension toolbar popup markup and presentation styles.

extension-setup/extensions-version/phishblockv1_slsu-sanjuan/popup.js (3254 bytes). Active-tab cached result rendering and manual prediction request handling.

extension-setup/extensions-version/phishblockv1_slsu-sanjuan/icons/128.jpeg (7238 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

extension-setup/extensions-version/phishblockv1_slsu-sanjuan/icons/16.jpeg (7238 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

extension-setup/extensions-version/phishblockv1_slsu-sanjuan/icons/48.jpeg (7238 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

extension-setup/extensions-version/phishblockv1_slsu-sanjuan/icons/icon128.png (16359 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

extension-setup/extensions-version/phishblockv1_slsu-sanjuan/icons/icon16.png (799 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

extension-setup/extensions-version/phishblockv1_slsu-sanjuan/icons/icon32.png (2138 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

extension-setup/extensions-version/phishblockv1_slsu-sanjuan/icons/icon48.png (3896 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

extension-setup/extensions-version/phishblockv1_slsu-sanjuan/icons/phish-block-logo.png (139641 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

extension-setup/extensions-version/phishblockv1_slsu-to/background.js (6176 bytes). Extension worker: configuration, fetch timeouts, device identity, registration, logging, and message routing.

extension-setup/extensions-version/phishblockv1_slsu-to/backup.txt (2742 bytes). Retained extension backup/reference text; not loaded by the manifest.

extension-setup/extensions-version/phishblockv1_slsu-to/config.json (235 bytes). Campus identifier and prediction/logging endpoint configuration for this distribution.

extension-setup/extensions-version/phishblockv1_slsu-to/content.js (10076 bytes). Browser-page assessment, result storage, warning banner, intervention overlay, and user actions.

extension-setup/extensions-version/phishblockv1_slsu-to/manifest.json (1259 bytes). Chrome Manifest V3 version, permissions, entry points, icons, and resource exposure.

extension-setup/extensions-version/phishblockv1_slsu-to/popup.html (3687 bytes). Extension toolbar popup markup and presentation styles.

extension-setup/extensions-version/phishblockv1_slsu-to/popup.js (3254 bytes). Active-tab cached result rendering and manual prediction request handling.

extension-setup/extensions-version/phishblockv1_slsu-to/icons/128.jpeg (7238 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

extension-setup/extensions-version/phishblockv1_slsu-to/icons/16.jpeg (7238 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

extension-setup/extensions-version/phishblockv1_slsu-to/icons/48.jpeg (7238 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

extension-setup/extensions-version/phishblockv1_slsu-to/icons/icon128.png (16359 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

extension-setup/extensions-version/phishblockv1_slsu-to/icons/icon16.png (799 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

extension-setup/extensions-version/phishblockv1_slsu-to/icons/icon32.png (2138 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

extension-setup/extensions-version/phishblockv1_slsu-to/icons/icon48.png (3896 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

extension-setup/extensions-version/phishblockv1_slsu-to/icons/phish-block-logo.png (139641 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/.htaccess (740 bytes). Project configuration, build input, metadata, or retained supporting resource; purpose is indicated by path and filename.

public/favicon.ico (0 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/inapp-1.0.0.zip (15210646 bytes). Project configuration, build input, metadata, or retained supporting resource; purpose is indicated by path and filename.

public/index.php (543 bytes). Public Laravel HTTP front controller; boots framework and dispatches requests.

public/jadoo.zip (21650972 bytes). Project configuration, build input, metadata, or retained supporting resource; purpose is indicated by path and filename.

public/robots.txt (24 bytes). Crawler directives; not authentication or route authorization.

public/build/manifest.json (331 bytes). Project configuration, build input, metadata, or retained supporting resource; purpose is indicated by path and filename.

public/build/assets/app-34mOoJaZ.js (0 bytes). Project configuration, build input, metadata, or retained supporting resource; purpose is indicated by path and filename.

public/build/assets/app-DB-J2rRD.css (8756 bytes). Project configuration, build input, metadata, or retained supporting resource; purpose is indicated by path and filename.

public/home/.babelrc (97 bytes). Landing theme asset or build metadata; Laravel uses resources/views/home.blade.php for its home route.

public/home/.eslintrc (537 bytes). Landing theme asset or build metadata; Laravel uses resources/views/home.blade.php for its home route.

public/home/.gitignore (543 bytes). Landing theme asset or build metadata; Laravel uses resources/views/home.blade.php for its home route.

public/home/.prettierignore (44 bytes). Landing theme asset or build metadata; Laravel uses resources/views/home.blade.php for its home route.

public/home/README.md (254 bytes). Landing theme asset or build metadata; Laravel uses resources/views/home.blade.php for its home route.

public/home/gulpfile.js (959 bytes). Landing theme script or Gulp build task; Laravel uses resources/views/home.blade.php for its home route.

public/home/package-lock.json (508983 bytes). Landing theme asset or build metadata; Laravel uses resources/views/home.blade.php for its home route.

public/home/package.json (1986 bytes). Landing theme asset or build metadata; Laravel uses resources/views/home.blade.php for its home route.

public/home/vendors.json (392 bytes). Landing theme asset or build metadata; Laravel uses resources/views/home.blade.php for its home route.

public/home/gulp/build.gulp.js (660 bytes). Landing theme script or Gulp build task; Laravel uses resources/views/home.blade.php for its home route.

public/home/gulp/clean.gulp.js (538 bytes). Landing theme script or Gulp build task; Laravel uses resources/views/home.blade.php for its home route.

public/home/gulp/gulp.json (669 bytes). Landing theme asset or build metadata; Laravel uses resources/views/home.blade.php for its home route.

public/home/gulp/pug.gulp.js (2520 bytes). Landing theme script or Gulp build task; Laravel uses resources/views/home.blade.php for its home route.

public/home/gulp/script.gulp.js (2222 bytes). Landing theme script or Gulp build task; Laravel uses resources/views/home.blade.php for its home route.

public/home/gulp/style.gulp.js (1855 bytes). Landing theme script or Gulp build task; Laravel uses resources/views/home.blade.php for its home route.

public/home/gulp/utils.js (549 bytes). Landing theme script or Gulp build task; Laravel uses resources/views/home.blade.php for its home route.

public/home/gulp/vendor.gulp.js (2511 bytes). Landing theme script or Gulp build task; Laravel uses resources/views/home.blade.php for its home route.

public/home/gulp/w3c.gulp.js (1095 bytes). Landing theme script or Gulp build task; Laravel uses resources/views/home.blade.php for its home route.

public/home/gulp/watch.gulp.js (918 bytes). Landing theme script or Gulp build task; Laravel uses resources/views/home.blade.php for its home route.

public/home/public/index.html (30237 bytes). Landing theme compiled static demo page; Laravel uses resources/views/home.blade.php for its home route.

public/home/public/assets/css/theme-rtl.css (394297 bytes). Landing theme compiled stylesheet; Laravel uses resources/views/home.blade.php for its home route.

public/home/public/assets/css/theme-rtl.css.map (823003 bytes). Generated source map linking compiled frontend code/styles to original source.

public/home/public/assets/css/theme-rtl.min.css (312699 bytes). Landing theme compiled stylesheet; Laravel uses resources/views/home.blade.php for its home route.

public/home/public/assets/css/theme-rtl.min.css.map (669760 bytes). Generated source map linking compiled frontend code/styles to original source.

public/home/public/assets/css/theme.css (395047 bytes). Landing theme compiled stylesheet; Laravel uses resources/views/home.blade.php for its home route.

public/home/public/assets/css/theme.css.map (822999 bytes). Generated source map linking compiled frontend code/styles to original source.

public/home/public/assets/css/theme.min.css (312868 bytes). Landing theme compiled stylesheet; Laravel uses resources/views/home.blade.php for its home route.

public/home/public/assets/css/theme.min.css.map (669756 bytes). Generated source map linking compiled frontend code/styles to original source.

public/home/public/assets/img/apple-store.png (795 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/home/public/assets/img/logo.svg (4184 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/home/public/assets/img/logo2.svg (2120 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/home/public/assets/img/play-store.png (950 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/home/public/assets/img/category/icon1.png (2225 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/home/public/assets/img/category/icon2.png (2020 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/home/public/assets/img/category/icon3.png (2626 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/home/public/assets/img/category/icon4.png (2004 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/home/public/assets/img/category/shape.svg (3201 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/home/public/assets/img/cta/cta-shape.svg (2484 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/home/public/assets/img/cta/mail.svg (295 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/home/public/assets/img/cta/send.png (2237 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/home/public/assets/img/cta/shape-bg1.png (4322 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/home/public/assets/img/cta/shape-bg2.png (3106 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/home/public/assets/img/dest/dest1.jpg (61257 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/home/public/assets/img/dest/dest2.jpg (62994 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/home/public/assets/img/dest/dest3.jpg (46896 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/home/public/assets/img/dest/navigation.svg (796 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/home/public/assets/img/dest/shape.svg (1667 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/home/public/assets/img/favicons/android-chrome-192x192.png (5514 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/home/public/assets/img/favicons/android-chrome-512x512.png (19024 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/home/public/assets/img/favicons/apple-touch-icon.png (5121 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/home/public/assets/img/favicons/browserconfig.xml (255 bytes). Landing theme asset or build metadata; Laravel uses resources/views/home.blade.php for its home route.

public/home/public/assets/img/favicons/favicon-16x16.png (296 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/home/public/assets/img/favicons/favicon-32x32.png (618 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/home/public/assets/img/favicons/favicon.ico (15406 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/home/public/assets/img/favicons/manifest.json (420 bytes). Landing theme asset or build metadata; Laravel uses resources/views/home.blade.php for its home route.

public/home/public/assets/img/favicons/mstile-150x150.png (22625 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/home/public/assets/img/favicons/site.webmanifest (263 bytes). Landing theme asset or build metadata; Laravel uses resources/views/home.blade.php for its home route.

public/home/public/assets/img/hero/hero-bg.svg (904 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/home/public/assets/img/hero/hero-img.png (116588 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/home/public/assets/img/hero/play.svg (180 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/home/public/assets/img/hero/shape.svg (54121 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/home/public/assets/img/icons/down.svg (269 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/home/public/assets/img/icons/up.svg (268 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/home/public/assets/img/partner/1.png (2093 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/home/public/assets/img/partner/2.png (1509 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/home/public/assets/img/partner/3.png (2385 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/home/public/assets/img/partner/4.png (1928 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/home/public/assets/img/partner/5.png (1065 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/home/public/assets/img/steps/bg.png (29926 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/home/public/assets/img/steps/booking-img.jpg (35695 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/home/public/assets/img/steps/building.svg (4434 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/home/public/assets/img/steps/favorite-placeholder.png (2862 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/home/public/assets/img/steps/heart.svg (1023 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/home/public/assets/img/steps/leaf.svg (956 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/home/public/assets/img/steps/map.svg (1426 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/home/public/assets/img/steps/selection.svg (1314 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/home/public/assets/img/steps/send.svg (907 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/home/public/assets/img/steps/taxi.svg (5808 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/home/public/assets/img/steps/water-sport.svg (10594 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/home/public/assets/img/testimonial/author.png (4220 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/home/public/assets/img/testimonial/author2.png (6026 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/home/public/assets/img/testimonial/author3.png (63964 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/home/public/assets/js/bootstrap-navbar.js (15430 bytes). Landing theme script or Gulp build task; Laravel uses resources/views/home.blade.php for its home route.

public/home/public/assets/js/theme.js (13430 bytes). Landing theme script or Gulp build task; Laravel uses resources/views/home.blade.php for its home route.

public/home/public/assets/js/theme.js.map (26958 bytes). Generated source map linking compiled frontend code/styles to original source.

public/home/public/assets/js/theme.min.js (7023 bytes). Landing theme script or Gulp build task; Laravel uses resources/views/home.blade.php for its home route.

public/home/public/assets/js/theme.min.js.map (20802 bytes). Generated source map linking compiled frontend code/styles to original source.

public/home/public/assets/video/.gitkeep (0 bytes). Landing theme asset or build metadata; Laravel uses resources/views/home.blade.php for its home route.

public/home/public/assets/video/beach.jpg (297332 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/home/public/assets/video/beach.mp4 (9985089 bytes). Template media/video resource; not part of inference or ingestion.

public/home/public/assets/video/beach.webm (9349765 bytes). Template media/video resource; not part of inference or ingestion.

public/home/public/vendors/@popperjs/popper.min.js (18594 bytes). Landing theme script or Gulp build task; Laravel uses resources/views/home.blade.php for its home route.

public/home/public/vendors/bootstrap/bootstrap.min.js (60091 bytes). Landing theme script or Gulp build task; Laravel uses resources/views/home.blade.php for its home route.

public/home/public/vendors/bootstrap/bootstrap.min.js.map (217655 bytes). Generated source map linking compiled frontend code/styles to original source.

public/home/public/vendors/fontawesome/all.min.js (1195450 bytes). Landing theme script or Gulp build task; Laravel uses resources/views/home.blade.php for its home route.

public/home/public/vendors/is/is.min.js (13181 bytes). Landing theme script or Gulp build task; Laravel uses resources/views/home.blade.php for its home route.

public/home/src/js/bootstrap-navbar.js (3939 bytes). Landing theme script or Gulp build task; Laravel uses resources/views/home.blade.php for its home route.

public/home/src/js/detector.js (996 bytes). Landing theme script or Gulp build task; Laravel uses resources/views/home.blade.php for its home route.

public/home/src/js/scroll-to-top.js (902 bytes). Landing theme script or Gulp build task; Laravel uses resources/views/home.blade.php for its home route.

public/home/src/js/theme.js (498 bytes). Landing theme script or Gulp build task; Laravel uses resources/views/home.blade.php for its home route.

public/home/src/js/utils.js (5720 bytes). Landing theme script or Gulp build task; Laravel uses resources/views/home.blade.php for its home route.

public/home/src/pug/index.pug (546 bytes). Landing theme Pug template source; Laravel uses resources/views/home.blade.php for its home route.

public/home/src/pug/layouts/Layout.pug (3073 bytes). Landing theme Pug template source; Laravel uses resources/views/home.blade.php for its home route.

public/home/src/pug/layouts/LayoutTheme.pug (581 bytes). Landing theme Pug template source; Laravel uses resources/views/home.blade.php for its home route.

public/home/src/pug/mixins/Variables.pug (591 bytes). Landing theme Pug template source; Laravel uses resources/views/home.blade.php for its home route.

public/home/src/pug/mixins/common/DestinationCard.pug (649 bytes). Landing theme Pug template source; Laravel uses resources/views/home.blade.php for its home route.

public/home/src/pug/mixins/common/Footer.pug (2009 bytes). Landing theme Pug template source; Laravel uses resources/views/home.blade.php for its home route.

public/home/src/pug/mixins/common/ServiceCard.pug (315 bytes). Landing theme Pug template source; Laravel uses resources/views/home.blade.php for its home route.

public/home/src/pug/mixins/core/Background.pug (877 bytes). Landing theme Pug template source; Laravel uses resources/views/home.blade.php for its home route.

public/home/src/pug/mixins/core/Functions.pug (924 bytes). Landing theme Pug template source; Laravel uses resources/views/home.blade.php for its home route.

public/home/src/pug/mixins/core/Section.pug (856 bytes). Landing theme Pug template source; Laravel uses resources/views/home.blade.php for its home route.

public/home/src/pug/mixins/core/ThemeStyle.pug (244 bytes). Landing theme Pug template source; Laravel uses resources/views/home.blade.php for its home route.

public/home/src/pug/mixins/home/landing/Booking.pug (3556 bytes). Landing theme Pug template source; Laravel uses resources/views/home.blade.php for its home route.

public/home/src/pug/mixins/home/landing/Cta.pug (1272 bytes). Landing theme Pug template source; Laravel uses resources/views/home.blade.php for its home route.

public/home/src/pug/mixins/home/landing/Destination.pug (771 bytes). Landing theme Pug template source; Laravel uses resources/views/home.blade.php for its home route.

public/home/src/pug/mixins/home/landing/HeroHeader.pug (1762 bytes). Landing theme Pug template source; Laravel uses resources/views/home.blade.php for its home route.

public/home/src/pug/mixins/home/landing/Partner.pug (686 bytes). Landing theme Pug template source; Laravel uses resources/views/home.blade.php for its home route.

public/home/src/pug/mixins/home/landing/Service.pug (1053 bytes). Landing theme Pug template source; Laravel uses resources/views/home.blade.php for its home route.

public/home/src/pug/mixins/home/landing/Testimonial.pug (2910 bytes). Landing theme Pug template source; Laravel uses resources/views/home.blade.php for its home route.

public/home/src/pug/mixins/navbars/NavbarJadoo.pug (1867 bytes). Landing theme Pug template source; Laravel uses resources/views/home.blade.php for its home route.

public/home/src/pug/mixins/sectionTitle/SectionTitle.pug (143 bytes). Landing theme Pug template source; Laravel uses resources/views/home.blade.php for its home route.

public/home/src/scss/_bootstrap.scss (2099 bytes). Landing theme SCSS style source; Laravel uses resources/views/home.blade.php for its home route.

public/home/src/scss/_user-variables.scss (135 bytes). Landing theme SCSS style source; Laravel uses resources/views/home.blade.php for its home route.

public/home/src/scss/_user.scss (149 bytes). Landing theme SCSS style source; Laravel uses resources/views/home.blade.php for its home route.

public/home/src/scss/theme.css (384647 bytes). Landing theme compiled stylesheet; Laravel uses resources/views/home.blade.php for its home route.

public/home/src/scss/theme.css.map (346020 bytes). Generated source map linking compiled frontend code/styles to original source.

public/home/src/scss/theme.scss (997 bytes). Landing theme SCSS style source; Laravel uses resources/views/home.blade.php for its home route.

public/home/src/scss/theme/.gitkeep (0 bytes). Landing theme asset or build metadata; Laravel uses resources/views/home.blade.php for its home route.

public/home/src/scss/theme/_badge.scss (248 bytes). Landing theme SCSS style source; Laravel uses resources/views/home.blade.php for its home route.

public/home/src/scss/theme/_browser-support.scss (387 bytes). Landing theme SCSS style source; Laravel uses resources/views/home.blade.php for its home route.

public/home/src/scss/theme/_button.scss (499 bytes). Landing theme SCSS style source; Laravel uses resources/views/home.blade.php for its home route.

public/home/src/scss/theme/_card.scss (752 bytes). Landing theme SCSS style source; Laravel uses resources/views/home.blade.php for its home route.

public/home/src/scss/theme/_form.scss (522 bytes). Landing theme SCSS style source; Laravel uses resources/views/home.blade.php for its home route.

public/home/src/scss/theme/_functions.scss (1234 bytes). Landing theme SCSS style source; Laravel uses resources/views/home.blade.php for its home route.

public/home/src/scss/theme/_helpers.scss (201 bytes). Landing theme SCSS style source; Laravel uses resources/views/home.blade.php for its home route.

public/home/src/scss/theme/_heroHeader.scss (1183 bytes). Landing theme SCSS style source; Laravel uses resources/views/home.blade.php for its home route.

public/home/src/scss/theme/_hover.scss (805 bytes). Landing theme SCSS style source; Laravel uses resources/views/home.blade.php for its home route.

public/home/src/scss/theme/_icon.scss (1138 bytes). Landing theme SCSS style source; Laravel uses resources/views/home.blade.php for its home route.

public/home/src/scss/theme/_mixed.scss (4143 bytes). Landing theme SCSS style source; Laravel uses resources/views/home.blade.php for its home route.

public/home/src/scss/theme/_mixins.scss (234 bytes). Landing theme SCSS style source; Laravel uses resources/views/home.blade.php for its home route.

public/home/src/scss/theme/_nav-tab.scss (319 bytes). Landing theme SCSS style source; Laravel uses resources/views/home.blade.php for its home route.

public/home/src/scss/theme/_reboot.scss (1370 bytes). Landing theme SCSS style source; Laravel uses resources/views/home.blade.php for its home route.

public/home/src/scss/theme/_testimonial.scss (608 bytes). Landing theme SCSS style source; Laravel uses resources/views/home.blade.php for its home route.

public/home/src/scss/theme/_theme.scss (291 bytes). Landing theme SCSS style source; Laravel uses resources/views/home.blade.php for its home route.

public/home/src/scss/theme/_topnav.scss (2 bytes). Landing theme SCSS style source; Laravel uses resources/views/home.blade.php for its home route.

public/home/src/scss/theme/_utilities.scss (8481 bytes). Landing theme SCSS style source; Laravel uses resources/views/home.blade.php for its home route.

public/home/src/scss/theme/_variables.scss (15949 bytes). Landing theme SCSS style source; Laravel uses resources/views/home.blade.php for its home route.

public/home/src/scss/theme/helpers/_background.scss (1335 bytes). Landing theme SCSS style source; Laravel uses resources/views/home.blade.php for its home route.

public/home/src/scss/theme/helpers/_borders.scss (684 bytes). Landing theme SCSS style source; Laravel uses resources/views/home.blade.php for its home route.

public/home/src/scss/theme/helpers/_colored-links.scss (275 bytes). Landing theme SCSS style source; Laravel uses resources/views/home.blade.php for its home route.

public/home/src/scss/theme/helpers/_flex.scss (511 bytes). Landing theme SCSS style source; Laravel uses resources/views/home.blade.php for its home route.

public/home/src/scss/theme/helpers/_position.scss (366 bytes). Landing theme SCSS style source; Laravel uses resources/views/home.blade.php for its home route.

public/home/src/scss/theme/helpers/_shadow.scss (805 bytes). Landing theme SCSS style source; Laravel uses resources/views/home.blade.php for its home route.

public/home/src/scss/theme/helpers/_text.scss (1354 bytes). Landing theme SCSS style source; Laravel uses resources/views/home.blade.php for its home route.

public/images/admin-profile.jpg (51182 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/images/admin-profile.webp (29614 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/images/slsu.png (37811 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/images/slsu.webp (33444 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/images/branding/landing-security-hero.webp (148794 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/images/branding/login-security-illustration.webp (130178 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/images/branding/phish-block-logo.png (139641 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/images/landing_page/dark-analytics.png (120929 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/images/landing_page/dark-analytics.webp (52406 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/images/landing_page/dark-dashboard.png (267547 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/images/landing_page/dark-dashboard.webp (99182 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/images/landing_page/dark-logs.png (213549 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/images/landing_page/dark-logs.webp (120786 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/images/landing_page/light-analytics.png (118500 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/images/landing_page/light-analytics.webp (52332 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/images/landing_page/light-dashboard.png (252265 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/images/landing_page/light-dashboard.webp (107820 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/images/landing_page/light-logs.png (197015 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/images/landing_page/light-logs.webp (125956 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/template/.DS_Store (8196 bytes). Bundled administration theme resource or build configuration; route integration is defined in Blade views.

public/template/.gitignore (2158 bytes). Bundled administration theme resource or build configuration; route integration is defined in Blade views.

public/template/LICENSE (1067 bytes). Bundled administration theme resource or build configuration; route integration is defined in Blade views.

public/template/README.md (761 bytes). Bundled administration theme resource or build configuration; route integration is defined in Blade views.

public/template/package-lock.json (65536 bytes). Bundled administration theme resource or build configuration; route integration is defined in Blade views.

public/template/package.json (587 bytes). Bundled administration theme resource or build configuration; route integration is defined in Blade views.

public/template/vite.config.js (1468 bytes). Bundled administration theme behavior/library script; route integration is defined in Blade views.

public/template/.github/workflows/action.yml (445 bytes). Bundled administration theme resource or build configuration; route integration is defined in Blade views.

public/template/dist/404-error.html (2298 bytes). Bundled administration theme static example page; route integration is defined in Blade views.

public/template/dist/create-product.html (11700 bytes). Bundled administration theme static example page; route integration is defined in Blade views.

public/template/dist/docs.html (11925 bytes). Bundled administration theme static example page; route integration is defined in Blade views.

public/template/dist/index.html (27937 bytes). Bundled administration theme static example page; route integration is defined in Blade views.

public/template/dist/inventory.html (16774 bytes). Bundled administration theme static example page; route integration is defined in Blade views.

public/template/dist/reports.html (14156 bytes). Bundled administration theme static example page; route integration is defined in Blade views.

public/template/dist/signin.html (3351 bytes). Bundled administration theme static example page; route integration is defined in Blade views.

public/template/dist/signup.html (3981 bytes). Bundled administration theme static example page; route integration is defined in Blade views.

public/template/dist/assets/site.webmanifest (263 bytes). Bundled administration theme resource or build configuration; route integration is defined in Blade views.

public/template/dist/assets/tabler-icons.ttf (2413932 bytes). Font or icon-font resource for theme typography.

public/template/dist/assets/tabler-icons.woff (1220100 bytes). Font or icon-font resource for theme typography.

public/template/dist/assets/tabler-icons.woff2 (831044 bytes). Font or icon-font resource for theme typography.

public/template/dist/assets/css/main.css (610869 bytes). Bundled administration theme stylesheet; route integration is defined in Blade views.

public/template/dist/assets/images/apple-touch-icon.png (13191 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/template/dist/assets/images/avatar-1.jpg (70649 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/template/dist/assets/images/avatar-2.jpg (76342 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/template/dist/assets/images/avatar-4.jpg (70583 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/template/dist/assets/images/favicon-16x16.png (802 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/template/dist/assets/images/favicon-32x32.png (1866 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/template/dist/assets/images/logo.svg (11148 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/template/dist/assets/images/product-1.png (1979684 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/template/dist/assets/images/product-10.png (1381675 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/template/dist/assets/images/product-2.png (1424284 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/template/dist/assets/images/product-3.png (1778859 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/template/dist/assets/images/product-4.png (1223042 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/template/dist/assets/images/product-5.png (1534351 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/template/dist/assets/images/product-6.png (1567090 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/template/dist/assets/images/product-7.png (1273947 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/template/dist/assets/images/product-8.png (1445582 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/template/dist/assets/images/product-9.png (1380628 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/template/dist/assets/js/main.js (667788 bytes). Bundled administration theme behavior/library script; route integration is defined in Blade views.

public/template/src/404-error.html (1540 bytes). Bundled administration theme static example page; route integration is defined in Blade views.

public/template/src/create-product.html (10977 bytes). Bundled administration theme static example page; route integration is defined in Blade views.

public/template/src/docs.html (11202 bytes). Bundled administration theme static example page; route integration is defined in Blade views.

public/template/src/index.html (27214 bytes). Bundled administration theme static example page; route integration is defined in Blade views.

public/template/src/inventory.html (16051 bytes). Bundled administration theme static example page; route integration is defined in Blade views.

public/template/src/reports.html (13433 bytes). Bundled administration theme static example page; route integration is defined in Blade views.

public/template/src/signin.html (2593 bytes). Bundled administration theme static example page; route integration is defined in Blade views.

public/template/src/signup.html (3223 bytes). Bundled administration theme static example page; route integration is defined in Blade views.

public/template/src/assets/images/logo-1.svg (11824 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/template/src/assets/images/logo-icon.svg (628 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/template/src/assets/images/logo.svg (11148 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/template/src/assets/images/product-1.png (1979684 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/template/src/assets/images/product-10.png (1381675 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/template/src/assets/images/product-2.png (1424284 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/template/src/assets/images/product-3.png (1778859 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/template/src/assets/images/product-4.png (1223042 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/template/src/assets/images/product-5.png (1534351 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/template/src/assets/images/product-6.png (1567090 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/template/src/assets/images/product-7.png (1273947 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/template/src/assets/images/product-8.png (1445582 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/template/src/assets/images/product-9.png (1380628 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/template/src/assets/images/avatar/avatar-1.jpg (70649 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/template/src/assets/images/avatar/avatar-2.jpg (76342 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/template/src/assets/images/avatar/avatar-3.jpg (71426 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/template/src/assets/images/avatar/avatar-4.jpg (70583 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/template/src/assets/images/avatar/avatar-5.jpg (59806 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/template/src/assets/images/avatar/avatar-6.jpg (66900 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/template/src/assets/images/favicon_io/android-chrome-192x192.png (14607 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/template/src/assets/images/favicon_io/android-chrome-512x512.png (46554 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/template/src/assets/images/favicon_io/apple-touch-icon.png (13191 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/template/src/assets/images/favicon_io/favicon-16x16.png (802 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/template/src/assets/images/favicon_io/favicon-32x32.png (1866 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/template/src/assets/images/favicon_io/favicon.ico (15406 bytes). Static image, branding, illustration, screenshot, or icon asset; not detection logic.

public/template/src/assets/images/favicon_io/site.webmanifest (263 bytes). Bundled administration theme resource or build configuration; route integration is defined in Blade views.

public/template/src/assets/js/chart.js (8282 bytes). Bundled administration theme behavior/library script; route integration is defined in Blade views.

public/template/src/assets/js/custom.js (46 bytes). Bundled administration theme behavior/library script; route integration is defined in Blade views.

public/template/src/assets/js/main.js (132 bytes). Bundled administration theme behavior/library script; route integration is defined in Blade views.

public/template/src/assets/js/sidebar.js (1462 bytes). Bundled administration theme behavior/library script; route integration is defined in Blade views.

public/template/src/assets/scss/_avatar.scss (2626 bytes). Bundled administration theme style source; route integration is defined in Blade views.

public/template/src/assets/scss/_border.scss (567 bytes). Bundled administration theme style source; route integration is defined in Blade views.

public/template/src/assets/scss/_button.scss (579 bytes). Bundled administration theme style source; route integration is defined in Blade views.

public/template/src/assets/scss/_custom.scss (1832 bytes). Bundled administration theme style source; route integration is defined in Blade views.

public/template/src/assets/scss/_icon-shape.scss (906 bytes). Bundled administration theme style source; route integration is defined in Blade views.

public/template/src/assets/scss/_utilities.scss (1070 bytes). Bundled administration theme style source; route integration is defined in Blade views.

public/template/src/assets/scss/_variables.scss (3412 bytes). Bundled administration theme style source; route integration is defined in Blade views.

public/template/src/assets/scss/style.scss (485 bytes). Bundled administration theme style source; route integration is defined in Blade views.

resources/css/app.css (12052 bytes). Application frontend entry point or source style/script.

resources/js/app.js (3 bytes). Application frontend entry point or source style/script.

resources/views/home.blade.php (28208 bytes). Server-rendered Blade page, shared layout, or reusable theme partial identified by filename.

resources/views/welcome.blade.php (72477 bytes). Server-rendered Blade page, shared layout, or reusable theme partial identified by filename.

resources/views/admin/alerts.blade.php (4354 bytes). Server-rendered Blade page, shared layout, or reusable theme partial identified by filename.

resources/views/admin/analytics.blade.php (9196 bytes). Server-rendered Blade page, shared layout, or reusable theme partial identified by filename.

resources/views/admin/campuses.blade.php (2171 bytes). Server-rendered Blade page, shared layout, or reusable theme partial identified by filename.

resources/views/admin/dashboard.blade.php (15969 bytes). Server-rendered Blade page, shared layout, or reusable theme partial identified by filename.

resources/views/admin/logs.blade.php (3319 bytes). Server-rendered Blade page, shared layout, or reusable theme partial identified by filename.

resources/views/admin/settings.blade.php (2820 bytes). Server-rendered Blade page, shared layout, or reusable theme partial identified by filename.

resources/views/auth/login.blade.php (10202 bytes). Server-rendered Blade page, shared layout, or reusable theme partial identified by filename.

resources/views/layouts/admin-template.blade.php (7506 bytes). Server-rendered Blade page, shared layout, or reusable theme partial identified by filename.

resources/views/layouts/admin.blade.php (13836 bytes). Server-rendered Blade page, shared layout, or reusable theme partial identified by filename.

resources/views/layouts/app.blade.php (469 bytes). Server-rendered Blade page, shared layout, or reusable theme partial identified by filename.

resources/views/layouts/auth-template.blade.php (463 bytes). Server-rendered Blade page, shared layout, or reusable theme partial identified by filename.

resources/views/layouts/dark-mode.blade.php (9551 bytes). Server-rendered Blade page, shared layout, or reusable theme partial identified by filename.

resources/views/layouts/footer.blade.php (410 bytes). Server-rendered Blade page, shared layout, or reusable theme partial identified by filename.

resources/views/layouts/home-template.blade.php (1543 bytes). Server-rendered Blade page, shared layout, or reusable theme partial identified by filename.

resources/views/layouts/template-head.blade.php (442 bytes). Server-rendered Blade page, shared layout, or reusable theme partial identified by filename.

resources/views/layouts/theme-toggle.blade.php (736 bytes). Server-rendered Blade page, shared layout, or reusable theme partial identified by filename.

routes/api.php (418 bytes). Laravel api route or command registration.

routes/console.php (210 bytes). Laravel console route or command registration.

routes/web.php (1221 bytes). Laravel web route or command registration.

supabase/migrations/20260717000000_ensure_phishing_logs.sql (2070 bytes). PostgreSQL schema, security policy, or trigger migration for existing database deployments.

supabase/migrations/20260717000001_automate_monitoring_data.sql (2815 bytes). PostgreSQL schema, security policy, or trigger migration for existing database deployments.

tests/Pest.php (1618 bytes). Automated test or shared test setup; intended assertions indexed in test coverage.

tests/TestCase.php (142 bytes). Automated test or shared test setup; intended assertions indexed in test coverage.

tests/extension-decisions.test.cjs (2734 bytes). Automated test or shared test setup; intended assertions indexed in test coverage.

tests/extension-routing.test.cjs (5303 bytes). Automated test or shared test setup; intended assertions indexed in test coverage.

tests/Feature/AuthenticationTest.php (942 bytes). Automated test or shared test setup; intended assertions indexed in test coverage.

tests/Feature/DeviceRegistrationTest.php (3030 bytes). Automated test or shared test setup; intended assertions indexed in test coverage.

tests/Feature/ExampleTest.php (147 bytes). Automated test or shared test setup; intended assertions indexed in test coverage.

tests/Feature/PhishingLogIngestionTest.php (4469 bytes). Automated test or shared test setup; intended assertions indexed in test coverage.

tests/Unit/ExampleTest.php (81 bytes). Automated test or shared test setup; intended assertions indexed in test coverage.

# 23. Excluded generated/dependency directories and extraction limits

vendor: Composer-installed framework and PHP libraries. 9558 files, 59863419 bytes. These are summarized by directory and dependency declarations rather than individually interpreted as project-authored code.

node_modules: npm-installed frontend libraries and build tools. 3306 files, 60910924 bytes. These are summarized by directory and dependency declarations rather than individually interpreted as project-authored code.

storage: private runtime logs, framework state, compiled views, cache, and application files. 99 files, 817578 bytes. These are summarized by directory and dependency declarations rather than individually interpreted as project-authored code.

bootstrap/cache: generated Laravel configuration and package caches. 3 files, 22889 bytes. These are summarized by directory and dependency declarations rather than individually interpreted as project-authored code.

.git: version-control objects and repository metadata. 1443 files, 101445351 bytes. These are summarized by directory and dependency declarations rather than individually interpreted as project-authored code.

Private .env values, seeded passwords, local database contents, raw corpus rows, notebook execution outputs, and historical training log bodies are not published. Model binaries were not deserialized. No live database schema or deployment was inspected. Full project-file inventory contains 868 files. This scope distinguishes repository-backed documentation from claims about current production state.
