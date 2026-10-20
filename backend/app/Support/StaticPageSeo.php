<?php

namespace App\Support;

final class StaticPageSeo
{
    private const PUBLIC_ORIGIN = 'https://potsandplanters.ae';

    public const PAGES = [
        'home' => ['label' => 'Home', 'path' => '/'],
        'shop' => ['label' => 'Shop', 'path' => '/shop'],
        'solutions' => ['label' => 'Solutions', 'path' => '/solutions'],
        'catalogues' => ['label' => 'Catalogues', 'path' => '/catalogues'],
        'news' => ['label' => 'News', 'path' => '/news'],
        'about' => ['label' => 'About', 'path' => '/about'],
        'contacts' => ['label' => 'Contacts', 'path' => '/contacts'],
        'private_policy' => ['label' => 'Privacy Policy', 'path' => '/private-policy'],
    ];

    public static function path(string $key): ?string
    {
        return self::PAGES[$key]['path'] ?? null;
    }

    public static function url(string $key): ?string
    {
        $path = self::path($key);

        return $path === null ? null : self::PUBLIC_ORIGIN.$path;
    }
}
