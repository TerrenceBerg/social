<?php


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('facebook_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->text('page_id');
            $table->text('name');
            $table->text('access_token');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facebook_pages');
    }
};
