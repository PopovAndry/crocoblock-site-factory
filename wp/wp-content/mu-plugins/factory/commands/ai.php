<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Factory_AI_Command {

	public function __invoke( array $args = [], array $assoc_args = [] ): void {
		$no_cache    = isset( $assoc_args['no-cache'] );
		$debug_cache = isset( $assoc_args['debug-cache'] );

		$prompt = trim( implode( ' ', $args ) );

		if ( empty( $prompt ) ) {
			WP_CLI::error( 'Please provide a prompt.' );
		}

		$preset = $this->detect_preset( $prompt );

		$cache_version = 'v2';
		$model         = 'gpt-4.1-mini';
		$cache_key     = md5( $cache_version . '|' . $model . '|' . ( $preset ?? 'no-preset' ) . '|' . $prompt );
		$cache_dir     = '/var/www/blueprints/cache';
		$cache_path    = "{$cache_dir}/{$cache_key}.json";

		$blueprint      = null;
		$base_blueprint = null;

		if ( ! $no_cache && file_exists( $cache_path ) ) {
			WP_CLI::log( 'Blueprint loaded from cache.' );

			if ( $debug_cache ) {
				WP_CLI::log( "Cache key: {$cache_key}" );
				WP_CLI::log( "Cache path: {$cache_path}" );
			}

			$cached_blueprint = json_decode( file_get_contents( $cache_path ), true );

			if ( is_array( $cached_blueprint ) ) {
				$blueprint = $cached_blueprint;
			} else {
				WP_CLI::warning( 'Invalid cache, regenerating...' );
			}
		}

		if ( ! is_array( $blueprint ) && $preset ) {
			WP_CLI::log( "Detected preset: {$preset}" );

			try {
				$manager        = new Factory_Blueprint_Preset_Manager();
				$base_blueprint = $manager->load_preset( $preset );

				if ( ! is_array( $base_blueprint ) ) {
					WP_CLI::warning( 'Preset did not return a valid blueprint. Fallback to AI generation.' );
					$base_blueprint = null;
				} else {
					WP_CLI::log( 'Preset loaded as base blueprint.' );
				}
			} catch ( Throwable $e ) {
				WP_CLI::warning( 'Preset load failed, fallback to AI: ' . $e->getMessage() );
				$base_blueprint = null;
			}
		}

		if ( ! is_array( $blueprint ) ) {
			WP_CLI::log(
				is_array( $base_blueprint )
					? 'Enhancing preset blueprint via AI...'
					: 'Generating blueprint via AI...'
			);

			$api_key = getenv( 'OPENAI_API_KEY' );

			if ( ! $api_key ) {
				WP_CLI::error( 'OPENAI_API_KEY not set.' );
			}

			$system_prompt = <<<SYS
You work with WordPress blueprints for Crocoblock Site Factory.

Return ONLY valid JSON. No markdown. No explanation.

If the user provides an existing blueprint, modify it according to the user request and return the resulting blueprint JSON.

If no existing blueprint is provided, generate a full blueprint from scratch.

The blueprint must follow this structure:

{
  "version": "0.2",
  "site": {
    "name": "Site name",
    "language": "en",
    "permalink": "/%postname%/"
  },
  "cpt": [
    {
      "slug": "job",
      "label": "Jobs",
      "singular": "Job",
      "supports": ["title", "editor"],
      "meta": [
        { "key": "salary", "type": "number", "label": "Salary" },
        { "key": "location", "type": "text", "label": "Location" }
      ]
    }
  ],
  "taxonomies": [
    {
      "slug": "job_type",
      "label": "Job Types",
      "singular": "Job Type",
      "post_type": "job",
      "terms": ["Full-time", "Part-time", "Remote"]
    }
  ],
  "listings": [
    {
      "slug": "job-card",
      "title": "Job Card",
      "post_type": "job",
      "fields": ["title", "salary", "location"]
    }
  ],
  "pages": {
    "archive": {
      "post_type": "job",
      "slug": "jobs",
      "title": "Jobs"
    }
  },
  "content": {
    "job": [
      {
        "title": "Frontend Developer",
        "content": "We are looking for a frontend developer.",
        "meta": {
          "salary": 3000,
          "location": "Berlin"
        },
        "terms": {
          "job_type": ["Full-time"]
        }
      },
      {
        "title": "Backend Developer",
        "content": "We are looking for a backend developer.",
        "meta": {
          "salary": 4000,
          "location": "Munich"
        },
        "terms": {
          "job_type": ["Full-time"]
        }
      }
    ]
  }
}

Rules:
- Use lowercase slugs.
- Use snake_case for meta keys and taxonomy slugs.
- Always include site, cpt, content.
- If the site needs archive output, include pages.archive.
- If the site uses repeatable cards, include listings.
- If content has categories or types, include taxonomies and terms.
- If modifying an existing blueprint, preserve existing CPTs, meta fields, taxonomies, listings, pages, and content unless the user explicitly asks to remove them.
- If the user asks to add a field, add it to CPT meta, listings fields, and demo content meta.
- If the user asks to customize a job board, preserve salary and location unless explicitly asked to remove them.

Hard requirements:
- Never return empty content arrays.
- For every CPT, generate at least 2 demo content items.
- Every content item must include title, content, meta values for all declared meta fields.
- If taxonomies are defined, every content item must include matching terms.
- Always include listings for each CPT.
- Always include pages.archive for the main CPT.
- For job board requests, include job_type taxonomy with Full-time, Part-time, Remote terms.
- For job board requests, include at least Frontend Developer and Backend Developer demo jobs.
SYS;

			$user_content = $prompt;

			if ( is_array( $base_blueprint ) ) {
				$user_content =
					"Modify this existing blueprint:\n\n" .
					json_encode( $base_blueprint, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) .
					"\n\nUser request:\n" .
					$prompt;
			}

			$payload = [
				'model'       => $model,
				'messages'    => [
					[
						'role'    => 'system',
						'content' => $system_prompt,
					],
					[
						'role'    => 'user',
						'content' => $user_content,
					],
				],
				'temperature' => 0.2,
			];

			$response = wp_remote_post(
				'https://api.openai.com/v1/chat/completions',
				[
					'headers' => [
						'Authorization' => 'Bearer ' . $api_key,
						'Content-Type'  => 'application/json',
					],
					'body'    => json_encode( $payload ),
					'timeout' => 60,
				]
			);

			if ( is_wp_error( $response ) ) {
				WP_CLI::error( $response->get_error_message() );
			}

			$status_code = wp_remote_retrieve_response_code( $response );
			$raw_body    = wp_remote_retrieve_body( $response );
			$body        = json_decode( $raw_body, true );

			if ( $status_code < 200 || $status_code >= 300 ) {
				$message = $body['error']['message'] ?? $raw_body;
				WP_CLI::error( "OpenAI API error: {$message}" );
			}

			$content = $body['choices'][0]['message']['content'] ?? '';

			if ( ! $content ) {
				WP_CLI::error( 'Empty response from AI.' );
			}

			$content = $this->clean_json_response( $content );

			$ai_blueprint = json_decode( $content, true );

			if ( ! is_array( $ai_blueprint ) ) {
				WP_CLI::log( 'Raw AI response:' );
				WP_CLI::log( $content );
				WP_CLI::error( 'Invalid JSON returned from AI.' );
			}

			if ( is_array( $base_blueprint ) ) {
				$blueprint = $this->merge_blueprints( $base_blueprint, $ai_blueprint );
				WP_CLI::log( 'Preset blueprint merged with AI result.' );
			} else {
				$blueprint = $ai_blueprint;
			}

			$this->cache_blueprint(
				$blueprint,
				$cache_dir,
				$cache_path,
				$no_cache,
				$debug_cache
			);
		}

		$path = '/var/www/blueprints/generated/ai-blueprint.json';

		file_put_contents(
			$path,
			json_encode( $blueprint, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE )
		);

		WP_CLI::success( "Blueprint saved: {$path}" );

		factory_reset_diff_report();

		WP_CLI::log( 'Applying blueprint...' );
		factory_apply_blueprint( $blueprint );
		factory_log_diff_report();

		WP_CLI::success( "Factory AI blueprint applied: {$path}" );

		WP_CLI::log( '' );
		WP_CLI::log( 'Running dry-run...' );

		$dry_run = new Factory_Dry_Run_Command();
		$dry_run->__invoke( [ $path ], [] );

		WP_CLI::log( '' );
		WP_CLI::log( 'Running validation...' );

		factory_validate_blueprint_state( $blueprint, true );

		WP_CLI::success( 'AI pipeline completed: apply → plan → validate' );
	}

	private function detect_preset( string $prompt ): ?string {
		if ( stripos( $prompt, 'job' ) !== false ) {
			return 'job-board';
		}

		if (
			stripos( $prompt, 'real estate' ) !== false ||
			stripos( $prompt, 'property' ) !== false ||
			stripos( $prompt, 'properties' ) !== false
		) {
			return 'real-estate';
		}

		return null;
	}

	private function clean_json_response( string $content ): string {
		$content = trim( $content );
		$content = preg_replace( '/^```json\s*/', '', $content );
		$content = preg_replace( '/^```\s*/', '', $content );
		$content = preg_replace( '/\s*```$/', '', $content );

		return trim( $content );
	}

	private function merge_blueprints( array $base, array $override ): array {
		return array_replace_recursive( $base, $override );
	}

	private function cache_blueprint(
		array $blueprint,
		string $cache_dir,
		string $cache_path,
		bool $no_cache,
		bool $debug_cache
	): void {
		if ( $no_cache ) {
			return;
		}

		if ( ! is_dir( $cache_dir ) ) {
			mkdir( $cache_dir, 0755, true );
		}

		file_put_contents(
			$cache_path,
			json_encode( $blueprint, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE )
		);

		WP_CLI::log( 'Blueprint cached.' );

		if ( $debug_cache ) {
			WP_CLI::log( "Cache saved: {$cache_path}" );
		}
	}
}