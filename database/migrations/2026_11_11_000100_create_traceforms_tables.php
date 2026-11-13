<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ---------------------------------------------------------------
        // Contas (workspaces) e membros
        // ---------------------------------------------------------------
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->foreignId('owner_id')->constrained('users');
            $table->string('timezone')->default('America/Sao_Paulo');
            $table->string('brand_color', 9)->default('#0e7c66');
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('account_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20); // owner|admin|editor|analyst|viewer
            $table->json('form_ids')->nullable(); // null = todos; array = restrito a esses formulários
            $table->timestamps();
            $table->unique(['account_id', 'user_id']);
        });

        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('email');
            $table->string('role', 20);
            $table->string('token', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
        });

        // ---------------------------------------------------------------
        // Formulários
        // ---------------------------------------------------------------
        Schema::create('forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('public_id', 16)->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status', 16)->default('draft'); // draft|published|closed
            $table->string('layout', 16)->default('classic'); // classic|conversational
            $table->json('theme')->nullable();
            $table->json('settings')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['account_id', 'status']);
        });

        Schema::create('form_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained()->cascadeOnDelete();
            $table->string('key', 40);
            $table->string('type', 30);
            $table->string('label', 500);
            $table->text('description')->nullable();
            $table->string('placeholder')->nullable();
            $table->boolean('required')->default(false);
            $table->json('options')->nullable();
            $table->json('settings')->nullable(); // min, max, conditions, etc.
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->unique(['form_id', 'key']);
        });

        // Links rastreados (gerador de UTM + encurtador)
        Schema::create('tracking_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('code', 12)->unique();
            $table->string('name');
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->string('utm_term')->nullable();
            $table->string('utm_content')->nullable();
            $table->json('extra_params')->nullable();
            $table->unsignedBigInteger('clicks')->default(0);
            $table->timestamps();
        });

        // ---------------------------------------------------------------
        // Sessões de visita (cada carregamento do formulário)
        // ---------------------------------------------------------------
        Schema::create('form_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('form_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tracking_link_id')->nullable()->constrained()->nullOnDelete();
            $table->string('visitor_id', 64)->index();
            $table->boolean('is_returning')->default(false);
            $table->unsignedInteger('visit_number')->default(1);
            $table->string('status', 12)->default('viewed'); // viewed|started|submitted

            // Rede / localização
            $table->string('ip', 64)->nullable();
            $table->string('country', 2)->nullable();
            $table->string('region')->nullable();
            $table->string('city')->nullable();

            // Dispositivo
            $table->text('user_agent')->nullable();
            $table->string('device_type', 20)->nullable();
            $table->string('device_brand', 60)->nullable();
            $table->string('device_model', 80)->nullable();
            $table->string('os_name', 40)->nullable();
            $table->string('os_version', 30)->nullable();
            $table->string('browser_name', 60)->nullable();
            $table->string('browser_version', 30)->nullable();
            $table->string('browser_engine', 30)->nullable();
            $table->boolean('is_bot')->default(false);
            $table->unsignedSmallInteger('screen_width')->nullable();
            $table->unsignedSmallInteger('screen_height')->nullable();
            $table->unsignedSmallInteger('viewport_width')->nullable();
            $table->unsignedSmallInteger('viewport_height')->nullable();
            $table->decimal('pixel_ratio', 4, 2)->nullable();
            $table->boolean('touch')->nullable();
            $table->string('language', 20)->nullable();
            $table->string('languages')->nullable();
            $table->string('timezone', 60)->nullable();
            $table->smallInteger('timezone_offset')->nullable();
            $table->string('connection_type', 20)->nullable();
            $table->decimal('connection_downlink', 6, 2)->nullable();
            $table->boolean('dark_mode')->nullable();
            $table->boolean('cookies_enabled')->nullable();
            $table->boolean('do_not_track')->nullable();
            $table->unsignedSmallInteger('hardware_concurrency')->nullable();
            $table->decimal('device_memory', 5, 2)->nullable();

            // Origem / atribuição
            $table->text('referrer')->nullable();
            $table->string('referrer_domain')->nullable();
            $table->string('channel', 30)->nullable()->index();
            $table->text('landing_url')->nullable();
            $table->text('parent_url')->nullable(); // página que incorporou o form (embed)
            $table->string('embed_mode', 12)->default('direct'); // direct|iframe|link
            $table->string('utm_source')->nullable()->index();
            $table->string('utm_medium')->nullable()->index();
            $table->string('utm_campaign')->nullable()->index();
            $table->string('utm_term')->nullable();
            $table->string('utm_content')->nullable();
            $table->string('utm_id')->nullable();
            $table->string('click_id_type', 20)->nullable();
            $table->string('click_id')->nullable();
            $table->json('first_touch')->nullable(); // atribuição de primeiro toque
            $table->json('query_params')->nullable();
            $table->json('hidden_fields')->nullable();

            // Engajamento
            $table->unsignedInteger('time_to_start_ms')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->unsignedInteger('active_ms')->nullable();
            $table->unsignedSmallInteger('max_step')->default(0);
            $table->string('last_field_key', 40)->nullable();
            $table->json('field_stats')->nullable(); // {key: {ms, focus, changes, errors}}
            $table->json('partial_data')->nullable();
            $table->unsignedSmallInteger('tab_switches')->default(0);
            $table->unsignedSmallInteger('paste_count')->default(0);

            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();

            $table->index(['form_id', 'created_at']);
            $table->index(['form_id', 'status']);
        });

        // ---------------------------------------------------------------
        // Respostas
        // ---------------------------------------------------------------
        Schema::create('submissions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('form_id')->constrained()->cascadeOnDelete();
            $table->foreignId('form_session_id')->nullable()->constrained()->nullOnDelete();
            $table->json('data');
            $table->boolean('is_spam')->default(false);
            $table->string('spam_reason')->nullable();
            $table->boolean('starred')->default(false);
            $table->text('notes')->nullable();
            $table->timestamp('submitted_at')->index();
            $table->timestamps();
            $table->index(['form_id', 'submitted_at']);
        });

        // ---------------------------------------------------------------
        // Auditoria
        // ---------------------------------------------------------------
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 60);
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('description')->nullable();
            $table->json('meta')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        foreach (['audit_logs', 'submissions', 'form_sessions', 'tracking_links', 'form_fields', 'forms', 'invitations', 'account_user', 'accounts'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
