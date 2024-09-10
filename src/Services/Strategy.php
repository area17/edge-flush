<?php

namespace A17\EdgeFlush\Services;

class Strategy
{
    public string $name;

    public array $urls = [];

    public array $models = [];

    public array $onChange = [];
    
    public function __construct(array $strategy)
    {
        $this->name = $strategy['strategy'];

        $this->urls = $strategy['urls'] ?? [];

        $this->models = $strategy['models'] ?? [];

        $this->onChange = $strategy['onChange'] ?? [];
    }
}
