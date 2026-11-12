<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Login pelo Trace ID.
 * Só acrescenta colunas: nenhum dado existente é apagado. A senha passa a ser opcional,
 * porque quem entra pelo Trace ID não tem senha no TraceForms.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('trace_id')->nullable()->unique()->after('id');
            $table->string('password')->nullable()->change();
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->uuid('trace_account_id')->nullable()->unique()->after('id');
            $table->json('trace_features')->nullable()->after('settings');
            $table->timestamp('trace_synced_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropUnique(['trace_account_id']);
            $table->dropColumn(['trace_account_id', 'trace_features', 'trace_synced_at']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['trace_id']);
            $table->dropColumn('trace_id');
        });
    }
};
