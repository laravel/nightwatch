#!/usr/bin/env bash

set -e

box compile

SIGNATURE=$(box info:signature build/agent.phar)

echo $SIGNATURE > build/signature.txt

sed -i "s/public const SIGNATURE = '.*';/public const SIGNATURE = '${SIGNATURE:0:7}';/" ../src/Payload.php;
