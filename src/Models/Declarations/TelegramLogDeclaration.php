<?php //>

namespace MatrixPlatform\Models\Declarations;

use MatrixPlatform\Columns\Declarations\Declares;
use MatrixPlatform\Columns\Declarations\Definition;
use MatrixPlatform\Columns\Declarations\Definitions;
use MatrixPlatform\Columns\Options\BundleOptions;
use MatrixPlatform\Columns\Presentation;
use MatrixPlatform\Support\Metadata;

class TelegramLogDeclaration implements Declares {

    /**
     * @return array<string, Definition>
     */
    public function definitions(): array {
        return array_merge(
            Definitions::primaryKey(),
            [
                'provider' => Definition::text(),
                'chat_id' => Definition::text(),
                'content' => Definition::text(),
                'data' => Definition::json(),
                'template' => Definition::text(),
                'schedule_time' => Definition::dateTime(),
                'send_time' => Definition::dateTime(),
                'response' => Definition::text(),
                'error' => Definition::text(),
                'ip' => Definition::text(),
                'locale' => Definition::text(),
                'status' => Definition::integer(Presentation::Select, [], new BundleOptions('telegram-log-status'))
            ],
            Definitions::auditings()
        );
    }

    public function metadata(): Metadata {
        return new Metadata('telegram-log', 'chat_id');
    }

}
