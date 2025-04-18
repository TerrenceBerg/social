<?php


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('social_auth_tokens', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('provider', 50); // e.g., 'twitter', 'facebook', 'google'

            $table->string('state')->nullable(); // PKCE flow
            $table->text('verifier')->nullable(); // Encrypted PKCE code_verifier
            $table->text('access_token')->nullable(); // Encrypted
            $table->text('refresh_token')->nullable(); // Encrypted

            $table->timestamp('expires_at')->nullable();

            $table->json('extra_data')->nullable(); // If you want to store scopes, token_type, etc.

            $table->timestamps();

            $table->index(['provider', 'state']);
            $table->index(['user_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_auth_tokens');
    }
};
