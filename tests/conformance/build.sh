#!/usr/bin/env bash
# Bundles @panneau/protocol/conformance from a checkout of panneau-js, into one ES module.
#
#   tests/conformance/build.sh <panneau-js checkout> <output file>
set -euo pipefail

protocol="$1/packages/protocol"
output="$2"

# The protocol's only dependency is zod
npm install --prefix "$protocol" --no-package-lock --no-audit --no-fund --omit=dev --ignore-scripts
npx --yes esbuild@0.28.2 "$protocol/src/conformance/index.ts" \
    --bundle --platform=node --format=esm --target=node24 --log-level=warning --tsconfig-raw='{}' \
    --outfile="$output"
