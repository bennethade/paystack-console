<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The endpoint registry.
 *
 * Parameter definitions are stored as a JSON document per endpoint rather
 * than in a separate table: Paystack parameters nest (objects inside arrays
 * inside objects) and are always read and written as a whole tree, so a
 * relational parameters table would add joins without enabling any query
 * the application actually needs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paystack_endpoint_categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('docs_url')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('paystack_endpoints', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique()->comment('Stable identifier, e.g. transaction.initialize');
            $table->string('operation_id')->nullable()->index();
            $table->foreignId('category_id')->constrained('paystack_endpoint_categories')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('method', 10);
            $table->string('path');
            $table->string('auth', 16)->default('secret')->comment('secret | public | none');
            $table->string('body_type', 16)->default('json')->comment('json | multipart | none');
            $table->json('parameters')->nullable();
            $table->json('example')->nullable();
            $table->string('workflow', 32)->nullable()->comment('Special UI: payment_redirect, bank_table, transaction_result');
            $table->string('pagination', 16)->nullable()->comment('page | cursor | both');
            $table->json('required_features')->nullable()->comment('Country features required, see config/paystack.php');
            $table->json('supported_currencies')->nullable()->comment('Null = no restriction');
            $table->boolean('is_dangerous')->default(false)->comment('Moves money or changes state irreversibly');
            $table->boolean('is_documented')->default(true)->comment('Present on the public API reference pages');
            $table->text('notes')->nullable();
            $table->string('docs_url')->nullable();
            $table->string('source', 16)->default('openapi')->comment('openapi | manual');
            $table->boolean('is_locally_modified')->default(false)->comment('Admin edits are preserved on re-import');
            $table->boolean('is_enabled')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['method', 'path']);
            $table->index(['category_id', 'is_enabled', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paystack_endpoints');
        Schema::dropIfExists('paystack_endpoint_categories');
    }
};
