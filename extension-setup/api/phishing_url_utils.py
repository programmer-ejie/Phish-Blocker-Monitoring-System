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
    })
