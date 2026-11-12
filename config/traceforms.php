<?php

return [
    // Limite máximo (MB) por arquivo enviado em formulários. Cada campo pode definir um limite menor.
    // Cadastro público em /register. Desligado: só entra quem for criado pela Administração
    // ou quem tiver um convite de uma conta. Ligue com TRACEFORMS_REGISTRATION_OPEN=true.
    'registration_open' => (bool) env('TRACEFORMS_REGISTRATION_OPEN', false),

    'upload_max_mb' => (int) env('TRACEFORMS_UPLOAD_MAX_MB', 25),
];
