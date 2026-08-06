<?php

// The following comment contains a non UTF-8 character: Café
throw new \RuntimeException('Whoops!', 999);
