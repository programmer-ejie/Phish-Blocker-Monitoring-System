"""Offline integration checks against the actual exported URL-text model."""
import importlib
from pathlib import Path
import sys
import tempfile
import unittest
from unittest.mock import Mock, patch

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from fastapi.testclient import TestClient
from phishing_url_utils import normalize_url, predict_urls
api = importlib.import_module('app')


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

    def test_saved_threshold_controls_blocking(self):
        url = 'https://example.com/threshold-check'
        score = float(predict_urls(api.model_bundle, [url]).iloc[0]['phishing_score'])
        for threshold, expected in [(score - 0.000001, 'Block'), (score + 0.000001, 'Proceed')]:
            with patch.dict(api.model_bundle, {'threshold': threshold}), patch.object(api, 'phishtank_urls', set()):
                response = self.client.get('/check_url', params={'url': url})
                self.assertEqual(response.json()['status'], expected)

    def test_blocklist_can_override_benign_model_prediction(self):
        url = 'https://example.com/CaseSensitive'
        with patch.dict(api.model_bundle, {'threshold': 1.0}), patch.object(api, 'phishtank_urls', {normalize_url(url)}):
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

    def test_logging_schema_and_supabase_payload(self):
        payload = {'url': 'https://example.com', 'score': 0.7, 'risk_level': 'High',
                   'status': 'Block', 'computer_number': 1, 'campus_name': 'Test Campus',
                   'metadata': {'ml_engine': 'phishing_url_bundle'}}
        with patch.object(api, 'SUPABASE_LOGS_URL', 'https://database.invalid/rest/v1/phishing_logs'), patch.object(api, 'SUPABASE_KEY', 'test-key'), patch.object(api.requests, 'post', return_value=Mock(status_code=201)) as post:
            response = self.client.post('/logs', json=payload)
        self.assertEqual(response.status_code, 201)
        self.assertEqual(response.json(), {'logged': True})
        self.assertEqual(post.call_args.kwargs['json']['metadata'], payload['metadata'])

    def test_unconfigured_logging_is_explicit(self):
        with patch.object(api, 'SUPABASE_LOGS_URL', ''):
            response = self.client.post('/logs', json={'url': 'https://example.com', 'risk_level': 'Low', 'status': 'Proceed'})
        self.assertEqual(response.status_code, 503)


if __name__ == '__main__':
    unittest.main()
