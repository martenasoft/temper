<?php

namespace App\Helper;

use Symfony\Component\String\Slugger\AsciiSlugger;

class StringHelper
{
    public static function slug(string $value): string
    {
        $slugger = new AsciiSlugger();
        return $slugger->slug($value);
    }
}