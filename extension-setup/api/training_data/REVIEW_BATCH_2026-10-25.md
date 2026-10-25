# Reviewed-data audit: 2026-10-25

The 500-entry queue now has 250 false-positive disagreements and 250 missed-phishing
disagreements under the existing labels, spanning 409 domain groups.
The old raw-distance ranking returned only missed-phishing cases. The new queue
normalizes confidence distance, balances error types, and caps one domain at three
entries. Queue review labels remain blank; no prediction was imported as truth.

There are 272 cases whose original label differs from what
`data2.csv` good/bad terminology suggests. This is an investigation flag, not proof:
its target taxonomy/provenance may differ, snapshots are old, and legitimate
websites can host compromised content. Existing labels and held-out domains have
NOT been changed based on those flags.

Added 30 benign training feedback rows across 9 domain groups. Each records a
published page URL, observed content purpose, reviewer identity, and review date.
The reviewed pages include public documentation, homepages, support pages,
government information, and a legitimate article about phishing. Representative
sources include https://support.microsoft.com/en-us/windows/ and
https://www.nasa.gov/news/recently-published/ . Only the exact reviewed URLs are
labeled; their whole domains are not trusted or bypassed.

All 30 additions passed the training-only domain check. None entered validation or
test. Frozen base dataset fingerprint remains
`7916b005a3869d206de312b75bfdfecd04df2039048d907657f583ccbdb551ce`.
The new CSV is training feedback, not independent test evidence. More independently
verified phishing and representative benign samples are still needed; these 30
examples are a starter batch and do not guarantee an accuracy improvement.

Local audit files (under artifacts/reviewed_data_audit_20261024T234709):
- `balanced_validation_review_queue.csv`: actual disagreements + raw data2 labels.
- `reviewed_training_decisions.csv`: 30 applied rows, zero held-out additions.
- `training_coverage.csv`: updated training representation.
- `summary.json`: counts and fingerprint.

Next: restart the PhishBlock kernel and Run All. The notebook automatically reads
`training_data/reviewed_training_urls.csv` and uses the balanced queue logic.
Quality gates and runtime model remain unchanged. A passed training run must still
be reviewed before deployment; no Git push or Render deployment was performed.
