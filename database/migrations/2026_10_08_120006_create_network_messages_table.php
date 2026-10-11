<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('network_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_id')->nullable()->constrained();
            $table->string('network_id', 64)->unique();
            $table->string('fact_fingerprint', 64)->nullable()->unique();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->json('response_body')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('network_messages');
    }
};
