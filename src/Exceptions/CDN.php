<?php

namespace A17\CDN\Exceptions;

class CDN extends \Exception
{
    public static function missingService($type): void
    {
        throw new self(
            'CDN service configuration is missing, please check config/cdn.php.',
        );
    }

    public static function classNotFound(string $class): void
    {
        throw new self('Service class not found: ' . $class);
    }
}
