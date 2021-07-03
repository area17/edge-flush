<?php

namespace A17\TwillTransformers\Exceptions;

class Block extends \Exception
{
    /**
     * @param $type
     * @throws \A17\TwillTransformers\Exceptions\Block
     */
    public static function missingService($type)
    {
        throw new self(
            'CDN service configuration is missing, please check config/cdn.php.',
        );
    }

    /**
     * @throws \A17\TwillTransformers\Exceptions\Block
     */
    public static function classNotFound($class)
    {
        throw new self('Service class not found: ' . $class);
    }
}
