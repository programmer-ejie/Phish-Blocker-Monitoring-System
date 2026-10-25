# Local training data

These CSVs were copied from the existing research workspace; they are historical
snapshots, not freshly collected threat feeds. `source_manifest.json` records their
original locations, sizes, and SHA-256 hashes. The notebook uses `data4.csv` and
verified rows from `phishtank.csv` by default. It does not overwrite the API's
runtime `dataset/phishtank.csv` or `dataset/phishing_url_bundle.joblib`.

`data2.csv` and `data3.csv` are optional because their label definitions must be
confirmed before mixing them into a phishing-specific task. `data1.csv` and
`exclude.csv` have no raw URL column; `exclude1.csv` has unconfirmed label
provenance. These files are retained for research but skipped by this URL-text
notebook. `exclude1.csv` also contains non-UTF-8 bytes; do not silently replace
characters if you later import it.

`reviewed_urls.csv` records exact known homepage false positives as explicit
training feedback. It is not a list of trusted domains, and it never bypasses
inference or threat checks. Its domain groups are assigned to training, so results
on those URLs are regression checks, not independent accuracy measurements.
Only add independently reviewed labels; never label arbitrary URLs based solely
on the model's current prediction.

Raw CSVs are ignored by Git to avoid shipping research corpora on every Render
build. The notebook, reviewed corrections, and source manifest can be committed.
Keep local backups of the raw CSVs for reproducibility.

## Frozen v3 experiment

`frozen_splits_v3.json` is the permanent domain assignment and dataset fingerprint
for the v3 experiment. Commit and retain it. Never delete or edit it to chase an
accuracy score. Training-only sampling does not change the holdout. New data,
labels, or correction-domain changes require a new experiment filename; results
across different experiments are not a fair comparison. Only freshly refitted
models are included in the v3 comparison, not the old deployed bundle.

## Reviewed training additions (v4)

`reviewed_training_urls.csv` is a separate evidence-backed training input with
url, label, evidence, reviewed_by, and reviewed_at columns. Its header-only default
is intentional. New/reviewed training-domain URLs can be added without changing
the base frozen dataset. Validation/test domain reviews are exported as excluded
and never moved into training. Actual applied training rows/labels/weights and
the reviewed CSV are fingerprinted in each run's manifest. Source-label changes
to the BASE corpus still require a new named experiment.
