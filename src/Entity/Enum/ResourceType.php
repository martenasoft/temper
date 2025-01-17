<?php

namespace App\Entity\Enum;

enum ResourceType: int
{
    case Dir = 1;
    case File = 2;

    public static  function setValue(int|string $value): self
    {

        if (is_numeric($value)) {
            $value = (int) $value;
        }

        if (is_string($value)) {
            $value = strtolower($value);

            $value = match ($value) {
                'file' => self::File->value,
                default => self::Dir->value,
            };
        }


        return self::tryFrom($value);
    }
}