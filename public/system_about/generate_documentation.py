"""Build text-only project documentation from the local source snapshot.
Run from any directory with Python, reportlab, and pypdf installed.
Never reads environment secrets, database contents, or serialized models.
"""
from pathlib import Path
from collections import Counter
import ast
import csv
import hashlib
import json
import os
import re
from html import escape
from reportlab.platypus import SimpleDocTemplate, Paragraph, Spacer
from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
from reportlab.lib.enums import TA_JUSTIFY
from reportlab.lib import colors
from reportlab.lib.pagesizes import A4
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from pypdf import PdfReader

OUT = Path(__file__).resolve().parent
ROOT = OUT.parents[1]
parts = []
def h(title): parts.append(('h', title))
def p(text):
    if text.strip(): parts.append(('p', text.strip()))
def read(path): return path.read_text(encoding='utf-8', errors='replace')

h('Phish Block Monitoring System: Complete Technical Documentation')
p('Source snapshot: 29 October 2026, Asia/Manila. Prepared from the local project repository for documentation, maintenance, deployment planning, and academic reference. Text only: architecture, operational flows, interfaces, database, source responsibilities, training, configuration, tests, and a file inventory. Sections flow continuously to use the page area efficiently.')
h('1. Scope, evidence, and reading guide')
p('This document describes implementation present in the repository. Configured addresses are configuration evidence, not proof of live deployment or service availability. Database structures are derived from migrations and SQL, not a connection to a production database. Test descriptions describe existing assertions; they do not claim a fresh passing test run. No live website, prediction endpoint, training job, migration, seeder, or external service was invoked to produce this documentation.')
p('The principal implementation is Laravel for the website and ingestion endpoints, Manifest V3 JavaScript for browser protection, and Python FastAPI for prediction. The following chapters explain their relationship. Later chapters extract source declarations, migration fields, test intent, existing documentation, dataset headers, and file purposes. Installed dependency internals, caches, private runtime data, model binary contents, and raw dataset rows are outside the prose extraction. Their roles and inventory categories are recorded separately. Secret values and seeded login credentials are intentionally excluded from this publicly located PDF.')
h('2. Purpose, users, and boundaries')
p('PhishBlock screens browser destination URLs and centralizes reported results across Southern Leyte State University campuses. Browser users receive quiet checks, warnings, or a blocking intervention. Administrators authenticate to review scans, risk, campus activity, computer identities, alerts, and analytics. Maintainers configure hosting and extension endpoints. Researchers prepare historical URL datasets, train candidate models, evaluate decisions, and package approved releases.')
p('The six canonical campus identifiers are SLSU-Bontoc, SLSU-Hinunangan, SLSU-Maasin, SLSU-Main, SLSU-SanJuan, and SLSU-TO. Each campus has an extension distribution folder. Identity belongs to a browser profile installation; it is not the Windows computer name. One physical computer using several profiles may register several devices.')
p('The prediction service assesses URL text, optional verified PhishTank entries, and optional Google Safe Browsing threat matches. It does not fetch submitted target pages, inspect page contents, follow redirects, query domain age, or use a universal trusted-domain bypass. Demonstration seed reasons mentioning these properties do not establish corresponding runtime detection capabilities.')
h('3. Architecture and responsibility split')
p('A content script runs inside an eligible browser page and requests a check from the extension background service worker. The worker reads campus configuration and calls the configured prediction API. FastAPI loads an approved local model bundle, normalizes the URL, computes a phishing score, applies the model decision policy and optional reputation checks, and returns structured risk information. The content script presents the result. Independently, the background worker registers the device and submits the result to Laravel. Laravel validates it, creates a phishing log, updates campus/computer statistics, and produces an alert when necessary. The admin website reads those database records on page requests.')
p('Prediction and logging are separate network operations. Render is the documented prediction host. Hostinger is the documented Laravel/MySQL deployment target. PostgreSQL/Supabase migration support remains in the repository for earlier deployments. Browser extensions communicate with Laravel over HTTP; they never contain database credentials or connect directly to MySQL. Render no longer provides a logs endpoint or writes monitoring records to Supabase.')
h('4. Full browsing and decision flow')
p('Step 1: Chrome loads the campus extension manifest. It registers background.js as its service worker, popup.html as the toolbar interface, and content.js at document_start for matching URLs. Step 2: background.js loads config.json once through a shared configuration promise. If configuration cannot be read, it retains a default prediction endpoint and blank campus/logging settings. Step 3: content.js skips localhost, 127.0.0.1, blank hosts, and browser pages; a recognizable brand substring never skips a check.')
p('Step 4: content.js creates a hidden overlay and sends fetch_phish_api with the current page URL. Step 5: the worker URL-encodes the destination, appends it to prediction_api_url, and performs a GET request with a nine-second timeout. Step 6: the API validates and classifies the URL and returns URL, score, engine, thresholds, model decision, final status, risk, and reason. Step 7: the worker starts asynchronous logging and immediately returns the prediction to the content script. Logging success is not required before showing the assessment.')
p('Step 8: Block displays an intervention overlay containing risk, score, and reason. Leave unsafe page attempts to close the window and then navigates to about:blank. Continue at my own risk removes the overlay. Suspicious or Medium shows a dismissible warning banner and leaves the page visible. Proceed removes the hidden overlay and keeps the page visible. Successful results are saved per URL and as lastPhishResult. Failed checks remove the overlay and leave the page available; service failure is not a safe verdict.')
p('This is a page-level intervention after navigation has begun, not a network firewall or guaranteed pre-request cancellation. The manifest does not define a declarative network blocking ruleset. A user may override a displayed intervention. The content script begins with the current URL; no general single-page-application URL observer or navigation history hook is implemented in this file.')
h('5. Device registration and identity allocation')
p('On the first successful assessment needing logging, the worker reads phishblockDeviceUuid from chrome.storage.local. If absent it creates crypto.randomUUID and saves it before requesting registration. The registration endpoint is derived from logging_api_url by replacing the final /phishing-logs path segment with /devices/register, preserving an application subfolder prefix. A logging URL that does not end with the expected path is rejected for registration.')
p('Laravel accepts a valid UUID and one of the six canonical campus names. It lowercases the UUID, upserts the campus, obtains a row lock on the campus, and finds an existing device for the campus/UUID pair. If new, it assigns one greater than the maximum computer number among devices and legacy phishing logs for that campus. The generated display name follows campus-PC-001, with at least three digits. The transaction allows five attempts. Registration refreshes last_seen_at and recalculates the distinct union of registered and logged computer numbers.')
p('The response contains device_uuid, campus_name, computer_number, and name. The extension verifies returned UUID, campus, and name, then saves phishblockDevice. A shared registrationPromise prevents concurrent checks from initiating duplicate registrations within the service-worker lifetime. Failed registration clears the promise so a later assessment can retry. A worker restart reuses the saved UUID and registers idempotently. Removing extension storage or uninstalling creates a new identity; cloning profile storage can duplicate an identity.')
h('6. Logging transaction and alerts')
p('The extension captures detected_at as an ISO UTC timestamp at logging initiation, before waiting for registration. It posts URL, score, risk_level, status, reason, device_uuid, campus_name, and approved model metadata. Requests for registration and logging use twelve-second timeouts. Blank logging_api_url disables registration/logging while prediction continues. Failed log submissions are written to the extension console and are not durably queued or automatically replayed.')
p('Laravel checks registered UUID against the submitted campus, rejects unknown or mismatched registrations, links device_id, and replaces any supplied computer number with the registered number. Legacy callers can omit device_uuid and supply computer_number. Only ml_engine, model_threshold, model_block_threshold, and model_decision metadata keys are persisted. Caller-selected database IDs are not accepted through the validated data. A transaction keeps the log, campus updates, and application-created alert consistent.')
p('On MySQL/SQLite the controller upserts and locks a campus and attaches campus_id before inserting. On PostgreSQL it expects supplied database triggers to register/link campuses and create alerts. In both paths the controller recomputes computer_count from the distinct union of log and device computer numbers. Proceed does not create an alert. Critical risk creates a Critical/Open alert. Block status or High risk creates a High/Open alert. Other non-Proceed results create a Medium/Monitoring alert. Automated alerts use owner Automated Detection and corresponding detection titles. Repeated suspicious events can create separate alerts; no aggregation/deduplication key is defined.')
h('7. Website routes, screens, authentication, and metrics')
p('GET / renders the public landing page through AdminController.home. It uses live database summaries, five recent monitoring records, capability descriptions, workflow text, campus coverage, and previews. Because this route is public and includes monitoring information, its current data exposure should be considered when choosing a deployment audience. The stock welcome.blade.php exists but is not the home route target.')
p('GET /login shows the login form; POST /login validates email/password and attempts authentication for an active account. Successful authentication regenerates the session and redirects to admin.dashboard. Failure returns an email-field error and preserves only the email input. POST /logout logs out, invalidates the session, regenerates the CSRF token, and redirects to login. All six admin GET routes require authentication plus an active user with role admin; a signed-in non-admin receives HTTP 403. No public user-registration or password-reset controller route is defined here.')
p('GET /admin/dashboard displays summary totals, campus health, recent records, priority alerts, hourly activity, and status mix. GET /admin/logs displays newest logs in pages of six, including Philippine time, score to four decimals, risk, status, reason, assigned device display name, campus, and URL. GET /admin/analytics displays hourly traffic, risk distribution, campus decision percentages, and campus health/threat exposure. GET /admin/alerts displays summary cards and five alerts per page. GET /admin/campuses displays counts, sync times, stored status, and uptime. GET /admin/settings displays settings grouped and sorted from the database.')
p('Total scans counts every phishing log. Blocked today counts Block records by created_at on the application current date. Review queue counts Suspicious records across the table. Active campuses counts is_active=true. Safe rate is rounded 100 times Safe-or-Low records divided by all records. Critical incidents counts Critical alerts whose status is neither Resolved nor Closed. Priority alerts count High and Critical; active alerts exclude Resolved/Closed. Alert campus count uses distinct non-null campus IDs.')
p('Campus threat rate is 100 times Block-plus-Suspicious divided by campus scans. Health score is rounded uptime_percentage minus 0.35 times threat rate, clamped from zero to 100. Scores at least 95 are Healthy; at least 85 are Watch; lower scores are At Risk. This computed dashboard health can differ from the stored campus status displayed on the campuses screen. Uptime is a stored value; the inspected code does not measure network uptime. Active campuses and recent sync values do not establish a continuous heartbeat service.')
p('Hourly traffic selects records since the start of the hour six hours ago and creates seven hourly buckets including the current hour. Bar height has a minimum four percent even for a zero-count bucket. Aggregations use created_at, while row display prefers detected_at and falls back to created_at, formatted in Asia/Manila. Latest ordering uses record creation time. monitoringRecords loads at most 100 newest records. Website views are server-rendered; no live polling or WebSocket feed is implemented in the inspected controllers/views. Shared layouts provide navigation, profile/logout, responsive menus, footer, and dark-mode preference under localStorage key phish-block-theme.')
h('8. Laravel API contract')
p('POST /api/devices/register: JSON device_uuid is required and must be a UUID; campus_name is required and must match Device.CAMPUSES. A successful response is HTTP 200 with identity fields. POST /api/phishing-logs: a successful insert is HTTP 201 with logged=true and the new id. Both routes use throttle:120,1, described in project setup as 120 requests per minute per IP. They are stateless public routes without dashboard login or browser CSRF-cookie requirements; registration is an identity allocation mechanism, not strong device authentication.')
p('Log validation: url is a required HTTP(S) URL string with at most 16,384 characters. score is nullable numeric from zero through one. risk_level must be Safe, Low, Medium, High, or Critical. status must be Proceed, Suspicious, or Block. reason is nullable with at most 8,192 characters. detected_at is nullable and must use the precise UTC millisecond shape YYYY-MM-DDTHH:mm:ss.sssZ. device_uuid is an optional-but-required-when-present UUID. computer_number is required without device_uuid and is an integer from one through 4,294,967,295. campus_name is required text up to 30 characters; unlike registration, this legacy log field is not restricted to the six-name enumeration.')
p('metadata is an optional array. ml_engine is nullable text up to 100 characters; warning/block thresholds are nullable numerics from zero through one; model_decision is nullable Proceed, Suspicious, or Block. Invalid JSON API data normally yields Laravel validation errors with HTTP 422. Throttled requests can yield HTTP 429. The controller validates field membership, not the semantic consistency of every score/risk/status combination; it trusts the submitted assessment and does not call FastAPI to independently re-score it.')
p('config/cors.php allows POST and OPTIONS, any origin, Content-Type and Accept headers, no credentials, and a 600-second max age for api/phishing-logs only. The registration route is not listed in that CORS path configuration. Extension worker host permissions cover remote calls, but ordinary website cross-origin callers should not assume the registration route has the same CORS policy. GET /up is Laravel framework health routing, distinct from FastAPI /health.')
h('9. Prediction API contract and hybrid decision policy')
p('GET / returns service information: message, model_name, pipeline_loaded, metadata_fallback_loaded=false, model_threshold, model_block_threshold, phishtank_loaded, and logging_backend=hostinger-laravel. GET /health returns healthy, model_loaded, and model_name with HTTP 200 when loaded and 503 when unavailable. GET /check_url requires query parameter url, length one through 16,384. FastAPI also provides generated /docs, /redoc, and /openapi.json documentation under its default configuration.')
p('The prediction response fields are url, score, ml_engine=phishing_url_bundle, model_threshold, model_block_threshold, ml_decision, risk_level, status, and reason. Note the mapping: API ml_decision is sent to Laravel as metadata.model_decision. The final status can differ from the ML-only decision because a verified reputation match or heuristic combination can raise final risk.')
p('Normalization trims input, accepts protocol-relative URLs and missing schemes by supplying HTTP, restricts schemes to HTTP/HTTPS, rejects whitespace/control characters, validates ports and hostnames, lowercases and IDNA-encodes hosts, strips a trailing host dot, supplies a slash for empty paths, and removes fragments. Path/query case and encoding remain intact. The normalized representation is scheme-independent; equivalent HTTP and HTTPS addresses can match the same normalized entry. Username information is retained when present. No target URL is fetched.')
p('predict_urls invokes the saved pipeline predict_proba and selects class 1 as phishing. Model classes must be ordered [0,1]. The warning threshold comes from bundle.threshold. A block threshold is optional and only accepted with validated policy and an ordered finite threshold between warning and one. Scores at or above a validated block threshold produce ML Block; at or above warning produce Suspicious; otherwise Proceed. Older warning-only bundles do not acquire an automatic blocking rule just because a score is high.')
p('The hybrid layer checks exact normalized membership in the loaded PhishTank set and optional Google Safe Browsing v4 threats for malware, social engineering, and potentially harmful applications. A positive reputation match or ML Block sets High/Block. Otherwise an ML warning or at least two pattern flags sets Medium/Suspicious. Remaining results are Low/Proceed. Pattern flags are IP hostname, @ in authority, at least three host dashes, and at least three host dots. The normal current hybrid path emits Low, Medium, and High; database/ingestion support Safe and Critical for other or older producers.')
p('PhishTank CSV requires a url column. If a verified column exists, only yes rows are loaded. Optional download by PHISHTANK_FILE_ID occurs only when the local file is absent. Missing/malformed optional PhishTank data leaves model detection available. Google requests use a six-second timeout; request/JSON failures are logged without the key and return no match. Invalid target URLs yield HTTP 422; missing model or unexpected classification errors yield HTTP 503. Absence of optional matches is not independent proof of safety.')
h('10. Model loading, feature families, and release integrity')
p('At startup the API loads dataset/phishing_url_bundle.joblib or MODEL_PATH. A missing file, wrong dictionary shape, invalid threshold, wrong classes, incompatible recorded scikit-learn version, or failed smoke prediction prevents readiness. The required bundle keys include pipeline and threshold. A test inference for example.com must return a finite probability from zero through one. The service never falls back to old numeric-feature metadata coefficients or old random-forest/pickle assets.')
p('Version-four-or-newer preprocessing bundles require deployment_approved. Version-five-or-newer bundles additionally require strict release proof, strict profile, matching warning/block thresholds and block-test proof when applicable, and a matching inference-helper source hash. The source hash policy tolerates checkout line-ending differences for text but not substantive source changes; binary file hashes remain exact. Serialization is only appropriate for trusted training artifacts; this documentation does not unpickle or execute model files.')
p('phishing_url_utils supports normalized raw URL baselines, leading-www-invariant text, separate hostname and path/query text, sparse and dense lexical structures, and a weighted probability ensemble. Removing a leading www label is a representation choice, not a domain allowlist. Lexical structure includes logged URL/host/path/query lengths, host dots/dashes, @ and percent counts, path depth, query separators, digit ratios, punycode marker, IP-host indicator, character entropy, homepage indicator, and account/login/security vocabulary. Not every research feature family is necessarily in the deployed binary; the actual saved pipeline determines runtime behavior.')
h('11. Database structure and relationships')
p('Domain tables are users, campuses, devices, phishing_logs, alerts, and system_settings. Framework support tables include password_reset_tokens, sessions, cache, cache_locks, jobs, job_batches, failed_jobs, and the migration repository. The following field register is extracted directly from Blueprint declarations and SQL. It records declared type, options, indexes, and constraints. Conditional migrations and database drivers mean not every declaration appears in every installed database; inspect the deployed schema before migration planning.')
for path in sorted((ROOT/'database/migrations').glob('*.php')):
    h('Migration: '+path.name)
    text=read(path)
    for line in text.splitlines():
        s=line.strip()
        if 'Schema::create(' in s or 'Schema::table(' in s:
            p('Table operation: '+s.replace('function (Blueprint $table)', '').replace(': void {','').rstrip(' {'))
        elif '$table->' in s and not s.startswith('//'):
            p('Declared field or constraint: '+s.replace('$table->','').rstrip(';'))
        elif 'DB::statement(' in s: p('Additional SQL operation: '+s)
p('PhishingLog belongs to Device and Campus; Alert belongs to Campus; Campus has many PhishingLog records. devices stores campus_name text rather than a campus foreign key. UUID and computer_number are each unique within campus_name. phishing_logs.device_id is nullable and constrained to devices. alerts.campus_id is nullable and becomes null on campus deletion. phishing_logs.campus_id is initially a nullable ID without a Blueprint foreign-key constraint in the supplied portable migration. The initial PostgreSQL SQL does not declare campus_id or updated_at although the later trigger/model path expects them; historical PostgreSQL installations therefore need schema compatibility review. MySQL-compatible migrations explicitly include timestamps and campus_id.')
p('PhishingLog casts score to float and metadata to array. detected_at is a custom immutable UTC accessor/mutator; PostgreSQL writes include an explicit offset, other drivers use a microsecond timestamp string. Campus casts last_sync_at to datetime, uptime_percentage to one decimal in the model, and is_active to boolean. Device casts last_seen_at to datetime. User hides password and remember_token, hashes assigned passwords, casts active state to boolean, and casts email verification time to datetime.')
p('The initial PostgreSQL log script enables row-level security and defines insert grants/policy for anon and authenticated roles, with URL length one through 4,096, valid risk, optional score bounds, and positive computer number. service_role receives broader rights. This is not equivalent to the Laravel maximum URL length of 16,384. devices migration enables PostgreSQL row-level security without defining a public device policy in that migration. The actual database role and installed policies determine direct database access.')
p('PostgreSQL BEFORE INSERT register_monitoring_campus upserts the campus and sets NEW.campus_id. AFTER INSERT create_monitoring_alert skips Proceed, creates the mapped alert, and updates campus counts from logs. Laravel controller recalculation also includes registered devices. The old action column is removed by a migration that first rewrites existing statuses by risk. detected_at is added separately and remains nullable for older logs. These are migration-time behavior changes, not proof of already-applied production changes.')
h('12. Seeding and configuration semantics')
p('DatabaseSeeder creates or updates an administrator using hardcoded seed credentials, canonicalizes legacy campus aliases SLSU-HC, SLSU-LU, and SLSU-BR, inserts five demonstration campus rows, twelve illustrative logs, four illustrative alerts, and nine settings. The seed omits a SanJuan demonstration row even though registration supports it. Seeded uptime, computer counts, times, reasons, thresholds, and notifications are demonstration content. Running this seeder can create or change existing records and reset the seeded administrator password; this documentation did not run it.')
p('The displayed settings include historical thresholds 0.85, 0.70, and 0.30, critical email alerts, escalation/digest schedules, a role preset, and session timeout text. Current API decisions use thresholds stored in the model bundle; no controller connects displayed settings to Python inference. The inspected web routes offer read screens, without update endpoints for settings, alert assignment/resolution, or campus editing. Email alert/digest labels alone do not implement dispatch jobs or a scheduler.')
p('config/database.php defaults to DB_CONNECTION, otherwise PostgreSQL when DATABASE_URL exists, otherwise SQLite. It contains SQLite, MySQL, MariaDB, PostgreSQL, SQL Server, and Redis connection configurations. DB_URL can override connection details, and PostgreSQL supports DATABASE_URL. Templates and source show intended configuration; private .env contents were not read. Website configuration covers application name/environment/key/debug/URL, database, sessions, cache, queue, filesystem, logging, mail, and services. Session and date behavior should be checked in the deployed configuration, especially blocked-today boundaries versus explicitly Philippine row display.')
h('13. Campus extension distributions and permissions')
for folder in sorted((ROOT/'extension-setup/extensions-version').glob('phishblock*')):
    cfg=json.loads(read(folder/'config.json')); manifest=json.loads(read(folder/'manifest.json'))
    h(folder.name)
    p('Campus: '+str(cfg.get('campus_name'))+'. Manifest name: '+manifest['name']+'. Version: '+manifest['version']+'. Prediction endpoint: '+cfg.get('prediction_api_url','')+'. Logging endpoint: '+(cfg.get('logging_api_url') or '(blank; disabled)')+'.')
    p('Permissions: '+', '.join(manifest.get('permissions',[]))+'. Host permissions: '+', '.join(manifest.get('host_permissions',[]))+'. Config and the branding logo are web-accessible resources for matching pages. Icons provide toolbar and extension branding. config.json assigns campus and endpoints; background.js handles remote requests, registration and logging; content.js handles page checks and interventions; popup.html defines the toolbar view; popup.js renders cached results and runs manual checks. backup.txt is retained reference material, not a manifest-loaded runtime script.')
p('The popup queries the active tab, displays its URL, looks up the exact URL in phishResultsByUrl, and runs a check when no cached result exists. Manual checks accept only HTTP(S), disable the button while waiting, and display unavailable state on failure. Scores are clamped to zero through one for percentage presentation. Popup checks update storage and the popup, but do not themselves invoke the content-script overlay path. The background also supports recheck_url forwarding to run_check in a specified tab. Cache entries have no explicit expiry or size-pruning mechanism in these scripts.')
h('14. Training, provenance, evaluation, and deployment workflow')
p('The notebook is the source of the research workflow. train_model.py executes its code cells in order without a Jupyter kernel, skips pip-install cells, uses a noninteractive plotting backend, tees output into an artifacts training log, and accepts --no-promote and --max-train-rows-per-source. It does not install dependencies, push Git, or deploy Render. Exit zero means promotion; exit two means a completed run without promotion; exceptions mean failed execution.')
p('Historical training inputs are retained separately from runtime dataset assets. data4 and verified PhishTank data are the documented default sources; the active experiment mechanism can replace the base data/split with a verified derived experiment. Other data files are optional or skipped when labels/provenance or raw URL fields are unsuitable. Reviewed homepage corrections and reviewed_training_urls require independent evidence; they are not trusted-domain bypass lists. Review additions require URL, explicit zero/one label, evidence, reviewer, and date. Evaluation-domain reviews are excluded from training.')
p('Frozen splits preserve domain assignments and dataset fingerprints. Reordering data reuses the same split; silently changing labels, data membership, seed, or reviewed-domain assignments is rejected rather than rebuilding an easier holdout. Training-only sampling leaves evaluation partitions unchanged. New base labels require a named experiment. Validation selects model/ensemble and warning/block thresholds; test evaluates the frozen selections. Raw-text diagnostics are not eligible as the production winner in the documented workflow.')
p('Strict warning acceptance requires precision at least 90 percent, phishing recall at least 80 percent, and benign warning rate at most 3 percent on validation and test, sufficient per-class support, and passing homepage regressions. Separate optional ML blocking requires precision at least 98 percent, benign block rate at most 0.5 percent, and at least 50 block predictions with corresponding test support. No qualifying block threshold disables ML-only blocking. A failed gate preserves the runtime model; --no-promote preserves it even when checks pass.')
p('The source-restored experiment manifest identifies phishstorm_restored_v1, 92,005 restored labels, and 4,023 quarantined reference rows. It describes a user-supplied publisher archive with matching extracted/member hashes and download-origin evidence; no publisher-signed checksum is claimed. This is historical source-label restoration, not verification of current website safety. Older notes about inaccessible source evidence are explicitly superseded by the newer verification manifest. The active experiment file points to training_data/experiments/phishstorm_restored_v1/experiment.json.')
p('A passing strict v5 workflow can generate github_api_release with the approved bundle, exact inference helper, requirements, verifier, model card, standalone Render configuration, and hash manifest. READY_FOR_GITHUB must be true. Failed runs do not create a production package. verify_release.py rechecks package integrity. Monorepo Render rootDir is extension-setup/api; a standalone API repository requires blank Root Directory and no monorepo rootDir. Approval gates and historical metrics do not guarantee future production accuracy. No model accuracy claim is made here from an unexecuted binary or old candidate report.')
h('15. Installation, deployment, maintenance, and troubleshooting')
p('The repository declares PHP ^8.3 and Laravel ^13.0. Frontend development declares Vite ^8, Tailwind ^4, Laravel Vite plugin ^3, and concurrently ^9. composer setup installs dependencies, creates an environment file if absent, generates a key, runs migrations, installs frontend packages, and builds assets. composer dev runs Laravel server, queue listener, and Vite concurrently. These scripts describe available operations; they were not executed for documentation generation.')
p('For Laravel hosting, use public as the web document root, production environment/debug settings, valid APP_KEY, intended APP_URL, and host-provided database credentials in private environment configuration. Use compatible migration history, back up existing data, apply migrations, and refresh configuration caches as described in HOSTINGER_SETUP.md. PostgreSQL SQL must not be pasted into MySQL phpMyAdmin. No credentials belong in extension configuration. Protect environment files and private research/runtime folders from direct web access.')
p('For FastAPI, the supplied Render configuration pins Python 3.12.10, installs requirements.txt, launches uvicorn app:app on 0.0.0.0 with $PORT and one worker, and checks /health. One worker avoids multiple model copies. Runtime dependency pins must match training. Optional variables are GOOGLE_API_KEY, PHISHTANK_FILE_ID, MODEL_PATH, and PHISHTANK_PATH. Relative override paths resolve from the API directory. A local environment can run uvicorn on loopback port 8000. /health model readiness and / response model information should be verified after actual deployment.')
p('Install or reload the appropriate campus folder using Chrome Load unpacked, configure its endpoints, and ensure Laravel device migrations exist before version 1.2 registration. A healthy prediction with an empty dashboard usually requires checking logging_api_url, campus identity, registration response, failed POSTs, rate limits, and backend/database logs. An unavailable prediction requires checking /health, bundle presence, dependencies, strict proof/helper hash, and the configured prediction address. Changing reviewed CSV rows alone never changes a running model; a passing train/package/deploy cycle is required.')
p('Maintenance should distinguish event detection time from receipt time, back up database and reproducibility inputs, preserve named split manifests, review failed candidates without overwriting runtime artifacts, and reload extensions after updates. There is no durable client log queue, device authentication secret, continuous online-device heartbeat, alert-resolution endpoint, or retention/archival job in the inspected implementation. These are current boundaries rather than completed features.')
h('16. Existing test coverage register')
for path in sorted(list((ROOT/'tests').rglob('*'))+list((ROOT/'extension-setup/api/tests').rglob('*.py'))):
    if not path.is_file() or '__pycache__' in path.parts: continue
    if path.suffix not in {'.php','.cjs','.py'}: continue
    text=read(path); names=re.findall(r"(?:test|it)\(\s*['\"]([^'\"]+)['\"]",text)
    names+=re.findall(r'def (test_\w+)\(',text)
    h(path.relative_to(ROOT).as_posix())
    if names:
        for name in names: p('Checks '+name.removeprefix('test_').replace('_',' ')+'.')
    else: p('Test bootstrap, framework configuration, fixtures, or shared test base.')
p('Project-documented verification commands include php artisan test --compact; node --test tests/extension-routing.test.cjs; node --test tests/extension-decisions.test.cjs; and python -m unittest discover -s extension-setup/api/tests -v with the required Python environment and httpx. Existing PHP feature tests use isolated SQLite and JavaScript tests stub network calls. This documentation-only change does not rerun application test suites or train a model.')

h('17. Source function and responsibility index')
for path in sorted((ROOT/'extension-setup/api').glob('*.py')):
    h(path.relative_to(ROOT).as_posix())
    try: tree=ast.parse(read(path))
    except SyntaxError: continue
    doc=ast.get_docstring(tree)
    if doc: p(doc)
    for node in ast.walk(tree):
        if isinstance(node,(ast.FunctionDef,ast.AsyncFunctionDef,ast.ClassDef)):
            description=ast.get_docstring(node)
            label=('Class ' if isinstance(node,ast.ClassDef) else 'Function ')+node.name
            p(label+': '+(description or 'Implements '+node.name.strip('_').replace('_',' ')+' within this module; its callers determine runtime use.'))
for path in sorted((ROOT/'app').rglob('*.php')):
    text=read(path); methods=re.findall(r'(public|protected|private) function (\w+)\(',text)
    h(path.relative_to(ROOT).as_posix())
    for visibility,name in methods: p(visibility.capitalize()+' method '+name+': '+re.sub(r'(?<!^)(?=[A-Z])',' ',name).lower()+'. See the behavior chapters for the associated screen, relation, cast, or transaction.')

h('18. Dependency and build declarations')
for rel in ['composer.json','package.json','public/home/package.json','public/template/package.json']:
    path=ROOT/rel
    if not path.exists(): continue
    h(rel); data=json.loads(read(path))
    for group in ['require','require-dev','dependencies','devDependencies','scripts']:
        if group in data:
            for name,value in data[group].items():
                p(group+' / '+name+': '+(value if isinstance(value,str) else '; '.join(value) if isinstance(value,list) else json.dumps(value)))
for rel in ['extension-setup/api/requirements.txt','extension-setup/api/requirements-training.txt']:
    h(rel)
    for line in read(ROOT/rel).splitlines():
        if line.strip() and not line.startswith('#'): p('Declared dependency: '+line)
p('Version strings above are repository declarations, not verification that those versions are available or installed. composer.lock and package-lock.json freeze dependency resolution. public/home uses its own Gulp/Pug/SCSS theme source and compiled output; public/template contains the administration template assets and its own build metadata. resources/views contains the Laravel page templates actually selected by application routes.')

h('19. Configuration variable name register')
for path in sorted(list((ROOT/'config').glob('*.php'))+[ROOT/'extension-setup/api/app.py']):
    text=read(path)
    keys=sorted(set(re.findall(r"(?:env|os.getenv)\(['\"]([A-Z][A-Z0-9_]*)",text)))
    if keys: p(path.relative_to(ROOT).as_posix()+': '+', '.join(keys)+'. Values come from environment or source defaults; private environment values are omitted.')

h('20. Dataset and research artifact register')
p('Raw dataset rows and historical browsing URLs are not reproduced. CSV schemas, file sizes, and names below explain available data without publishing their contents. Artifacts are research evidence, not automatic production deployment. Serialized bundles are inventoried as files only, never executed for extraction.')
api=ROOT/'extension-setup/api'
for sub in ['dataset','training_data','artifacts']:
    base=api/sub
    files=sorted(x for x in base.rglob('*') if x.is_file() and '__pycache__' not in x.parts) if base.exists() else []
    p(sub+': '+str(len(files))+' files; '+str(sum(x.stat().st_size for x in files))+' bytes. Extensions: '+', '.join(k+'='+str(v) for k,v in sorted(Counter(x.suffix or '(none)' for x in files).items()))+'.')
    if sub!='artifacts':
        for path in files:
            if path.suffix.lower()=='.csv':
                try:
                    with path.open(encoding='utf-8-sig',errors='replace',newline='') as f: header=next(csv.reader(f),[])
                    if header and len(header)<50 and all(len(v)<100 for v in header) and not any('://' in v for v in header):
                        p(path.relative_to(ROOT).as_posix()+' column headings: '+', '.join(header)+'.')
                except OSError: pass

h('21. Existing written documentation, extracted as text')
p('The following preserves explanatory prose from project-maintained notes, including historical status. Earlier chapters resolve material differences in favor of current source. Historical statements, commands, metrics, or deployment observations below are attributed to their source file and are not new verification. Code fences are omitted because this is a prose documentation PDF; source declarations and contracts are covered elsewhere. Links and paths remain text references.')
docs=[ROOT/'README.md']+sorted((ROOT/'extension-setup').rglob('*.md'))
for path in docs:
    rel=path.relative_to(ROOT).as_posix()
    if '/artifacts/' in rel or '/github_api_release/' in rel: continue
    h('Source notes: '+rel)
    fence=False; buf=[]
    def flush():
        if buf: p(' '.join(buf)); buf.clear()
    for line in read(path).splitlines():
        if line.strip().startswith('```'): flush(); fence=not fence; continue
        if fence: continue
        if not line.strip(): flush(); continue
        clean=re.sub(r'!\[([^\]]*)\]\([^)]*\)',r'\1',line)
        clean=re.sub(r'\[([^\]]+)\]\(([^)]+)\)',r'\1 (\2)',clean)
        clean=clean.replace('`','').replace('**','')
        if clean.startswith('#'): flush(); p(clean.lstrip('# '))
        elif re.match(r'^\s*(?:[-*]|\d+\.) ',clean): flush(); p(clean.lstrip(' -*'))
        else: buf.append(clean.strip())
    flush()

h('22. Full project file-purpose inventory')
p('Each project file discovered outside installed dependencies, Git internals, caches, private environment files, and runtime storage is listed below with its role and size. The inventory includes static assets, historical artifacts, training corpora, compiled bundles, and retained backups as well as application code. No source file is implied to be loaded merely because it exists. Large installed third-party trees are summarized after the project inventory.')
skip={'.git','node_modules','vendor','__pycache__','.pytest_cache','.venv','venv'}
def purpose(rel):
    path=Path(rel); name=path.name; suffix=path.suffix.lower()
    if '/artifacts/' in rel:
        if suffix=='.joblib': return 'Saved research candidate or release bundle; approval status depends on its corresponding report.'
        if suffix=='.csv': return 'Research output rows for metrics, coverage, reviews, predictions, or comparisons identified by the filename.'
        if suffix=='.json': return 'Research manifest, evaluation metrics, or release proof identified by the filename.'
        if suffix=='.log': return 'Training execution transcript; contents not copied into this public document.'
        return 'Generated research/release artifact; filename and parent run identify its experiment.'
    if suffix in {'.csv','.zip'} and ('training_data/' in rel or '/dataset/' in rel): return 'Dataset, reviewed input, historical snapshot, or source archive; consult data provenance and schema register.'
    if suffix in {'.joblib','.pkl'}: return 'Serialized model asset; only the configured approved joblib bundle is runtime authority.'
    if rel.startswith('app/Http/Controllers/'): return 'HTTP request/controller implementation; contracts and flow documented in preceding chapters.'
    if rel.startswith('app/Models/'): return 'Eloquent domain model defining persistence attributes, casts, or relationships.'
    if rel.startswith('app/Http/Middleware/'): return 'Request access-control middleware for active administrators.'
    if rel.startswith('database/migrations/'): return 'Ordered schema/data migration; field declarations listed in the database chapter.'
    if rel.startswith('supabase/migrations/'): return 'PostgreSQL schema, security policy, or trigger migration for existing database deployments.'
    if rel.startswith('database/seeders/'): return 'Administrator and demonstration-data initialization; may modify existing records.'
    if rel.startswith('database/factories/'): return 'Synthetic model fixture factory for tests/development.'
    if rel.startswith('tests/') or '/tests/' in rel: return 'Automated test or shared test setup; intended assertions indexed in test coverage.'
    if rel.startswith('resources/views/'): return 'Server-rendered Blade page, shared layout, or reusable theme partial identified by filename.'
    if rel.startswith('config/'): return 'Laravel '+path.stem+' service configuration and environment-default declarations.'
    if rel.startswith('routes/'): return 'Laravel '+path.stem+' route or command registration.'
    if name=='config.json' and 'extensions-version/' in rel: return 'Campus identifier and prediction/logging endpoint configuration for this distribution.'
    if name=='manifest.json' and 'extensions-version/' in rel: return 'Chrome Manifest V3 version, permissions, entry points, icons, and resource exposure.'
    if name=='background.js': return 'Extension worker: configuration, fetch timeouts, device identity, registration, logging, and message routing.'
    if name=='content.js': return 'Browser-page assessment, result storage, warning banner, intervention overlay, and user actions.'
    if name=='popup.js': return 'Active-tab cached result rendering and manual prediction request handling.'
    if name=='popup.html': return 'Extension toolbar popup markup and presentation styles.'
    if name=='backup.txt': return 'Retained extension backup/reference text; not loaded by the manifest.'
    if suffix in {'.png','.jpg','.jpeg','.webp','.gif','.ico','.svg','.avif'}: return 'Static image, branding, illustration, screenshot, or icon asset; not detection logic.'
    if suffix in {'.ttf','.woff','.woff2','.eot','.otf'}: return 'Font or icon-font resource for theme typography.'
    if suffix in {'.mp4','.webm'}: return 'Template media/video resource; not part of inference or ingestion.'
    if suffix=='.map': return 'Generated source map linking compiled frontend code/styles to original source.'
    if rel.startswith('public/template/'): return 'Bundled administration theme '+({'.js':'behavior/library script','.css':'stylesheet','.html':'static example page','.scss':'style source'}.get(suffix,'resource or build configuration'))+'; route integration is defined in Blade views.'
    if rel.startswith('public/home/'): return 'Landing theme '+({'.pug':'Pug template source','.scss':'SCSS style source','.js':'script or Gulp build task','.css':'compiled stylesheet','.html':'compiled static demo page'}.get(suffix,'asset or build metadata'))+'; Laravel uses resources/views/home.blade.php for its home route.'
    if name.endswith('.lock') or name=='package-lock.json': return 'Dependency resolution lock file for reproducible installation.'
    if name=='model_meta.json': return 'Legacy numeric-feature model metadata; not a runtime inference fallback.'
    if name=='db_query_create.txt': return 'Retained historical PostgreSQL database setup reference; not MySQL installation SQL.'
    if suffix=='.ipynb': return 'Research notebook defining data preparation, fitting, validation, reporting, and guarded promotion.'
    if suffix=='.md': return 'Human-readable project/setup/research documentation; selected notes extracted earlier.'
    if suffix=='.py': return 'Python prediction, preprocessing, training, provenance, or release helper; function index lists declarations.'
    if '/training_data/' in rel: return 'Training provenance, frozen split assignment, review evidence, or experiment-selection metadata.'
    if rel.startswith('bootstrap/'): return 'Laravel application bootstrapping or provider registration.'
    if rel.startswith('resources/'): return 'Application frontend entry point or source style/script.'
    if name=='index.php': return 'Public Laravel HTTP front controller; boots framework and dispatches requests.'
    if name=='artisan': return 'Laravel command-line entry point.'
    if name=='robots.txt': return 'Crawler directives; not authentication or route authorization.'
    if name in {'render.yaml','Procfile','runtime.txt','.python-version'}: return 'Prediction-service deployment/runtime process configuration.'
    if name in {'.env.example','.envexample'}: return 'Environment variable template; real private environment files are excluded.'
    return 'Project configuration, build input, metadata, or retained supporting resource; purpose is indicated by path and filename.'

inventory=[]; excluded=Counter()
def suffix_is_private(name):
    return Path(name).suffix.lower() in {'.sqlite', '.sqlite3', '.db', '.pem', '.key'}

for directory, dirs, files in os.walk(ROOT):
    current=Path(directory)
    dirs[:]=sorted(d for d in dirs if d not in skip and not (current==ROOT and d=='storage') and not (current==ROOT/'bootstrap' and d=='cache') and (current/d)!=OUT)
    for name in sorted(files):
        path=current/name; rel=path.relative_to(ROOT).as_posix()
        if name=='.env' or name.startswith('.env.') and name!='.env.example' or suffix_is_private(name):
            excluded['private environment or database file']+=1; continue
        inventory.append((rel,path.stat().st_size,purpose(rel)))
for rel,size,role in inventory: p(rel+' ('+str(size)+' bytes). '+role)
h('23. Excluded generated/dependency directories and extraction limits')
for rel in ['vendor','node_modules','storage','bootstrap/cache','.git']:
    base=ROOT/rel
    if not base.exists(): continue
    count=0; size=0
    for directory,dirs,files in os.walk(base):
        for name in files:
            try: count+=1; size+=(Path(directory)/name).stat().st_size
            except OSError: pass
    roles={'vendor':'Composer-installed framework and PHP libraries','node_modules':'npm-installed frontend libraries and build tools','storage':'private runtime logs, framework state, compiled views, cache, and application files','.git':'version-control objects and repository metadata','bootstrap/cache':'generated Laravel configuration and package caches'}
    p(rel+': '+roles[rel]+'. '+str(count)+' files, '+str(size)+' bytes. These are summarized by directory and dependency declarations rather than individually interpreted as project-authored code.')
p('Private .env values, seeded passwords, local database contents, raw corpus rows, notebook execution outputs, and historical training log bodies are not published. Model binaries were not deserialized. No live database schema or deployment was inspected. Full project-file inventory contains '+str(len(inventory))+' files. This scope distinguishes repository-backed documentation from claims about current production state.')

def render():
    md=OUT/'Phish_Block_System_Documentation.md'
    md.write_text('\n\n'.join(('# '+v) if k=='h' else v for k,v in parts)+'\n',encoding='utf-8')
    font=Path('C:/Windows/Fonts/arial.ttf'); bold=Path('C:/Windows/Fonts/arialbd.ttf')
    if font.exists():
        pdfmetrics.registerFont(TTFont('DocBody',str(font)))
        pdfmetrics.registerFont(TTFont('DocBold',str(bold)))
    else:
        font=Path('/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf')
        if font.exists():
            pdfmetrics.registerFont(TTFont('DocBody',str(font)))
            pdfmetrics.registerFont(TTFont('DocBold',str(font.with_name('DejaVuSans-Bold.ttf'))))
    body='DocBody' if 'DocBody' in pdfmetrics.getRegisteredFontNames() else 'Helvetica'
    strong='DocBold' if body=='DocBody' else 'Helvetica-Bold'
    styles=getSampleStyleSheet()
    styles.add(ParagraphStyle(name='DenseBody',fontName=body,fontSize=9.1,leading=12.3,spaceAfter=5,alignment=TA_JUSTIFY,splitLongWords=True))
    styles.add(ParagraphStyle(name='DenseHead',fontName=strong,fontSize=11,leading=14,spaceBefore=10,spaceAfter=6,keepWithNext=True,splitLongWords=True))
    pdf=OUT/'Phish_Block_System_Documentation.pdf'
    def footer(canvas,doc):
        canvas.setFont(body,7)
        canvas.setFillColor(colors.HexColor('#444444'))
        canvas.drawString(29,19,'Phish Block | Source documentation | 29 October 2026')
        canvas.drawRightString(A4[0]-29,19,'Page '+str(doc.page))
    doc=SimpleDocTemplate(str(pdf),pagesize=A4,rightMargin=29,leftMargin=29,topMargin=28,bottomMargin=33,title='Phish Block Monitoring System - Complete Technical Documentation',author='Project source documentation')
    story=[]
    for kind,value in parts:
        value=value.replace('\x00','').replace('\t',' ')
        story.append(Paragraph(escape(value),styles['DenseHead' if kind=='h' else 'DenseBody']))
    doc.build(story,onFirstPage=footer,onLaterPages=footer)
    reader=PdfReader(str(pdf)); texts=[page.extract_text() or '' for page in reader.pages]
    report={'pdf':pdf.name,'pages':len(reader.pages),'project_files_inventoried':len(inventory),'words':sum(len(t.split()) for t in texts),'blank_pages':[i+1 for i,t in enumerate(texts) if len(t.strip())<80],'pdf_bytes':pdf.stat().st_size,'sha256':hashlib.sha256(pdf.read_bytes()).hexdigest(),'scope':'Local source snapshot only; no live services or database access; no model deserialization.'}
    (OUT/'documentation_validation.json').write_text(json.dumps(report,indent=2)+'\n',encoding='utf-8')
    print(json.dumps(report,indent=2))

if __name__ == '__main__':
    render()

