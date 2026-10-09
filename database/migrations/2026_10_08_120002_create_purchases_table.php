<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('card_id')->nullable()->constrained();
            $table->string('network_authorization_id', 64)->unique();
            $table->unsignedBigInteger('amount_cents');
            $table->string('currency', 3);
            $table->string('mcc', 4);
            $table->string('merchant_name');
            $table->string('merchant_city');
            $table->string('merchant_country', 2);
            $table->timestampTz('occurred_at');
            $table->string('decision');
            $table->string('reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};
