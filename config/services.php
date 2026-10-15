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

    // Google Analytics only loads in production, and only once a visitor accepts analytics cookies.
    'google_analytics' => [
        'measurement_id' => env('GOOGLE_ANALYTICS_ID', 'G-Q41SRJTMLE'),
    ],

    // Stream publishes one ArcGIS feature service per water company, all with the same fields.
    // Regions list the keys they need in `overflow_feeds`; only those feeds are polled.
    'stream' => [
        'feeds' => [
            'yorkshire_water' => [
                'name' => 'Yorkshire Water',
                'url' => env('STREAM_YORKSHIRE_WATER_URL', 'https://services-eu1.arcgis.com/1WqkK5cDKUbF0CkH/arcgis/rest/services/Yorkshire_Water_Storm_Overflow_Activity/FeatureServer/0'),
            ],
            'northumbrian_water' => [
                'name' => 'Northumbrian Water',
                'url' => env('STREAM_NORTHUMBRIAN_WATER_URL', 'https://services-eu1.arcgis.com/MSNNjkZ51iVh8yBj/arcgis/rest/services/Northumbrian_Water_Storm_Overflow_Activity_2_view/FeatureServer/0'),
            ],
            'united_utilities' => [
                'name' => 'United Utilities',
                'url' => env('STREAM_UNITED_UTILITIES_URL', 'https://services5.arcgis.com/5eoLvR0f8HKb7HWP/arcgis/rest/services/United_Utilities_Storm_Overflow_Activity/FeatureServer/0'),
            ],
            'severn_trent' => [
                'name' => 'Severn Trent Water',
                'url' => env('STREAM_SEVERN_TRENT_URL', 'https://services1.arcgis.com/NO7lTIlnxRMMG9Gw/arcgis/rest/services/Severn_Trent_Water_Storm_Overflow_Activity/FeatureServer/0'),
            ],
            'thames_water' => [
                'name' => 'Thames Water',
                'url' => env('STREAM_THAMES_WATER_URL', 'https://services2.arcgis.com/g6o32ZDQ33GpCIu3/arcgis/rest/services/Thames_Water_Storm_Overflow_Activity_(Production)_view/FeatureServer/0'),
            ],
            'anglian_water' => [
                'name' => 'Anglian Water',
                'url' => env('STREAM_ANGLIAN_WATER_URL', 'https://services3.arcgis.com/VCOY1atHWVcDlvlJ/arcgis/rest/services/stream_service_outfall_locations_view/FeatureServer/0'),
            ],
            'wessex_water' => [
                'name' => 'Wessex Water',
                'url' => env('STREAM_WESSEX_WATER_URL', 'https://services.arcgis.com/3SZ6e0uCvPROr4mS/arcgis/rest/services/Wessex_Water_Storm_Overflow_Activity/FeatureServer/0'),
            ],
        ],
    ],

    'environment_agency' => [
        'flood_monitoring_url' => env('EA_FLOOD_MONITORING_URL', 'https://environment.data.gov.uk/flood-monitoring'),
        'bathing_water_url' => env('EA_BATHING_WATER_URL', 'https://environment.data.gov.uk'),
    ],

    'ordnance_survey' => [
        'open_rivers_path' => env('OS_OPEN_RIVERS_PATH', storage_path('app/private/oprvrs_gb.gpkg')),
    ],

    'overpass' => [
        'url' => env('OVERPASS_URL', 'https://overpass-api.de/api/interpreter'),
    ],

];
