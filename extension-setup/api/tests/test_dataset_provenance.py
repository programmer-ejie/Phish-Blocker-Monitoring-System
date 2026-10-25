import csv
import json
from pathlib import Path
import sys
import tempfile
import unittest

import pandas as pd

sys.path.insert(0,str(Path(__file__).resolve().parents[1]))
from audit_dataset_provenance import EXPECTED_HEADER, SOURCE_RECORD, parse_local_reference, sha256
from dataset_experiments import prepare_verified_experiment, resolve_active_experiment


def write_reference(path, rows):
    with path.open('w',encoding='ascii',newline='') as handle:
        writer=csv.writer(handle);writer.writerow(EXPECTED_HEADER)
        for url,label in rows:writer.writerow([url]+[0]*12+[label])


class ReferenceParserTests(unittest.TestCase):
    def test_conflicting_reference_labels_are_not_used(self):
        with tempfile.TemporaryDirectory() as directory:
            p=Path(directory)/'reference.csv'
            write_reference(p,[('same.example/',0),('same.example/',1),('good.example/',0)])
            records,_,_,conflicts=parse_local_reference(p)
            self.assertEqual(records['url'].tolist(),['good.example/'])
            self.assertEqual(len(conflicts),2)

    def test_unquoted_url_comma_requires_valid_numeric_suffix(self):
        with tempfile.TemporaryDirectory() as directory:
            p=Path(directory)/'reference.csv'
            write_reference(p,[])
            with p.open('a',encoding='ascii') as handle:
                handle.write('good.example/a,b,'+','.join(['0']*13)+'\n')
                handle.write('invalid.example/,'+','.join(['0']*12+['2'])+'\n')
            records,rejected,repairs,_=parse_local_reference(p)
            self.assertEqual(records['url'].tolist(),['good.example/a,b'])
            self.assertEqual(len(repairs),1)
            self.assertEqual(len(rejected),1)


class VerifiedExperimentTests(unittest.TestCase):
    def fixture(self,root,status='verified_primary_snapshot'):
        data=root/'training_data';data.mkdir()
        reference=data/'reference.csv'
        write_reference(reference,[('good.example/',0),('attack.example/',1)])
        pd.DataFrame([{'url':'good.example/','type':'phishing'},
                      {'url':'attack.example/','type':'benign'},
                      {'url':'good.example/page','type':'defacement'}]).to_csv(data/'data4.csv',index=False)
        (data/'frozen_splits_v3.json').write_text('old frozen experiment must remain unchanged')
        record={'status':status,'source_record':SOURCE_RECORD,'reference_file':str(reference),
                'sha256':sha256(reference),'verified_by':'synthetic test fixture',
                'verified_at':'2026-10-25','retrieved_from':'https://research.aalto.fi/files/16859732/urlset.csv.zip',
                'label_mapping':{'0':'legitimate','1':'phishing'}}
        verification=data/'verification.json';verification.write_text(json.dumps(record))
        return data,verification

    def test_pending_source_cannot_change_labels_or_activate(self):
        with tempfile.TemporaryDirectory() as directory:
            root=Path(directory);data,verification=self.fixture(root,'pending')
            before=sha256(data/'data4.csv')
            with self.assertRaisesRegex(ValueError,'not authenticated'):
                prepare_verified_experiment(root,verification,activate=True)
            self.assertEqual(sha256(data/'data4.csv'),before)
            self.assertFalse((data/'active_experiment.json').exists())
            self.assertFalse((data/'experiments').exists())

    def test_verified_source_creates_separate_data_and_split_location(self):
        with tempfile.TemporaryDirectory() as directory:
            root=Path(directory);data,verification=self.fixture(root)
            original=sha256(data/'data4.csv');old_split=sha256(data/'frozen_splits_v3.json')
            _,manifest=prepare_verified_experiment(root,verification,activate=True)
            self.assertEqual(manifest['changed_records'],2)
            sources,split_path,_=resolve_active_experiment(root)
            corrected=pd.read_csv(sources['data4.csv'])
            self.assertEqual(corrected['type'].tolist(),['benign','phishing','defacement'])
            self.assertNotEqual(split_path,data/'frozen_splits_v3.json')
            self.assertEqual(sha256(data/'data4.csv'),original)
            self.assertEqual(sha256(data/'frozen_splits_v3.json'),old_split)

    def test_changed_verified_dataset_is_rejected(self):
        with tempfile.TemporaryDirectory() as directory:
            root=Path(directory);_,verification=self.fixture(root)
            _,manifest=prepare_verified_experiment(root,verification,activate=True)
            (root/manifest['data4_path']).write_text('modified')
            with self.assertRaisesRegex(ValueError,'changed after source verification'):
                resolve_active_experiment(root)

    def test_wrong_reference_checksum_is_rejected(self):
        with tempfile.TemporaryDirectory() as directory:
            root=Path(directory);_,verification=self.fixture(root)
            record=json.loads(verification.read_text());record['sha256']='not-the-source-hash'
            verification.write_text(json.dumps(record))
            with self.assertRaisesRegex(ValueError,'SHA-256'):
                prepare_verified_experiment(root,verification)


if __name__=='__main__':unittest.main()
