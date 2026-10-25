"""Checks for split persistence, fair evaluation, and production artifact guards."""
from pathlib import Path
import sys
import tempfile
import unittest

import numpy as np
import pandas as pd

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from retraining_utils import (freeze_splits, metric_summary, select_threshold,
                              promotion_failures, promote_candidate,
                              select_block_threshold, blocking_failures,
                              apply_training_reviews, validation_review_queue)
from phishing_url_utils import (url_lexical_features, host_text_urls, path_query_text_urls,
                                decision_thresholds, model_decision, UrlProbabilityEnsemble)


POLICY = {'min_precision': 0.9, 'min_recall': 0.8,
          'max_false_positive_rate': 0.03, 'min_test_examples_per_class': 100}


class FrozenSplitTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.path = Path(self.temp.name) / 'splits.json'
        self.frame = pd.DataFrame([
            {'url': f'{i}.domain-{g}.com/{i}', 'label': g % 2, 'group': f'domain-{g}.com'}
            for g in range(100) for i in range(3)
        ])

    def test_reordering_reuses_exact_assignments_and_does_not_rewrite_file(self):
        first, _ = freeze_splits(self.frame, self.path, {'domain-1.com'})
        original = self.path.read_bytes()
        again, _ = freeze_splits(self.frame.sample(frac=1, random_state=4), self.path, {'domain-1.com'})
        self.assertEqual(dict(zip(first.url, first.split)), dict(zip(again.url, again.split)))
        self.assertEqual(self.path.read_bytes(), original)
        self.assertTrue(first[first.group.eq('domain-1.com')].split.eq('train').all())
        self.assertEqual(first.groupby('group')['split'].nunique().max(), 1)
        self.assertEqual(set(first.split), {'train', 'validation', 'test'})

    def test_changed_label_or_new_data_cannot_silently_rebuild_holdout(self):
        freeze_splits(self.frame, self.path, set())
        original = self.path.read_bytes()
        changed = self.frame.copy()
        changed.loc[0, 'label'] = 1
        for frame in [changed, self.frame.iloc[:-1]]:
            with self.assertRaisesRegex(ValueError, 'Dataset, labels'):
                freeze_splits(frame, self.path, set())
        self.assertEqual(self.path.read_bytes(), original)

    def test_changing_seed_or_reviewed_domains_is_rejected(self):
        freeze_splits(self.frame, self.path, set())
        with self.assertRaises(ValueError):
            freeze_splits(self.frame, self.path, set(), seed=2)
        with self.assertRaises(ValueError):
            freeze_splits(self.frame, self.path, {'domain-1.com'})

    def test_training_only_sampling_leaves_full_holdout_identical(self):
        assigned, _ = freeze_splits(self.frame, self.path, set())
        holdout = assigned[assigned.split.ne('train')].url.tolist()
        for count in [5, 15]:
            train = assigned[assigned.split.eq('train')].sample(n=count, random_state=7)
            self.assertFalse(set(train.url).intersection(holdout))
            reused, _ = freeze_splits(self.frame, self.path, set())
            self.assertEqual(reused[reused.split.ne('train')].url.tolist(), holdout)


class QualityGateTests(unittest.TestCase):
    def test_infeasible_recall_fallback_respects_false_positive_limit(self):
        y = np.array([0]*200+[1]*200)
        scores = np.array([.2]*190+[.9]*10+[.95]*100+[.1]*100)
        threshold, passed = select_threshold(y, scores, POLICY)
        self.assertFalse(passed)
        self.assertLessEqual(metric_summary(y, scores, threshold)['false_positive_rate'], .03)

    def test_strict_block_policy_can_disable_blocking(self):
        policy = {'min_precision': .98, 'max_false_positive_rate': .005, 'min_predictions':50}
        y = np.array([0]*200+[1]*200)
        self.assertIsNone(select_block_threshold(y, np.full(400,.5), .4, policy))
        self.assertEqual(blocking_failures(None, None, policy), [])
        threshold = select_block_threshold(y, np.array([.1]*200+[.9]*200), .5, policy)
        self.assertAlmostEqual(threshold,.9)

    def test_component_features_preserve_real_host_and_path_case(self):
        urls = ['instagram.com.attacker.example/Login?Token=AbC']
        self.assertEqual(host_text_urls(urls), ['instagram.com.attacker.example'])
        self.assertIn('/Login', path_query_text_urls(urls)[0])
        self.assertIn('Token=AbC', path_query_text_urls(urls)[0])

    def test_model_warning_is_not_a_block_without_validation(self):
        self.assertEqual(model_decision({'threshold': .5}, .99), 'Suspicious')
        with self.assertRaises(ValueError):
            decision_thresholds({'threshold':.5,'decision_policy':{'block_threshold':.8,'validated':False}})

    def test_feasible_threshold_and_infeasible_policy_are_explicit(self):
        y = np.array([0] * 200 + [1] * 200)
        threshold, passed = select_threshold(y, np.array([0.1]*200+[0.9]*200), POLICY)
        self.assertTrue(passed)
        metrics = metric_summary(y, np.array([0.1]*200+[0.9]*200), threshold)
        self.assertEqual(promotion_failures(metrics, metrics, True, POLICY), [])
        _, passed = select_threshold(y, np.full(400, 0.5), POLICY)
        self.assertFalse(passed)

    def test_homepage_success_cannot_override_bad_phishing_recall(self):
        y = np.array([0] * 200 + [1] * 200)
        scores = np.array([0.1]*200+[0.9]*100+[0.1]*100)
        metrics = metric_summary(y, scores, 0.5)
        failures = promotion_failures(metrics, metrics, True, POLICY)
        self.assertTrue(any('recall' in reason for reason in failures))

    def test_failed_or_disabled_promotion_preserves_runtime_and_passing_promotion_backs_up(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            runtime, candidate = root/'runtime.joblib', root/'candidate.joblib'
            runtime.write_bytes(b'old evaluated model')
            candidate.write_bytes(b'new evaluated model')
            self.assertFalse(promote_candidate(candidate, runtime, root, ['failed recall'], enabled=True))
            self.assertEqual(runtime.read_bytes(), b'old evaluated model')
            self.assertFalse(promote_candidate(candidate, runtime, root, [], enabled=False))
            self.assertTrue(promote_candidate(candidate, runtime, root, [], enabled=True))
            self.assertEqual(runtime.read_bytes(), b'new evaluated model')
            self.assertEqual((root/'previous_deployed_bundle.joblib').read_bytes(), b'old evaluated model')

    def test_lexical_features_keep_structure_and_are_finite(self):
        matrix = url_lexical_features(['example.com/', '127.0.0.1/verify?password=123',
                                       'xn--example.test/A?B=C'])
        self.assertEqual(matrix.shape, (3, 17))
        self.assertTrue(np.isfinite(matrix.toarray()).all())
        self.assertFalse(np.array_equal(matrix.toarray()[0], matrix.toarray()[1]))


class ReviewTests(unittest.TestCase):
    def test_queue_balances_both_error_types_and_limits_one_domain(self):
        frame = pd.DataFrame([{'url':f'{i}.example/{j}','label':label,'source':'original','group':f'{i}.example'}
            for label in [0,1] for i in range(label*20,(label+1)*20) for j in range(10)])
        scores = np.where(frame['label'].eq(0),.99,.001)
        queue = validation_review_queue(frame,scores,.64,limit=40,max_per_group=2)
        self.assertEqual(queue['existing_label'].value_counts().to_dict(),{0:20,1:20})
        self.assertLessEqual(queue.groupby('group').size().max(),2)
        self.assertTrue(queue['label'].eq('').all())

    def test_explicit_reviews_never_add_an_evaluation_domain_to_training(self):
        train = pd.DataFrame([{'url':'train.example.com/','label':1,'source':'original',
                               'group':'train.example.com','sample_weight':1.,'split':'train'}])
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory)/'reviews.csv'
            path.write_text('url,label,evidence,reviewed_by,reviewed_at\n'
                            'https://train.example.com/,0,manual ownership check,reviewer,2026-10-25\n'
                            'https://heldout.example.com/,0,manual ownership check,reviewer,2026-10-25\n')
            group = lambda u: u.split('/')[0]
            result, decisions = apply_training_reviews(train,path,{'heldout.example.com'},group)
            self.assertEqual(len(result),1)
            self.assertEqual(result.iloc[0]['label'],0)
            self.assertEqual(int(decisions['applied'].sum()),1)
            self.assertFalse(result['url'].str.contains('heldout').any())

    def test_missing_review_evidence_is_rejected(self):
        train = pd.DataFrame([{'url':'train.example.com/','label':0}])
        with tempfile.TemporaryDirectory() as directory:
            path=Path(directory)/'reviews.csv'
            path.write_text('url,label,evidence,reviewed_by,reviewed_at\nhttps://example.com/,0,,reviewer,2026-10-25\n')
            with self.assertRaisesRegex(ValueError,'explicit label, evidence'):
                apply_training_reviews(train,path,set(),lambda u:u)

    def test_review_queue_does_not_assign_labels_as_truth(self):
        frame=pd.DataFrame([{'url':'example.com/','label':0,'source':'original','group':'example.com'}])
        queue=validation_review_queue(frame,[.99],.5)
        self.assertEqual(queue.iloc[0]['existing_label'],0)
        self.assertEqual(queue.iloc[0]['label'],'')
        self.assertEqual(queue.iloc[0]['evidence'],'')


if __name__ == '__main__':
    unittest.main()
