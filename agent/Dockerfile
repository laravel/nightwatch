FROM php:8.4-alpine

WORKDIR /nightwatch-agent
COPY version.txt agent/agent-wrapper.php ./
COPY agent/build ./agent/build/

WORKDIR /nightwatch-agent
ENTRYPOINT ["php", "agent-wrapper.php"]
