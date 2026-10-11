<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->foreignId('purchase_id')->nullable()->constrained();
            $table->foreignId('purchase_event_id')->nullable()->constrained();
            $table->timestampTz('occurred_at');
            $table->string('type');
            $table->bigInteger('amount_cents');
            $table->string('reference', 64)->nullable();
            $table->timestamps();

            $table->index(['company_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_entries');
    }
};
