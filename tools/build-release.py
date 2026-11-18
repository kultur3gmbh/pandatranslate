#!/usr/bin/env python3
"""Build committed source; keep WordPress.org and GitHub update channels separate."""
from pathlib import Path
import hashlib
import io
import json
import os
import re
import subprocess
import zipfile

root = Path(__file__).resolve().parents[1]
slug = 'pandatranslate-multilingual' if (root / 'pandatranslate-multilingual.php').exists() else 'pandatranslate'

def git(*args):
    return subprocess.check_output(['git', *args], cwd=root)

if git('status', '--porcelain').strip():
    raise SystemExit('Commit source changes before building.')
main = (root / (slug + '.php')).read_text()

def header(name):
    found = re.search(r'^\s*\* ' + re.escape(name) + r':\s*(.+)$', main, re.M)
    if not found:
        raise SystemExit('Missing plugin header: ' + name)
    return found[1].strip()

version = header('Version')
if not re.fullmatch(r'\d+\.\d+\.\d+', version):
    raise SystemExit('Invalid release version.')
output = Path(os.environ.get('PANDA_OUTPUT_DIR', '/Users/matthias/Documents/Codex/Outputs'))
output.mkdir(parents=True, exist_ok=True)
source_archive = git('archive', '--format=zip', '--prefix=' + slug + '/', 'HEAD')
with zipfile.ZipFile(io.BytesIO(source_archive)) as archive:
    source = {item.filename: archive.read(item) for item in archive.infolist() if not item.is_dir()}

# Catch stale readable copies before packaging either distribution.
source_map = root / 'sources/manifest.json'
if source_map.exists():
    for entry in json.loads(source_map.read_text())['files']:
        if hashlib.sha256((root / entry['runtime']).read_bytes()).hexdigest() != entry['sha256']:
            raise SystemExit('Refresh readable JavaScript source: ' + entry['runtime'])

variants = [('github', '')] if slug.endswith('-multilingual') else [('github', ''), ('wordpress-org', '-wordpress-org')]
for distribution, suffix in variants:
    files = dict(source)
    if distribution == 'github' and slug == 'pandatranslate':
        files[slug + '/includes/class-panda-public-updater.php'] = git('show', 'HEAD:tools/github/class-panda-public-updater.php')
        files[slug + '/includes/github-bootstrap.php'] = git('show', 'HEAD:tools/github/bootstrap.php')
        text = files[slug + '/pandatranslate.php'].decode()
        text = text.replace(' * Version:', ' * Update URI: https://github.com/kultur3gmbh/pandatranslate\n * Version:', 1)
        files[slug + '/pandatranslate.php'] = text.encode()
    if distribution == 'wordpress-org':
        files = {name: data for name, data in files.items() if not name.endswith(('.po', '.mo'))}
    package = output / (slug + '-' + version + suffix + '.zip')
    with zipfile.ZipFile(package, 'w', zipfile.ZIP_DEFLATED) as archive:
        for name, data in sorted(files.items()):
            if '/tests/' in name or '/tools/' in name or '/.git' in name:
                raise SystemExit('Unexpected development file in archive: ' + name)
            archive.writestr(name, data)
    with zipfile.ZipFile(package) as check:
        if check.testzip():
            raise SystemExit('Invalid ZIP.')
    checksum = hashlib.sha256(package.read_bytes()).hexdigest()
    (output / (package.name + '.sha256')).write_text(checksum + '  ' + package.name + '\n')
    if distribution == 'github':
        readme = (root / 'readme.txt').read_text()
        tested = re.search(r'^Tested up to:\s*(.+)$', readme, re.M)
        manifest = {'slug': slug, 'version': version, 'requires': header('Requires at least'), 'requires_php': header('Requires PHP'), 'tested': tested[1] if tested else '', 'sha256': checksum, 'source_commit': git('rev-parse', 'HEAD').decode().strip()}
        if slug.endswith('-multilingual'):
            manifest['requires_pandatranslate'] = header('Requires Panda Translate')
        (output / (slug + '-update.json')).write_text(json.dumps(manifest, indent=2) + '\n')
    print(package)
