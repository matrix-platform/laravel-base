<?php //>

namespace MatrixPlatform\Support;

class Resources {

    private $bundles = [];
    private $folders = [];

    public function __construct() {
        $this->folders[] = base_path();

        foreach (tokenize(config('matrix.packages')) as $name) {
            $this->folders[] = app("PackageInfo:{$name}")->getPath();
        }
    }

    public function config($token, $default = null) {
        list($name, $key) = explode('.', $token, 2);

        return data_get($this->getConfigBundle($name), $key, $default);
    }

    public function getAdminMenuBundle($name) {
        return $this->getBundle("menu/{$name}");
    }

    public function getConfigBundle($name) {
        return $this->getBundle("cfg/{$name}");
    }

    public function getI18nBundle($name, $locale = null) {
        $locale = $locale ?: app()->getLocale();

        return $this->getBundle("i18n/{$locale}/{$name}");
    }

    public function translate($token, $locale = null) {
        list($name, $key) = explode('.', $token, 2);

        return data_get($this->getI18nBundle($name, $locale), $key, $token);
    }

    private function getBundle($name) {
        if (!array_key_exists($name, $this->bundles)) {
            $bundle = null;

            foreach ($this->folders as $folder) {
                $data = $this->load("{$folder}/resources/{$name}.php");

                if (is_array($data)) {
                    $bundle = $bundle ? array_replace_recursive($data, $bundle) : $data;
                }
            }

            $this->bundles[$name] = $bundle;
        }

        return $this->bundles[$name];
    }

    private function load($path) {
        return is_file($path) ? isolate_require($path) : null;
    }

}
