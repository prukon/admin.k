<?php

/**
 * HTMLPurifier configuration.
 *
 * This project uses Summernote for blog post HTML content.
 * We keep a strict allow-list, but include headings and tables
 * (Bootstrap classes) to support richer formatting.
 *
 * @link http://htmlpurifier.org/live/configdoc/plain.html
 */
return [
    'encoding'           => 'UTF-8',
    'finalize'           => true,
    'ignoreNonStrings'   => false,
    'cachePath'          => storage_path('framework/cache'),
    'cacheFileMode'      => 0755,
    'settings'      => [
        'default' => [
            'HTML.Doctype'             => 'HTML 4.01 Transitional',
            'HTML.Allowed'             => implode(',', [
                // Text / structure
                'div[class|style]',
                'span[class|style]',
                'p[class|style]',
                'br',
                'hr',
                'blockquote[class]',
                'pre[class]',
                'code[class]',

                // Headings (no h1)
                'h2[class]',
                'h3[class]',
                'h4[class]',

                // Lists
                'ul[class]',
                'ol[class]',
                'li[class]',

                // Emphasis
                'b',
                'strong',
                'i',
                'em',
                'u',

                // Links / images
                'a[href|title|target|rel|class]',
                'img[width|height|alt|src|class]',

                // Figures (inline images)
                'figure[class]',
                'figcaption[class]',

                // Tables (Bootstrap)
                'table[class]',
                'thead',
                'tbody',
                'tfoot',
                'tr[class]',
                'th[colspan|rowspan|class]',
                'td[colspan|rowspan|class]',
            ]),
            'CSS.AllowedProperties'    => implode(',', [
                'font',
                'font-size',
                'font-weight',
                'font-style',
                'font-family',
                'text-decoration',
                'padding-left',
                'color',
                'background-color',
                'text-align',
            ]),
            'AutoFormat.AutoParagraph' => true,
            'AutoFormat.RemoveEmpty'   => true,
        ],
        'test'    => [
            'Attr.EnableID' => 'true',
        ],
        'custom_attributes' => [
            ['a', 'target', 'Enum#_blank,_self,_target,_top'],
        ],
        'custom_definition' => [
            'id'  => 'html5-definitions',
            'rev' => 1,
            'debug' => false,
            'elements' => [
                ['section', 'Block', 'Flow', 'Common'],
                ['nav',     'Block', 'Flow', 'Common'],
                ['article', 'Block', 'Flow', 'Common'],
                ['aside',   'Block', 'Flow', 'Common'],
                ['header',  'Block', 'Flow', 'Common'],
                ['footer',  'Block', 'Flow', 'Common'],
                ['address', 'Block', 'Flow', 'Common'],
                ['hgroup', 'Block', 'Required: h2 | h3 | h4', 'Common'],
                ['figure', 'Block', 'Optional: (figcaption, Flow) | (Flow, figcaption) | Flow', 'Common'],
                ['figcaption', 'Inline', 'Flow', 'Common'],
                ['s',    'Inline', 'Inline', 'Common'],
                ['var',  'Inline', 'Inline', 'Common'],
                ['sub',  'Inline', 'Inline', 'Common'],
                ['sup',  'Inline', 'Inline', 'Common'],
                ['mark', 'Inline', 'Inline', 'Common'],
                ['wbr',  'Inline', 'Empty', 'Core'],
                ['ins', 'Block', 'Flow', 'Common', ['cite' => 'URI', 'datetime' => 'CDATA']],
                ['del', 'Block', 'Flow', 'Common', ['cite' => 'URI', 'datetime' => 'CDATA']],
            ],
            'attributes' => [
                ['table', 'height', 'Text'],
                ['td', 'border', 'Text'],
                ['th', 'border', 'Text'],
                ['tr', 'width', 'Text'],
                ['tr', 'height', 'Text'],
                ['tr', 'border', 'Text'],
            ],
        ],
    ],
];

