#!/usr/bin/env python3
"""Check current PHP messages and compiled catalogs, without translation services."""
from pathlib import Path
import gettext
import subprocess
import tempfile

root = Path(__file__).resolve().parents[1]
slug = 'pandatranslate-multilingual' if (root / 'pandatranslate-multilingual.php').exists() else 'pandatranslate'
files = [root / (slug + '.php'), *sorted((root / 'includes').glob('*.php'))]
with tempfile.TemporaryDirectory(prefix='panda-catalog-check-') as directory:
    template = Path(directory) / 'current.pot'
    subprocess.run(['xgettext', '--language=PHP', '--from-code=UTF-8', '--keyword=__', '--keyword=_e',
                    '--keyword=esc_html__', '--keyword=esc_attr__', '--keyword=esc_html_e', '--keyword=esc_attr_e',
                    '--keyword=_n:1,2', '--keyword=_x:1,2c', '--keyword=esc_html_x:1,2c', '--keyword=esc_attr_x:1,2c',
                    '-o', str(template), *map(str, files)], check=True)
    for po in sorted((root / 'languages').glob(slug + '-*.po')):
        comparison = subprocess.run(['msgcmp', str(po), str(template)], capture_output=True, text=True)
        if comparison.returncode:
            raise SystemExit(comparison.stderr)
        compiled = Path(directory) / po.with_suffix('.mo').name
        subprocess.run(['msgfmt', '--check', str(po), '-o', str(compiled)], check=True)
        with compiled.open('rb') as expected, po.with_suffix('.mo').open('rb') as installed:
            if gettext.GNUTranslations(expected)._catalog != gettext.GNUTranslations(installed)._catalog:
                raise SystemExit('Stale compiled translation catalog: ' + str(po.with_suffix('.mo')))
        print('PASS current messages and compiled catalog: ' + po.name)
