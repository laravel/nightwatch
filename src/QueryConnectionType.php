<?php

namespace Laravel\Nightwatch;

enum QueryConnectionType: string
{
    case Read = 'read';
    case Write = 'write';
    case Direct = 'direct';
    case Unknown = 'unknown';
}
