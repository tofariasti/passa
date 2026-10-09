<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('card_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('card_id')->constrained();
            $table->foreignId('purchase_id')->constrained();
            $table->timestampTz('occurred_at');
            $table->string('type');
            $table->bigInteger('amount_cents');
            $table->string('reference', 64)->unique();
            $table->timestamps();

            $table->index(['card_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_transactions');
    }
};
