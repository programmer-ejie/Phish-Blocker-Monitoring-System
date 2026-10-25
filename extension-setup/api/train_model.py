"""Run the notebook's training code once without a Jupyter kernel.

Use the PhishBlock Python environment: python train_model.py
No pip installation, Git push, or Render deployment occurs in this runner.
"""
import argparse
from datetime import datetime, timezone
import json
import os
from pathlib import Path
import sys


class Tee:
    def __init__(self, *streams):
        self.streams = streams

    def write(self, value):
        for stream in self.streams:
            stream.write(value)
            stream.flush()
        return len(value)

    def flush(self):
        for stream in self.streams:
            stream.flush()


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--no-promote', action='store_true', help='Always preserve the runtime model, even if gates pass.')
    parser.add_argument('--max-train-rows-per-source', type=int, help='Training-only limit; leaves frozen evaluation partitions unchanged.')
    args = parser.parse_args()
    root = Path(__file__).resolve().parent
    os.chdir(root)
    os.environ.setdefault('MPLBACKEND', 'Agg')
    notebook = json.loads((root/'retrain_phishing_urls.ipynb').read_text(encoding='utf-8'))
    artifacts = root/'artifacts'
    artifacts.mkdir(exist_ok=True)
    log_path = artifacts/('training_'+datetime.now(timezone.utc).strftime('%Y%m%dT%H%M%S%fZ')+'.log')
    namespace = {}
    original_stdout, original_stderr = sys.stdout, sys.stderr
    with log_path.open('w',encoding='utf-8') as log:
        sys.stdout, sys.stderr = Tee(original_stdout,log), Tee(original_stderr,log)
        try:
            print('Python:',sys.executable)
            print('Log:',log_path)
            for index,cell in enumerate(notebook['cells']):
                if cell['cell_type'] != 'code':
                    continue
                source = ''.join(cell['source'])
                if '%pip' in source:
                    continue
                print('Executing notebook cell',index)
                exec(compile(source,f'notebook-cell-{index}','exec'),namespace)
                if 'ROOT = Path.cwd().resolve()' in source:
                    if args.no_promote:
                        namespace['PROMOTE_MODEL'] = False
                    if args.max_train_rows_per_source is not None:
                        namespace['MAX_TRAIN_ROWS_PER_SOURCE'] = args.max_train_rows_per_source
                    namespace['plt'].show = lambda: None
        except ModuleNotFoundError:
            print('Missing dependency: use the PhishBlock Python environment or install requirements-training.txt.')
            raise
        finally:
            sys.stdout, sys.stderr = original_stdout, original_stderr
    return 0 if namespace.get('manifest',{}).get('local_model_updated') else 2


if __name__ == '__main__':
    raise SystemExit(main())
