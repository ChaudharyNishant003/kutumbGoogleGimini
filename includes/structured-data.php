<?php

// Every json_encode() below adds JSON_HEX_TAG | JSON_HEX_AMP (plus the quote
// flags for good measure) because these blocks are embedded directly inside
// a <script type="application/ld+json"> tag using admin-typed free text
// (property/blog/FAQ content). Without those flags, a value containing
// "</script><script>...' breaks out of the JSON-LD block and runs as HTML —
// json_encode does not escape < or > by default.

function structured_data_local_business(): string
{
    $data = [
        '@context' => 'https://schema.org',
        '@type' => 'RealEstateAgent',
        'name' => SITE_NAME,
        'url' => SITE_URL,
        'telephone' => SITE_PHONE,
        'email' => SITE_EMAIL,
        'address' => ['@type' => 'PostalAddress', 'streetAddress' => SITE_ADDRESS],
    ];
    return '<script type="application/ld+json">' . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . '</script>';
}

/** @param array<int, array{name: string, url: string}> $items */
function structured_data_breadcrumbs(array $items): string
{
    $listItems = [];
    foreach ($items as $i => $item) {
        $listItems[] = [
            '@type' => 'ListItem',
            'position' => $i + 1,
            'name' => $item['name'],
            'item' => SITE_URL . $item['url'],
        ];
    }
    $data = ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $listItems];
    return '<script type="application/ld+json">' . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . '</script>';
}

// Product+Offer rather than schema.org's RealEstateListing type — Google's rich-result
// docs support Product/Offer price+availability snippets, not a distinct listing type.
function structured_data_property(array $property): string
{
    $data = [
        '@context' => 'https://schema.org',
        '@type' => 'Product',
        'name' => $property['name'],
        'description' => $property['description'],
        'offers' => [
            '@type' => 'Offer',
            'price' => (float)$property['price_min'],
            'priceCurrency' => 'INR',
            'availability' => 'https://schema.org/InStock',
            'url' => SITE_URL . '/property.php?id=' . (int)$property['id'],
        ],
    ];
    if (!empty($property['image_path'])) {
        $data['image'] = SITE_URL . UPLOAD_URL . $property['image_path'];
    }
    return '<script type="application/ld+json">' . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . '</script>';
}

/** @param array<int, array{question: string, answer: string}> $faqs */
function structured_data_faq(array $faqs): string
{
    $items = [];
    foreach ($faqs as $faq) {
        $items[] = [
            '@type' => 'Question',
            'name' => $faq['question'],
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq['answer']],
        ];
    }
    $data = ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $items];
    return '<script type="application/ld+json">' . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . '</script>';
}

function structured_data_article(array $post): string
{
    $data = [
        '@context' => 'https://schema.org',
        '@type' => 'Article',
        'headline' => $post['title'],
        'description' => $post['excerpt'],
        'datePublished' => date('c', strtotime($post['published_at'])),
        'dateModified' => date('c', strtotime($post['updated_at'] ?? $post['published_at'])),
        'author' => ['@type' => 'Organization', 'name' => SITE_NAME],
    ];
    if (!empty($post['cover_image_path'])) {
        $data['image'] = SITE_URL . UPLOAD_URL . $post['cover_image_path'];
    }
    return '<script type="application/ld+json">' . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . '</script>';
}
