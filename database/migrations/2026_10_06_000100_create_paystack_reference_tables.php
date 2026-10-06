<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Countries, currencies and the country↔currency pivot.
 *
 * Credentials are intentionally NOT stored here. They live only in
 * config/paystack.php (populated from the environment).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paystack_currencies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 3)->unique();
            $table->string('name');
            $table->string('symbol', 8);
            $table->string('subunit_name', 32)->nullable();
            $table->unsignedInteger('subunit_factor')->default(100);
            $table->unsignedBigInteger('minimum_amount')->default(0)->comment('In the subunit');
            $table->boolean('is_enabled')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('paystack_countries', function (Blueprint $table) {
            $table->id();
            $table->string('code', 2)->unique();
            $table->string('name');
            $table->string('flag', 16)->nullable();
            $table->string('bank_country')->nullable()->comment('Value accepted by GET /bank?country=');
            $table->foreignId('default_currency_id')->nullable()->constrained('paystack_currencies')->nullOnDelete();
            $table->json('channels')->nullable();
            $table->json('features')->nullable();
            $table->json('recipient_types')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_enabled')->default(true)->index();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('paystack_country_currency', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->constrained('paystack_countries')->cascadeOnDelete();
            $table->foreignId('currency_id')->constrained('paystack_currencies')->cascadeOnDelete();
            $table->unique(['country_id', 'currency_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paystack_country_currency');
        Schema::dropIfExists('paystack_countries');
        Schema::dropIfExists('paystack_currencies');
    }
};
