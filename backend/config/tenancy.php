<?php

return [
    'central_connection' => env('DB_CONNECTION', 'mysql'),
    // These named connections may use separate database creation/runtime credentials.
    'provisioning_connection' => env('TENANT_PROVISIONING_CONNECTION', env('DB_CONNECTION', 'mysql')),
    'template_connection' => env('TENANT_TEMPLATE_CONNECTION', env('DB_CONNECTION', 'mysql')),
    'sqlite_directory' => env('TENANT_SQLITE_DIRECTORY', storage_path('app/tenants')),
];
