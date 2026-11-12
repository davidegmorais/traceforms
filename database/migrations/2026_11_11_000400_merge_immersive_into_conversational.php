<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** "Imersivo" e "Uma por vez" viraram um formato só: "Uma por vez" (tela cheia, uma pergunta por tela). */
    public function up(): void
    {
        DB::table('forms')->where('layout', 'immersive')->update(['layout' => 'conversational']);
    }

    public function down(): void
    {
        //
    }
};
