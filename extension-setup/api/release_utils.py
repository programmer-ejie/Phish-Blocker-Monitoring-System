"""Strict release proof and standalone GitHub packaging. No automatic publishing."""
import csv
import hashlib
import importlib.metadata
import json
import math
from pathlib import Path
import shutil
import tempfile


STRICT_QUALITY_POLICY = {'min_precision': .90, 'min_recall': .80,
                         'max_false_positive_rate': .03, 'min_test_examples_per_class': 100}
STRICT_BLOCK_POLICY = {'min_precision': .98, 'max_false_positive_rate': .005, 'min_predictions': 50}
HOMEPAGES = ['https://www.instagram.com/', 'https://instagram.com/',
             'https://www.facebook.com/', 'https://facebook.com/',
             'https://www.google.com/', 'https://google.com/']
MODEL_PACKAGES = ['numpy', 'scipy', 'pandas', 'scikit-learn', 'joblib']


def file_hash(path):
    digest = hashlib.sha256()
    with Path(path).open('rb') as handle:
        for chunk in iter(lambda: handle.read(1024*1024), b''):
            digest.update(chunk)
    return digest.hexdigest()


def strict_report_failures(report):
    """Recompute fixed strict limits, independently of editable notebook settings."""
    failures = list(report.get('gate_failures', []))
    if report.get('source_provenance_verified') is False:
        failures.append('Upstream label provenance is unresolved; authenticate the source-reference experiment before release.')
    for split in ['validation', 'test']:
        metrics = report.get(split+'_metrics') or {}
        for key, minimum in [('precision', .90), ('recall', .80)]:
            value = metrics.get(key, float('nan'))
            if not isinstance(value, (int, float)) or not math.isfinite(value) or value < minimum:
                failures.append(f'Strict {split} {key} must be at least {minimum:.0%}.')
        value = metrics.get('false_positive_rate', float('nan'))
        if not isinstance(value, (int, float)) or not math.isfinite(value) or value > .03:
            failures.append(f'Strict {split} benign warning rate must be at most 3%.')
        if split == 'test' and min(metrics.get('tp', 0)+metrics.get('fn', 0),
                                   metrics.get('tn', 0)+metrics.get('fp', 0)) < 100:
            failures.append('Strict test support must include at least 100 examples per class.')
    if not report.get('regressions_passed', False):
        failures.append('All six reviewed homepage checks must pass.')
    blocks = [report.get('block_validation_metrics'), report.get('block_test_metrics')]
    if any(value is not None for value in blocks):
        for split, metrics in zip(['validation', 'test'], blocks):
            if metrics is None:
                failures.append('Missing '+split+' automatic-block metrics.')
                continue
            p, fpr = metrics.get('precision', float('nan')), metrics.get('false_positive_rate', float('nan'))
            if not math.isfinite(p) or p < .98 or not math.isfinite(fpr) or fpr > .005:
                failures.append(f'Strict {split} automatic blocking requires precision >=98% and benign block rate <=0.5%.')
            if metrics.get('tp', 0)+metrics.get('fp', 0) < 50:
                failures.append(f'Strict {split} automatic blocking needs at least 50 predictions.')
    return list(dict.fromkeys(failures))


def runtime_dependency_failures(source, versions):
    pins = {}
    for line in (Path(source)/'requirements.txt').read_text(encoding='utf-8').splitlines():
        if '==' in line and not line.lstrip().startswith('#'):
            name, value = line.strip().split('==', 1)
            pins[name.lower()] = value.strip()
    return [f'Runtime pin for {name} must match trained version {versions.get(name)}.'
            for name in MODEL_PACKAGES if not versions.get(name) or pins.get(name) != versions[name]]


def verify_release_package(directory):
    root = Path(directory).resolve()
    manifest = json.loads((root/'release_manifest.json').read_text(encoding='utf-8'))
    failures = strict_report_failures(manifest['report'])
    if manifest.get('release_profile') != 'strict' or failures:
        raise ValueError('Strict release checks failed: '+'; '.join(failures))
    mandatory = {'app.py', 'phishing_url_utils.py', 'release_utils.py',
                 'requirements.txt', 'dataset/phishing_url_bundle.joblib'}
    if not mandatory <= set(manifest['files']):
        raise ValueError('Incomplete runtime package.')
    for name, expected in manifest['files'].items():
        path = (root/name).resolve()
        if root not in path.parents or not path.is_file() or file_hash(path) != expected:
            raise ValueError('Release file missing or changed: '+name)
    if runtime_dependency_failures(root, manifest['report']['versions']):
        raise ValueError('Release runtime dependencies do not match training.')
    for name in MODEL_PACKAGES:
        if importlib.metadata.version(name) != manifest['report']['versions'][name]:
            raise ValueError('Verification Python environment has a different '+name+' version.')
    import joblib
    from phishing_url_utils import predict_urls
    bundle = joblib.load(root/'dataset/phishing_url_bundle.joblib')
    if not bundle.get('deployment_approved') or bundle.get('release_profile') != 'strict':
        raise ValueError('Model is not approved for strict deployment.')
    if strict_report_failures(bundle.get('release_metrics', {})):
        raise ValueError('Model carries a failing strict release proof.')
    if not predict_urls(bundle, HOMEPAGES)['decision'].eq('Proceed').all():
        raise ValueError('Exported homepage regression checks failed.')
    return manifest


def build_release_package(source, run_dir, candidate, report):
    """Build only a passing strict release; exclude credentials, corpora, and old models."""
    source, run_dir, candidate = Path(source), Path(run_dir), Path(candidate)
    failures = strict_report_failures(report)+runtime_dependency_failures(source, report['versions'])
    if failures:
        raise ValueError('Release blocked: '+'; '.join(failures))
    if report.get('helper_sha256') != file_hash(source/'phishing_url_utils.py'):
        raise ValueError('Inference helper differs from the evaluated training run.')
    destination = run_dir/'github_api_release'
    if destination.exists():
        raise FileExistsError('Release folder already exists; do not overwrite a verified release.')
    temporary = Path(tempfile.mkdtemp(prefix='.release-', dir=run_dir)).resolve()
    try:
        for name in ['app.py', 'phishing_url_utils.py', 'release_utils.py', 'verify_release.py',
                     'requirements.txt', 'Procfile', 'runtime.txt', '.python-version', '.envexample']:
            shutil.copy2(source/name, temporary/name)
        (temporary/'dataset').mkdir()
        shutil.copy2(candidate, temporary/'dataset/phishing_url_bundle.joblib')
        blueprint = (source/'render.yaml').read_text(encoding='utf-8')
        (temporary/'render.yaml').write_text('\n'.join(line for line in blueprint.splitlines()
            if not line.strip().startswith('rootDir:'))+'\n', encoding='utf-8')
        (temporary/'.gitignore').write_text('.env\n.venv/\n__pycache__/\n*.pyc\n*.log\ndataset/phishtank.csv\n', encoding='utf-8')
        (temporary/'README.md').write_text(
            '# Strict PhishBlock API release\n\nCopy these contents into the root of the phishblock_api GitHub repository.\n'
            'Render Root Directory: blank. Build: pip install -r requirements.txt.\n'
            'Start: uvicorn app:app --host 0.0.0.0 --port $PORT --workers 1. Health: /health.\n'
            'Configure GOOGLE_API_KEY and PHISHTANK_FILE_ID in Render if needed. No .env is bundled.\n'
            'Install requirements, then run python verify_release.py before pushing.\n'
            'Read MODEL_CARD.md; passing historical development checks is not a guarantee on future URLs.\n', encoding='utf-8')
        metrics = report['test_metrics']
        (temporary/'MODEL_CARD.md').write_text(
            '# Model release report\n\nProfile: strict. Selected model: '+report['selected_model']+'\n\n'
            f'Test warning precision: {metrics["precision"]:.2%}\n\n'
            f'Test phishing recall: {metrics["recall"]:.2%}\n\n'
            f'Test benign warning rate: {metrics["false_positive_rate"]:.2%}\n\n'
            'The model and inference helper match the evaluated export. All homepage checks passed.\n'
            'The datasets are historical and this holdout has been inspected during development.\n'
            'Use recent independent data for final assurance. Scores are not calibrated probabilities.\n'
            'Warnings and automatic blocks use different thresholds; threat-list matches may also block.\n', encoding='utf-8')
        files = {p.relative_to(temporary).as_posix(): file_hash(p)
                 for p in temporary.rglob('*') if p.is_file()}
        (temporary/'release_manifest.json').write_text(json.dumps(
            {'release_profile':'strict', 'files':files, 'report':report}, indent=2), encoding='utf-8')
        verify_release_package(temporary)
        temporary.rename(destination)
    finally:
        # Only clean this function's own temporary child of the explicitly named run directory.
        if temporary.exists() and temporary.parent == run_dir.resolve():
            shutil.rmtree(temporary)
    return destination
