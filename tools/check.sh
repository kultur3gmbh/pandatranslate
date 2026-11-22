#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
while IFS= read -r -d '' file; do php -l "$file" >/dev/null; done < <(find . -name '*.php' -not -path './dist/*' -print0)
for test in tests/*-test.php; do php -d zend.assertions=1 -d assert.exception=1 "$test"; done
for file in assets/*.js; do node --check "$file"; done
for test in tests/*-test.js; do node "$test"; done
for file in languages/*.po; do msgfmt --check "$file" -o /dev/null; done

if [[ "${PANDATRANSLATE_TEST_WARNING_REGRESSIONS:-}" == "1" ]]; then
  php tests/warning-regressions-integration.php
  php tests/migration-batches-integration.php
  php tests/migration-formal-integration.php
  php tests/language-performance-integration.php
  php tests/background-language-integration.php
  php tests/google-batch-integration.php
  php tests/stored-templates-integration.php
fi

if [[ "${PANDATRANSLATE_TEST_WOO_SYSTEM_PAGES:-}" == "1" ]]; then
  php tests/woocommerce-system-pages-integration.php
fi

if [[ "${PANDATRANSLATE_TEST_WOO_SYSTEM_PAGES:-}" == "1" ]]; then
  php tests/javascript-inventory-integration.php
fi

if [[ "${PANDATRANSLATE_TEST_HTTP_SCAN:-}" == "1" ]]; then
  php tests/scan-http-integration.php
fi
