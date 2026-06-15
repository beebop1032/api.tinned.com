<?php

namespace App\Model\App;

class VersionResponse
{
    public string $version;

    public function __construct(string $version)
    {
        $this->version = $version;
    }
}
