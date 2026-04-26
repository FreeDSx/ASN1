#!/usr/bin/env bash
#
# Bench HEAD vs working-tree baseline, with N paired runs to weed out noise.
#
# Each pair:
#   1. Stashes the change-under-measurement (src/, tests/unit/, phpstan.tests.neon).
#   2. Runs `composer bench` against the baseline; saves JSON.
#   3. Pops the stash.
#   4. Runs `composer bench` against HEAD; saves JSON.
#
# Bench infrastructure (tests/bin/, tests/performance/, composer.json) is NOT
# stashed — it must remain present for both runs.
#
# At the end, all N pairs are aggregated by `asn1-bench-aggregate.php`, which
# reports median delta, per-pair range, sign-consistency, and a regression
# verdict. Exits with the aggregator's status.
#
# Usage:
#   composer bench-vs-baseline                       # 5 pairs (default)
#   composer bench-vs-baseline -- -n 7               # 7 pairs
#   composer bench-vs-baseline -- -n 7 --threshold=2.0 --consistency=80
#   composer bench-vs-baseline -- --paths='src/ tests/unit/'  # custom stash scope
#
# A `trap` ensures the stash is popped if any single iteration fails, so your
# working tree is never left in the stashed state.

set -euo pipefail

cd "$(dirname "$0")/../.."

PAIRS=5
STASH_PATHS=(src/ tests/unit/ phpstan.tests.neon)
EXTRA_AGG_ARGS=()

while [[ $# -gt 0 ]]; do
    case "$1" in
        -n|--pairs)
            PAIRS="$2"
            shift 2
            ;;
        --paths=*)
            # shellcheck disable=SC2206  # word-split into array on purpose
            STASH_PATHS=(${1#--paths=})
            shift
            ;;
        --threshold=*|--consistency=*)
            EXTRA_AGG_ARGS+=("$1")
            shift
            ;;
        -h|--help)
            sed -n '2,28p' "$0" | sed 's/^# \?//'
            exit 0
            ;;
        *)
            echo "unknown argument: $1" >&2
            exit 2
            ;;
    esac
done

if [[ ! "$PAIRS" =~ ^[0-9]+$ ]] || (( PAIRS < 1 )); then
    echo "--pairs must be a positive integer (got: $PAIRS)" >&2
    exit 2
fi

if [ -z "$(git status --porcelain -- "${STASH_PATHS[@]}")" ]; then
    echo "No uncommitted changes within: ${STASH_PATHS[*]}" >&2
    echo "Override with --paths='dir1 dir2 ...' if needed." >&2
    exit 1
fi

RUN_ID="asn1-bench.$$.$(date +%s)"
RUN_DIR="$(mktemp -d -t "${RUN_ID}.XXXXXX")"
echo "Run artifacts: ${RUN_DIR}"

STASH_LABEL="bench-vs-baseline-$$"
STASH_ACTIVE=0

restore_stash() {
    if (( STASH_ACTIVE == 1 )) && git stash list | grep -q "${STASH_LABEL}"; then
        echo "--- Restoring stashed changes (cleanup)…" >&2
        git stash pop --quiet >/dev/null 2>&1 || true
        STASH_ACTIVE=0
    fi
}
trap restore_stash EXIT INT TERM

BASE_FILES=()
HEAD_FILES=()

for ((i = 1; i <= PAIRS; i++)); do
    BASE_OUT="${RUN_DIR}/baseline-${i}.json"
    HEAD_OUT="${RUN_DIR}/head-${i}.json"

    echo
    echo "=== Pair ${i}/${PAIRS} — baseline ==="
    git stash push -u -m "${STASH_LABEL}" --quiet -- "${STASH_PATHS[@]}"
    STASH_ACTIVE=1
    composer bench -- --out="${BASE_OUT}"

    echo
    echo "=== Pair ${i}/${PAIRS} — head ==="
    git stash pop --quiet
    STASH_ACTIVE=0
    composer bench -- --out="${HEAD_OUT}"

    BASE_FILES+=("${BASE_OUT}")
    HEAD_FILES+=("${HEAD_OUT}")
done

trap - EXIT INT TERM

echo
echo "=== Aggregated comparison (${PAIRS} pair$([[ $PAIRS -gt 1 ]] && echo s)) ==="

AGG_ARGS=()
for f in "${BASE_FILES[@]}"; do
    AGG_ARGS+=("--baseline=$f")
done
for f in "${HEAD_FILES[@]}"; do
    AGG_ARGS+=("--head=$f")
done
composer bench-aggregate -- "${AGG_ARGS[@]}" "${EXTRA_AGG_ARGS[@]}"
