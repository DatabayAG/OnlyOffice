<?php

namespace srag\Plugins\OnlyOffice\StorageService\Infrastructure\Common;

use Exception;
use Ramsey\Uuid\Uuid as RamseyUuid;

/**
 * Class UUID
 *
 * @package srag\Plugins\OnlyOffice\StorageService\Infrastructure\Common
 *
 * @author  Theodor Truffer <thoe@fluxlabs.ch>
 */
class UUID
{
    protected string $uuid;

    /**
     * @throws Exception
     */
    public function __construct(string $uuid = '')
    {
        $this->uuid = $uuid !== '' ? $uuid : RamseyUuid::uuid4()->toString();
    }

    public function asString(): string
    {
        return $this->uuid;
    }
}
