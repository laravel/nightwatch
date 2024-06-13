<?php

namespace Laravel\Nightwatch\Records;

use Laravel\Nightwatch\Types\MediumText;
use Laravel\Nightwatch\Types\TinyText;

final class Query
{
    /**
     * @param  non-empty-string  $timestamp
     * @param  non-empty-string  $group
     * @param  non-empty-string  $trace_id
     * @param  'job'|'request'  $execution_context
     * @param  non-empty-string  $execution_id
     * @param  non-empty-string  $sql
     * @param  'select'|'insert'|'update'|'delete'  $category
     * @param  non-empty-string  $file
     * @param  non-negative-int  $line
     * @param  non-negative-int  $duration
     * @param  non-empty-string  $connection
     */
    public function __construct(
        public string $timestamp,
        public string $deploy_id,
        public string $server,
        public string $group,
        public string $trace_id,
        public string $execution_context,
        public string $execution_id,
        public string $user,
        // --- //
        public string $sql,
        public string $category,
        public string $file,
        public int $line,
        public int $duration,
        public string $connection,
    ) {
        $this->sql = MediumText::limit($this->sql);
        $this->file = TinyText::limit($this->file);
        $this->connection = TinyText::limit($this->connection);
    }
}
