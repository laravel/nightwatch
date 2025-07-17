#!/usr/bin/env bash

set -e

box compile

SIGNATURE=$(box info:signature build/agent.phar)

echo $SIGNATURE > build/signature.txt
