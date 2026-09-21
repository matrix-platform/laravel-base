<?php //>

namespace MatrixPlatform\Models\Declarations;

use MatrixPlatform\Columns\Declarations\Declares;
use MatrixPlatform\Columns\Declarations\Definition;
use MatrixPlatform\Columns\Declarations\Definitions;
use MatrixPlatform\Columns\Presentation;
use MatrixPlatform\Support\Metadata;

class PageDeclaration implements Declares {

    /**
     * @return array<string, Definition>
     */
    public function definitions(): array {
        return array_merge(
            Definitions::primaryKey(),
            [
                'path' => Definition::text(required: true, unique: true),
                'title' => Definition::text(required: true),
                'seo_title' => Definition::text(translatable: true, tab: 'seo'),
                'seo_description' => Definition::text(translatable: true, tab: 'seo'),
                'og_image' => Definition::json(Presentation::DriveImage, translatable: true, tab: 'seo'),
                'data' => Definition::composite('page-data')
            ],
            Definitions::schedules(),
            Definitions::ranking(),
            Definitions::auditings()
        );
    }

    public function metadata(): Metadata {
        return new Metadata('page', 'title', ranking: 'ranking', enable: 'enable_time', disable: 'disable_time');
    }

}
