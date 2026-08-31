<?php //>

namespace MatrixPlatform\Models\Declarations;

use MatrixPlatform\Columns\Declarations\Declares;
use MatrixPlatform\Columns\Declarations\Definition;
use MatrixPlatform\Columns\Declarations\Definitions;
use MatrixPlatform\Columns\Options\BundleOptions;
use MatrixPlatform\Columns\Presentation;
use MatrixPlatform\Support\Metadata;

class SmsLogDeclaration implements Declares {

    /**
     * @return array<string, Definition>
     */
    public function definitions(): array {
        return array_merge(
            Definitions::primaryKey(),
            [
                'provider' => Definition::text(),
                'receiver' => Definition::text(),
                'content' => Definition::text(),
                'template' => Definition::text(),
                'schedule_time' => Definition::dateTime(),
                'send_time' => Definition::dateTime(),
                'response' => Definition::text(),
                'error' => Definition::text(),
                'ip' => Definition::text(),
                'locale' => Definition::text(),
                'status' => Definition::integer(Presentation::Select, [], new BundleOptions('sms-log-status'))
            ],
            Definitions::auditings()
        );
    }

    public function metadata(): Metadata {
        return new Metadata('sms-log', 'receiver');
    }

}
