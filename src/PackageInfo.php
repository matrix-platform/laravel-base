<?php //>

namespace MatrixPlatform;

class PackageInfo {

    public function __construct(private $path) {}

    public function getPath() {
        return $this->path;
    }

}
