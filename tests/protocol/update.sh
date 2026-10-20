#!/usr/bin/env bash
# Copies the protocol's generated schemas from @panneau/protocol, at a ref of panneau-js.
#
#   tests/protocol/update.sh [ref]    # default: v5.x
set -euo pipefail

ref="${1:-v5.x}"
dir="$(cd "$(dirname "$0")" && pwd)"
# Exact refs only: a bare pattern also matches refs/heads/changeset-release/v5.x
sha="$(git ls-remote https://github.com/folkloreinc/panneau-js.git "refs/heads/${ref}" "refs/tags/${ref}" | head -n1 | cut -f1)"
sha="${sha:-$ref}"
base="https://raw.githubusercontent.com/folkloreinc/panneau-js/${sha}/packages/protocol/schema"

curl -fsSL "${base}/definition.schema.json" -o "${dir}/definition.schema.json"
curl -fsSL "${base}/openapi.json" -o "${dir}/openapi.json"
curl -fsSL "${base}/boot.schema.json" -o "${dir}/boot.schema.json"
echo "${sha}" > "${dir}/VERSION"
echo "Updated the protocol schemas to folkloreinc/panneau-js@${sha}"
