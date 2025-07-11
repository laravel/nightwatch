FROM php:8.4-alpine

WORKDIR /nightwatch-agent
COPY version.txt agent/entrypoint.sh ./
COPY agent/build ./agent/build/

ENV NIGHTWATCH_INGEST_URI=0.0.0.0:2407

WORKDIR /nightwatch-agent
ENTRYPOINT ["./entrypoint.sh"]
