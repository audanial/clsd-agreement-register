<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        /*
         * Private & Confidential documents for the Legal Submission Portal.
         *
         * 'serve' => false is deliberate and load-bearing: with 'serve' => true
         * Laravel registers GET+PUT /storage/{path} routes for a local disk, which
         * hand out files to anyone holding a signed URL — no per-user authorization
         * and no audit entry. P&C documents are served ONLY through a controller
         * that authorises, logs the download, then streams the bytes. Never call
         * Storage::url() or temporaryUrl() on this disk.
         *
         * 'throw' => true also differs from the disks above, on purpose. A register
         * row that silently fails to save is a bug; a legal agreement that silently
         * fails to save is a lost document.
         *
         * Driver is env-driven so local development uses the private local disk and
         * production can move to an S3-compatible bucket by changing .env only. Each
         * document row records the disk it was written to, so a later switch does not
         * strand files already uploaded.
         */
        'documents' => [
            'driver' => env('DOCUMENTS_DISK_DRIVER', 'local'),
            'root' => storage_path('app/private/documents'),
            'key' => env('DOCUMENTS_AWS_ACCESS_KEY_ID'),
            'secret' => env('DOCUMENTS_AWS_SECRET_ACCESS_KEY'),
            'region' => env('DOCUMENTS_AWS_DEFAULT_REGION'),
            'bucket' => env('DOCUMENTS_AWS_BUCKET'),
            'endpoint' => env('DOCUMENTS_AWS_ENDPOINT'),
            'visibility' => 'private',
            'serve' => false,
            'throw' => true,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
