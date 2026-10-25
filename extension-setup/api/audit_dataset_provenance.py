"""Audit source-label disagreements without changing training or evaluation labels.

Run in the PhishBlock environment: python audit_dataset_provenance.py
The local PhishStorm-format reference is NOT claimed to be byte-authenticated
against the original publisher archive. Proposed corrections remain pending.
"""
import argparse
import collections
import csv
from datetime import datetime, timezone
import hashlib
import json
import math
from pathlib import Path
from urllib.parse import urlsplit

import pandas as pd
import tldextract
from phishing_url_utils import normalize_url


SOURCE_RECORD = 'https://research.aalto.fi/en/datasets/phishstorm-phishing-legitimate-url-dataset/'
EXPECTED_HEADER = ['domain', 'ranking', 'mld_res', 'mld.ps_res', 'card_rem',
    'ratio_Rrem', 'ratio_Arem', 'jaccard_RR', 'jaccard_RA', 'jaccard_AR',
    'jaccard_AA', 'jaccard_ARrd', 'jaccard_ARrem', 'label']


def sha256(path):
    return hashlib.sha256(Path(path).read_bytes()).hexdigest()


def parse_local_reference(path):
    """Validate every physical record; quarantine malformed/ambiguous encodings.

    A URL containing an unquoted comma can be reconstructed only when all final
    13 feature/label fields are finite numbers and the label is exactly 0 or 1.
    Non-ASCII URLs are excluded because this local file's encoding is unconfirmed.
    """
    records, rejected, repairs = [], [], []
    with Path(path).open(encoding='latin1', newline='') as handle:
        header = next(csv.reader([next(handle)]))
        if header != EXPECTED_HEADER:
            raise ValueError('Reference header does not match the expected PhishStorm layout.')
        for line_number, physical_line in enumerate(handle, 2):
            try:
                row = next(csv.reader([physical_line], strict=True))
                if len(row) < 14:
                    raise ValueError('fewer than 14 fields')
                url = ','.join(row[:-13])
                features = [float(value) for value in row[-13:]]
                if not all(math.isfinite(value) for value in features) or features[-1] not in [0, 1]:
                    raise ValueError('invalid numeric tail or non-binary label')
                if not url.isascii():
                    raise ValueError('non-ASCII URL with unconfirmed source encoding')
                normalized = normalize_url(url)
                if normalized is None:
                    raise ValueError('invalid URL')
                records.append({'reference_line':line_number,'reference_url':url,
                                'url':normalized,'reference_label':int(features[-1])})
                if len(row) > 14:
                    repairs.append({'reference_line':line_number,'reason':'URL comma reconstructed from validated numeric suffix'})
            except (ValueError, csv.Error, StopIteration) as exc:
                rejected.append({'reference_line':line_number,'reason':str(exc)})
    frame = pd.DataFrame(records)
    contradictions = set(frame.groupby('url')['reference_label'].nunique().loc[lambda s:s>1].index)
    contradictory = frame[frame['url'].isin(contradictions)].copy()
    return frame[~frame['url'].isin(contradictions)].drop_duplicates('url'), rejected, repairs, contradictory


def audit(root, publisher_snapshot=None):
    root = Path(root)
    data_dir = root/'training_data'
    output = root/'artifacts'/('source_label_audit_'+datetime.now(timezone.utc).strftime('%Y%m%dT%H%M%S%fZ'))
    output.mkdir(parents=True)
    reference,rejected,repairs,contradictory = parse_local_reference(data_dir/'exclude1.csv')
    reference.to_csv(output/'parsed_local_reference.csv',index=False)
    pd.DataFrame(rejected,columns=['reference_line','reason']).to_csv(output/'quarantined_reference_rows.csv',index=False)
    pd.DataFrame(repairs,columns=['reference_line','reason']).to_csv(output/'reference_parse_repairs.csv',index=False)
    contradictory.to_csv(output/'contradictory_reference_labels.csv',index=False)
    original = pd.read_csv(data_dir/'data4.csv',encoding='utf-8-sig')
    publisher_comparison = None
    if publisher_snapshot:
        published = pd.read_csv(publisher_snapshot,encoding='utf-8-sig')
        local_rows = collections.Counter(zip(original['url'],original['type']))
        publisher_rows = collections.Counter(zip(published['url'],published['type']))
        publisher_comparison = {'publisher_csv_sha256':sha256(publisher_snapshot),
            'local_csv_sha256':sha256(data_dir/'data4.csv'),
            'publisher_rows':len(published),'local_rows':len(original),
            'extra_local_records':sum((local_rows-publisher_rows).values()),
            'missing_publisher_records':sum((publisher_rows-local_rows).values())}
    original['source_row'] = original.index+2
    original['raw_url'] = original['url']
    original['url'] = original['url'].map(normalize_url)
    merged = original.merge(reference,on='url',how='inner')
    merged['aggregation_label'] = merged['type'].map({'benign':0,'phishing':1})
    mismatches = merged[merged['aggregation_label'].notna()
        & merged['aggregation_label'].ne(merged['reference_label'])].copy()
    frozen = json.loads((data_dir/'frozen_splits_v3.json').read_text())
    extract = tldextract.TLDExtract(suffix_list_urls=(),include_psl_private_domains=True)
    def group(url):
        host=urlsplit('http://'+url).hostname
        return extract(host).top_domain_under_public_suffix or host
    mismatches['group']=mismatches['url'].map(group)
    mismatches['frozen_partition']=mismatches['group'].map(lambda value:
        frozen['assignments'].get(hashlib.sha256(value.encode()).hexdigest(),'not in current experiment'))
    mismatches['approval_status']='pending_original_reference_authentication'
    mismatches['applied']=False
    mismatches['primary_label_semantics']=SOURCE_RECORD
    mismatches.to_csv(output/'proposed_source_label_corrections.csv',index=False)
    summary={'source_record':SOURCE_RECORD,
        'confirmed_primary_semantics':{'0':'legitimate','1':'phishing'},
        'local_reference_sha256':sha256(data_dir/'exclude1.csv'),
        'local_reference_identity':'PhishStorm-format inferred match; original publisher bytes not authenticated',
        'publisher_download_status':'HTTP 403 from the linked Aalto archive; no primary snapshot obtained',
        'reference_unique_urls':len(reference),
        'reference_unique_class_counts':{str(k):int(v) for k,v in reference['reference_label'].value_counts().items()},
        'quarantined_reference_rows':len(rejected),'reconstructed_url_rows':len(repairs),
        'contradictory_reference_records':len(contradictory),
        'matched_data4_records':len(merged),'proposed_label_disagreements':len(mismatches),
        'disagreements_by_original_type':{str(k):int(v) for k,v in mismatches['type'].value_counts().items()},
        'disagreements_by_current_partition':{str(k):int(v) for k,v in mismatches['frozen_partition'].value_counts().items()},
        'publisher_comparison':publisher_comparison,'corrections_applied':0,
        'runtime_model_changed':False,'frozen_split_sha256':sha256(data_dir/'frozen_splits_v3.json')}
    (output/'summary.json').write_text(json.dumps(summary,indent=2),encoding='utf-8')
    return output,summary


if __name__=='__main__':
    parser=argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--publisher-snapshot',type=Path,help='Optional downloaded original Kaggle malicious_phish.csv, for multiset comparison.')
    args=parser.parse_args()
    output,summary=audit(Path(__file__).resolve().parent,args.publisher_snapshot)
    print(json.dumps(summary,indent=2))
    print('Audit output:',output)
