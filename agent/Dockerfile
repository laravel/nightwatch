FROM php:8.4-alpine

WORKDIR /nightwatch-agent
COPY version.txt ./
COPY agent/build ./agent/build/

ENV NIGHTWATCH_INGEST_URI=0.0.0.0:2407

WORKDIR /nightwatch-agent
ENTRYPOINT ["php", "agent/build/agent.phar"]
