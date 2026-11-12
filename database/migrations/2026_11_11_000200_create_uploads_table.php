<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Arquivos enviados em campos "Envio de arquivo" e "Assinatura".
        // São gravados antes do envio da resposta e vinculados a ela no submit.
        Schema::create('uploads', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('form_id')->constrained()->cascadeOnDelete();
            $table->foreignId('form_session_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('submission_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('field_key', 40);
            $table->string('original_name');
            $table->string('mime', 120)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('disk', 20)->default('local');
            $table->string('path');
            $table->timestamps();
            $table->index(['form_id', 'submission_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('uploads');
    }
};
