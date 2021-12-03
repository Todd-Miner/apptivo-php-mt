<?php

declare(strict_types=1);

namespace ToddMinerTech\ApptivoPhp;

/**
 * Class ResultObject
 *
 * Generic result object returned by all methods for error handling
 * 
 * Will contain a simple bool to track success, then the payload will either be the returned string/object/array/etc, or a string with a failure message.
 *
 * @package ToddMinerTech\apptivo-php-mt
 */
final class ResultObject
{
    public $isSuccessful;
    public $payload = null;

    private function __construct(bool $isSuccessful, $payload = null)
    {
        $this->isSuccessful = $isSuccessful;
        $this->payload = $payload;
    }

    public static function fail($payload): ResultObject
    {
        return new static(false, $payload);
    }

    public static function success($payload): ResultObject
    {
        return new static(true, $payload);
    }
}