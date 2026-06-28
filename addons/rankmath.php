<?php
/**
 * Optional Rank Math integration.
 *
 * @package Frontend_Text_Edit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Frontend_Text_Edit_RankMath_Addon {
	/**
	 * Register Rank Math text-edit hooks.
	 */
	public static function register(): void {
		add_filter( 'frontend_text_edit_segment_block_names', array( __CLASS__, 'add_segment_blocks' ) );
		add_filter( 'frontend_text_edit_updated_block', array( __CLASS__, 'sync_faq_attrs' ), 10, 2 );
		add_filter( 'frontend_text_edit_rendered_segment_selectors', array( __CLASS__, 'rendered_segment_selectors' ), 10, 3 );
	}

	/**
	 * Add Rank Math FAQ blocks to the text segment adapter.
	 *
	 * @param array<int,string> $names Block names.
	 * @return array<int,string>
	 */
	public static function add_segment_blocks( array $names ): array {
		return self::merge_block_names( $names, array( 'rank-math/faq-block' ) );
	}

	/**
	 * Return stable rendered FAQ selectors for segment matching.
	 *
	 * @param array<int,array<string,string>> $selectors Existing selectors.
	 * @param string                          $block_name Block name.
	 * @param array<string,mixed>             $item Editable item.
	 * @return array<int,array<string,string>>
	 */
	public static function rendered_segment_selectors( array $selectors, string $block_name, array $item ): array {
		if ( 'rank-math/faq-block' !== $block_name ) {
			return $selectors;
		}

		$segment = isset( $item['segment'] ) && is_array( $item['segment'] ) ? $item['segment'] : array();
		$index   = isset( $segment['index'] ) ? absint( $segment['index'] ) : 0;
		$selectors[] = 0 === $index % 2
			? array( 'tag' => 'h3', 'class' => 'rank-math-question' )
			: array( 'tag' => 'div', 'class' => 'rank-math-answer', 'mark_child_tag' => 'p' );

		return $selectors;
	}

	/**
	 * Keep Rank Math FAQ block attributes in sync after a frontend text edit.
	 *
	 * @param array<string,mixed> $block Parsed block.
	 * @param array<string,mixed> $context Text edit update context.
	 * @return array<string,mixed>
	 */
	public static function sync_faq_attrs( array $block, array $context ): array {
		if ( 'rank-math/faq-block' !== (string) ( $context['block_name'] ?? '' ) ) {
			return $block;
		}

		$segment_index = $context['segment_index'] ?? null;
		if ( null === $segment_index || ! isset( $block['attrs']['questions'] ) || ! is_array( $block['attrs']['questions'] ) ) {
			return $block;
		}

		$segment_index  = absint( $segment_index );
		$question_index = (int) floor( $segment_index / 2 );
		$field          = 0 === $segment_index % 2 ? 'title' : 'content';
		if ( ! isset( $block['attrs']['questions'][ $question_index ] ) || ! is_array( $block['attrs']['questions'][ $question_index ] ) ) {
			return $block;
		}

		$text = sanitize_text_field( (string) ( $context['text'] ?? '' ) );
		if ( 'content' === $field ) {
			$text = sanitize_textarea_field( (string) ( $context['text'] ?? '' ) );
		}

		$block['attrs']['questions'][ $question_index ][ $field ] = $text;
		return $block;
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

Frontend_Text_Edit_RankMath_Addon::register();
