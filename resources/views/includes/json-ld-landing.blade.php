{{-- Семантическая разметка: JSON-LD (рекомендованный формат для Google; RDFa/Microdata дублировать не требуется). --}}
@unless(request()->routeIs('blog.show'))
    @php
        $siteUrl = url('/');
        $orgId = $siteUrl . '#organization';
        $websiteId = $siteUrl . '#website';
        $pageId = $pageCanonical . '#webpage';

        $description = \Illuminate\Support\Str::limit(
            \Illuminate\Support\Str::squish(strip_tags($pageDescription)),
            500,
            ''
        );

        $graph = [
            [
                '@type' => 'Organization',
                '@id' => $orgId,
                'name' => 'kidscrm.online',
                'legalName' => 'ИП Устьян Евгений Артурович',
                'url' => $siteUrl,
                'email' => 'kidscrmonline@gmail.com',
                'identifier' => [
                    '@type' => 'PropertyValue',
                    'name' => 'ИНН',
                    'value' => '110211351590',
                ],
            ],
            [
                '@type' => 'WebSite',
                '@id' => $websiteId,
                'url' => $siteUrl,
                'name' => 'kidscrm.online',
                'publisher' => ['@id' => $orgId],
                'inLanguage' => 'ru-RU',
            ],
        ];

        $webPageTypes = ['WebPage'];
        if (request()->routeIs('blog.index')) {
            $webPageTypes[] = 'CollectionPage';
        }

        $webPageNode = [
            '@type' => count($webPageTypes) === 1 ? $webPageTypes[0] : $webPageTypes,
            '@id' => $pageId,
            'url' => $pageCanonical,
            'name' => $pageTitle,
            'isPartOf' => ['@id' => $websiteId],
            'inLanguage' => 'ru-RU',
            'publisher' => ['@id' => $orgId],
        ];
        if ($description !== '') {
            $webPageNode['description'] = $description;
        }
        $graph[] = $webPageNode;

        if (request()->is('/')) {
            $graph[] = [
                '@type' => 'SoftwareApplication',
                '@id' => $siteUrl . '#software',
                'name' => 'kidscrm.online',
                'applicationCategory' => 'BusinessApplication',
                'applicationSubCategory' => 'CRM',
                'operatingSystem' => 'Web browser',
                'url' => $siteUrl,
                'offers' => [
                    '@type' => 'Offer',
                    'price' => '0',
                    'priceCurrency' => 'RUB',
                    'description' => 'Без абонентской платы — комиссия только с успешных платежей',
                ],
                'provider' => ['@id' => $orgId],
            ];
        }

        $path = trim(request()->path(), '/');
        $skipBreadcrumb = request()->routeIs('blog.category') || request()->is('/');

        if (!$skipBreadcrumb) {
            $crumbName = match ($path) {
                'blog' => 'Блог',
                'public-offerta' => 'Публичная оферта',
                'policy' => 'Политика конфиденциальности',
                'crm-dlya-futbolnoy-sekcii' => 'Футбольные секции',
                'crm-dlya-tancevalnoy-studii' => 'Танцевальные студии',
                'crm-dlya-shkoly-edinoborstv' => 'Школы единоборств',
                'crm-dlya-detskogo-razvivayushchego-centra' => 'Развивающие центры',
                'crm-dlya-shkol-gimnastiki-i-akrobatiki' => 'Гимнастика и акробатика',
                'crm-dlya-detskih-yazykovyh-shkol' => 'Языковые школы',
                default => \Illuminate\Support\Str::limit(
                    trim(\Illuminate\Support\Str::before($pageTitle, '—')),
                    80
                ) ?: 'Страница',
            };

            $graph[] = [
                '@type' => 'BreadcrumbList',
                '@id' => $pageCanonical . '#breadcrumb',
                'itemListElement' => [
                    [
                        '@type' => 'ListItem',
                        'position' => 1,
                        'name' => 'Главная',
                        'item' => $siteUrl,
                    ],
                    [
                        '@type' => 'ListItem',
                        'position' => 2,
                        'name' => $crumbName,
                        'item' => $pageCanonical,
                    ],
                ],
            ];
        }

        $payload = [
            '@context' => 'https://schema.org',
            '@graph' => $graph,
        ];

        $jsonFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS;
    @endphp
    <script type="application/ld+json">
        {!! json_encode($payload, $jsonFlags) !!}
    </script>
@endunless
