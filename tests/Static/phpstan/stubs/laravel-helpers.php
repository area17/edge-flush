<?php

/**
 * @param mixed $value
 * @return bool
 */
function blank(mixed $value): bool{}

/**
 * @template TKey of array-key
 * @template TValue
 *
 * @param object|string|int|array<TKey, TValue> $value
 * @return \Illuminate\Support\Collection<TKey, TValue>
 */
function collect(object|string|int|array $value = []): \Illuminate\Support\Collection{}
