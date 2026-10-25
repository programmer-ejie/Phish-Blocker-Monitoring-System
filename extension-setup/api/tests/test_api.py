"""Offline integration checks against the actual exported URL-text model."""
import importlib
from pathlib import Path
import sys
import tempfile
import unittest
import numpy as np
from unittest.mock import patch

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from fastapi.testclient import TestClient
from phishing_url_utils import normalize_url, predict_urls, model_text_urls
api = importlib.import_module('app')


class ScoreModel:
    classes_ = np.array([0, 1])
    def predict_proba(self, urls):
        return np.tile([0.1, 0.9], (len(urls), 1))


class ApiTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.client = TestClient(api.app)
        cls.client.__enter__()

    @classmethod
    def tearDownClass(cls):
        cls.client.__exit__(None, None, None)

    def setUp(self):
        self.google = patch.object(api, 'GOOGLE_API_KEY', None)
        self.google.start()
        self.addCleanup(self.google.stop)

    def test_v2_text_removes_www_consistently_and_preserves_paths(self):
        urls = ['https://www.instagram.com/Case?Token=AbC', 'https://instagram.com/Case?Token=AbC']
        self.assertEqual(model_text_urls(urls), ['instagram.com/Case?Token=AbC'] * 2)
        self.assertEqual(normalize_url(urls[0]), 'www.instagram.com/Case?Token=AbC')
        self.assertNotEqual(model_text_urls(['https://instagram.com/'])[0],
                            model_text_urls(['https://instagram.com/other-path'])[0])

    def test_v2_text_does_not_whitelist_lookalike_domains(self):
        url = 'https://www.instagram.com.attacker.example/verify?Token=AbC'
        self.assertEqual(model_text_urls([url]), ['instagram.com.attacker.example/verify?Token=AbC'])

    def test_health_and_model_identity(self):
        response = self.client.get('/health')
        self.assertEqual(response.status_code, 200)
        self.assertTrue(response.json()['healthy'])
        home = self.client.get('/').json()
        self.assertEqual(home['model_name'], 'phishing_url_bundle.joblib')
        self.assertAlmostEqual(home['model_threshold'], api.model_bundle['threshold'])

    def test_inference_matches_training_helper_without_network(self):
        url = 'https://example.com/CaseSensitive/Path?Token=AbC'
        expected = float(predict_urls(api.model_bundle, [url]).iloc[0]['phishing_score'])
        with patch.object(api.requests, 'post') as post:
            response = self.client.get('/check_url', params={'url': url})
        self.assertEqual(response.status_code, 200)
        body = response.json()
        self.assertEqual(body['url'], url)
        self.assertAlmostEqual(body['score'], expected)
        self.assertEqual(body['ml_engine'], 'phishing_url_bundle')
        self.assertTrue({'risk_level', 'status', 'reason'} <= body.keys())
        post.assert_not_called()

    def test_legacy_saved_threshold_controls_warning_not_blocking(self):
        url = 'https://example.com/threshold-check'
        score = float(predict_urls(api.model_bundle, [url]).iloc[0]['phishing_score'])
        for threshold, expected in [(score - 0.000001, 'Suspicious'), (score + 0.000001, 'Proceed')]:
            with patch.dict(api.model_bundle, {'threshold': threshold, 'decision_policy': {}}), patch.object(api, 'phishtank_urls', set()):
                response = self.client.get('/check_url', params={'url': url})
                self.assertEqual(response.json()['status'], expected)

    def test_blocklist_can_override_benign_model_prediction(self):
        url = 'https://example.com/CaseSensitive'
        with patch.dict(api.model_bundle, {'threshold': 1.0, 'decision_policy': {}}), patch.object(api, 'phishtank_urls', {normalize_url(url)}):
            response = self.client.get('/check_url', params={'url': url})
        self.assertEqual(response.json()['status'], 'Block')
        self.assertIn('PhishTank', response.json()['reason'])

    def test_invalid_urls_are_rejected(self):
        for url in ['', 'javascript:alert(1)', 'file:///etc/passwd', 'https://example.com:bad/', 'not a url']:
            with self.subTest(url=url):
                self.assertEqual(self.client.get('/check_url', params={'url': url}).status_code, 422)

    def test_model_unavailable_is_not_reported_as_safe(self):
        with patch.object(api, 'model_bundle', None):
            self.assertEqual(self.client.get('/health').status_code, 503)
            self.assertEqual(self.client.get('/check_url', params={'url': 'https://example.com'}).status_code, 503)

    def test_missing_model_fails_loading(self):
        with self.assertRaisesRegex(RuntimeError, 'Model bundle missing'):
            api.load_model_bundle(Path('missing-model-test.joblib'))

    def test_invalid_threshold_fails_loading(self):
        with patch.object(api.joblib, 'load', return_value={'pipeline': None, 'threshold': float('nan')}):
            with self.assertRaisesRegex(RuntimeError, 'invalid decision threshold'):
                api.load_model_bundle(api.MODEL_PATH)

    def test_blocklist_preserves_path_case_and_filters_unverified(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / 'blocklist.csv'
            path.write_text('url,verified\nhttps://example.com/Case,yes\nhttps://example.com/unverified,no\n', encoding='utf-8')
            self.assertEqual(api.load_phishtank_blocklist(path), {'example.com/Case'})

    def test_optional_google_failure_leaves_model_available(self):
        with patch.object(api, 'GOOGLE_API_KEY', 'test-key'), patch.object(api.requests, 'post', side_effect=api.requests.Timeout):
            self.assertEqual(self.client.get('/check_url', params={'url': 'https://example.com'}).status_code, 200)

    def test_validated_block_threshold_is_distinct_from_warning(self):
        bundle = {'pipeline': ScoreModel(), 'threshold': 0.4,
                  'decision_policy': {'validated': True, 'block_threshold': 0.95}}
        with patch.object(api, 'model_bundle', bundle), patch.object(api, 'phishtank_urls', set()):
            response = self.client.get('/check_url', params={'url': 'https://example.com'})
            self.assertEqual(response.json()['status'], 'Suspicious')
            bundle['decision_policy']['block_threshold'] = 0.85
            response = self.client.get('/check_url', params={'url': 'https://example.com'})
            self.assertEqual(response.json()['status'], 'Block')

    def test_high_legacy_score_is_not_automatic_proof_of_phishing(self):
        bundle = {'pipeline': ScoreModel(), 'threshold': 0.4}
        with patch.object(api, 'model_bundle', bundle), patch.object(api, 'phishtank_urls', set()):
            response = self.client.get('/check_url', params={'url': 'https://example.com'})
        self.assertEqual(response.json()['status'], 'Suspicious')
        self.assertIsNone(response.json()['model_block_threshold'])

    def test_unapproved_v4_candidate_cannot_be_served(self):
        bundle = {'pipeline': ScoreModel(), 'threshold': 0.4, 'preprocessing_version': 4,
                  'deployment_approved': False}
        with patch.object(api.joblib, 'load', return_value=bundle):
            with self.assertRaisesRegex(RuntimeError, 'failed deployment gates'):
                api.load_model_bundle(api.MODEL_PATH)

    def test_v5_missing_strict_proof_is_rejected(self):
        bundle = {'pipeline': ScoreModel(), 'threshold': .4, 'preprocessing_version': 5,
                  'release_profile': 'strict', 'deployment_approved': True}
        with patch.object(api.joblib, 'load', return_value=bundle):
            with self.assertRaisesRegex(RuntimeError, 'Strict release proof'):
                api.load_model_bundle(api.MODEL_PATH)

    def test_v5_threshold_cannot_differ_from_approved_evaluation(self):
        metrics = {'precision': 1., 'recall': 1., 'false_positive_rate': 0.,
                   'tp': 200, 'tn': 200, 'fp': 0, 'fn': 0}
        proof = {'threshold': .5, 'block_threshold': None, 'validation_metrics': metrics,
                 'test_metrics': metrics, 'regressions_passed': True}
        bundle = {'pipeline': ScoreModel(), 'threshold': .4, 'preprocessing_version': 5,
                  'release_profile': 'strict', 'deployment_approved': True, 'release_metrics': proof}
        with patch.object(api.joblib, 'load', return_value=bundle):
            with self.assertRaisesRegex(RuntimeError, 'threshold differs'):
                api.load_model_bundle(api.MODEL_PATH)

    def test_v5_helper_must_match_evaluated_source(self):
        metrics = {'precision': 1., 'recall': 1., 'false_positive_rate': 0.,
                   'tp': 200, 'tn': 200, 'fp': 0, 'fn': 0}
        proof = {'threshold': .5, 'block_threshold': None, 'validation_metrics': metrics,
                 'test_metrics': metrics, 'regressions_passed': True, 'helper_sha256': 'outdated'}
        bundle = {'pipeline': ScoreModel(), 'threshold': .5, 'preprocessing_version': 5,
                  'release_profile': 'strict', 'deployment_approved': True, 'release_metrics': proof}
        with patch.object(api.joblib, 'load', return_value=bundle):
            with self.assertRaisesRegex(RuntimeError, 'helper differs'):
                api.load_model_bundle(api.MODEL_PATH)

    def test_logging_is_not_handled_by_prediction_server(self):
        self.assertEqual(self.client.post('/logs', json={}).status_code, 404)
        self.assertEqual(self.client.get('/').json()['logging_backend'], 'hostinger-laravel')


if __name__ == '__main__':
    unittest.main()
