<?php

declare(strict_types=1);

return [
    /*
     * Name of the Lattice SVG-sprite icon rendered at the top of the auth
     * layout. The consuming app's sprite must contain a matching symbol.
     */
    'brand_icon' => env('OIDC_UI_BRAND_ICON', 'logo'),
];
