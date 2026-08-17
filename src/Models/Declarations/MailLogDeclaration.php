<?php //>

namespace MatrixPlatform\Models\Declarations;

use MatrixPlatform\Columns\ColumnType;
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
                'provider' => new Definition(ColumnType::Text),
                'sender' => new Definition(ColumnType::Text),
                'receiver' => new Definition(ColumnType::Text),
                'subject' => new Definition(ColumnType::Text),
                'content' => new Definition(ColumnType::Text),
                'template' => new Definition(ColumnType::Text),
                'schedule_time' => new Definition(ColumnType::DateTime),
                'send_time' => new Definition(ColumnType::DateTime),
                'response' => new Definition(ColumnType::Text),
                'error' => new Definition(ColumnType::Text),
                'ip' => new Definition(ColumnType::Text),
                'status' => new Definition(ColumnType::Integer, Presentation::Select, [], new BundleOptions('mail-log-status'))
            ],
            Definitions::auditings()
        );
    }

    public function metadata(): Metadata {
        return new Metadata('mail-log', 'subject');
    }

}
