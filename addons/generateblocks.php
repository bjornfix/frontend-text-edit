<?php
/**
 * Optional GenerateBlocks integration.
 *
 * @package Frontend_Text_Edit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Frontend_Text_Edit_GenerateBlocks_Addon {
	/**
	 * Register GenerateBlocks text-edit hooks.
	 */
	public static function register(): void {
		add_filter( 'frontend_text_edit_supported_block_names', array( __CLASS__, 'add_text_blocks' ) );
		add_filter( 'frontend_text_edit_button_block_names', array( __CLASS__, 'add_button_blocks' ) );
		add_filter( 'frontend_text_edit_stable_render_class', array( __CLASS__, 'stable_render_class' ), 10, 3 );
	}

	/**
	 * Add GenerateBlocks simple text blocks.
	 *
	 * @param array<int,string> $names Block names.
	 * @return array<int,string>
	 */
	public static function add_text_blocks( array $names ): array {
		return self::merge_block_names( $names, array( 'generateblocks/headline', 'generateblocks/button' ) );
	}

	/**
	 * Add GenerateBlocks button blocks.
	 *
	 * @param array<int,string> $names Block names.
	 * @return array<int,string>
	 */
	public static function add_button_blocks( array $names ): array {
		return self::merge_block_names( $names, array( 'generateblocks/button' ) );
	}

	/**
	 * Return a stable GenerateBlocks render class for dynamic block matching.
	 *
	 * @param string            $class Existing adapter class.
	 * @param string            $html Stored block HTML.
	 * @param array<int,string> $classes Parsed HTML classes.
	 */
	public static function stable_render_class( string $class, string $html, array $classes ): string {
		unset( $html );
		if ( '' !== $class ) {
			return $class;
		}

		foreach ( $classes as $candidate ) {
			$candidate = trim( (string) $candidate );
			if ( preg_match( '/^gb-(?:headline|button)-[a-z0-9]+$/i', $candidate ) ) {
				return $candidate;
			}
		}

		return '';
	}

	/**
	 * Merge and de-duplicate block names.
	 *
	 * @param array<int,string> $names Existing names.
	 * @param array<int,string> $extra Extra names.
	 * @return array<int,string>
	 */
	private static function merge_block_names( array $names, array $extra ): array {
		return array_values( array_unique( array_merge( array_map( 'strval', $names ), $extra ) ) );
	}
}

Frontend_Text_Edit_GenerateBlocks_Addon::register();
