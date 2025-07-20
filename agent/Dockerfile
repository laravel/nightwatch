FROM php:8.4-alpine

COPY agent/composer.lock agent/composer.json /
COPY --from=composer /usr/bin/composer /composer

RUN ./composer check-platform-reqs --lock --no-dev \
    && rm ./composer*

WORKDIR /nightwatch-agent

COPY version.txt agent/entrypoint.sh ./
COPY agent/build ./agent/build/

ENV NIGHTWATCH_INGEST_URI=0.0.0.0:2407

WORKDIR /nightwatch-agent
ENTRYPOINT ["./entrypoint.sh"]
