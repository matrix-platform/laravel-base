<?php //>

namespace MatrixPlatform\Support;

enum ResourceGroup: string {

    case Cfg = 'cfg';
    case I18n = 'i18n';
    case Menu = 'i18n/menu';
    case Model = 'i18n/model';
    case Options = 'i18n/options';
    case Template = 'i18n/template';

    public function config(): string {
        return 'resource-' . str_replace('/', '-', $this->value);
    }

    public function directory(): string {
        return str_replace('i18n', 'i18n/' . app()->getLocale(), $this->value);
    }

}
