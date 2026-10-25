"""Shared offline URL normalization and inference for the retraining notebook."""

import ipaddress
import re
from urllib.parse import urlsplit, urlunsplit


def normalize_url(value):
    """Return a scheme-independent URL string, or None for an invalid input.

    Hostnames are case insensitive; path/query case and encoding are preserved.
    No URLs are requested and no redirects are followed.
    """
    if not isinstance(value, str):
        return None
    value = value.strip()
    if not value or len(value) > 16384 or re.search(r"[\x00-\x20\x7f]", value):
        return None
    if value.startswith("//"):
        value = "http:" + value
    elif not re.match(r"^[a-zA-Z][a-zA-Z0-9+.-]*://", value):
        value = "http://" + value
    try:
        parts = urlsplit(value)
        if parts.scheme.lower() not in {"http", "https"} or not parts.hostname:
            return None
        host = parts.hostname.rstrip(".").encode("idna").decode("ascii").lower()
        try:
            ipaddress.ip_address(host)
        except ValueError:
            if "." not in host or not re.fullmatch(r"[a-z0-9._-]+", host):
                return None
        host_display = f"[{host}]" if ":" in host else host
        port = parts.port  # Validate the port before accepting the URL.
        if port is not None:
            host_display += f":{port}"
        userinfo = parts.netloc.rsplit("@", 1)[0] + "@" if "@" in parts.netloc else ""
        path = parts.path or "/"
        return urlunsplit(("", userinfo + host_display, path, parts.query, ""))[2:]
    except (ValueError, UnicodeError):
        return None


def predict_urls(bundle, urls):
    """Apply the exported model and its validation-selected decision threshold."""
    import pandas as pd

    if isinstance(urls, str):
        urls = [urls]
    urls = list(urls)
    cleaned = [normalize_url(url) for url in urls]
    invalid = [i for i, value in enumerate(cleaned) if value is None]
    if invalid:
        raise ValueError(f"Invalid HTTP(S) URL inputs at positions {invalid[:10]}")
    model = bundle["pipeline"]
    phishing_index = list(model.classes_).index(1)
    scores = model.predict_proba(cleaned)[:, phishing_index]
    return pd.DataFrame({
        "url": urls,
        "phishing_score": scores,
        "prediction": ["phishing" if s >= bundle["threshold"] else "benign" for s in scores],
        "decision": [model_decision(bundle, float(s)) for s in scores],
    })


def model_text_urls(urls):
    """Version 2 training text: ignore a leading www label for both classes.

    This is a model representation, not a blocklist/domain trust rule. Preserve
    the original hostname for blocklist matching and all path/query information.
    """
    result = []
    for value in urls:
        cleaned = normalize_url(value)
        if cleaned is None:
            raise ValueError('The text model requires valid HTTP(S) URLs.')
        if cleaned.startswith('www.'):
            cleaned = cleaned[4:]
        result.append(cleaned)
    return result


def normalized_model_urls(urls):
    """Raw-URL baseline representation, refitted on the same frozen partitions."""
    cleaned = [normalize_url(value) for value in urls]
    if any(value is None for value in cleaned):
        raise ValueError('The text model requires valid HTTP(S) URLs.')
    return cleaned


def url_lexical_features(urls):
    """Offline URL structure features; no allowlists, DNS, or page requests."""
    import math
    from collections import Counter
    import numpy as np
    from scipy.sparse import csr_matrix

    rows = []
    for url in urls:
        parsed = urlsplit('http://' + url)
        host = parsed.hostname or ''
        try:
            ipaddress.ip_address(host)
            is_ip = 1
        except ValueError:
            is_ip = 0
        counts = Counter(url)
        entropy = -sum((n / len(url)) * math.log2(n / len(url)) for n in counts.values())
        rows.append([math.log1p(len(url)), math.log1p(len(host)),
            math.log1p(len(parsed.path)), math.log1p(len(parsed.query)),
            host.count('.'), host.count('-'), url.count('@'), url.count('%'),
            parsed.path.count('/'), parsed.query.count('&'),
            sum(c.isdigit() for c in url) / len(url),
            sum(c.isdigit() for c in host) / max(1, len(host)),
            int('xn--' in host), is_ip, entropy,
            int(parsed.path in {'', '/'}),
            int(any(word in url.lower() for word in ['login', 'verify', 'account', 'secure', 'password']))])
    return csr_matrix(np.asarray(rows, dtype=np.float32).reshape(-1, 17))


def host_text_urls(urls):
    """Separate hostname vocabulary from path/query vocabulary."""
    return [(urlsplit('http://' + url).hostname or '') for url in urls]


def path_query_text_urls(urls):
    parts = [urlsplit('http://' + url) for url in urls]
    return ['path=' + (p.path or '/') + ' query=' + p.query for p in parts]


def dense_url_features(urls):
    return url_lexical_features(urls).toarray()


class UrlProbabilityEnsemble:
    """Soft vote of already fitted models; weights are selected on validation."""
    def __init__(self, models, weights):
        import numpy as np
        weights = np.asarray(weights, dtype=float)
        if not models or len(models) != len(weights) or not np.isfinite(weights).all() or (weights <= 0).any():
            raise ValueError('Ensemble requires positive finite weights for each fitted model.')
        if any(list(model.classes_) != [0, 1] for model in models):
            raise ValueError('Ensemble models must use 0=benign, 1=phishing.')
        self.models = models
        self.weights = weights / weights.sum()
        self.classes_ = np.asarray([0, 1])

    def predict_proba(self, urls):
        import numpy as np
        urls = list(urls)
        return np.average([model.predict_proba(urls) for model in self.models], axis=0, weights=self.weights)

    def predict(self, urls):
        return (self.predict_proba(urls)[:, 1] >= 0.5).astype(int)


def decision_thresholds(bundle):
    """Legacy/uncertified models warn only. Never infer trust from score size."""
    import math
    warning = float(bundle['threshold'])
    if not math.isfinite(warning) or not 0 <= warning <= 1:
        raise ValueError('Invalid warning threshold.')
    policy = bundle.get('decision_policy', {})
    block = policy.get('block_threshold')
    if block is not None:
        block = float(block)
        if not policy.get('validated') or not math.isfinite(block) or not warning <= block <= 1:
            raise ValueError('ML blocking requires validated, ordered warning/block thresholds.')
    return warning, block


def model_decision(bundle, score):
    warning, block = decision_thresholds(bundle)
    if block is not None and score >= block:
        return 'Block'
    return 'Suspicious' if score >= warning else 'Proceed'
