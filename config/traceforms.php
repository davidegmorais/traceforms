<?php

return [
    // Limite máximo (MB) por arquivo enviado em formulários. Cada campo pode definir um limite menor.
    'upload_max_mb' => (int) env('TRACEFORMS_UPLOAD_MAX_MB', 25),
];
