<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user data: favourites, preferences, saved request configurations and
 * the request history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('endpoint_id')->constrained('paystack_endpoints')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'endpoint_id']);
        });

        Schema::create('user_api_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('country_code', 2)->nullable();
            $table->string('currency_code', 3)->nullable();
            $table->string('environment', 8)->default('test');
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('saved_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('endpoint_id')->constrained('paystack_endpoints')->cascadeOnDelete();
            $table->string('name');
            $table->string('country_code', 2)->nullable();
            $table->string('currency_code', 3)->nullable();
            $table->json('params')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'endpoint_id']);
        });

        Schema::create('api_request_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('endpoint_id')->nullable()->constrained('paystack_endpoints')->nullOnDelete();
            $table->string('endpoint_key')->index();
            $table->string('environment', 8);
            $table->string('country_code', 2)->nullable();
            $table->string('currency_code', 3)->nullable();
            $table->string('method', 10);
            $table->string('path');
            $table->text('url');
            $table->json('params')->nullable()->comment('Masked form input, used for replay');
            $table->json('request_query')->nullable();
            $table->json('request_body')->nullable();
            $table->json('request_headers')->nullable()->comment('Masked; never contains the bearer token');
            $table->unsignedSmallInteger('status_code')->nullable()->index();
            $table->boolean('success')->default(false);
            $table->unsignedInteger('duration_ms')->nullable();
            $table->json('response_headers')->nullable();
            $table->longText('response_body')->nullable();
            $table->boolean('response_truncated')->default(false);
            $table->string('error_message')->nullable();
            $table->boolean('is_mock')->default(false);
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_request_logs');
        Schema::dropIfExists('saved_requests');
        Schema::dropIfExists('user_api_preferences');
        Schema::dropIfExists('user_favorites');
    }
};
