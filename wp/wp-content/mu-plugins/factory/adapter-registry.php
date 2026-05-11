<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Factory_Adapter_Registry {

	public function get_adapter_keys(): array {
		return [
			'plugins'  => Factory_Plugin_Adapter::class,
			'theme'    => Factory_Theme_Adapter::class,
			'taxonomy' => Factory_Taxonomy_Adapter::class,
			'core'     => Factory_WP_Core_Adapter::class,
			'meta'     => Factory_JetEngine_Adapter::class,
			'listings' => Factory_JetEngine_Listing_Adapter::class,
			'render'   => Factory_Render_Adapter::class,
			'single'   => Factory_Single_Adapter::class,
			'content'  => Factory_Content_Adapter::class,
		];
	}

	public function get_adapters(): array {
		return [
			new Factory_Plugin_Adapter(),
			new Factory_Theme_Adapter(),
			new Factory_Taxonomy_Adapter(),
			new Factory_WP_Core_Adapter(),
			new Factory_JetEngine_Adapter(),
			new Factory_JetEngine_Listing_Adapter(),
			new Factory_Render_Adapter(),
			new Factory_Single_Adapter(),
			new Factory_Content_Adapter(),
		];
	}

	public function get_dependencies(): array {
		return [
			Factory_Content_Adapter::class => [
				Factory_Taxonomy_Adapter::class,
				Factory_WP_Core_Adapter::class,
				Factory_Content_Adapter::class,
			],

			Factory_JetEngine_Adapter::class => [
				Factory_WP_Core_Adapter::class,
				Factory_JetEngine_Adapter::class,
			],

			Factory_JetEngine_Listing_Adapter::class => [
				Factory_WP_Core_Adapter::class,
				Factory_JetEngine_Adapter::class,
				Factory_JetEngine_Listing_Adapter::class,
			],

			Factory_Render_Adapter::class => [
				Factory_JetEngine_Listing_Adapter::class,
				Factory_Render_Adapter::class,
			],

			Factory_Single_Adapter::class => [
				Factory_WP_Core_Adapter::class,
				Factory_Single_Adapter::class,
			],
		];
	}
}
