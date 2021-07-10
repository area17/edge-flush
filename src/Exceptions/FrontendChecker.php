<?php

namespace A17\CDN\Exceptions;

class FrontendChecker extends \Exception
{
    /**
     * @param $type
     * @throws \A17\CDN\Exceptions\Block
     */
    public static function unsupportedType($type)
    {
        throw new self(
            "UNSUPPORTED TYPE: we cannot check if the application is on frontend using '$type'",
        );
    }
}
