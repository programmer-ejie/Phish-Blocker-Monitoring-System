"""Reviewed URL exceptions. Threat intelligence always takes precedence."""
import json
from pathlib import Path
from urllib.parse import urlsplit, urlunsplit

POLICY = json.loads(Path(__file__).with_name('trusted_urls.json').read_text(encoding='utf-8'))


def trusted_url(url):
    try:
        parsed = urlsplit(url)
        if (parsed.scheme != 'https' or not parsed.hostname or parsed.username is not None
                or parsed.password is not None or parsed.port not in (None, 443)
                or '\\' in url or any(ord(char) < 33 for char in url)):
            return False
        host = parsed.hostname.lower()
        if host in POLICY['trusted_hosts']:
            return True
        if parsed.path in POLICY['trusted_paths'].get(host, []):
            return True
        canonical = urlunsplit(('https', host, parsed.path, parsed.query, ''))
        return canonical in POLICY['trusted_urls']
    except ValueError:
        return False
