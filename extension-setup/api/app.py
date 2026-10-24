"""Render-ready phishing detection API using the retrained URL-text bundle."""
from contextlib import asynccontextmanager
from pathlib import Path
from urllib.parse import urlsplit
import csv
import importlib.metadata
import ipaddress
import logging
import math
import os
import re

import joblib
import requests
from dotenv import load_dotenv
from fastapi import FastAPI, HTTPException, Query
from fastapi.responses import JSONResponse
from phishing_url_utils import normalize_url, predict_urls

BASE_DIR = Path(__file__).resolve().parent
load_dotenv(BASE_DIR / '.env')
logger = logging.getLogger('uvicorn.error')

def configured_path(name, default):
    path = Path(os.getenv(name) or default)
    return path if path.is_absolute() else BASE_DIR / path

MODEL_PATH = configured_path('MODEL_PATH', 'dataset/phishing_url_bundle.joblib')
PHISHTANK_PATH = configured_path('PHISHTANK_PATH', 'dataset/phishtank.csv')
GOOGLE_API_KEY = os.getenv('GOOGLE_API_KEY')
model_bundle = None
phishtank_urls = set()


def load_model_bundle(path):
    if not path.is_file():
        raise RuntimeError(f'Model bundle missing: {path}. Include it in the deployment.')
    # Only load your trusted training artifact: joblib files can execute Python code.
    bundle = joblib.load(path)
    if not isinstance(bundle, dict) or not {'pipeline', 'threshold'} <= bundle.keys():
        raise RuntimeError('Expected a phishing_url_bundle.joblib dictionary.')
    threshold = float(bundle['threshold'])
    if not math.isfinite(threshold) or not 0 <= threshold <= 1:
        raise RuntimeError('The bundle has an invalid decision threshold.')
    if list(bundle['pipeline'].classes_) != [0, 1]:
        raise RuntimeError('Expected model classes 0=benign and 1=phishing.')
    trained_version = bundle.get('versions', {}).get('scikit-learn')
    if trained_version and trained_version != importlib.metadata.version('scikit-learn'):
        raise RuntimeError('scikit-learn version must match the version used for training: ' + trained_version)
    # Exercise the complete text preprocessing + inference path before becoming ready.
    result = predict_urls(bundle, ['https://example.com/'])
    score = float(result.iloc[0]['phishing_score'])
    if not math.isfinite(score) or not 0 <= score <= 1:
        raise RuntimeError('Model returned an invalid score during startup.')
    logger.info('Loaded %s; decision threshold %.6f', path.name, threshold)
    return bundle


def load_phishtank_blocklist(path):
    if not path.is_file():
        logger.warning('Optional PhishTank blocklist is absent; model detection remains enabled.')
        return set()
    urls = set()
    with path.open(encoding='utf-8-sig', newline='') as handle:
        reader = csv.DictReader(handle)
        if 'url' not in (reader.fieldnames or []):
            raise ValueError('PhishTank CSV must contain a url column.')
        for row in reader:
            if 'verified' in row and row['verified'].strip().lower() != 'yes':
                continue
            value = normalize_url(row.get('url'))
            if value is not None:
                urls.add(value)
    logger.info('Loaded %d verified PhishTank URLs', len(urls))
    return urls


@asynccontextmanager
async def lifespan(app):
    global model_bundle, phishtank_urls
    model_bundle = load_model_bundle(MODEL_PATH)
    # Optional Drive download preserves the existing PHISHTANK_FILE_ID configuration.
    # The model itself is committed to the repo and is never downloaded at runtime.
    if not PHISHTANK_PATH.exists() and os.getenv('PHISHTANK_FILE_ID'):
        try:
            import gdown
            PHISHTANK_PATH.parent.mkdir(parents=True, exist_ok=True)
            gdown.download(id=os.environ['PHISHTANK_FILE_ID'], output=str(PHISHTANK_PATH), quiet=True)
        except Exception:
            logger.warning('Optional PhishTank download failed; continuing with the model.')
    try:
        phishtank_urls = load_phishtank_blocklist(PHISHTANK_PATH)
    except (OSError, ValueError, csv.Error):
        logger.warning('Optional PhishTank blocklist could not be loaded; continuing with the model.')
        phishtank_urls = set()
    yield
    model_bundle = None


app = FastAPI(title='Phishing Detection API', lifespan=lifespan)


def check_with_google_safebrowsing(url, api_key=None):
    if not api_key:
        return False
    body = {
        'client': {'clientId': 'PhishingDetector', 'clientVersion': '2.0'},
        'threatInfo': {
            'threatTypes': ['MALWARE', 'SOCIAL_ENGINEERING', 'POTENTIALLY_HARMFUL_APPLICATION'],
            'platformTypes': ['ANY_PLATFORM'], 'threatEntryTypes': ['URL'],
            'threatEntries': [{'url': url}],
        },
    }
    try:
        response = requests.post('https://safebrowsing.googleapis.com/v4/threatMatches:find',
            params={'key': api_key}, json=body, timeout=6)
        response.raise_for_status()
        return bool(response.json().get('matches'))
    except (requests.RequestException, ValueError):
        # Do not log the request URL: it contains the API key.
        logger.warning('Optional Google Safe Browsing check failed.')
        return False


def pattern_based_check(url):
    parsed = urlsplit(url)
    host = parsed.hostname or ''
    try:
        ipaddress.ip_address(host)
        is_ip = True
    except ValueError:
        is_ip = False
    return {'Has IP': is_ip, 'Has @': '@' in parsed.netloc,
            'Many Dashes': host.count('-') >= 3,
            'Suspicious Subdomain': host.count('.') >= 3}


def hybrid_check(url, api_key=None):
    if model_bundle is None:
        raise HTTPException(status_code=503, detail='The phishing model is not ready.')
    url = url.strip()
    # Keep path/query case, matching training normalization. Never fetch the target URL.
    if url.startswith('//'):
        url = 'http:' + url
    elif not re.match(r'^[a-zA-Z][a-zA-Z0-9+.-]*://', url):
        url = 'http://' + url
    normalized = normalize_url(url)
    if normalized is None:
        raise HTTPException(status_code=422, detail='Provide a valid HTTP(S) URL.')
    result = predict_urls(model_bundle, [url]).iloc[0]
    score = float(result['phishing_score'])
    threshold = float(model_bundle['threshold'])
    reasons = []
    high_risk = False
    if normalized in phishtank_urls:
        high_risk = True
        reasons.append('Phishing (PhishTank)')
    if check_with_google_safebrowsing(url, api_key):
        high_risk = True
        reasons.append('Threat detected (Google Safe Browsing)')
    if score >= threshold:
        high_risk = True
        reasons.append(f'ML phishing score {score:.3f} meets model threshold {threshold:.3f}')
    else:
        reasons.append(f'ML score {score:.3f} below model threshold {threshold:.3f}')
    flags = [name for name, flagged in pattern_based_check(url).items() if flagged]
    risk_level = 'High' if high_risk else ('Medium' if len(flags) >= 2 else 'Low')
    if flags:
        reasons.append('Pattern flags: ' + ', '.join(flags))
    return {'url': url, 'score': score, 'ml_engine': 'phishing_url_bundle',
            'model_threshold': threshold, 'risk_level': risk_level,
            'status': {'High': 'Block', 'Medium': 'Suspicious', 'Low': 'Proceed'}[risk_level],
            'reason': ' | '.join(reasons)}


@app.get('/')
def home():
    return {'message': 'Phishing Detection API is running',
            'model_name': 'phishing_url_bundle.joblib',
            'pipeline_loaded': model_bundle is not None,
            'metadata_fallback_loaded': False,
            'model_threshold': float(model_bundle['threshold']) if model_bundle else None,
            'phishtank_loaded': bool(phishtank_urls),
            'logging_backend': 'hostinger-laravel'}


@app.get('/check_url')
def check_url(url: str = Query(..., min_length=1, max_length=16384)):
    try:
        return hybrid_check(url, GOOGLE_API_KEY)
    except HTTPException:
        raise
    except Exception:
        logger.exception('URL classification failed')
        raise HTTPException(status_code=503, detail='URL classification is temporarily unavailable.')


@app.get('/health')
def health():
    ready = model_bundle is not None
    return JSONResponse(status_code=200 if ready else 503,
                        content={'healthy': ready, 'model_loaded': ready,
                                 'model_name': 'phishing_url_bundle.joblib'})
