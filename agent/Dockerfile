FROM php:8.4-alpine

COPY agent/composer.lock agent/composer.json agent/install-composer.sh /

# install composer and check platform reqs
RUN ./install-composer.sh \
    && php composer.phar check-platform-reqs --lock --no-dev \
    && rm composer.* \
    && rm install-composer.sh

WORKDIR /nightwatch-agent

COPY version.txt agent/entrypoint.sh ./
COPY agent/build ./agent/build/

ENV NIGHTWATCH_INGEST_URI=0.0.0.0:2407

WORKDIR /nightwatch-agent
ENTRYPOINT ["./entrypoint.sh"]
