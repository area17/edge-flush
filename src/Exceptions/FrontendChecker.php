<?php declare(strict_types=1);

namespace A17\EdgeFlush\Exceptions;

class FrontendChecker extends \Exception
{
    public static function unsupportedType(string $type): void
    {
        throw new self("UNSUPPORTED TYPE: we cannot check if the application is on frontend using '$type'");
    }
}
