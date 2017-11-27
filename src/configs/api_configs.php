<?php 


return [


	'view_routes' => [
		'actions/print',
		'admin/stats/giving',
	],
	'file_routes' => [
		'download',
	],
	'client_id' 		=> env('client_id', 1),
    'client_secret' 	=> env('client_secret', 'abc'),
    'url'   			=> env('url', 'http://localhost:8001'),
    'secret_url'   		=> env('secret_url', 'http://localhost:8001'),
    'grant_type' 		=> env('grant_type','client_credentials'),
    'languages' => [
        'en'
    ],
    'multilingualSites' => [
        'localhost'
    ],
    'tracking_hits' => true,

    'security_code' 	=> env('security_code', 'qwe123'),

    'use_frontend_repo' => env('use_frontend_repo', false),
    'frontend_repo_url' => env('frontend_repo_url', 'https://localhost:8080/pc/'),
    'cache_frontend_for'=> env('cache_frontend_for', 60*24*31),
    'not_found_redirect_seconds' => env('not_found_redirect_seconds', 0),
    'not_found_redirect_code' => env('not_found_redirect_code', 301),
    'not_found_redirect_mode' => env('not_found_redirect_mode', 'http'), // other option is "view"
	'domains' => []
];
