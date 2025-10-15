FROM php:8.4-alpine

COPY agent/composer.lock agent/composer.json agent/docker/install-composer.sh /

# install composer and check platform reqs
RUN ./install-composer.sh \
    && php composer.phar check-platform-reqs --lock --no-dev \
    && rm composer.* \
    && rm install-composer.sh

WORKDIR /nightwatch-agent

COPY version.txt agent/docker/entrypoint.sh agent/nightwatch-status agent/helpers.php ./
COPY agent/build ./agent/build/

ENV NIGHTWATCH_INGEST_URI=0.0.0.0:2407

WORKDIR /nightwatch-agent
HEALTHCHECK --interval=30s --timeout=5s --start-period=5s --retries=3 CMD ["php", "nightwatch-status"]
ENTRYPOINT ["./entrypoint.sh"]
