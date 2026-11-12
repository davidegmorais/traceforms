<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Com o login pelo Trace ID, o TraceForms deixa de ter administração da plataforma,
 * cadastro, convites e senhas próprias. Remove o que sobrou disso no banco:
 *  - tabelas de convites e de redefinição de senha;
 *  - marcação de administrador da plataforma e suspensão de usuário;
 *  - senhas antigas (apaga os hashes; a coluna fica vazia e sem uso).
 * Suspensão de EMPRESA continua: é usada quando a licença fica inativa no Trace ID.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('invitations');
        Schema::dropIfExists('password_reset_tokens');

        foreach (['is_super_admin', 'suspended_at'] as $column) {
            if (Schema::hasColumn('users', $column)) {
                Schema::table('users', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }

        DB::table('users')->update(['password' => null, 'remember_token' => null]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_super_admin')->default(false);
            $table->timestamp('suspended_at')->nullable();
        });
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
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
    }
};
