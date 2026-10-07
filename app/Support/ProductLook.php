<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Support\Facades\Storage;

final class ProductLook
{
    private const BY_CATEGORY = [
        'ulam' => ['🍲', '#f3e3c8'],
        'baked' => ['🥖', '#f6e3b5'],
        'sweets' => ['🍮', '#ffe7a8'],
        'pantry' => ['🧂', '#ffd2b8'],
    ];

    /** @return array{emoji: string, bg: string, image: ?string} */
    public static function for(Product $product): array
    {
        [$emoji, $bg] = self::BY_CATEGORY[$product->category?->slug ?? ''] ?? ['🍽️', '#efe6f8'];

        return [
            'emoji' => $emoji,
            'bg' => $bg,
            'image' => $product->image_path ? Storage::disk('public')->url($product->image_path) : null,
        ];
    }
}