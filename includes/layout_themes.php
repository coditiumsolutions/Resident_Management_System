<?php

declare(strict_types=1);

/**
 * Available layout color combinations.
 * Top navbar and side navbar share the same primary color.
 */
function layout_themes(): array
{
    return [
        'navy_gold' => [
            'id' => 'navy_gold',
            'name' => 'Navy & Gold',
            'primary' => '#0A174E',
            'secondary' => '#F5D042',
        ],
        'charcoal_yellow' => [
            'id' => 'charcoal_yellow',
            'name' => 'Charcoal & Yellow',
            'primary' => '#101820',
            'secondary' => '#FEE715',
        ],
        'deep_navy_mint' => [
            'id' => 'deep_navy_mint',
            'name' => 'Deep Navy & Mint',
            'primary' => '#00203F',
            'secondary' => '#ADEFD1',
        ],
        'black_neon' => [
            'id' => 'black_neon',
            'name' => 'Black & Neon',
            'primary' => '#0A0A0A',
            'secondary' => '#39FF14',
        ],
        'forest_green' => [
            'id' => 'forest_green',
            'name' => 'Forest & Leaf',
            'primary' => '#2C5F2D',
            'secondary' => '#97BC62',
        ],
        'espresso_coral' => [
            'id' => 'espresso_coral',
            'name' => 'Espresso & Coral',
            'primary' => '#2D2926',
            'secondary' => '#ED6F63',
        ],
        'ocean_ice' => [
            'id' => 'ocean_ice',
            'name' => 'Ocean & Ice',
            'primary' => '#00537A',
            'secondary' => '#A8E8F9',
        ],
        'marine_amber' => [
            'id' => 'marine_amber',
            'name' => 'Marine & Amber',
            'primary' => '#013C58',
            'secondary' => '#F5A201',
        ],
        'midnight_gold' => [
            'id' => 'midnight_gold',
            'name' => 'Midnight & Gold',
            'primary' => '#102944',
            'secondary' => '#ffb20b',
        ],
    ];
}

function layout_theme_default_id(): string
{
    return 'deep_navy_mint';
}

function layout_theme_cookie_name(): string
{
    return 'rms_layout_theme';
}

function layout_get_theme_id(): string
{
    $themes = layout_themes();

    if (!empty($_SESSION['layout_theme']) && isset($themes[$_SESSION['layout_theme']])) {
        return (string) $_SESSION['layout_theme'];
    }

    $cookieName = layout_theme_cookie_name();
    if (!empty($_COOKIE[$cookieName]) && isset($themes[$_COOKIE[$cookieName]])) {
        $_SESSION['layout_theme'] = (string) $_COOKIE[$cookieName];
        return (string) $_COOKIE[$cookieName];
    }

    return layout_theme_default_id();
}

function layout_get_theme(): array
{
    $themes = layout_themes();
    $id = layout_get_theme_id();

    return $themes[$id] ?? $themes[layout_theme_default_id()];
}

function layout_set_theme(string $themeId): bool
{
    $themes = layout_themes();
    if (!isset($themes[$themeId])) {
        return false;
    }

    $_SESSION['layout_theme'] = $themeId;

    setcookie(
        layout_theme_cookie_name(),
        $themeId,
        [
            'expires' => time() + (60 * 60 * 24 * 365),
            'path' => '/',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => false,
            'samesite' => 'Lax',
        ]
    );

    return true;
}

function layout_theme_css_vars(?array $theme = null): string
{
    $theme = $theme ?? layout_get_theme();
    $primary = $theme['primary'];
    $secondary = $theme['secondary'];

    return ':root{'
        . "--rp-top-navbar:{$primary};"
        . "--rp-sidebar-bg:{$primary};"
        . "--rp-second-navbar:{$secondary};"
        . "--rp-theme-primary:{$primary};"
        . "--rp-theme-secondary:{$secondary};"
        . '}';
}
