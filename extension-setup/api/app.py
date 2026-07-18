from fastapi import FastAPI, HTTPException, Query
from fastapi.responses import JSONResponse
from dotenv import load_dotenv
from pydantic import BaseModel, ConfigDict, Field
from typing import Any, Literal
import joblib
import pandas as pd
from urllib.parse import parse_qs, urlparse
import os
import requests
import gdown
import traceback
import time
import json
import math

load_dotenv()

# ===============================
# Config
# ===============================
DATASET_DIR = "dataset"
os.makedirs(DATASET_DIR, exist_ok=True)

# Environment variable names expected on Render
ENV_FILE_IDS = {
    "phishtank.csv": "PHISHTANK_FILE_ID",
}

GOOGLE_API_KEY = os.getenv("GOOGLE_API_KEY", None)
SUPABASE_URL = os.getenv("SUPABASE_URL", "").rstrip("/")
SUPABASE_KEY = os.getenv("SUPABASE_KEY", "")
SUPABASE_LOGS_URL = (
    f"{SUPABASE_URL}/rest/v1/phishing_logs" if SUPABASE_URL else ""
)

# Globals for model/data
ml_pipeline = None
MODEL_META = None
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

ml_pipeline = safe_load_joblib(
    os.path.join(DATASET_DIR, "sklearn_pipeline.joblib")
)

try:
    with open(os.path.join(DATASET_DIR, "model_meta.json"), encoding="utf-8") as meta_file:
        MODEL_META = json.load(meta_file)
except (OSError, ValueError) as exc:
    print(f"Could not load model metadata fallback: {exc}")

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
def extract_url_features(url, feature_names=None):
    parsed = urlparse(url)
    domain = parsed.netloc.split(":")[0]
    path = parsed.path
    path_parts = [part for part in path.split("/") if part]

    feats = {
        "UrlLength": len(url),
        "HostnameLength": len(domain),
        "NumDots": url.count("."),
        "SubdomainLevel": max(domain.count(".") - 1, 0),
        "PathLevel": len(path_parts),
        "NumDash": url.count("-"),
        "NumDashInHostname": domain.count("-"),
        "NumNumericChars": sum(c.isdigit() for c in url),
        "NoHttps": int(not url.lower().startswith("https")),
        "AtSymbol": int("@" in url),
        "TildeSymbol": int("~" in url),
        "NumUnderscore": url.count("_"),
        "NumPercent": url.count("%"),
        "NumQueryComponents": len(parse_qs(parsed.query)),
        "NumAmpersand": url.count("&"),
        "NumHash": url.count("#"),
        "DoubleSlashInPath": int("//" in path),
        "SuspiciousSubdomain": int(domain.count(".") > 2),
        "ContainsBrand": int(any(b in url.lower() for b in ["paypal", "bank", "amazon"])),
    }

    selected_features = feature_names
    if selected_features is not None:
        return {str(col): feats.get(str(col), 0) for col in selected_features}
    return feats


def extract_pipeline_features(url):
    parsed = urlparse(url)
    host = parsed.netloc.split(":")[0].lower()
    parts = host.split(".")
    is_ip = len(parts) == 4 and all(
        part.isdigit() and 0 <= int(part) <= 255 for part in parts
    )
    shorteners = {"bit.ly", "tinyurl.com", "t.co", "goo.gl", "ow.ly", "is.gd"}

    counts = {}
    for char in url:
        counts[char] = counts.get(char, 0) + 1
    entropy = -sum(
        (count / len(url)) * math.log2(count / len(url))
        for count in counts.values()
    ) if url else 0.0

    return {
        "url_length": len(url),
        "domain_length": len(host),
        "path_length": len(parsed.path),
        "count_dots": url.count("."),
        "count_hyphen": url.count("-"),
        "count_at": url.count("@"),
        "num_query_params": len(parse_qs(parsed.query)),
        "has_https": int(parsed.scheme.lower() == "https"),
        "has_ip": int(is_ip),
        "subdomain_depth": max(host.count(".") - 1, 0),
        "has_shortener": int(host in shorteners),
        "count_percent_encoded": url.count("%"),
        "entropy": entropy,
    }


def predict_from_model_metadata(url):
    if not MODEL_META:
        return None

    values = extract_pipeline_features(url)

    names = MODEL_META.get("feature_names", [])
    means = MODEL_META.get("scaler_mean", [])
    scales = MODEL_META.get("scaler_scale", [])
    coefficients = MODEL_META.get("coef", [])
    if not (len(names) == len(means) == len(scales) == len(coefficients)):
        return None

    scaled = [
        (values.get(name, 0.0) - mean) / (scale or 1.0)
        for name, mean, scale in zip(names, means, scales)
    ]
    linear_score = float(MODEL_META.get("intercept", 0.0)) + sum(
        coefficient * value
        for coefficient, value in zip(coefficients, scaled)
    )
    bounded_score = max(min(linear_score, 700), -700)
    return 1.0 / (1.0 + math.exp(-bounded_score))


def predict_ml_probability(url):
    if ml_pipeline is not None:
        try:
            feature_names = MODEL_META.get("feature_names", []) if MODEL_META else []
            features = extract_pipeline_features(url)
            pipeline_df = pd.DataFrame(
                [[features.get(name, 0.0) for name in feature_names]],
                columns=feature_names,
            )
            return float(ml_pipeline.predict_proba(pipeline_df)[0][1]), "pipeline"
        except Exception as pipeline_error:
            print(f"Pipeline ML inference failed: {pipeline_error}")

    fallback_score = predict_from_model_metadata(url)
    return fallback_score, "metadata_fallback"

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

    try:
        ml_proba, ml_engine = predict_ml_probability(url)
    except Exception as e:
        print("❌ ML inference error:", e)
        traceback.print_exc()
        ml_proba = None
        ml_engine = "unavailable"

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
        status = "Block"
    elif risk_level == "Medium":
        status = "Suspicious"
    else:
        status = "Proceed"

    return {
        "url": url,
        "score": float(ml_proba) if ml_proba is not None else None,
        "ml_engine": ml_engine,
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
    metadata: dict[str, Any] = Field(default_factory=dict)


def save_phishing_log(log: PhishingLog) -> None:
    if not SUPABASE_LOGS_URL or not SUPABASE_KEY:
        raise HTTPException(
            status_code=503,
            detail="Supabase logging is not configured on the server.",
        )

    payload = log.model_dump(exclude_none=True)
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
        "pipeline_loaded": ml_pipeline is not None,
        "metadata_fallback_loaded": MODEL_META is not None,
        "phishtank_loaded": len(phishtank_urls) > 0,
        "supabase_configured": bool(SUPABASE_LOGS_URL and SUPABASE_KEY),
    }

@app.get("/check_url")
def check_url(url: str = Query(...)):
    if ml_pipeline is None and MODEL_META is None:
        return JSONResponse(
            status_code=500,
            content={
                "error": "ML pipeline and metadata fallback are unavailable.",
                "pipeline_loaded": ml_pipeline is not None,
                "metadata_fallback_loaded": MODEL_META is not None,
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
    ok = ml_pipeline is not None
    return {"healthy": ok}
