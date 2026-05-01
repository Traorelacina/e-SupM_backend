<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conseils', function (Blueprint $table) {
            $table->id();

            // Contenu principal
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('excerpt')->nullable();          // résumé court

            // Catégorie : nutrition | astuce | recette
            $table->enum('category', ['nutrition', 'astuce', 'recette'])->index();

            // Type de contenu : text | video | image | mixed
            $table->enum('content_type', ['text', 'video', 'image', 'mixed'])->default('text');

            // Corps (HTML enrichi pour text/mixed)
            $table->longText('body')->nullable();

            // Vidéo
            $table->string('video_url')->nullable();       // YouTube, Vimeo ou upload direct
            $table->string('video_provider')->nullable();  // youtube | vimeo | local
            $table->string('video_duration')->nullable();  // ex: "5:32"

            // Médias
            $table->string('thumbnail')->nullable();       // image de couverture
            $table->json('gallery')->nullable();           // galerie d'images

            // Méta
            $table->string('tags')->nullable();            // JSON ou CSV
            $table->string('reading_time')->nullable();    // ex: "3 min"
            $table->unsignedBigInteger('views')->default(0);
            $table->unsignedBigInteger('likes')->default(0);

            // Recette — champs spécifiques
            $table->json('recipe_ingredients')->nullable(); // [{name, qty, unit}]
            $table->integer('recipe_prep_time')->nullable();  // minutes
            $table->integer('recipe_cook_time')->nullable();  // minutes
            $table->integer('recipe_servings')->nullable();
            $table->enum('recipe_difficulty', ['facile', 'moyen', 'difficile'])->nullable();

            // Statut & publication
            $table->boolean('is_published')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->timestamp('published_at')->nullable();

            // Auteur (admin)
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // Index
            $table->index(['category', 'is_published']);
            $table->index(['is_featured', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conseils');
    }
};