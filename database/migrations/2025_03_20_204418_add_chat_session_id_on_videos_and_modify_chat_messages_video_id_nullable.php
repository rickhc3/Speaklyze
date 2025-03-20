<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->uuid('chat_session_id')->nullable()->after('id');

            // 🔥 Adiciona um índice UNIQUE para permitir a referência na chave estrangeira
            $table->unique('chat_session_id');
        });

        Schema::table('chat_messages', function (Blueprint $table) {
            $table->uuid('chat_session_id')->nullable()->after('id');

            // 🔥 Agora podemos criar a chave estrangeira corretamente
            $table->foreign('chat_session_id')->references('chat_session_id')->on('videos')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropForeign(['chat_session_id']);
            $table->dropColumn('chat_session_id');
        });

        Schema::table('videos', function (Blueprint $table) {
            $table->dropUnique(['chat_session_id']); // Remove o índice UNIQUE
            $table->dropColumn('chat_session_id');
        });
    }
};

