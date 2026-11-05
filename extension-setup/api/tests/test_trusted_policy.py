import sys
from pathlib import Path
import unittest

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from trusted_url_policy import trusted_url, POLICY


class TrustedPolicyTests(unittest.TestCase):
    def test_approved_domains_and_resources(self):
        for host in POLICY['trusted_hosts']:
            self.assertTrue(trusted_url('https://' + host + '/'))
        for url in POLICY['trusted_urls']:
            self.assertTrue(trusted_url(url + '#section'))
        self.assertTrue(trusted_url('https://github.com/login'))
        for url in ['https://drive.google.com/drive/folders/new-folder',
                    'https://docs.google.com/forms/d/new-form/edit',
                    'https://sites.google.com/view/new-site',
                    'https://github.com/another/repository']:
            self.assertTrue(trusted_url(url))

    def test_exclusions_and_lookalikes(self):
        for url in [
            'https://coingraph-update.com/', 'https://mail.printakid.com/',
            'https://testcsfund.site.je/', 'https://l.facebook.com/l.php?u=https://chatgpt.com',
            'https://l.messenger.com/l.php?u=https://chatgpt.com',
            'https://chatgpt.com.evil.example/', 'https://chatgpt.com@evil.example/',
            'https://user@chatgpt.com/', 'http://chatgpt.com/',
            'https://chatgpt.com:8443/', 'https://drive.google.com.evil.example/file',
            'https://evil.example/?url=https://drive.google.com/file',
        ]:
            with self.subTest(url=url):
                self.assertFalse(trusted_url(url))
