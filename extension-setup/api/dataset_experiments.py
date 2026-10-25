"""Create source-verified label-restoration experiments without rewriting old data."""
from datetime import datetime, timezone
import json
from pathlib import Path

import pandas as pd
from audit_dataset_provenance import parse_local_reference, sha256, SOURCE_RECORD
from phishing_url_utils import normalize_url


def _contained(path, directory):
    path, directory = Path(path).resolve(), Path(directory).resolve()
    if directory not in path.parents:
        raise ValueError('Experiment path must remain inside training_data/experiments.')
    return path


def prepare_verified_experiment(root, verification_path, name='phishstorm_restored_v1', activate=False):
    root = Path(root).resolve()
    verification_path = Path(verification_path).resolve()
    verification = json.loads(verification_path.read_text(encoding='utf-8'))
    if verification.get('status') != 'verified_primary_snapshot':
        raise ValueError('Primary PhishStorm snapshot is not authenticated. No labels or active experiment were changed.')
    if verification.get('source_record') != SOURCE_RECORD or not all(
            verification.get(key) for key in ['reference_file', 'sha256', 'verified_by', 'verified_at', 'retrieved_from']):
        raise ValueError('Complete publisher provenance and reviewer verification are required.')
    if verification.get('label_mapping') != {'0':'legitimate','1':'phishing'}:
        raise ValueError('Label mapping must match the original publisher definition.')
    reference_path = Path(verification['reference_file'])
    if not reference_path.is_absolute():
        reference_path = root/reference_path
    if sha256(reference_path) != verification['sha256']:
        raise ValueError('Reference file SHA-256 does not match the verified snapshot.')
    reference,rejected,repairs,contradictory = parse_local_reference(reference_path)
    if reference.empty or set(reference['reference_label']) != {0,1}:
        raise ValueError('Verified reference needs parseable records for both classes.')
    if not name or any(character not in 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_-' for character in name):
        raise ValueError('Use an experiment name containing only letters, numbers, hyphens, or underscores.')
    destination = _contained(root/'training_data/experiments'/name, root/'training_data/experiments')
    if destination.exists():
        raise FileExistsError('Experiment already exists; immutable snapshots must not be overwritten.')
    original_path = root/'training_data/data4.csv'
    original = pd.read_csv(original_path,encoding='utf-8-sig')
    labels = dict(zip(reference['url'],reference['reference_label']))
    source_labels = original['url'].map(normalize_url).map(labels)
    old = original['type'].map({'benign':0,'phishing':1})
    changed = old.notna() & source_labels.notna() & old.ne(source_labels)
    corrected = original.copy()
    corrected.loc[changed,'type'] = source_labels[changed].map({0:'benign',1:'phishing'})
    # Preserve other threat taxonomies; never label malware/defacement benign.
    destination.mkdir(parents=True)
    corrected_path = destination/'data4.csv'
    corrected.to_csv(corrected_path,index=False)
    changes = original.loc[changed].copy().rename(columns={'type':'original_type'})
    changes['restored_type'] = corrected.loc[changed,'type']
    changes['reference_sha256'] = verification['sha256']
    changes.to_csv(destination/'source_label_changes.csv',index=False)
    pd.DataFrame(rejected,columns=['reference_line','reason']).to_csv(destination/'reference_quarantine.csv',index=False)
    contradictory.to_csv(destination/'contradictory_reference_labels.csv',index=False)
    manifest = {'version':1,'experiment':name,'source_reference_verified':True,
        'created_utc':datetime.now(timezone.utc).isoformat(),
        'reference_verification':verification,'original_data4_sha256':sha256(original_path),
        'data4_path':corrected_path.relative_to(root).as_posix(),
        'data4_sha256':sha256(corrected_path),
        'split_path':(destination/'frozen_splits.json').relative_to(root).as_posix(),
        'changed_records':int(changed.sum()),'reference_quarantined':len(rejected),
        'reference_reconstructed_url_rows':len(repairs),
        'scope':'historical source-label restoration; not current web ground truth',
        'comparison_note':'New experiment: do not directly compare metrics to the old label set or claim a fresh time-based holdout.'}
    manifest_path = destination/'experiment.json'
    manifest_path.write_text(json.dumps(manifest,indent=2),encoding='utf-8')
    if activate:
        (root/'training_data/active_experiment.json').write_text(json.dumps(
            {'manifest':manifest_path.relative_to(root).as_posix()},indent=2),encoding='utf-8')
    return manifest_path,manifest


def resolve_active_experiment(root):
    root = Path(root).resolve()
    pointer = root/'training_data/active_experiment.json'
    if not pointer.exists():
        return {}, None, None
    registry = json.loads(pointer.read_text(encoding='utf-8'))
    manifest_path = _contained(root/registry['manifest'], root/'training_data/experiments')
    manifest = json.loads(manifest_path.read_text(encoding='utf-8'))
    if manifest.get('version') != 1 or manifest.get('source_reference_verified') is not True:
        raise ValueError('Active dataset experiment lacks primary-reference verification.')
    dataset = _contained(root/manifest['data4_path'], manifest_path.parent)
    split_path = _contained(root/manifest['split_path'], manifest_path.parent)
    if sha256(dataset) != manifest['data4_sha256']:
        raise ValueError('Immutable experiment dataset changed after source verification.')
    return {'data4.csv':dataset},split_path,manifest
