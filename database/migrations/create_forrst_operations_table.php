<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the forrst_operations table for async operation persistence.
 *
 * This table stores the state of long-running async operations,
 * allowing clients to check status days or weeks later.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('forrst_operations', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('function');
            $table->string('version')->nullable();
            $table->string('status')->default('pending')->index();
            $table->decimal('progress', 5, 4)->nullable();
            $table->jsonb('result')->nullable();
            $table->jsonb('errors')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->string('caller_id')->nullable()->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();

            $table->index(['caller_id', 'status']);
            $table->index(['function', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('forrst_operations');
    }
};
