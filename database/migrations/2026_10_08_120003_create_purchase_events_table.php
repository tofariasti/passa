<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_id')->nullable()->constrained();
            $table->string('network_id', 64)->unique();
            $table->string('authorization_network_id', 64)->index();
            $table->string('type');
            $table->timestampTz('occurred_at');
            $table->unsignedBigInteger('amount_cents')->nullable();
            $table->string('currency', 3)->nullable();
            $table->unsignedInteger('sequence')->nullable();
            $table->boolean('is_final')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_events');
    }
};
