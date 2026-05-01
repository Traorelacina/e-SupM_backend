<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConseilRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        $conseilId = $this->route('conseil')?->id;

        return [
            // Base
            'title'        => ['required', 'string', 'max:255'],
            'slug'         => [
                'nullable', 'string', 'max:255',
                Rule::unique('conseils', 'slug')->ignore($conseilId),
            ],
            'excerpt'      => ['nullable', 'string', 'max:500'],
            'category'     => ['required', Rule::in(['nutrition', 'astuce', 'recette'])],
            'content_type' => ['required', Rule::in(['text', 'video', 'image', 'mixed'])],

            // Corps
            'body'         => ['nullable', 'string'],

            // Vidéo
            'video_url'      => ['nullable', 'url'],
            'video_provider' => ['nullable', Rule::in(['youtube', 'vimeo', 'local'])],
            'video_duration' => ['nullable', 'string', 'max:20'],

            // Médias
            'thumbnail'    => ['nullable'],   // chemin ou upload (géré séparément)
            'thumbnail_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'gallery'      => ['nullable', 'array'],
            'gallery.*'    => ['nullable', 'string'],

            // Méta
            'tags'         => ['nullable', 'string', 'max:500'],
            'reading_time' => ['nullable', 'string', 'max:20'],

            // Recette
            'recipe_ingredients'  => [
                Rule::requiredIf(fn () => $this->category === 'recette'),
                'nullable', 'array',
            ],
            'recipe_ingredients.*.name' => ['required_with:recipe_ingredients', 'string'],
            'recipe_ingredients.*.qty'  => ['required_with:recipe_ingredients', 'string'],
            'recipe_ingredients.*.unit' => ['nullable', 'string'],
            'recipe_prep_time'    => ['nullable', 'integer', 'min:0'],
            'recipe_cook_time'    => ['nullable', 'integer', 'min:0'],
            'recipe_servings'     => ['nullable', 'integer', 'min:1'],
            'recipe_difficulty'   => ['nullable', Rule::in(['facile', 'moyen', 'difficile'])],

            // Publication
            'is_published' => ['boolean'],
            'is_featured'  => ['boolean'],
            'published_at' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required'    => 'Le titre est obligatoire.',
            'category.required' => 'La catégorie est obligatoire.',
            'category.in'       => 'Catégorie invalide.',
            'content_type.required' => 'Le type de contenu est obligatoire.',
            'video_url.url'     => "L'URL de la vidéo n'est pas valide.",
            'thumbnail_file.image' => 'La miniature doit être une image.',
            'thumbnail_file.max'   => 'La miniature ne doit pas dépasser 5 Mo.',
        ];
    }
}