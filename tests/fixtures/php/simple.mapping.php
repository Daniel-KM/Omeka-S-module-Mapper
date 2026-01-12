<?php declare(strict_types=1);

/**
 * Example PHP mapping file.
 *
 * PHP mapping files must return an array with the mapping structure.
 */
return [
    'info' => [
        'label' => 'Simple PHP Mapping',
        'from' => 'array',
        'to' => 'omeka',
        'querier' => 'jsdot',
    ],
    'maps' => [
        [
            'from' => ['path' => 'title'],
            'to' => ['field' => 'dcterms:title'],
        ],
        [
            'from' => ['path' => 'creator'],
            'to' => ['field' => 'dcterms:creator'],
        ],
        [
            'from' => ['path' => 'date'],
            'to' => ['field' => 'dcterms:date'],
        ],
        [
            'from' => ['path' => 'description'],
            'to' => ['field' => 'dcterms:description'],
        ],
    ],
];
