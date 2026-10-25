"""Prepare a NEW research experiment only after authenticating the original source."""
import argparse
from pathlib import Path
from dataset_experiments import prepare_verified_experiment

if __name__=='__main__':
    parser=argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--verification',type=Path,required=True,help='Publisher snapshot verification JSON; pending records are refused.')
    parser.add_argument('--name',default='phishstorm_restored_v1')
    parser.add_argument('--activate',action='store_true',help='Have the notebook automatically use this NEW dataset and split file.')
    args=parser.parse_args()
    path,manifest=prepare_verified_experiment(Path(__file__).resolve().parent,args.verification,args.name,args.activate)
    print('Prepared source-restored experiment:',path)
    print('Restored historical labels:',manifest['changed_records'])
    print('Original datasets, old frozen splits, and runtime model were preserved.')
