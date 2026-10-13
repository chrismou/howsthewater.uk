<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'user_agent' => env('HTTP_USER_AGENT', 'howsthewater.uk (hello@howsthewater.uk)'),

    'stream' => [
        'yorkshire_water_url' => env(
            'STREAM_YORKSHIRE_WATER_URL',
            'https://services-eu1.arcgis.com/1WqkK5cDKUbF0CkH/arcgis/rest/services/Yorkshire_Water_Storm_Overflow_Activity/FeatureServer/0',
        ),
    ],

    'environment_agency' => [
        'flood_monitoring_url' => env('EA_FLOOD_MONITORING_URL', 'https://environment.data.gov.uk/flood-monitoring'),
    ],

    'ordnance_survey' => [
        'open_rivers_path' => env('OS_OPEN_RIVERS_PATH', storage_path('app/private/oprvrs_gb.gpkg')),
    ],

    'overpass' => [
        'url' => env('OVERPASS_URL', 'https://overpass-api.de/api/interpreter'),
    ],

];
