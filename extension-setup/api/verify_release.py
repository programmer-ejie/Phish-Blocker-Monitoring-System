"""Run inside a generated github_api_release folder before pushing."""
import argparse
from pathlib import Path
from release_utils import verify_release_package


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('directory', nargs='?', default=str(Path(__file__).resolve().parent))
    args = parser.parse_args()
    verify_release_package(args.directory)
    print('PASS: strict metrics, runtime pins, model approval, file hashes, and homepage predictions.')
