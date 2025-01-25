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

    public static function isAlphabet(string $word): bool
    {
        return preg_match('/^[a-z]+$/i', $word);
    }

    public static function isAlphabetNeighbour(string $word, string $substr): int
    {
        if(($pos = strpos($word, $substr)) === false) {
            return 1;
        }

        return ($pos === 0 || self::isAlphabet(substr($word, $pos - 1, 1))) &&
            self::isAlphabet(substr($word, $pos + strlen($substr), 1)) ? 2 : 0;

    }

    public static function replaceType(string $fileItem, string $word, string $replace): string
    {
        //$fileItem = "some value PageService some value PageController";
        //$fileItem = 'some value $pageService some value page_service PageService PAGE_SERVICE';
        if (!preg_match_all("/.{1}($word).{1}/i", $fileItem, $match)) {
            //  return $fileItem;
        }
        $result = $fileItem;

        foreach ($match[0] as $index => $item) {

            $w = trim($item);

            if (substr($w, 0, 1) === '$') {
                $type = 'TCC';
            } elseif (preg_match('/^[A-Z]{1}/', $w) && preg_match('/[A-Z]{1}$/', $w)) {
                $type = 'TCCF';
            } elseif (preg_match('/[a-z]+[A-Z]/', $w, $match1)) {
                $type = 'TCC';
            } elseif (preg_match('/[a-z]+_*[a-z]/', $w, $match1)) {
                $type = 'TSC';
            } elseif (preg_match('/[A-Z]+_*[A-Z]/', $w, $match1)) {
                $type = 'TUC';
            } else {
                $type = '';
            }

            if (!empty($type)) {
                return preg_replace(
                    '/'. $match[1][$index] .'/',
                    "__{$type}_{$replace}__",
                    $result, 1
                );
            }
        }

        return $fileItem;
    }

    public static function isFirstSymbol(string $word, array $symbols): bool
    {
        $word = trim($word);
        foreach ($symbols as $symbol) {
            if (stripos($word, $symbol) === 0) {

                return true;
            }
        }
        return false;
    }
}