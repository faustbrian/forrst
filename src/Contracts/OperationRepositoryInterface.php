<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Contracts;

use Cline\Forrst\Data\OperationData;
use Cline\Forrst\Exceptions\ForbiddenException;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * Forrst async operation repository contract interface.
 *
 * Defines the contract for implementing persistent storage for async operations
 * created by the async extension. Repositories manage the complete lifecycle of
 * async operation state from creation through completion or cancellation.
 *
 * Provides CRUD operations for managing async operation state including status
 * tracking, result storage, and progress monitoring. Implementations must ensure
 * thread-safe operations and support concurrent access patterns.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/forrst/extensions/async Async extension specification
 */
interface OperationRepositoryInterface
{
    /**
     * Find an operation by ID with access control.
     *
     * Retrieves a single operation by its unique identifier. Returns null if the
     * operation does not exist, has been deleted, or the user is unauthorized to access it.
     *
     * @param string      $id     Operation ID
     * @param null|string $userId User ID for access control (null = system access)
     *
     * @return null|OperationData The operation or null if not found or unauthorized
     */
    public function find(string $id, ?string $userId = null): ?OperationData;

    /**
     * Save an operation with ownership.
     *
     * Persists an operation to storage. If the operation already exists (based on ID),
     * it should be updated. If it does not exist, it should be created. For new operations,
     * the userId becomes the owner. For existing operations, implementations MUST verify
     * the userId matches the owner before allowing updates.
     *
     * WARNING: This method does NOT provide concurrency protection. For state transitions
     * that may be subject to race conditions, use {@see saveIfVersionMatches()} instead.
     *
     * @param OperationData $operation The operation to save
     * @param null|string   $userId    User ID to associate with operation (owner for new operations)
     *
     * @throws ForbiddenException If userId doesn't match existing operation's owner
     */
    public function save(OperationData $operation, ?string $userId = null): void;

    /**
     * Save an operation only if the lock version matches (compare-and-swap).
     *
     * Provides optimistic locking for safe concurrent updates. The operation is only
     * saved if the current lock_version in storage matches the provided expectedVersion.
     * On success, the lock_version is incremented automatically.
     *
     * Use this method for all state transitions to prevent race conditions where multiple
     * processes attempt to update the same operation simultaneously.
     *
     * @param OperationData $operation       The operation to save with updated state
     * @param int           $expectedVersion The lock_version expected in storage
     * @param null|string   $userId          User ID for access control
     *
     * @throws ForbiddenException If userId doesn't match existing operation's owner
     * @return bool               True if save succeeded (version matched), false if version mismatch
     */
    public function saveIfVersionMatches(
        OperationData $operation,
        int $expectedVersion,
        ?string $userId = null,
    ): bool;

    /**
     * Delete an operation with access control.
     *
     * Removes an operation from storage by its ID. Idempotent - silently succeeds
     * if the operation does not exist. Implementations MUST verify user ownership
     * before deletion and throw ForbiddenException if the authenticated user does
     * not own the operation.
     *
     * @param string      $id     Operation ID
     * @param null|string $userId User ID for access control (null = system access)
     *
     * @throws ForbiddenException If user doesn't own the operation
     */
    public function delete(string $id, ?string $userId = null): void;

    /**
     * Count active (non-terminal) operations for a user.
     *
     * Returns the count of operations in pending or running states for the specified user.
     * Used for enforcing concurrent operation limits per user to prevent resource exhaustion.
     *
     * @param string $userId User ID to count operations for
     *
     * @return int Number of active operations
     */
    public function countActiveByOwner(string $userId): int;

    /**
     * Delete operations that expired before the given timestamp.
     *
     * Used for periodic cleanup of expired operations to prevent unbounded storage
     * growth. Only deletes operations where expires_at (from metadata) is before
     * the specified cutoff timestamp. System-level operation - no access control.
     *
     * RECOMMENDED: Run this as a scheduled job (e.g., hourly via cron).
     *
     * @param DateTimeInterface $before Delete operations with expires_at before this time
     * @param int               $limit  Maximum number of operations to delete per call (default: 1000)
     *
     * @return int Number of operations deleted
     */
    public function deleteExpiredBefore(DateTimeInterface $before, int $limit = 1_000): int;

    /**
     * List operations with optional filters and access control.
     *
     * Retrieves a paginated list of operations matching the specified criteria.
     * Supports filtering by status and function name, with cursor-based pagination
     * for efficient traversal of large result sets.
     *
     * DATABASE INDEXES REQUIRED:
     * - (user_id, status, created_at) for user + status filtering
     * - (user_id, function, created_at) for user + function filtering
     * - (user_id, status, function, created_at) for combined filtering
     * - (created_at) for unfiltered queries
     *
     * @param null|string $status   Filter by status (pending, running, completed, failed, cancelled)
     * @param null|string $function Filter by function name
     * @param int         $limit    Maximum number of results to return (default: 50, max: 100)
     * @param null|string $cursor   Pagination cursor for fetching subsequent pages. Base64-encoded JSON
     *                              with format: {"last_id": "uuid", "last_created_at": "2024-01-01T00:00:00Z"}
     * @param null|string $userId   Filter by user ID (null = all operations, requires admin permissions)
     *
     * @throws InvalidArgumentException                                           If limit is less than 1 or greater than 100
     * @return array{operations: array<int, OperationData>, next_cursor: ?string} Operations and next page cursor
     */
    public function list(
        ?string $status = null,
        ?string $function = null,
        int $limit = 50,
        ?string $cursor = null,
        ?string $userId = null,
    ): array;
}
