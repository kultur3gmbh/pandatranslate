#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
while IFS= read -r -d '' file; do php -l "$file" >/dev/null; done < <(find . -name '*.php' -not -path './dist/*' -print0)
for test in tests/*-test.php; do php -d zend.assertions=1 -d assert.exception=1 "$test"; done
for file in assets/*.js; do node --check "$file"; done
for file in languages/*.po; do msgfmt --check "$file" -o /dev/null; done
