<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Models;

use Carbon\CarbonImmutable;
use Cline\Forrst\Data\ErrorData;
use Cline\Forrst\Data\OperationData;
use Cline\Forrst\Data\OperationStatus;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Override;

use function array_map;
use function now;

/**
 * Eloquent model representing async RPC operations.
 *
 * Tracks the lifecycle of asynchronous function executions including status,
 * progress, results, and errors. Supports operation expiration, caller tracking,
 * and conversion to/from OperationData transfer objects. Used by the async
 * extension to manage long-running operations.
 *
 * @property null|string                           $caller_id    Optional identifier for the calling entity
 * @property null|CarbonImmutable                  $cancelled_at When execution was cancelled
 * @property null|CarbonImmutable                  $completed_at When execution completed
 * @property CarbonImmutable                       $created_at   When the operation was created
 * @property null|array<int, array<string, mixed>> $errors       Error details when failed
 * @property null|CarbonImmutable                  $expires_at   When the operation expires and can be cleaned up
 * @property string                                $function     Function name that was invoked
 * @property string                                $id           Unique operation identifier (ULID)
 * @property null|array<string, mixed>             $metadata     Optional metadata for tracking
 * @property null|float                            $progress     Optional progress indicator (0.0-1.0)
 * @property null|array<string, mixed>             $result       Function result data when succeeded
 * @property null|CarbonImmutable                  $started_at   When execution started
 * @property string                                $status       Current status (pending, running, succeeded, failed, cancelled)
 * @property CarbonImmutable                       $updated_at   When the operation was last updated
 * @property null|string                           $version      Optional function version
 *
 * @author Brian Faust <brian@cline.sh>
 * @see https://docs.cline.sh/forrst/extensions/async
 *
 * @phpstan-use HasFactory<Factory<self>>
 */
final class Operation extends Model
{
    /** @use HasFactory<Factory<self>> */
    use HasFactory;

    /**
     * Indicates if the model uses auto-incrementing IDs.
     *
     * Set to false because operations use ULID strings as primary keys.
     *
     * @var bool
     */
    #[Override()]
    public $incrementing = false;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    #[Override()]
    protected $table = 'forrst_operations';

    /**
     * The primary key type.
     *
     * @var string
     */
    #[Override()]
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    #[Override()]
    protected $fillable = [
        'id',
        'function',
        'version',
        'status',
        'progress',
        'result',
        'errors',
        'metadata',
        'caller_id',
        'started_at',
        'completed_at',
        'cancelled_at',
        'expires_at',
    ];

    /**
     * Create a model instance from an OperationData transfer object.
     *
     * Converts the typed Data object into an Eloquent model for database persistence.
     * Transforms ErrorData instances to arrays and enums to string values.
     *
     * @param OperationData $data The operation data transfer object
     *
     * @return self The new model instance
     */
    public static function fromOperationData(OperationData $data): self
    {
        $errors = null;

        if ($data->errors !== null) {
            /** @var array<int, array<string, mixed>> $errors */
            $errors = array_map(
                fn (ErrorData $err): array => $err->toArray(),
                $data->errors,
            );
        }

        return new self([
            'id' => $data->id,
            'function' => $data->function,
            'version' => $data->version,
            'status' => $data->status->value,
            'progress' => $data->progress,
            'result' => $data->result,
            'errors' => $errors,
            'metadata' => $data->metadata,
            'started_at' => $data->startedAt,
            'completed_at' => $data->completedAt,
            'cancelled_at' => $data->cancelledAt,
        ]);
    }

    /**
     * Convert the model to an OperationData transfer object.
     *
     * Transforms the Eloquent model into a typed Data object for use in responses.
     * Converts error arrays to ErrorData instances and status strings to enums.
     *
     * @return OperationData The operation data transfer object
     */
    public function toOperationData(): OperationData
    {
        $errors = null;

        if ($this->errors !== null) {
            $errors = array_map(
                ErrorData::create(...),
                $this->errors,
            );
        }

        return new OperationData(
            id: $this->id,
            function: $this->function,
            version: $this->version,
            status: OperationStatus::from($this->status),
            progress: $this->progress,
            result: $this->result,
            errors: $errors,
            startedAt: $this->started_at,
            completedAt: $this->completed_at,
            cancelledAt: $this->cancelled_at,
            metadata: $this->metadata,
        );
    }

    /**
     * Scope to filter operations by status.
     *
     * @param Builder<self> $query  The query builder instance
     * @param string        $status The status to filter by (e.g., 'pending', 'running', 'succeeded')
     *
     * @return Builder<self> The filtered query builder
     */
    #[Scope()]
    protected function withStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to filter operations by function name.
     *
     * @param Builder<self> $query    The query builder instance
     * @param string        $function The function name to filter by
     *
     * @return Builder<self> The filtered query builder
     */
    #[Scope()]
    protected function forFunction(Builder $query, string $function): Builder
    {
        return $query->where('function', $function);
    }

    /**
     * Scope to filter operations by caller ID.
     *
     * @param Builder<self> $query    The query builder instance
     * @param string        $callerId The caller identifier to filter by
     *
     * @return Builder<self> The filtered query builder
     */
    #[Scope()]
    protected function forCaller(Builder $query, string $callerId): Builder
    {
        return $query->where('caller_id', $callerId);
    }

    /**
     * Scope to retrieve expired operations.
     *
     * Returns operations with an expires_at timestamp in the past, useful for
     * cleanup jobs that need to remove stale operations.
     *
     * @param Builder<self> $query The query builder instance
     *
     * @return Builder<self> The filtered query builder
     */
    #[Scope()]
    protected function expired(Builder $query): Builder
    {
        $query->whereNotNull('expires_at')
            ->where('expires_at', '<', now());

        return $query;
    }

    /**
     * Get the attributes that should be cast to native types.
     *
     * @return array<string, string> Attribute casting configuration
     */
    #[Override()]
    protected function casts(): array
    {
        return [
            'progress' => 'float',
            'result' => 'array',
            'errors' => 'array',
            'metadata' => 'array',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
        ];
    }
}
