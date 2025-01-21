<?php

namespace App\Service;

use function Symfony\Component\String\s;

class TemplateService
{
    public function clearTemplates(array $templates): array
    {
        $result = [];
        foreach ($templates as $template) {
            if (preg_match('/__([A-Z]+)__(\w+)__/', $template, $matches) && !empty($matches[2])) {
                $label = s($matches[2])
                    ->replaceMatches('/\W+|_+|\-+/', '')
                    ->lower()
                    ->toString();
                $result[$label][] = $template;
            }
        }
        return $result;
    }
}