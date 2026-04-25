#!/bin/bash

if [ -z "$1" ]; then
    echo '{"error": "No input provided"}'
    exit 1
fi

INPUT="$1"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)

# تحديد الهدف
if [[ -f "$INPUT" ]]; then
    TARGET="multiple"
    DOMAINS=$(cat "$INPUT")
else
    TARGET="$INPUT"
    DOMAINS="$INPUT"
fi

# 🔥 pipeline كله in-memory
RESULTS=$(
    (
        echo "$DOMAINS" | subfinder -dL /dev/stdin --recursive -all -silent 2>/dev/null
        echo "$DOMAINS" | assetfinder --subs-only 2>/dev/null
    ) | sort -u | grep .
)

# 🧠 بناء JSON بـ jq
echo "$RESULTS" | jq -R '{subdomain: .}' | jq -s \
--arg target "$TARGET" \
--arg timestamp "$TIMESTAMP" \
'{
    target: $target,
    meta: {
        timestamp: $timestamp,
        tools: ["subfinder", "assetfinder"]
    },
    data: .
}'