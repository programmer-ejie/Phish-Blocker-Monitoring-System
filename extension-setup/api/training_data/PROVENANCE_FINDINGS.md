# Source identity resolved ? 2026-10-25

The user supplied the Aalto publisher archive at `dataset/urlset.csv.zip`.
Its Windows download-origin metadata identifies research.aalto.fi, and the ZIP
member, extracted CSV, and existing local reference share the exact SHA-256:
`f7229c4f5f105a74f5b72df71ed8154f9fca10b6535b488192434fd988c848d3`.
This is a documented user-supplied download verification, not a publisher-signed
checksum claim. The canonical copy is `training_data/phishstorm_original.csv`.

The `phishstorm_restored_v1` derived experiment is now active. It restores 92,005
historical source labels in both directions, with a full change log, immutable
CSV hash, source evidence, and a separate frozen split path. Malformed or ambiguous
reference records were quarantined. Original data4.csv, the old frozen split,
and the runtime model were not overwritten. Other threat categories were kept.

The notebook automatically selects the new experiment. Use the PhishBlock kernel,
restart, and Run All. Do not directly compare these metrics with the previous
incorrect-label experiment. The strict release limits are unchanged; historical
source restoration is not proof of current live URL status or a fresh final test.

The report below is the historical audit that led to this repair. Its original
HTTP-403/source-identity blocker has now been resolved by the supplied archive.

---

# Dataset provenance findings (2026-10-25)

## Confirmed facts

The local `data4.csv` contains all 651,191 records from the original Kaggle v1
`malicious_phish.csv`, plus one extra local record. The original was downloaded
from the publisher API and compared as URL/type multisets, not just file size.
Different line endings/file bytes do not imply altered labels. Both hashes and
record counts are in DATASET_PROVENANCE.json.

The uploader declares PhishStorm as an upstream source used to expand phishing
examples. Aalto's original record explicitly defines label 0 as legitimate and
label 1 as phishing, and describes 48,009 examples of each class from 2014.

Primary sources:
- https://www.kaggle.com/datasets/sid321axn/malicious-urls-dataset
- https://research.aalto.fi/en/datasets/phishstorm-phishing-legitimate-url-dataset/
- https://doi.org/10.24342/f49465b2-c68a-4182-9171-075f0ed797d5

## Strong evidence of an aggregation/label-convention problem

The local `exclude1.csv` has the PhishStorm feature layout. After conservative
parsing, exact normalized-URL joins found 96,062 matching
aggregate records, with 92,005 disagreements:

- 47,673 aggregate phishing records match a local reference label of legitimate.
- 44,332 aggregate benign records match a local reference label of phishing.

This is consistent with inverted upstream label handling, not merely a shortage
of familiar homepage examples. It can make meaningful recall/precision targets
unreliable. The disagreement count is evidence for review, NOT a count of already
confirmed corrections or current live phishing sites.

## Important unresolved identity check

The local reference's identity is inferred; its original publisher bytes have
not been authenticated. The official Aalto archive returns HTTP 403 from this
environment, and the legacy university CSV endpoint returned no data. The
parser quarantined 4,023 malformed/ambiguous records,
excluded contradictory reference labels, and did not invent replacements.
No model predictions, domain popularity, or majority votes were used as truth.

Accordingly, all proposed corrections remain pending. Raw data, existing
train/validation/test labels, frozen split file, and runtime model are unchanged.

## Prepared workflow

1. Obtain the original `urlset.csv` from Aalto's `urlset.csv.zip` (or identify an
   existing original download). Save it as `training_data/phishstorm_original.csv`.
2. Authenticate that source and record the exact file checksum, reviewer, and
   retrieval evidence in `phishstorm_reference_verification.json`. Its current
   status is pending. Do not mark the existing local copy verified merely because
   its schema looks similar or its labels improve the model's metrics.
3. Once verified, run:

   `python prepare_verified_dataset.py --verification training_data/phishstorm_reference_verification.json --activate`

   This creates a NEW source-restored dataset under training_data/experiments,
   with a complete change log and its own frozen split path. It never overwrites
   raw data4 or the old frozen split. Malware/defacement categories stay intact.
4. The notebook automatically discovers the verified active experiment. Restart
   the PhishBlock kernel and Run All. Old and new label-set scores must not be
   compared as if the evaluation tasks were identical.
5. Strict >=80% recall and the other release gates remain unchanged. Historical
   label restoration is not current threat verification; a fresh independent
   final dataset is still needed for strong production claims.

Until step 1/2 is complete, do NOT rerun the old dataset expecting a different
result. The blocker is original-reference access, not a request to relax gates.

## Local evidence

Full source comparison, quarantined reference rows, and proposed corrections:
`artifacts/source_label_audit_20261025T013618706250Z`. The raw source snapshot and URL tables stay
under ignored artifacts; no Git push or production deployment was performed.
