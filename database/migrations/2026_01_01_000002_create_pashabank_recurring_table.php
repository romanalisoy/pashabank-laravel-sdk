<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create($this->table(), function (Blueprint $table): void {
            $table->id();
            $table->string('merchant_key')->index();
            $table->string('biller_client_id')->unique();
            $table->string('expiry', 4);
            $table->string('card_mask')->nullable();
            $table->string('status')->index();
            $table->nullableMorphs('owner');
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->table());
    }

    private function table(): string
    {
        return (string) config('pashabank.persistence.tables.recurring', 'pashabank_recurring');
    }
};
