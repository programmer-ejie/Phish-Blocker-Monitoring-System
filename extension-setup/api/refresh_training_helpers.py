"""Run with %run ./refresh_training_helpers.py to refresh a notebook kernel.

Load exact sibling source files without consulting an old module or .pyc cache.
Register their real module names so exported sklearn pipelines remain picklable.
"""
from pathlib import Path
import importlib.util
import sys


def load_helper_source(name, directory):
    path = Path(directory) / (name + '.py')
    spec = importlib.util.spec_from_file_location(name, path)
    module = importlib.util.module_from_spec(spec)
    previous = sys.modules.get(name)
    sys.modules[name] = module
    try:
        exec(compile(path.read_bytes(), str(path), 'exec'), module.__dict__)
    except BaseException:
        if previous is None:
            sys.modules.pop(name, None)
        else:
            sys.modules[name] = previous
        raise
    return module


API_DIRECTORY = Path(__file__).resolve().parent
sys.path.insert(0, str(API_DIRECTORY))
_url_helpers = load_helper_source('phishing_url_utils', API_DIRECTORY)
_training_helpers = load_helper_source('retraining_utils', API_DIRECTORY)
_release_helpers = load_helper_source('release_utils', API_DIRECTORY)
_audit_helpers = load_helper_source('audit_dataset_provenance', API_DIRECTORY)
_experiment_helpers = load_helper_source('dataset_experiments', API_DIRECTORY)
HELPER_EXPORTS = {}
for _module, _names in [
    (_url_helpers, ['normalize_url', 'predict_urls', 'model_text_urls',
                   'normalized_model_urls', 'url_lexical_features']),
    (_training_helpers, ['freeze_splits', 'metric_summary', 'select_threshold',
                        'promotion_failures', 'promote_candidate', 'sha256_file']),
]:
    for _name in _names:
        if not hasattr(_module, _name):
            raise RuntimeError(f'{_module.__file__} is outdated: missing {_name}. Restore the current helper file.')
        HELPER_EXPORTS[_name] = getattr(_module, _name)
for _name in ['host_text_urls', 'path_query_text_urls', 'dense_url_features', 'UrlProbabilityEnsemble',
              'decision_thresholds', 'model_decision']:
    HELPER_EXPORTS[_name] = getattr(_url_helpers, _name)
for _name in ['select_block_threshold', 'blocking_failures', 'apply_training_reviews', 'validation_review_queue']:
    HELPER_EXPORTS[_name] = getattr(_training_helpers, _name)
for _name in ['STRICT_QUALITY_POLICY', 'STRICT_BLOCK_POLICY', 'strict_report_failures',
              'runtime_dependency_failures', 'build_release_package']:
    HELPER_EXPORTS[_name] = getattr(_release_helpers, _name)
HELPER_EXPORTS['resolve_active_experiment'] = _experiment_helpers.resolve_active_experiment
globals().update(HELPER_EXPORTS)
print('Loaded training helpers directly from:', API_DIRECTORY)
print('normalized_model_urls available:', callable(normalized_model_urls))
