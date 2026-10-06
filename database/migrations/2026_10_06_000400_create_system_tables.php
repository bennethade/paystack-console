<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Webhook events, system settings / feature flags and the audit log.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('environment', 8);
            $table->string('country_code', 2)->nullable();
            $table->string('event')->nullable()->index();
            $table->string('reference')->nullable()->index();
            $table->longText('payload');
            $table->json('headers')->nullable();
            $table->string('signature', 256)->nullable();
            $table->boolean('signature_valid')->default(false)->index();
            $table->string('verification_note')->nullable();
            $table->string('source_ip', 45)->nullable();
            $table->boolean('ip_allowed')->default(false);
            $table->timestamps();

            $table->index('created_at');
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('group', 32)->default('general')->index();
            $table->json('value')->nullable();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action')->index();
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('meta')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->nullable()->index();

            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('webhook_events');
    }
};
