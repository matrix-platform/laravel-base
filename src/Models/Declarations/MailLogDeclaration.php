<?php //>

namespace MatrixPlatform\Models\Declarations;

use MatrixPlatform\Columns\Declarations\Declares;
use MatrixPlatform\Columns\Declarations\Definition;
use MatrixPlatform\Columns\Declarations\Definitions;
use MatrixPlatform\Columns\Options\BundleOptions;
use MatrixPlatform\Columns\Presentation;
use MatrixPlatform\Support\Metadata;

class MailLogDeclaration implements Declares {

    /**
     * @return array<string, Definition>
     */
    public function definitions(): array {
        return array_merge(
            Definitions::primaryKey(),
            [
                'provider' => Definition::text(),
                'sender' => Definition::text(),
                'receiver' => Definition::text(),
                'subject' => Definition::text(),
                'content' => Definition::text(),
                'template' => Definition::text(),
                'schedule_time' => Definition::dateTime(),
                'send_time' => Definition::dateTime(),
                'response' => Definition::text(),
                'error' => Definition::text(),
                'ip' => Definition::text(),
                'locale' => Definition::text(),
                'status' => Definition::integer(Presentation::Select, [], new BundleOptions('mail-log-status'))
            ],
            Definitions::auditings()
        );
    }

    public function metadata(): Metadata {
        return new Metadata('mail-log', 'subject');
    }

}
