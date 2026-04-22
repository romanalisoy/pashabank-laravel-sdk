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
            $table->string('transaction_id')->unique();
            $table->string('command', 4);
            $table->string('status')->index();
            $table->bigInteger('amount_minor')->default(0);
            $table->string('currency', 3);
            $table->string('result_code', 4)->nullable();
            $table->string('approval_code')->nullable();
            $table->string('rrn')->nullable();
            $table->string('card_mask')->nullable();
            $table->string('three_ds_status')->nullable();
            $table->string('client_ip', 45)->nullable();
            $table->string('description', 125)->nullable();
            $table->nullableMorphs('payable');
            $table->unsignedBigInteger('recurring_id')->nullable()->index();
            $table->string('parent_transaction_id')->nullable()->index();
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
        return (string) config('pashabank.persistence.tables.transactions', 'pashabank_transactions');
    }
};
