<?php


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('facebook_entities', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->text('entity_id');
            $table->enum('entity_type', ['page', 'group']);
            $table->text('name');
            $table->text('access_token');
            $table->timestamps();

            $table->unique(['user_id', 'entity_id', 'entity_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facebook_entities');
    }
};
