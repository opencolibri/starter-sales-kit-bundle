<?php

declare(strict_types=1);

// The tests render the pages without JavaScript: only the entrypoint the layout asks for.
return [
    'admin' => ['path' => './assets/admin.js', 'entrypoint' => true],
];
