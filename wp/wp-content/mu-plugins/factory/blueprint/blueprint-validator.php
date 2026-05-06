<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Factory_Blueprint_Validator {

	public function validate( array $blueprint ): array {
		$errors = [];

		if ( empty( $blueprint['site'] ) || ! is_array( $blueprint['site'] ) ) {
			$errors[] = 'Missing site section.';
		}

		if ( empty( $blueprint['cpt'] ) || ! is_array( $blueprint['cpt'] ) ) {
			$errors[] = 'Missing cpt section.';
		}

		if ( empty( $blueprint['content'] ) || ! is_array( $blueprint['content'] ) ) {
			$errors[] = 'Missing content section.';
		}

		foreach ( $blueprint['cpt'] ?? [] as $index => $cpt ) {

			if ( empty( $cpt['slug'] ) ) {
				$errors[] = "CPT #{$index} missing slug.";
			}

			if ( empty( $cpt['label'] ) ) {
				$errors[] = "CPT #{$index} missing label.";
			}

			if ( empty( $cpt['meta'] ) || ! is_array( $cpt['meta'] ) ) {
				$errors[] = "CPT {$cpt['slug']} missing meta.";
			}
		}

		foreach ( $blueprint['content'] ?? [] as $post_type => $items ) {

			if ( ! is_array( $items ) || empty( $items ) ) {
				$errors[] = "Content for {$post_type} is empty.";
				continue;
			}

			foreach ( $items as $i => $item ) {

				if ( empty( $item['title'] ) ) {
					$errors[] = "{$post_type} content item #{$i} missing title.";
				}

				if ( empty( $item['meta'] ) || ! is_array( $item['meta'] ) ) {
					$errors[] = "{$post_type} content item #{$i} missing meta.";
				}
			}
		}

		return $errors;
	}
}