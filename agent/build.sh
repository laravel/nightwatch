#!/usr/bin/env bash

set -e

docker build --platform=linux/amd64 -t agent-builder -f docker/Dockerfile.build .

docker run --rm -v $(pwd):/app --entrypoint composer agent-builder install --prefer-dist --no-dev --no-interaction --classmap-authoritative
docker run --rm -v $(pwd):/app agent-builder
SIGNATURE=$(docker run --rm -v $(pwd):/app agent-builder info:signature build/agent.phar)

echo $SIGNATURE >build/signature.txt
