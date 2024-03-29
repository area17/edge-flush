<?php declare(strict_types=1);

namespace A17\EdgeFlush\Exceptions;

class EdgeFlush extends \Exception
{
    public static function missingService(): void
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
