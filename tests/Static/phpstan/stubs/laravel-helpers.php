<?php

/**
 * @phpstan-assert-if-false !=null $value
 */
function blank(mixed $value): bool{}

/**
 * @phpstan-assert-if-true !=null $value
 */
function filled(mixed $value): bool{}

/**
 * @template TKey of array-key
 * @template TValue
 *
 * @param object|string|int|array<TKey, TValue> $value
 * @return \Illuminate\Support\Collection<TKey, TValue>
 */
function collect(object|string|int|array $value = []): \Illuminate\Support\Collection{}
