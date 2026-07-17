from fastapi import FastAPI, HTTPException, Query
from fastapi.responses import JSONResponse
from dotenv import load_dotenv
from pydantic import BaseModel, ConfigDict, Field
from typing import Any, Literal
import joblib
import pandas as pd
from urllib.parse import urlparse
import os
import requests
import gdown
import traceback
import time

load_dotenv()

# ===============================
# Config
# ===============================
DATASET_DIR = "dataset"
os.makedirs(DATASET_DIR, exist_ok=True)

# Environment variable names expected on Render
ENV_FILE_IDS = {
    "phishing_detector_model.pkl": "MODEL_FILE_ID",
    "scaler.pkl": "SCALER_FILE_ID",
    "feature_columns.pkl": "FEATURES_FILE_ID",
    "phishtank.csv": "PHISHTANK_FILE_ID",
}

GOOGLE_API_KEY = os.getenv("GOOGLE_API_KEY", None)
SUPABASE_URL = os.getenv("SUPABASE_URL", "").rstrip("/")
SUPABASE_KEY = os.getenv("SUPABASE_KEY", "")
SUPABASE_LOGS_URL = (
    f"{SUPABASE_URL}/rest/v1/phishing_logs" if SUPABASE_URL else ""
)

# Globals for model/data
rf_model = None
scaler = None
TRAIN_FEATURES = None
phishtank_urls = set()

# ===============================
# Download via gdown
# ===============================
def download_from_drive_with_gdown(file_id: str, dest_path: str) -> bool:
    if not file_id:
        print(f"⚠️ Missing file ID for {dest_path}.")
        return False

    if os.path.exists(dest_path):
        print(f"✅ {dest_path} already exists.")
        return True

    url = f"https://drive.google.com/uc?id={file_id}"
    try:
        print(f"⬇️ Downloading {dest_path} from Google Drive (id={file_id})...")
        out = gdown.download(url, dest_path, quiet=False, fuzzy=True)
        if out and os.path.exists(dest_path):
            print(f"✅ Downloaded {dest_path}")
            return True
        else:
            print(f"❌ gdown failed to download {dest_path}.")
            return False
    except Exception as e:
        print(f"❌ Exception while downloading {dest_path}: {e}")
        traceback.print_exc()
        return False

# Download required files
for filename, env_name in ENV_FILE_IDS.items():
    file_id = os.getenv(env_name)
    dest_path = os.path.join(DATASET_DIR, filename)
    if file_id:
        success = download_from_drive_with_gdown(file_id, dest_path)
        if not success:
            print(f"⚠️ Warning: could not download {filename} (env {env_name}).")
    else:
        print(f"⚠️ Environment variable {env_name} not set. {filename} not downloaded automatically.")

# ===============================
# Safe load
# ===============================
def safe_load_joblib(path):
    try:
        start = time.time()
        obj = joblib.load(path)
        print(f"✅ Loaded `{path}` in {time.time()-start:.2f}s")
        return obj
    except Exception as e:
        print(f"❌ Failed to load `{path}`: {e}")
        traceback.print_exc()
        return None

rf_model = safe_load_joblib(os.path.join(DATASET_DIR, "phishing_detector_model.pkl"))
scaler = safe_load_joblib(os.path.join(DATASET_DIR, "scaler.pkl"))
TRAIN_FEATURES = safe_load_joblib(os.path.join(DATASET_DIR, "feature_columns.pkl"))

# ===============================
# Load PhishTank
# ===============================
def load_phishtank_blocklist(file_path=os.path.join(DATASET_DIR, "phishtank.csv")):
    try:
        df = pd.read_csv(file_path, dtype=str)
        if "url" in df.columns:
            urls = set(df["url"].str.strip().str.lower())
            print(f"✅ Loaded {len(urls)} URLs from PhishTank.")
            return urls
        else:
            print("⚠️ 'url' column not found in phishtank.csv. Returning empty set.")
            return set()
    except FileNotFoundError:
        print(f"⚠️ PhishTank CSV not found at {file_path}.")
        return set()
    except Exception as e:
        print(f"❌ Error loading PhishTank CSV: {e}")
        traceback.print_exc()
        return set()

phishtank_urls = load_phishtank_blocklist()

# ===============================
# Google Safe Browsing
# ===============================
def check_with_google_safebrowsing(url, api_key=GOOGLE_API_KEY):
    if not api_key:
        return False
    endpoint = "https://safebrowsing.googleapis.com/v4/threatMatches:find"
    body = {
        "client": {"clientId": "PhishingDetector", "clientVersion": "1.0"},
        "threatInfo": {
            "threatTypes": ["MALWARE", "SOCIAL_ENGINEERING", "POTENTIALLY_HARMFUL_APPLICATION"],
            "platformTypes": ["ANY_PLATFORM"],
            "threatEntryTypes": ["URL"],
            "threatEntries": [{"url": url}]
        }
    }
    try:
        resp = requests.post(f"{endpoint}?key={api_key}", json=body, timeout=6)
        res = resp.json()
        return bool(res.get("matches"))
    except Exception as e:
        print(f"⚠️ SafeBrowsing check failed: {e}")
        return False

# ===============================
# Feature extraction
# ===============================
def extract_url_features(url):
    parsed = urlparse(url)
    domain = parsed.netloc
    path = parsed.path

    feats = {
        "UrlLength": len(url),
        "HostnameLength": len(domain),
        "NumDots": url.count("."),
        "NumDash": url.count("-"),
        "NumNumericChars": sum(c.isdigit() for c in url),
        "NoHttps": int(not url.lower().startswith("https")),
        "AtSymbol": int("@" in url),
        "DoubleSlashInPath": int("//" in path),
        "SuspiciousSubdomain": int(domain.count(".") > 2),
        "ContainsBrand": int(any(b in url.lower() for b in ["paypal", "bank", "amazon"])),
    }

    if isinstance(TRAIN_FEATURES, (list, tuple)):
        return {col: feats.get(col, 0) for col in TRAIN_FEATURES}
    else:
        return feats

# ===============================
# Pattern-based detection (softer)
# ===============================
def pattern_based_check(url):
    parsed = urlparse(url)
    domain = parsed.netloc.lower()
    host = domain.split(":")[0]  # strip port if present

    # More robust IP check
    parts = host.split(".")
    is_ip = (
        len(parts) == 4 and
        all(p.isdigit() and 0 <= int(p) <= 255 for p in parts)
    )

    has_at = "@" in url
    # Only flag if there are many dashes (3 or more) in hostname
    many_dashes = host.count("-") >= 3
    # Only flag as suspicious if subdomain depth is high
    suspicious_subdomain = host.count(".") >= 3

    patterns = {
        "Has IP": is_ip,
        "Has @": has_at,
        "Many Dashes": many_dashes,
        "Suspicious Subdomain": suspicious_subdomain,
    }
    return patterns

# ===============================
# Unified Hybrid Check (tuned risk logic)
# ===============================
def hybrid_check(url, api_key=GOOGLE_API_KEY):
    url = url.strip().lower()

    # --- Checks ---
    phishtank_flag = url in phishtank_urls
    api_flag = check_with_google_safebrowsing(url, api_key) if api_key else False

    feats = extract_url_features(url)
    features_df = pd.DataFrame([feats])
    try:
        X_scaled = scaler.transform(features_df)
        ml_proba = rf_model.predict_proba(X_scaled)[0][1]
    except Exception as e:
        print("❌ ML inference error:", e)
        traceback.print_exc()
        ml_proba = None

    pattern_flags = pattern_based_check(url)
    num_pattern_flags = sum(pattern_flags.values())
    pattern_flag = num_pattern_flags > 0

    # --- Risk assessment ---
    risk_level = "Low"
    reason_list = []

    # Strong signals: blocklists / Google Safe Browsing
    if phishtank_flag:
        risk_level = "High"
        reason_list.append("Phishing (PhishTank)")
    if api_flag:
        risk_level = "High"
        reason_list.append("Phishing (Google Safe Browsing)")

    # ML-based signals
    if ml_proba is not None:
        if ml_proba >= 0.85:
            risk_level = "High"
            reason_list.append(f"High ML score ({ml_proba:.2f})")
        elif ml_proba >= 0.70:
            if num_pattern_flags >= 1:
                risk_level = "High"
                reason_list.append(f"ML score {ml_proba:.2f} + pattern flags")
            else:
                # No strong patterns, but ML is moderately high
                if risk_level != "High":
                    risk_level = "Medium"
                reason_list.append(f"Medium ML score ({ml_proba:.2f})")
        elif ml_proba >= 0.50:
            # Borderline zone
            if num_pattern_flags >= 2 and risk_level == "Low":
                risk_level = "Medium"
                reason_list.append(
                    f"Borderline ML score ({ml_proba:.2f}) + multiple patterns"
                )
            else:
                reason_list.append(f"Low ML score ({ml_proba:.2f})")
        else:
            reason_list.append(f"Very low ML score ({ml_proba:.2f})")
    else:
        reason_list.append("ML score unavailable")

    # Pattern-only influence when everything else is low
    if risk_level == "Low":
        if num_pattern_flags >= 2:
            risk_level = "Medium"
            labels = [k for k, v in pattern_flags.items() if v]
            reason_list.append("Suspicious Pattern: " + ", ".join(labels))
        elif num_pattern_flags == 1:
            labels = [k for k, v in pattern_flags.items() if v]
            # Only log, don't upgrade to Medium
            reason_list.append("Mild pattern flags: " + ", ".join(labels))

    # --- Final status ---
    if risk_level == "High":
        status = "❌ Block"
    elif risk_level == "Medium":
        status = "⚠️ Suspicious"
    else:
        status = "✅ Proceed"

    return {
        "url": url,
        "score": float(ml_proba) if ml_proba is not None else None,
        "risk_level": risk_level,
        "status": status,
        "reason": " | ".join(reason_list) if reason_list else "No strong signals"
    }

# ===============================
# FastAPI app
# ===============================
app = FastAPI(title="Phishing Detection API")


class PhishingLog(BaseModel):
    model_config = ConfigDict(extra="ignore", str_strip_whitespace=True)

    url: str = Field(min_length=1, max_length=4096)
    score: float | None = Field(default=None, ge=0, le=1)
    risk_level: Literal["Safe", "Low", "Medium", "High", "Critical"]
    status: str = Field(min_length=1, max_length=50)
    reason: str | None = None
    computer_number: int | None = Field(default=None, gt=0)
    campus_name: str | None = Field(default=None, max_length=100)
    action: str | None = Field(default=None, max_length=50)
    metadata: dict[str, Any] = Field(default_factory=dict)


def save_phishing_log(log: PhishingLog) -> None:
    if not SUPABASE_LOGS_URL or not SUPABASE_KEY:
        raise HTTPException(
            status_code=503,
            detail="Supabase logging is not configured on the server.",
        )

    payload = log.model_dump(exclude_none=True)
    if "action" not in payload:
        payload["action"] = (
            "Blocked" if log.risk_level in {"High", "Critical"} else "Allowed"
        )

    try:
        response = requests.post(
            SUPABASE_LOGS_URL,
            headers={
                "apikey": SUPABASE_KEY,
                "Authorization": f"Bearer {SUPABASE_KEY}",
                "Content-Type": "application/json",
                "Prefer": "return=minimal",
            },
            json=payload,
            timeout=10,
        )
    except requests.RequestException as exc:
        print(f"Supabase logging request failed: {exc}")
        raise HTTPException(
            status_code=502,
            detail="Could not reach the monitoring database.",
        ) from exc

    if response.status_code not in {200, 201, 204}:
        print(f"Supabase logging failed with HTTP {response.status_code}")
        raise HTTPException(
            status_code=502,
            detail="The monitoring database rejected the log.",
        )

@app.get("/")
def home():
    return {
        "message": "🛡️ Phishing Detection API is running!",
        "model_loaded": rf_model is not None,
        "scaler_loaded": scaler is not None,
        "features_loaded": TRAIN_FEATURES is not None,
        "phishtank_loaded": len(phishtank_urls) > 0,
        "supabase_configured": bool(SUPABASE_LOGS_URL and SUPABASE_KEY),
    }

@app.get("/check_url")
def check_url(url: str = Query(...)):
    # Fail fast if model missing
    if rf_model is None or scaler is None or TRAIN_FEATURES is None:
        return JSONResponse(
            status_code=500,
            content={
                "error": "Model or artifacts not loaded on server. Check logs.",
                "model_loaded": rf_model is not None,
                "scaler_loaded": scaler is not None,
                "features_loaded": TRAIN_FEATURES is not None
            }
        )

    # Use unified checker
    try:
        result = hybrid_check(url)
        return JSONResponse(content=result)
    except Exception as e:
        print("❌ Hybrid check error:", e)
        traceback.print_exc()
        return JSONResponse(status_code=500, content={"error": "Hybrid check failed", "detail": str(e)})


@app.post("/logs", status_code=201)
def create_log(log: PhishingLog):
    save_phishing_log(log)
    return {"logged": True}

@app.get("/health")
def health():
    ok = rf_model is not None and scaler is not None and TRAIN_FEATURES is not None
    return {
        "healthy": ok,
        "supabase_configured": bool(SUPABASE_LOGS_URL and SUPABASE_KEY),
    }
