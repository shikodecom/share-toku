<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_system_admin')->default(false);
        });
        Schema::create('workspaces', function (Blueprint $t) {
            $t->id();
            $t->ulid('public_id')->unique();
            $t->string('name');
            $t->string('type')->default('personal');
            $t->foreignId('owner_user_id')->constrained('users');
            $t->timestamps();
            $t->softDeletes();
        });
        Schema::create('workspace_members', function (Blueprint $t) {
            $t->id();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('role');
            $t->timestamps();
            $t->unique(['workspace_id', 'user_id']);
        });
        Schema::create('workspace_subscriptions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->string('plan_code');
            $t->string('status');
            $t->timestamp('starts_at');
            $t->timestamp('ends_at')->nullable();
            $t->string('billing_provider')->nullable();
            $t->string('provider_subscription_id')->nullable();
            $t->json('metadata_json')->nullable();
            $t->timestamps();
            $t->index(['workspace_id', 'status', 'starts_at', 'ends_at']);
        });
        Schema::create('categories', function (Blueprint $t) {
            $t->id();
            $t->ulid('public_id')->unique();
            $t->string('name');
            $t->string('slug')->unique();
            $t->text('description')->nullable();
            $t->boolean('is_active')->default(true);
            $t->integer('sort_order')->default(0);
            $t->timestamps();
        });
        Schema::create('services', function (Blueprint $t) {
            $t->id();
            $t->ulid('public_id')->unique();
            $t->string('name');
            $t->string('slug')->unique();
            $t->text('description')->nullable();
            $t->string('official_url', 2048)->nullable();
            $t->string('logo_url', 2048)->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->softDeletes();
        });
        Schema::create('category_service', function (Blueprint $t) {
            $t->foreignId('category_id')->constrained()->cascadeOnDelete();
            $t->foreignId('service_id')->constrained()->cascadeOnDelete();
            $t->primary(['category_id', 'service_id']);
        });
        Schema::create('referral_programs', function (Blueprint $t) {
            $t->id();
            $t->ulid('public_id')->unique();
            $t->foreignId('service_id')->constrained();
            $t->string('name');
            $t->text('description')->nullable();
            $t->text('invitee_benefit_text')->nullable();
            $t->text('inviter_benefit_text')->nullable();
            $t->text('conditions_text')->nullable();
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('ends_at')->nullable();
            $t->string('official_terms_url', 2048)->nullable();
            $t->string('public_listing_policy')->default('needs_review');
            $t->string('external_distribution_policy')->default('needs_review');
            $t->text('policy_notes')->nullable();
            $t->timestamp('policy_checked_at')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->softDeletes();
        });
        Schema::create('referral_offers', function (Blueprint $t) {
            $t->id();
            $t->ulid('public_id')->unique();
            $t->foreignId('workspace_id')->constrained();
            $t->foreignId('referral_program_id')->constrained();
            $t->string('title')->nullable();
            $t->string('referral_code')->nullable();
            $t->string('referral_url', 2048)->nullable();
            $t->text('invitee_benefit_override')->nullable();
            $t->text('inviter_benefit_override')->nullable();
            $t->text('conditions_override')->nullable();
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('ends_at')->nullable();
            $t->timestamp('last_verified_at')->nullable();
            $t->string('status')->default('draft');
            $t->foreignId('created_by_user_id')->constrained('users');
            $t->foreignId('updated_by_user_id')->nullable()->constrained('users');
            $t->timestamps();
            $t->softDeletes();
            $t->index(['workspace_id', 'status']);
            $t->index(['referral_program_id', 'status', 'ends_at']);
        });
        Schema::create('review_requests', function (Blueprint $t) {
            $t->id();
            $t->ulid('public_id')->unique();
            $t->foreignId('referral_offer_id')->constrained();
            $t->foreignId('submitted_by_user_id')->constrained('users');
            $t->timestamp('submitted_at');
            $t->string('status');
            $t->foreignId('reviewed_by_user_id')->nullable()->constrained('users');
            $t->timestamp('reviewed_at')->nullable();
            $t->text('decision_reason')->nullable();
            $t->json('snapshot_json');
            $t->timestamps();
        });
        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('actor_user_id')->nullable()->constrained('users');
            $t->foreignId('workspace_id')->nullable()->constrained();
            $t->string('auditable_type');
            $t->unsignedBigInteger('auditable_id');
            $t->string('action');
            $t->json('before_json')->nullable();
            $t->json('after_json')->nullable();
            $t->json('metadata_json')->nullable();
            $t->timestamp('created_at');
            $t->index(['auditable_type', 'auditable_id']);
        });
        Schema::create('sites', function (Blueprint $t) {
            $t->id();
            $t->ulid('public_id')->unique();
            $t->foreignId('workspace_id')->constrained();
            $t->string('name');
            $t->string('domain')->index();
            $t->string('status')->default('pending');
            $t->timestamp('verified_at')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });
        Schema::create('site_consents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('site_id')->constrained();
            $t->foreignId('user_id')->constrained();
            $t->string('terms_version');
            $t->timestamp('consented_at');
            $t->timestamp('withdrawn_at')->nullable();
            $t->timestamps();
        });
        Schema::create('site_authorization_codes', function (Blueprint $t) {
            $t->id();
            $t->ulid('public_id')->unique();
            $t->foreignId('site_id')->constrained();
            $t->foreignId('user_id')->constrained();
            $t->string('code_hash', 64)->unique();
            $t->string('code_challenge', 64);
            $t->string('redirect_uri', 2048);
            $t->string('state_hash', 64)->nullable();
            $t->timestamp('expires_at');
            $t->timestamp('consumed_at')->nullable();
            $t->timestamp('created_at');
        });
        Schema::create('site_access_tokens', function (Blueprint $t) {
            $t->id();
            $t->ulid('public_id')->unique();
            $t->foreignId('site_id')->constrained();
            $t->string('token_hash', 64)->unique();
            $t->json('scopes_json');
            $t->timestamp('last_used_at')->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->timestamp('revoked_at')->nullable();
            $t->foreignId('created_by_user_id')->constrained('users');
            $t->timestamp('created_at');
        });
        Schema::create('category_relations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('source_category_id')->constrained('categories');
            $t->foreignId('target_category_id')->constrained('categories');
            $t->unsignedTinyInteger('relevance_score');
            $t->boolean('is_bidirectional')->default(false);
            $t->boolean('is_active')->default(true);
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->unique(['source_category_id', 'target_category_id']);
        });
        Schema::create('site_category_exclusions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('site_id')->constrained();
            $t->foreignId('category_id')->constrained();
            $t->timestamps();
            $t->unique(['site_id', 'category_id']);
        });
        Schema::create('placements', function (Blueprint $t) {
            $t->id();
            $t->ulid('public_id')->unique();
            $t->foreignId('site_id')->constrained();
            $t->foreignId('owner_referral_offer_id')->constrained('referral_offers');
            $t->string('placement_key');
            $t->timestamp('last_resolved_at')->nullable();
            $t->timestamps();
            $t->unique(['site_id', 'owner_referral_offer_id', 'placement_key'], 'placements_identity_unique');
        });
        Schema::create('analytics_events', function (Blueprint $t) {
            $t->id();
            $t->ulid('event_id')->unique();
            $t->timestamp('occurred_at');
            $t->timestamp('received_at');
            $t->string('event_type');
            $t->foreignId('site_id')->constrained();
            $t->foreignId('placement_id')->constrained();
            $t->foreignId('referral_offer_id')->constrained();
            $t->string('slot');
            $t->string('content_key')->nullable();
            $t->string('page_path', 512)->nullable();
            $t->json('metadata_json')->nullable();
            $t->timestamp('created_at');
            $t->index(['occurred_at', 'site_id', 'slot']);
        });
        Schema::create('daily_metrics', function (Blueprint $t) {
            $t->id();
            $t->date('metric_date');
            $t->foreignId('site_id')->constrained();
            $t->foreignId('placement_id')->constrained();
            $t->foreignId('referral_offer_id')->constrained();
            $t->string('slot');
            $t->unsignedBigInteger('impressions')->default(0);
            $t->unsignedBigInteger('clicks')->default(0);
            $t->timestamps();
            $t->unique(['metric_date', 'site_id', 'placement_id', 'referral_offer_id', 'slot'], 'daily_metrics_identity_unique');
        });
    }

    public function down(): void
    {
        foreach (['daily_metrics', 'analytics_events', 'placements', 'site_category_exclusions', 'category_relations', 'site_access_tokens', 'site_authorization_codes', 'site_consents', 'sites', 'audit_logs', 'review_requests', 'referral_offers', 'referral_programs', 'category_service', 'services', 'categories', 'workspace_subscriptions', 'workspace_members', 'workspaces'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('is_system_admin'));
    }
};
