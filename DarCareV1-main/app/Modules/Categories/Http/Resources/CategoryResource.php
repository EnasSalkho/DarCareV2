<?php
// app/Modules/Categories/Http/Resources/CategoryResource.php

namespace App\Modules\Categories\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'slug'        => $this->slug,
            // The categories table stores an emoji in `icon`; there is no
            // `image` column. `image` used to read that missing attribute and
            // therefore always fell back to a placeholder file that does not
            // exist, so every client rendered a broken image.
            'icon'        => $this->icon,
            'image'       => $this->imageUrl(),
            'description' => $this->description,
        ];
    }

    /**
     * Only emit a URL when a real uploaded image exists, so clients can tell
     * "no image, use the icon" apart from "image that fails to load".
     */
    private function imageUrl(): ?string
    {
        $path = $this->icon;

        if (! is_string($path) || $path === '') {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        // An emoji is not a path; anything without a directory separator or
        // file extension is treated as an icon, not an uploaded file.
        if (! str_contains($path, '/') && ! str_contains($path, '.')) {
            return null;
        }

        return asset('storage/' . ltrim($path, '/'));
    }
}
