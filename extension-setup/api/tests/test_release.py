"""Strict acceptance cannot be bypassed by editable notebook flags or packaging."""
import copy
import importlib.metadata
from pathlib import Path
import shutil
import sys
import tempfile
import unittest

import joblib
from sklearn.dummy import DummyClassifier

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from release_utils import (STRICT_QUALITY_POLICY, MODEL_PACKAGES, build_release_package,
                           file_hash, strict_report_failures, verify_release_package)

API = Path(__file__).resolve().parents[1]


def passing_report():
    metrics = {'precision':1., 'recall':1., 'false_positive_rate':0.,
               'tp':200, 'tn':200, 'fp':0, 'fn':0}
    return {'selected_model':'synthetic-test-only', 'threshold':.5,'block_threshold':None,
            'validation_metrics':dict(metrics),'test_metrics':dict(metrics),
            'block_validation_metrics':None,'block_test_metrics':None,
            'regressions_passed':True,'gate_failures':[],
            'quality_policy':dict(STRICT_QUALITY_POLICY),
            'versions':{name:importlib.metadata.version(name) for name in MODEL_PACKAGES},
            'helper_sha256':file_hash(API/'phishing_url_utils.py')}


class StrictReleaseTests(unittest.TestCase):
    def test_unresolved_source_labels_block_even_perfect_metrics(self):
        report=passing_report();report['source_provenance_verified']=False
        self.assertTrue(any('provenance' in item for item in strict_report_failures(report)))

    def fixture(self, parent):
        source=Path(parent)/'source'; source.mkdir()
        for name in ['app.py','phishing_url_utils.py','release_utils.py','verify_release.py',
                     'requirements.txt','Procfile','runtime.txt','.python-version','.envexample','render.yaml']:
            shutil.copy2(API/name,source/name)
        # A secret file exists in the fixture but must never be copied into a release.
        (source/'.env').write_text('TEST_ONLY_SECRET=do-not-package')
        report=passing_report()
        model=DummyClassifier(strategy='constant',constant=0).fit(['one','two'],[0,1])
        bundle={'pipeline':model,'threshold':.5,'preprocessing_version':5,
                'release_profile':'strict','deployment_approved':True,
                'release_metrics':copy.deepcopy(report),'versions':report['versions']}
        candidate=Path(parent)/'candidate.joblib';joblib.dump(bundle,candidate)
        run=Path(parent)/'run';run.mkdir()
        return source,run,candidate,report

    def test_lowered_notebook_policy_cannot_approve_66_percent_recall(self):
        report=passing_report()
        report['quality_policy']['min_recall']=.1
        report['test_metrics']['recall']=.66
        failures=strict_report_failures(report)
        self.assertTrue(any('recall' in item and '80%' in item for item in failures))

    def test_failed_report_produces_no_release_folder(self):
        with tempfile.TemporaryDirectory() as parent:
            source,run,candidate,report=self.fixture(parent)
            report['test_metrics']['recall']=.66
            with self.assertRaisesRegex(ValueError,'Release blocked'):
                build_release_package(source,run,candidate,report)
            self.assertFalse((run/'github_api_release').exists())

    def test_passed_package_is_standalone_verified_and_secret_free(self):
        with tempfile.TemporaryDirectory() as parent:
            source,run,candidate,report=self.fixture(parent)
            package=build_release_package(source,run,candidate,report)
            verify_release_package(package)
            self.assertFalse((package/'.env').exists())
            self.assertFalse((package/'training_data').exists())
            self.assertNotIn('rootDir:',(package/'render.yaml').read_text())
            self.assertTrue((package/'MODEL_CARD.md').exists())
            self.assertEqual(file_hash(package/'dataset/phishing_url_bundle.joblib'),file_hash(candidate))

    def test_changed_package_file_fails_verification(self):
        with tempfile.TemporaryDirectory() as parent:
            source,run,candidate,report=self.fixture(parent)
            package=build_release_package(source,run,candidate,report)
            (package/'app.py').write_text('# changed after verification')
            with self.assertRaisesRegex(ValueError,'changed: app.py'):
                verify_release_package(package)

    def test_wrong_runtime_pin_prevents_packaging(self):
        with tempfile.TemporaryDirectory() as parent:
            source,run,candidate,report=self.fixture(parent)
            (source/'requirements.txt').write_text('scikit-learn==0.0.0')
            with self.assertRaisesRegex(ValueError,'Runtime pin'):
                build_release_package(source,run,candidate,report)

    def test_missing_homepage_proof_prevents_release(self):
        report=passing_report();report['regressions_passed']=False
        self.assertTrue(strict_report_failures(report))


if __name__=='__main__':
    unittest.main()
