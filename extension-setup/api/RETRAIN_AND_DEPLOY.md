# Ready to retrain the source-restored experiment

The original PhishStorm ZIP has now been supplied and checked. The active
experiment is `phishstorm_restored_v1`, with 92,005 restored source labels in a
separate derived dataset. The notebook selects it automatically. Use the
PhishBlock kernel, restart, and Run All. Original datasets and old splits remain
unchanged. Strict >=80% recall and all other gates still apply. No replacement
model has been trained or deployed by this dataset repair.

Earlier source-access warnings below are historical and are superseded by
`training_data/phishstorm_reference_verification.json` and
`training_data/source_restored_experiment_manifest.json`.

---

# Data provenance work required before the next training run

Read `training_data/PROVENANCE_FINDINGS.md`. A full source-label audit identified
92,005 disagreements against a local PhishStorm-format reference. The original
publisher archive is currently inaccessible (HTTP 403), so none was automatically
applied. `phishstorm_reference_verification.json` remains pending. Do not repeatedly
rerun unchanged data or treat proposed corrections as verified ground truth.

After authenticating the original snapshot, `prepare_verified_dataset.py` creates
and optionally activates a NEW experiment without overwriting the old labels or
split. The notebook/runner automatically uses that verified active experiment.
The strict deployment requirements are unchanged.

---

# Final profile: STRICT

You selected strict deployment. The 80% recall requirement remains mandatory;
no assistive/relaxed acceptance profile is enabled.

After a real retraining run, look for **READY_FOR_GITHUB: True**. Only then does
`artifacts/url_retraining_v5_*/github_api_release/` contain an approved standalone
API. Copy that folder's **contents** into the root of your `phishblock_api` repository,
install its requirements, run `python verify_release.py`, review changes, then push.
Its Render Root Directory is blank; the generated Blueprint removes the monorepo
rootDir. The package excludes your .env, raw corpora, old models, and private logs.
It includes the evaluated bundle, exact inference helper, strict verifier, pinned
dependencies, MODEL_CARD.md, and SHA-256 file manifest.

The acceptance limits are rechecked independently of editable notebook settings:
precision >=90%, recall >=80%, and benign warning rate <=3% on validation/test;
homepage regressions must pass. When ML blocking is enabled it also needs >=98%
precision, <=0.5% benign block rate, and sufficient validation/test support.
Runtime dependency pins must match the training environment. New v5 bundles
carry strict metric proof, thresholds, and helper hash checked by API startup.

A failing run prints READY_FOR_GITHUB: False, preserves the runtime model, and
creates no release package. Editing PROMOTE_MODEL=True cannot bypass the gates.
Changing code or repackaging cannot make a 66% recall model satisfy 80% recall.
The latest failed candidate remains research-only. Do not repeatedly rerun
unchanged data expecting different accuracy; improve verified training evidence.

---

# One-run retraining and deployment (v4)

## Execute

In VS Code select **PhishBlock Training (Python 3.12)**, restart the kernel,
then **Run All** in `retrain_phishing_urls.ipynb`. The setup reads the exact helper
source to avoid stale imports. The Matplotlib dependency is healthy in this
registered environment; do not select an unrelated project's `.venv`.

You can also run the identical notebook code from a terminal, without Jupyter:

```powershell
& "C:\Users\perso\EJIE\Sir Buloy Act\web extention\.venv\Scripts\python.exe" "C:\Users\perso\EJIE\Herd\phish-block-monitoring-system\extension-setup\api\train_model.py"
```

On a new machine, create a Python 3.12 environment, install
`requirements-training.txt`, then run `python train_model.py` from this folder.
The runner installs nothing, logs to `artifacts/training_*.log`, and never pushes
GitHub or deploys Render. Exit code 0 means a model was promoted; code 2 means a
completed research run that did not promote. An exception means execution failed.
`--no-promote` preserves the runtime model even if every check passes.

## What Run All does

1. Audits source labels and removes contradictory equivalent URLs.
2. Reuses the permanent `training_data/frozen_splits_v3.json` assignments. This
   filename remains v3 deliberately: v4 features do not redefine evaluation rows.
3. Reads the optional reviewed-training CSV. Only reviewed rows with an explicit
   0/1 label, evidence, reviewer, and date are accepted. Frozen validation/test
   domains are excluded from training, even if someone submits a review for them.
4. Fits raw-text diagnostics, www-invariant text, separate hostname/path/query
   features, nonlinear structure features, and validation-selected soft voting.
   All compared approaches receive the same training rows and sample weights.
5. Selects models and warning/block thresholds on validation only, then evaluates
   the frozen choices on test. The raw-URL baseline cannot be a production winner.
6. Exports review queues, coverage, metrics, a reload-verified bundle, and a push
   decision. It backs up and replaces the runtime model only when all gates pass.

No known domain is automatically trusted. A substring such as `google.` no longer
bypasses the extension check. Suspicious results show a dismissible banner;
blocking results show the existing intervention overlay in all six extensions.

## Warning and automatic-block policy

The warning/detection threshold keeps the existing acceptance requirements on
**both validation and test**: precision >=90%, phishing recall >=80%, and benign
warning rate <=3%, plus enough test data per class and all homepage regressions.
These targets were not reduced to make a weak model pass.

Automatic ML blocking is separate: validation precision >=98%, benign block rate
<=0.5%, and at least 50 block predictions. If no point qualifies, ML blocking is
DISABLED before test evaluation. If a blocking threshold is selected, test must
also satisfy its precision/FPR/support limits or promotion is blocked. Warnings
still work without model-only blocking. These historical metrics are acceptance
checks, not guarantees on future traffic.

The API treats older bundles as **warning-only**; a large uncalibrated score alone
is insufficient to block. A new bundle must carry validated decision thresholds
and `deployment_approved: true`. Failed v4 research candidates cannot be served.
Verified PhishTank and configured Google threat matches can still block.

## Reading the result

The final `LOCAL_MODEL_UPDATED` value is decisive:

- **True:** every required gate passed. The evaluated model now exists at
  `dataset/phishing_url_bundle.joblib`; the previous runtime model is backed up
  in the run folder. Review the metrics and Git diff before pushing.
- **False:** the runtime model was preserved. Read `gate_failures` and the review
  reports. Do not rename/copy an unapproved candidate into the deployment folder
  or lower the requirements merely to obtain a passing message.

## Add better training evidence without code edits

Fill `training_data/reviewed_training_urls.csv` with:

```text
url,label,evidence,reviewed_by,reviewed_at
```

Use `0` for independently reviewed benign URLs and `1` for independently verified
phishing. Include the evidence/source, actual reviewer, and ISO date. An empty
file containing only the header is valid and Run All works without manual edits.
No new URLs are automatically labeled safe from their domain or current score.

Each run creates `validation_review_queue.csv`. Review labels are deliberately
blank. A disagreement does not prove the source label is wrong. Use the queue to
investigate data quality and collect independent TRAINING examples, not blindly
copy predictions into the training CSV. Reviews from frozen validation/test
**domains** are recorded as excluded; changing their labels would need a new
named experiment rather than silently contaminating the current holdout.

Other outputs include `training_coverage.csv`, `training_review_decisions.csv`,
`validation_comparison.csv`, `paired_test_comparison.csv`, `test_by_source.csv`,
`homepage_regression_checks.csv`, and `metrics_and_manifest.json` under
`artifacts/url_retraining_v4_*/`. Test mistakes are for reporting, not auto-training.
The initial validation-only audit is in `artifacts/quality_audit_*/`.

## Push and deploy

For a passing run, include the promoted runtime model, `phishing_url_utils.py`,
`app.py`, pinned `requirements.txt`, and current deployment configuration. For
reproducibility include the notebook, `retraining_utils.py`,
`refresh_training_helpers.py`, `train_model.py`, `requirements-training.txt`,
frozen split JSON, and reviewed CSVs. Keep raw corpora, artifacts, and `.env` local.
Deploy the updated extension background/content/popup scripts for all six campuses
and the Laravel controller if logging the new decision metadata.

For the separate `phishblock_api` repository put the API contents at its root;
Render Root Directory must be blank and its Blueprint must omit the monorepo
`rootDir`. For the full monitoring repository retain `extension-setup/api`.
The notebook/runner never pushes either repository automatically.

After deployment verify `/health`, the warning/block thresholds in `/`, and the
Facebook/Instagram homepage decisions. Reload all six extensions to clear old
verdicts. Review the non-blocking warning and strong-signal blocking behavior.

The source data are historical and the test set has been inspected repeatedly.
Keep it as development evidence; use a recently collected independent final set
before making strong production-accuracy claims. Better data quality requires
real review evidence; rerunning unchanged data cannot guarantee a better model.
