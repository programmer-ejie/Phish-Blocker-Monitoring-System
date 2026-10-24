# Phishing Detection API

FastAPI backend for the browser extension. Inference uses the retrained character
TF-IDF + Logistic Regression bundle at `dataset/phishing_url_bundle.joblib`, with
its saved decision threshold (0.569018 for the supplied artifact). There is no
fallback to the old numeric-feature model or metadata coefficients.

## Deploy to Render

Commit and push the API changes, including the model binary and
`phishing_url_utils.py`. Keep `.env` out of Git. Do not include the old `.pkl` or
Random Forest files when creating a fresh API-only repository.

For a Web Service connected to the full monitoring-system repository:

| Setting | Value |
| --- | --- |
| Runtime | Python 3 |
| Root Directory | `extension-setup/api` |
| Build Command | `pip install -r requirements.txt` |
| Start Command | `uvicorn app:app --host 0.0.0.0 --port $PORT --workers 1` |
| Health Check Path | `/health` |
| Environment | `PYTHON_VERSION=3.12.10` |

Alternatively create a Blueprint using `extension-setup/api/render.yaml` as its
Blueprint path. That file assumes the full repository layout. If deploying an
API-only repository, clear Root Directory / remove `rootDir` from the Blueprint.

The pinned scikit-learn, NumPy, SciPy, pandas, and joblib versions match the
training environment. Keep them synchronized with any replacement model.
Python 3.12 is specified in `.python-version`; the Blueprint pins 3.12.10.
`runtime.txt` is retained for older tooling; Render uses `PYTHON_VERSION` or
`.python-version`.

Set these environment variables as needed:

- `GOOGLE_API_KEY`: optional Google Safe Browsing checks.
- `PHISHTANK_FILE_ID`: optional public Google Drive CSV ID, downloaded at startup
  only when the local CSV is absent. Existing `dataset/phishtank.csv` is ignored
  by Git; set this ID if you want a blocklist on Render without committing it.
- `MODEL_PATH` / `PHISHTANK_PATH`: optional path overrides, relative to the API
  directory unless absolute. Model default: `dataset/phishing_url_bundle.joblib`.

The model is loaded and smoke-tested at startup. A missing or incompatible model
fails startup instead of returning misleading safe results. `/health` returns 200
only while the model is loaded. Optional PhishTank/Google failures leave ML
detection enabled. One worker avoids loading multiple copies of the model.

## Verify after deployment

1. Visit `https://YOUR-SERVICE.onrender.com/health` and check `healthy: true`.
2. Visit `/` and check `model_name` and `model_threshold`.
3. Call `/check_url?url=https%3A%2F%2Fexample.com%2F`. Scores are predictions,
   not independently verified threat verdicts.
4. Configure `logging_api_url` in each extension config to your Hostinger website's `/api/phishing-logs` endpoint. Logging is handled by Laravel/MySQL, not Render.
5. Set `prediction_api_url` in each extension's `config.json` to the Render
   `/check_url?url=` address. Reload the extension after changing its configuration.

`/check_url` preserves `url`, `score`, `ml_engine`, `risk_level`, `status`, and
`reason`, adding `model_threshold`. Model scores at or above the saved threshold
produce `High` / `Block`; verified blocklist or Google matches also block. Below
threshold, multiple heuristic patterns can produce `Medium` / `Suspicious`.
This intentionally replaces the old fixed 0.70/0.85 score rules. Path/query case
is preserved to match training preprocessing. Submitted target URLs are not fetched.

## Run locally

```powershell
python -m venv .venv
.\.venv\Scripts\python -m pip install -r requirements.txt
.\.venv\Scripts\python -m uvicorn app:app --host 127.0.0.1 --port 8000
```

Copy `.envexample` to `.env` for local settings. `/docs` provides the interactive
API documentation. In a development environment install `httpx`, then run
`python -m unittest discover -s tests -v` for the offline API checks.

Render references: [FastAPI deployment](https://render.com/docs/deploy-fastapi),
[Python version configuration](https://render.com/docs/python-version), and
[Blueprint specification](https://render.com/docs/blueprint-spec).

## Logging architecture

Render only serves predictions. `/logs` and Supabase configuration have been removed.
The extensions submit logs directly to the Laravel `/api/phishing-logs` endpoint.
See `../HOSTINGER_SETUP.md` for MySQL deployment and all six extensions.
