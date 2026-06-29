<?php

namespace App\Service\Content;

/**
 * Source d'autorité du contrat de blocs des landing pages.
 * B (TypeScript) et C (formulaires) DOIVENT refléter ce catalogue.
 */
final class BlockCatalog
{
    /** type de bloc => liste des champs de 1er niveau requis */
    public const TYPES = [
        'hero'        => ['title'],
        'richText'    => ['markdown'],
        'gallery'     => ['images'],
        'cta'         => ['heading', 'button'],
        'collection'  => ['source'],
        'features'    => ['items'],
        'stats'       => ['items'],
        'testimonial' => ['quote'],
        'faq'         => ['items'],
        'video'       => ['url'],
        'newsletter'  => ['title'],
    ];

    /** sources autorisées pour le bloc 'collection' */
    public const COLLECTION_SOURCES = ['products', 'articles', 'trips', 'childBoxes'];

    public static function isType(string $type): bool
    {
        return isset(self::TYPES[$type]);
    }

    /** @return list<string> */
    public static function types(): array
    {
        return array_keys(self::TYPES);
    }

    /** @return list<string> champs requis du type, [] si type inconnu */
    public static function requiredFields(string $type): array
    {
        return self::TYPES[$type] ?? [];
    }
}
