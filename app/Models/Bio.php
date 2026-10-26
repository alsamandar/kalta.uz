<?php

namespace App\Models;

use App\Traits\Kaltaable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bio extends Model
{
    use HasFactory, Kaltaable;

    /** Colour themes for the public page, stored in the "style" column. */
    public const THEMES = ['default', 'sunset', 'ocean', 'forest', 'night'];

    /** Link fields shown on the page, in order. */
    public const LINKS = ['instagram', 'telegram', 'twitter', 'github', 'blog'];

    protected $guarded = [];

    /**
     * The links this page actually has. Old rows may hold anything the user typed,
     * so only http(s) URLs are ever returned.
     *
     * @return array<int, array{key: string, url: string}>
     */
    public function links(): array
    {
        $links = [];

        foreach (self::LINKS as $key) {
            if ($url = safe_url($this->{$key})) {
                $links[] = ['key' => $key, 'url' => $url];
            }
        }

        return $links;
    }

    public static function label(string $key): string
    {
        return match ($key) {
            'twitter' => 'X',
            'github' => 'GitHub',
            'blog' => __('Website'),
            default => ucfirst($key),
        };
    }

    public function theme(): string
    {
        return in_array($this->style, self::THEMES, true) ? $this->style : 'default';
    }

    /** Public path of the avatar, or null. */
    public function avatarUrl(): ?string
    {
        return $this->avatar_icon ? asset($this->avatar_icon) : null;
    }
}
