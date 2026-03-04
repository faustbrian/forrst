<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Console\Commands;

use Carbon\CarbonImmutable;
use Cline\Forrst\Contracts\OperationRepositoryInterface;
use Illuminate\Console\Command;
use Override;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

use function sprintf;

/**
 * Console command to clean up expired async operations.
 *
 * Removes async operations that have exceeded their TTL to prevent unbounded
 * storage growth. Should be scheduled to run periodically (recommended: hourly).
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/forrst/extensions/async
 */
final class CleanupExpiredOperationsCommand extends Command
{
    /**
     * The console command signature.
     *
     * @var string
     */
    #[Override()]
    protected $signature = 'forrst:cleanup-operations
        {--limit=1000 : Maximum number of operations to delete per run}
        {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    #[Override()]
    protected $description = 'Clean up expired async operations';

    /**
     * Execute the console command.
     *
     * @param OperationRepositoryInterface $repository Operation repository
     * @param LoggerInterface              $logger     Logger for recording operations
     *
     * @return int Command exit code
     */
    public function handle(
        OperationRepositoryInterface $repository,
        LoggerInterface $logger = new NullLogger(),
    ): int {
        $limit = (int) $this->option('limit');
        $dryRun = (bool) $this->option('dry-run');
        $now = CarbonImmutable::now();

        if ($dryRun) {
            $this->info('Dry run mode - no operations will be deleted.');
        }

        $this->info(sprintf(
            'Cleaning up operations expired before %s (limit: %d)',
            $now->toIso8601String(),
            $limit,
        ));

        if ($dryRun) {
            $this->warn('Dry run complete. Use without --dry-run to actually delete operations.');

            return self::SUCCESS;
        }

        $deleted = $repository->deleteExpiredBefore($now, $limit);

        $logger->info('Expired operations cleanup completed', [
            'deleted_count' => $deleted,
            'limit' => $limit,
            'before' => $now->toIso8601String(),
        ]);

        $this->info(sprintf('Deleted %d expired operation(s).', $deleted));

        if ($deleted >= $limit) {
            $this->warn('Limit reached. Consider running again to delete more operations.');
        }

        return self::SUCCESS;
    }
}
