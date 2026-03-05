#!/bin/sh

shutdown() {
    echo "Received signal, shutting down..."
    if [ -n "$child" ]; then
        kill -TERM "$child" 2>/dev/null
        wait "$child"
    fi
    exit 0
}

trap shutdown TERM INT

NIGHTWATCH_DOCKER_AGENT=1 php agent/build/agent.phar &
child=$!

wait "$child"
