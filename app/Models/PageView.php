<?php

namespace App\Models;

use Database\Factories\PageViewFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

class PageView extends Model
{
    /** @use HasFactory<PageViewFactory> */
    use HasFactory;

    use MassPrunable;

    public $timestamps = false;

    protected $fillable = ['path', 'visit_hash', 'referrer_host', 'device_type', 'viewed_at'];

    protected function casts(): array
    {
        return ['viewed_at' => 'datetime'];
    }

    /** @return Builder<self> */
    public function prunable(): Builder
    {
        return self::query()->where('viewed_at', '<', now()->subYear());
    }
}
