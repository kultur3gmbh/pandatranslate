#!/usr/bin/env python3
"""Build only committed, clean source into a WordPress ZIP and checksum manifest."""
from pathlib import Path
import hashlib, io, json, re, subprocess, zipfile
root=Path(__file__).resolve().parents[1]
slug='pandatranslate-multilingual' if (root/'pandatranslate-multilingual.php').exists() else 'pandatranslate'
def git(*args):return subprocess.check_output(['git',*args],cwd=root)
if git('status','--porcelain').strip():raise SystemExit('Commit source changes before building.')
main=(root/(slug+'.php')).read_text()
def header(name):
    found=re.search(r'^\s*\* '+re.escape(name)+r':\s*(.+)$',main,re.M)
    if not found:raise SystemExit('Missing plugin header: '+name)
    return found[1].strip()
version=header('Version')
if not re.fullmatch(r'\d+\.\d+\.\d+',version):raise SystemExit('Invalid release version.')
output=root/'dist';output.mkdir(exist_ok=True)
archive=git('archive','--format=zip','--prefix='+slug+'/','HEAD')
package=output/(slug+'-'+version+'.zip');package.write_bytes(archive)
with zipfile.ZipFile(io.BytesIO(archive)) as check:
    if check.testzip():raise SystemExit('Invalid ZIP.')
    assert slug+'/'+slug+'.php' in check.namelist()
    assert all('/tests/' not in name and '/tools/' not in name for name in check.namelist())
checksum=hashlib.sha256(archive).hexdigest()
manifest={'slug':slug,'version':version,'requires':header('Requires at least'),'requires_php':header('Requires PHP'),'tested':'6.4','sha256':checksum,'source_commit':git('rev-parse','HEAD').decode().strip()}
if slug.endswith('-multilingual'):manifest['requires_pandatranslate']=header('Requires PandaTranslate')
(output/(slug+'-update.json')).write_text(json.dumps(manifest,indent=2)+'\n')
(output/(package.name+'.sha256')).write_text(checksum+'  '+package.name+'\n')
print(package.name+' — '+checksum)
