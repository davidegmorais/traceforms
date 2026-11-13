<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar_path')->nullable()->after('password');
            $table->boolean('is_super_admin')->default(false)->after('avatar_path');
            $table->timestamp('suspended_at')->nullable()->after('is_super_admin');
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->timestamp('suspended_at')->nullable()->after('settings');
            $table->string('suspension_reason')->nullable()->after('suspended_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['avatar_path', 'is_super_admin', 'suspended_at']));
        Schema::table('accounts', fn (Blueprint $t) => $t->dropColumn(['suspended_at', 'suspension_reason']));
    }
};
