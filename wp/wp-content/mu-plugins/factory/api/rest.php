<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'rest_api_init',
	static function () {

            register_rest_route(
            'factory/v1',
            '/validate',
            [
                'methods'             => 'POST',
                'callback'            => 'factory_rest_validate',
                'permission_callback' => '__return_true',
            ]
        );

		register_rest_route(
			'factory/v1',
			'/summary',
			[
				'methods'             => 'GET',
				'callback'            => 'factory_rest_summary',
				'permission_callback' => '__return_true',
			]
		);

		register_rest_route(
			'factory/v1',
			'/doctor',
			[
				'methods'             => 'GET',
				'callback'            => 'factory_rest_doctor',
				'permission_callback' => '__return_true',
			]
		);

		register_rest_route(
			'factory/v1',
			'/runs',
			[
				'methods'             => 'GET',
				'callback'            => 'factory_rest_runs',
				'permission_callback' => '__return_true',
			]
		);
            register_rest_route(
            'factory/v1',
            '/run/latest',
            [
                'methods'             => 'GET',
                'callback'            => 'factory_rest_latest_run',
                'permission_callback' => '__return_true',
            ]
        );
        register_rest_route(
            'factory/v1',
            '/explain/latest',
            [
                'methods'             => 'GET',
                'callback'            => 'factory_rest_explain_latest',
                'permission_callback' => '__return_true',
            ]
        );
        register_rest_route(
            'factory/v1',
            '/index',
            [
                'methods'             => 'GET',
                'callback'            => 'factory_rest_index',
                'permission_callback' => '__return_true',
            ]
        );
        register_rest_route(
            'factory/v1',
            '/capabilities',
            [
                'methods'             => 'GET',
                'callback'            => 'factory_rest_capabilities',
                'permission_callback' => '__return_true',
            ]
        );
	}
);

    function factory_rest_validate(): WP_REST_Response {

        $latest = factory_get_latest_run_name();

        if ( ! $latest ) {

            return new WP_REST_Response(
                [
                    'status'  => 'error',
                    'message' => 'No runs found.',
                ],
                404
            );
        }

        $run = factory_get_run_manifest( $latest );

        if ( ! is_array( $run ) ) {

            return new WP_REST_Response(
                [
                    'status'  => 'error',
                    'message' => 'Invalid run manifest.',
                ],
                500
            );
        }

        $blueprint =
            $run['blueprint'] ?? [];

        $result =
            factory_validate_blueprint_state(
                $blueprint,
                false
            );

        return new WP_REST_Response(
            [
                'status' => $result['status'] ?? 'error',
                'checks' => $result['checks'] ?? [],
            ]
        );
    }

    function factory_rest_capabilities(): WP_REST_Response {

        return new WP_REST_Response(
            [
                'version' => '1.0',

                'ai'      => true,
                'docker'  => true,
                'wp_cli'  => true,

                'presets' => [
                    'job-board',
                    'real-estate',
                ],

                'commands' => [
                    'ai',
                    'apply',
                    'validate',
                    'fix',
                    'doctor',
                    'summary',
                    'runs',
                    'latest',
                    'run',
                    'explain',
                    'reset',
                ],

                'adapters' => [
                    'plugins',
                    'theme',
                    'taxonomy',
                    'wp_core',
                    'jetengine',
                    'listing',
                    'render',
                    'single',
                    'content',
                ],
            ]
        );
    }
    function factory_rest_index(): WP_REST_Response {

        return new WP_REST_Response(
            [
                'name'        => 'Crocoblock Site Factory API',
                'version'     => '1.0',
                'status'      => 'active',
                'endpoints'   => [
                '/summary',
                '/doctor',
                '/runs',
                '/run/latest',
                '/explain/latest',
                '/index',
                '/capabilities',
                ],
                'description' => 'Runtime inspection and orchestration API for Factory.',
            ]
        );
    }

    function factory_rest_explain_latest(): WP_REST_Response {

        $latest = factory_get_latest_run_name();

        if ( ! $latest ) {
            return new WP_REST_Response(
                [
                    'status'  => 'error',
                    'message' => 'No runs found.',
                ],
                404
            );
        }

        $run = factory_get_run_manifest( $latest );

        if ( ! is_array( $run ) ) {
            return new WP_REST_Response(
                [
                    'status'  => 'error',
                    'message' => 'Invalid run manifest.',
                ],
                500
            );
        }

        $blueprint = $run['blueprint'] ?? [];

        $response = [
            'site'         => $blueprint['site']['name'] ?? '',
            'cpt'          => [],
            'taxonomies'   => [],
            'listings'     => [],
            'archive'      => '',
            'demo_content' => [],
        ];

        foreach ( $blueprint['cpt'] ?? [] as $cpt ) {

            $response['cpt'][] = [
                'slug' => $cpt['slug'] ?? '',
                'meta' => array_map(
                    static fn( $field ) => $field['key'] ?? '',
                    $cpt['meta'] ?? []
                ),
            ];
        }

        foreach ( $blueprint['taxonomies'] ?? [] as $taxonomy ) {

            $response['taxonomies'][] =
                $taxonomy['slug'] ?? '';
        }

        foreach ( $blueprint['listings'] ?? [] as $listing ) {

            $response['listings'][] =
                $listing['title'] ?? '';
        }

        $archive =
            $blueprint['pages']['archive']['slug']
            ?? '';

        if ( $archive ) {
            $response['archive'] =
                '/' . trim( $archive, '/' ) . '/';
        }

        foreach (
            $blueprint['content'] ?? []
            as $items
        ) {

            foreach ( $items as $item ) {

                $response['demo_content'][] =
                    $item['title'] ?? '';
            }
        }

        return new WP_REST_Response( $response );
    }

function factory_rest_summary(): WP_REST_Response {

	$latest = factory_get_latest_run_name();

	if ( ! $latest ) {
		return new WP_REST_Response(
			[
				'status'  => 'error',
				'message' => 'No runs found.',
			],
			404
		);
	}

	$run = factory_get_run_manifest( $latest );

	if ( ! is_array( $run ) ) {
		return new WP_REST_Response(
			[
				'status'  => 'error',
				'message' => 'Invalid run manifest.',
			],
			500
		);
	}

	$blueprint = $run['blueprint'] ?? [];

	$current = factory_validate_blueprint_state(
		$blueprint,
		false
	);

	$state = ( $current['status'] ?? 'error' ) === 'ok'
		? 'IN SYNC'
		: 'DRIFT';

	$cpt_count = count( $blueprint['cpt'] ?? [] );

	$taxonomy_count = count(
		$blueprint['taxonomies'] ?? []
	);

	$listing_count = count(
		$blueprint['listings'] ?? []
	);

	$content_count = 0;

	foreach ( $blueprint['content'] ?? [] as $items ) {
		if ( is_array( $items ) ) {
			$content_count += count( $items );
		}
	}

	return new WP_REST_Response(
		[
			'status'         => $state,
			'latest_run'     => $latest,
			'site'           => $blueprint['site']['name'] ?? '-',
			'cpt_count'      => $cpt_count,
			'taxonomy_count' => $taxonomy_count,
			'listing_count'  => $listing_count,
			'content_count'  => $content_count,
			'doctor'         => $state === 'IN SYNC'
				? 'healthy'
				: 'issues detected',
		]
	);
}

function factory_rest_doctor(): WP_REST_Response {

	$latest = factory_get_latest_run_name();

	if ( ! $latest ) {
		return new WP_REST_Response(
			[
				'status'  => 'error',
				'message' => 'No runs found.',
			],
			404
		);
	}

	$run = factory_get_run_manifest( $latest );

	if ( ! is_array( $run ) ) {
		return new WP_REST_Response(
			[
				'status'  => 'error',
				'message' => 'Invalid run manifest.',
			],
			500
		);
	}

	$blueprint = $run['blueprint'] ?? [];

	$current = factory_validate_blueprint_state(
		$blueprint,
		false
	);

	$issues = [];

	foreach ( $current['checks'] ?? [] as $check ) {
		if ( ( $check['status'] ?? '' ) === 'ok' ) {
			continue;
		}

		$issues[] = [
			'status'  => $check['status'] ?? 'error',
			'message' => $check['message'] ?? '',
		];
	}

	return new WP_REST_Response(
		[
			'status'     => $current['status'] ?? 'error',
			'latest_run' => $latest,
			'prompt'     => $run['prompt'] ?? '',
			'issues'     => $issues,
		]
	);
}

    function factory_rest_latest_run(): WP_REST_Response {

        $latest = factory_get_latest_run_name();

        if ( ! $latest ) {
            return new WP_REST_Response(
                [
                    'status'  => 'error',
                    'message' => 'No runs found.',
                ],
                404
            );
        }

        $run = factory_get_run_manifest( $latest );

        if ( ! is_array( $run ) ) {
            return new WP_REST_Response(
                [
                    'status'  => 'error',
                    'message' => 'Invalid run manifest.',
                ],
                500
            );
        }

        return new WP_REST_Response(
            [
                'status' => 'ok',
                'run'    => $run,
            ]
        );
    }

function factory_rest_runs( WP_REST_Request $request ): WP_REST_Response {

	$registry = factory_get_runs_registry();

	if ( empty( $registry ) ) {
		return new WP_REST_Response(
			[
				'status'  => 'error',
				'message' => 'Run registry not found.',
				'runs'    => [],
			],
			404
		);
	}

	$runs = $registry['runs'] ?? [];

	if ( $request->get_param( 'latest' ) ) {
		$latest = $registry['latest'] ?? '';

		$runs = array_values(
			array_filter(
				$runs,
				static fn( $run ) => ( $run['file'] ?? '' ) === $latest
			)
		);
	}

	if ( $request->get_param( 'failed' ) ) {
		$runs = array_values(
			array_filter(
				$runs,
				static fn( $run ) => ( $run['status'] ?? '' ) !== 'ok'
			)
		);
	}

	$limit = (int) $request->get_param( 'limit' );

	if ( $limit > 0 ) {
		$runs = array_slice(
			$runs,
			0,
			$limit
		);
	}

	$rows = [];

	foreach ( $runs as $run ) {
		$rows[] = [
			'file'      => $run['file'] ?? '',
			'timestamp' => $run['timestamp'] ?? '',
			'status'    => $run['status'] ?? '',
			'preset'    => $run['preset'] ?? '',
			'prompt'    => $run['prompt'] ?? '',
		];
	}

	return new WP_REST_Response(
		[
			'status' => 'ok',
			'latest' => $registry['latest'] ?? null,
			'runs'   => $rows,
		]
	);
}