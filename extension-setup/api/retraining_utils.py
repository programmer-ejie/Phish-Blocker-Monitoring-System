"""Training-only split, evaluation, and local promotion controls."""
from datetime import datetime, timezone
import hashlib
import json
import os
from pathlib import Path
import shutil
import tempfile

import numpy as np
from sklearn.metrics import (accuracy_score, average_precision_score,
    balanced_accuracy_score, confusion_matrix, f1_score, fbeta_score,
    precision_recall_curve, precision_score, recall_score, roc_auc_score)


def sha256_file(path):
    digest = hashlib.sha256()
    with Path(path).open('rb') as handle:
        for chunk in iter(lambda: handle.read(1024 * 1024), b''):
            digest.update(chunk)
    return digest.hexdigest()


def freeze_splits(frame, path, reviewed_groups, seed=42):
    """Persist domain assignments BEFORE training subsampling; fail on data drift.

    Changing row order or training sample size cannot change the holdout. A new
    dataset needs a separately named experiment; this function never silently
    rebuilds an existing experiment's partitions.
    """
    path = Path(path)
    required = {'url', 'label', 'group'}
    if not required <= set(frame):
        raise ValueError('Split input needs url, label, and group columns.')
    if frame.empty or not frame['url'].is_unique or frame[list(required)].isna().any().any():
        raise ValueError('Split input must contain unique nonempty URL records.')
    if not set(frame['label']).issubset({0, 1}):
        raise ValueError('Labels must be 0=benign or 1=phishing.')
    group_hashes = {g: hashlib.sha256(g.encode()).hexdigest() for g in frame['group'].unique()}
    row_keys = sorted(hashlib.sha256(f'{r.url}\0{int(r.label)}\0{r.group}'.encode()).hexdigest()
                      for r in frame.itertuples())
    fingerprint = hashlib.sha256('\n'.join(row_keys).encode()).hexdigest()
    reviewed_hashes = sorted(hashlib.sha256(g.encode()).hexdigest() for g in reviewed_groups)
    if path.exists():
        manifest = json.loads(path.read_text(encoding='utf-8'))
        if manifest.get('version') != 3 or manifest.get('seed') != seed:
            raise ValueError('Frozen split version/seed mismatch. Keep the original settings.')
        if manifest.get('dataset_fingerprint') != fingerprint or manifest.get('reviewed_group_hashes') != reviewed_hashes:
            raise ValueError('Dataset, labels, corrections, or grouping changed. The frozen holdout was NOT rebuilt. '
                             'Restore the original inputs or use a NEW experiment filename; results across experiments are not directly comparable.')
    else:
        assignments = {}
        for group, digest in group_hashes.items():
            bucket = int(hashlib.sha256(f'{seed}:{group}'.encode()).hexdigest()[:16], 16) / 2**64
            assignments[digest] = 'train' if group in reviewed_groups or bucket < 0.6 else ('validation' if bucket < 0.8 else 'test')
        manifest = {'version': 3, 'seed': seed, 'dataset_fingerprint': fingerprint,
                    'created_utc': datetime.now(timezone.utc).isoformat(),
                    'strategy': 'stable SHA256 of registrable domain; 60/20/20; reviewed domains train-only',
                    'reviewed_group_hashes': reviewed_hashes, 'assignments': assignments,
                    'rows': len(frame)}
        path.parent.mkdir(parents=True, exist_ok=True)
        # Exclusive create protects an already frozen experiment from accidental replacement.
        with path.open('x', encoding='utf-8') as handle:
            json.dump(manifest, handle, indent=2)
    assignments = manifest['assignments']
    if set(assignments) != set(group_hashes.values()) or not set(assignments.values()) <= {'train', 'validation', 'test'}:
        raise ValueError('Frozen assignment file is incomplete or corrupt.')
    if any(assignments[h] != 'train' for h in reviewed_hashes):
        raise ValueError('Reviewed correction groups must remain train-only.')
    result = frame.copy()
    result['split'] = result['group'].map(lambda g: assignments[group_hashes[g]])
    return result, manifest


def metric_summary(y, scores, threshold, beta=2):
    pred = np.asarray(scores) >= threshold
    tn, fp, fn, tp = confusion_matrix(y, pred, labels=[0, 1]).ravel()
    return {'accuracy': float(accuracy_score(y, pred)),
            'balanced_accuracy': float(balanced_accuracy_score(y, pred)),
            'precision': float(precision_score(y, pred, zero_division=0)),
            'recall': float(recall_score(y, pred, zero_division=0)),
            'f1': float(f1_score(y, pred, zero_division=0)),
            'fbeta': float(fbeta_score(y, pred, beta=beta, zero_division=0)),
            'roc_auc': float(roc_auc_score(y, scores)),
            'average_precision': float(average_precision_score(y, scores)),
            'false_positive_rate': float(fp / (fp + tn)),
            'tn': int(tn), 'fp': int(fp), 'fn': int(fn), 'tp': int(tp)}


def select_threshold(y, scores, policy, beta=2):
    """Select by validation only; explicitly mark an infeasible policy."""
    y, scores = np.asarray(y), np.asarray(scores)
    if set(y) != {0, 1} or not np.isfinite(scores).all():
        raise ValueError('Threshold selection requires both classes and finite scores.')
    p, r, thresholds = precision_recall_curve(y, scores)
    p, r = p[:-1], r[:-1]
    predicted_positive = len(scores) - np.searchsorted(np.sort(scores), thresholds, side='left')
    true_positive = np.rint(r * (y == 1).sum())
    fpr = (predicted_positive - true_positive) / (y == 0).sum()
    fbeta = (1 + beta**2) * p * r / np.maximum(beta**2 * p + r, 1e-12)
    feasible = ((p >= policy['min_precision']) & (r >= policy['min_recall'])
                & (fpr <= policy['max_false_positive_rate']))
    eligible = np.flatnonzero(feasible)
    met = bool(len(eligible))
    if not met:
        # Do not pick a high-recall fallback that ignores the false-alert limit.
        eligible = np.flatnonzero((p >= policy['min_precision']) & (fpr <= policy['max_false_positive_rate']))
        if not len(eligible):
            eligible = np.flatnonzero(fpr <= policy['max_false_positive_rate'])
        if not len(eligible):
            return 1.0, False
    best = eligible[np.argmax(fbeta[eligible])]
    return float(thresholds[best]), met


def select_block_threshold(y, scores, warning_threshold, policy):
    """A second, stricter threshold; no feasible validation point means warnings only."""
    y, scores = np.asarray(y), np.asarray(scores)
    p, r, thresholds = precision_recall_curve(y, scores)
    predicted = len(scores) - np.searchsorted(np.sort(scores), thresholds, side='left')
    tp = np.rint(r[:-1] * (y == 1).sum())
    fpr = (predicted - tp) / (y == 0).sum()
    feasible = ((thresholds >= warning_threshold) & (p[:-1] >= policy['min_precision'])
                & (fpr <= policy['max_false_positive_rate']) & (predicted >= policy['min_predictions']))
    eligible = np.flatnonzero(feasible)
    return float(thresholds[eligible[np.argmax(r[:-1][eligible])]]) if len(eligible) else None


def blocking_failures(validation_metrics, test_metrics, policy):
    if validation_metrics is None and test_metrics is None:
        return []  # ML-only blocking was disabled BEFORE test evaluation.
    failures = []
    for split, metrics in [('validation', validation_metrics), ('test', test_metrics)]:
        if metrics['precision'] < policy['min_precision']:
            failures.append(f'{split} ML-block precision below {policy["min_precision"]:.3f}')
        if metrics['false_positive_rate'] > policy['max_false_positive_rate']:
            failures.append(f'{split} ML-block false-positive rate exceeds {policy["max_false_positive_rate"]:.3f}')
        if metrics['tp'] + metrics['fp'] < policy['min_predictions']:
            failures.append(f'{split} has insufficient automatic-block predictions for evaluation')
    return failures


def apply_training_reviews(train, reviews_path, evaluation_groups, group_function, weight=20.0):
    """Apply only evidence-backed training reviews; never move held-out domains."""
    import pandas as pd
    from phishing_url_utils import normalize_url, model_text_urls
    reviews_path = Path(reviews_path)
    columns = ['url', 'label', 'evidence', 'reviewed_by', 'reviewed_at']
    reviews = pd.read_csv(reviews_path, encoding='utf-8-sig', keep_default_na=False)
    if not set(columns) <= set(reviews):
        raise ValueError('Reviewed training CSV requires: ' + ', '.join(columns))
    if reviews.empty:
        return train.copy(), pd.DataFrame(columns=columns + ['applied', 'reason'])
    if any(reviews[column].astype(str).str.strip().eq('').any() for column in columns):
        raise ValueError('Every reviewed row needs an explicit label, evidence, reviewer, and review date.')
    reviews['label'] = pd.to_numeric(reviews['label'], errors='raise')
    if not set(reviews['label']).issubset({0, 1}):
        raise ValueError('Reviewed labels must be 0 or 1; model predictions are not review evidence.')
    pd.to_datetime(reviews['reviewed_at'], errors='raise', utc=True)
    reviews['url'] = reviews['url'].map(normalize_url)
    if reviews['url'].isna().any():
        raise ValueError('Invalid URL in reviewed training data.')
    reviews['key'] = model_text_urls(reviews['url'])
    if reviews.groupby('key')['label'].nunique().max() > 1:
        raise ValueError('Contradictory reviewed labels for equivalent URLs.')
    reviews['group'] = reviews['url'].map(group_function)
    reviews['applied'] = ~reviews['group'].isin(evaluation_groups)
    reviews['reason'] = np.where(reviews['applied'], 'evidence-backed training feedback', 'held-out domain: excluded from training')
    approved = reviews[reviews['applied']].drop_duplicates('url').copy()
    result = train[~pd.Series(model_text_urls(train['url']), index=train.index).isin(approved['key'])].copy()
    approved['source'] = 'reviewed_training_urls.csv'
    approved['sample_weight'] = weight
    approved['split'] = 'train'
    result = pd.concat([result, approved.reindex(columns=train.columns)], ignore_index=True)
    return result, reviews.drop(columns=['key'])


def validation_review_queue(frame, scores, threshold, limit=500, max_per_group=3):
    """Queue disagreements for HUMAN review, never silently assign replacement labels."""
    result = frame[['url', 'label', 'source', 'group']].copy().rename(columns={'label': 'existing_label'})
    result['score'] = np.asarray(scores)
    result['predicted_label'] = (result['score'] >= threshold).astype(int)
    result = result[result['existing_label'] != result['predicted_label']].copy()
    if limit < 1 or max_per_group < 1:
        raise ValueError('Review queue limits must be positive.')
    result['error_type'] = np.where(result['existing_label'].eq(0),
        'false_positive_under_existing_label', 'false_negative_under_existing_label')
    # Raw score distance otherwise lets missed positives crowd out false alarms.
    scale = np.where(result['existing_label'].eq(0), max(1-threshold, 1e-12), max(threshold, 1e-12))
    result['priority'] = np.abs(result['score'] - threshold) / scale
    result = result.sort_values('priority', ascending=False)
    result = result.groupby(['error_type', 'group'], sort=False).head(max_per_group)
    groups = [part for _,part in result.groupby('error_type',sort=True)]
    quota = max(1, limit // max(1,len(groups)))
    chosen = [part.head(quota) for part in groups]
    if chosen:
        import pandas as pd
        selected = pd.concat(chosen)
        remainder = result[~result.index.isin(selected.index)].head(max(0,limit-len(selected)))
        result = pd.concat([selected,remainder]).head(limit)
    else:
        result = result.head(limit)
    result['partition'] = 'validation (development only)'
    for column in ['label', 'evidence', 'reviewed_by', 'reviewed_at']:
        result[column] = ''
    return result


def promotion_failures(validation_metrics, test_metrics, regressions_passed, policy):
    failures = []
    for split, metrics in [('validation', validation_metrics), ('test', test_metrics)]:
        for key, minimum in [('precision', policy['min_precision']), ('recall', policy['min_recall'])]:
            if not np.isfinite(metrics[key]) or metrics[key] < minimum:
                failures.append(f'{split} {key} {metrics[key]:.3f} is below {minimum:.3f}')
        fpr = metrics['false_positive_rate']
        if not np.isfinite(fpr) or fpr > policy['max_false_positive_rate']:
            failures.append(f'{split} false-positive rate {fpr:.3f} exceeds {policy["max_false_positive_rate"]:.3f}')
    minimum = policy.get('min_test_examples_per_class', 100)
    if min(test_metrics['tp'] + test_metrics['fn'], test_metrics['tn'] + test_metrics['fp']) < minimum:
        failures.append('Test set has too few examples per class for promotion.')
    if not regressions_passed:
        failures.append('One or more reviewed homepage regression checks failed.')
    return failures


def promote_candidate(candidate, runtime, run_dir, failures, enabled=False):
    """Back up and atomically replace the local artifact only when every gate passes."""
    if not enabled or failures:
        return False
    candidate, runtime, run_dir = Path(candidate), Path(runtime), Path(run_dir)
    runtime.parent.mkdir(parents=True, exist_ok=True)
    if runtime.exists():
        shutil.copy2(runtime, run_dir / 'previous_deployed_bundle.joblib')
    fd, temporary = tempfile.mkstemp(prefix='.candidate-', dir=runtime.parent)
    os.close(fd)
    try:
        shutil.copyfile(candidate, temporary)
        os.replace(temporary, runtime)
    finally:
        if os.path.exists(temporary):
            os.remove(temporary)
    return True
