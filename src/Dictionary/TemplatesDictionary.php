<?php

namespace App\Dictionary;

class TemplatesDictionary
{
    public const TCC = 'TCC';
    public const ITMES = [
        self::TCC
    ];

    public const LANG_PHP = 'php';

    public const LANGS = [
        self::LANG_PHP => [
            'TSC' => [
               'FIRST_LINE_SYMBOL' => ['#', '/'],
               'FILE_EXTENSION' => ['.yaml', '.yml', '.tpl', '.env'],
            ],
            'TCC' => [
                'FIRST_LINE_SYMBOL' => ['$'],
                'FILE_EXTENSION' => ['.php', '.js'],
            ],
        ]
    ];
}