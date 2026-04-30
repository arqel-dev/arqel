<?php

declare(strict_types=1);

namespace Arqel\Export\Contracts;

/**
 * Resolves the iterable of records to be exported by a queued job.
 *
 * `ProcessExportJob` stores only the FQCN of the resolver (not the
 * record set itself) in the job payload. At handle-time, the job
 * resolves the class out of the container and calls `resolve()`.
 *
 * This avoids serialising potentially huge collections into the
 * queue payload — implementations should return a streaming source
 * (lazy collection, generator, Eloquent cursor) so the exporter
 * can write row-by-row.
 */
interface RecordsResolver
{
    /**
     * @return iterable<int, mixed>
     */
    public function resolve(): iterable;
}
