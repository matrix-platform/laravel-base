<?php //>

namespace MatrixPlatform\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use MatrixPlatform\Attributes\Declared;
use MatrixPlatform\Models\Declarations\PageDeclaration;

/**
 * @property int $id
 * @property string $path
 * @property string $title
 * @property ?string $seo_title__tw
 * @property ?string $seo_title__en
 * @property ?string $seo_description__tw
 * @property ?string $seo_description__en
 * @property ?list<array<string, mixed>> $og_image__tw
 * @property ?list<array<string, mixed>> $og_image__en
 * @property ?array<string, mixed> $data
 * @property ?Carbon $enable_time
 * @property ?Carbon $disable_time
 * @property int $ranking
 * @property ?int $creator_id
 * @property Carbon $create_time
 * @property ?int $updater_id
 * @property ?Carbon $update_time
 */
#[Declared(PageDeclaration::class)]
class Page extends BaseModel {

    protected $table = 'base_page';

    /**
     * @return HasMany<Block, $this>
     */
    public function blocks(): HasMany {
        return $this->hasMany(Block::class, 'page_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array {
        $casts = [
            'data' => 'array',
            'disable_time' => 'datetime',
            'enable_time' => 'datetime'
        ];

        foreach (locales() as $locale) {
            $casts["og_image__{$locale}"] = 'array';
        }

        return $casts;
    }

}
