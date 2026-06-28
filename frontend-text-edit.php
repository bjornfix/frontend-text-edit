<?php
/**
 * Plugin Name: Frontend Text Edit
 * Description: Frontend inline text editing for supported WordPress block content, saved back to native Gutenberg markup.
 * Version: 0.1.0
 * Author: basicus
 * Author URI: https://profiles.wordpress.org/basicus/
 * License: GPL-2.0-or-later
 * Text Domain: frontend-text-edit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Frontend_Text_Edit {
	const VERSION = '0.1.0';
	const REST_NAMESPACE = 'frontend-text-edit/v1';

	/**
	 * Bootstrap hooks.
	 */
	public static function init(): void {
		add_filter( 'the_content', array( __CLASS__, 'mark_rendered_content' ), 99 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ), 30 );
		add_action( 'admin_bar_menu', array( __CLASS__, 'add_admin_bar_node' ), 90 );
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );
	}

	/**
	 * Add an admin-bar entry for editor-only frontend text fixes.
	 */
	public static function add_admin_bar_node( WP_Admin_Bar $admin_bar ): void {
		if ( ! self::context_allowed() ) {
			return;
		}

		$admin_bar->add_node(
			array(
				'id'    => 'frontend-text-edit',
				'title' => esc_html__( 'Frontend Text Edit', 'frontend-text-edit' ),
				'href'  => '#frontend-text-edit',
				'meta'  => array(
					'class' => 'frontend-text-edit-admin-bar',
				),
			)
		);
	}

	/**
	 * Load editor-only frontend text-edit assets.
	 */
	public static function enqueue_assets(): void {
		if ( ! self::context_allowed() ) {
			return;
		}

		$post_id = (int) get_queried_object_id();
		$base    = plugin_dir_url( __FILE__ );
		$version = self::VERSION;

		wp_enqueue_style(
			'frontend-text-edit',
			$base . 'assets/frontend-text-edit.css',
			array(),
			$version
		);
		wp_enqueue_script(
			'frontend-text-edit',
			$base . 'assets/frontend-text-edit.js',
			array(),
			$version,
			true
		);
		wp_localize_script(
			'frontend-text-edit',
			'FrontendTextEdit',
			array(
				'postId'   => $post_id,
				'endpoint' => esc_url_raw( rest_url( self::REST_NAMESPACE . '/text' ) ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'labels'   => array(
					'open'        => __( 'Frontend Text Edit', 'frontend-text-edit' ),
					'close'       => __( 'Close', 'frontend-text-edit' ),
					'active'      => __( 'Click text to edit it inline.', 'frontend-text-edit' ),
					'inactive'    => __( 'Frontend Text Edit is off.', 'frontend-text-edit' ),
					'save'        => __( 'Save', 'frontend-text-edit' ),
					'cancel'      => __( 'Cancel', 'frontend-text-edit' ),
					'saved'       => __( 'Saved.', 'frontend-text-edit' ),
					'error'       => __( 'Could not save this text change.', 'frontend-text-edit' ),
					'unchanged'   => __( 'No text change to save.', 'frontend-text-edit' ),
				),
			)
		);
	}

	/**
	 * Add inline-edit markers to supported rendered block text for logged-in editors.
	 */
	public static function mark_rendered_content( string $content ): string {
		if ( ! self::context_allowed() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		$post_id = (int) get_queried_object_id();
		$post    = $post_id ? get_post( $post_id ) : null;
		if ( ! $post ) {
			return $content;
		}

		$items                 = self::items_for_content( (string) $post->post_content );
		$marked_segment_blocks = array();
		foreach ( $items as $item ) {
			$html = (string) ( $item['html'] ?? '' );
			if ( '' === $html ) {
				continue;
			}

			if ( self::item_is_segment( $item ) ) {
				if ( 'html_text' === self::item_segment_type( $item ) ) {
					$segment_block_key = self::item_segment_block_key( $item );
					if ( isset( $marked_segment_blocks[ $segment_block_key ] ) ) {
						continue;
					}

					$segment_items = array_values(
						array_filter(
							$items,
							static function ( array $candidate ) use ( $segment_block_key ): bool {
								return Frontend_Text_Edit::item_is_segment( $candidate )
									&& 'html_text' === Frontend_Text_Edit::item_segment_type( $candidate )
									&& $segment_block_key === Frontend_Text_Edit::item_segment_block_key( $candidate );
							}
						)
					);
					$content = self::mark_rendered_html_text_segment_group( $content, $segment_items );
					$marked_segment_blocks[ $segment_block_key ] = true;
					continue;
				}

				$content = self::mark_rendered_segment_text( $content, $item );
				continue;
			}

			if ( false !== strpos( $content, $html ) ) {
				$marked = self::mark_html( $html, $item );
				if ( '' === $marked || $marked === $html ) {
					continue;
				}

				$content = self::replace_first_string( $html, $marked, $content );
				continue;
			}

			$next_content = self::mark_rendered_equivalent( $content, $item );
			if ( $next_content !== $content ) {
				$content = $next_content;
				continue;
			}

			$content = self::mark_rendered_simple_text( $content, $item );
		}

		return $content;
	}

	/**
	 * Register REST routes for frontend text edits.
	 */
	public static function register_rest_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/text',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'rest_items' ),
					'permission_callback' => array( __CLASS__, 'rest_permission' ),
					'args'                => array(
						'post_id' => array(
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'rest_update' ),
					'permission_callback' => array( __CLASS__, 'rest_permission' ),
					'args'                => array(
						'post_id' => array(
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
						'path'    => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'text'    => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => array( __CLASS__, 'sanitize_text' ),
						),
						'hash'    => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);
	}

	/**
	 * REST permission callback for frontend text edits.
	 */
	public static function rest_permission( WP_REST_Request $request ) {
		$post_id = absint( $request->get_param( 'post_id' ) );
		$post    = $post_id ? get_post( $post_id ) : null;
		if ( ! $post || ! self::post_type_allowed( (string) $post->post_type ) ) {
			return new WP_Error( 'frontend_text_edit_invalid_post', __( 'This content cannot be edited from the frontend.', 'frontend-text-edit' ), array( 'status' => 404 ) );
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error( 'frontend_text_edit_forbidden', __( 'You are not allowed to edit this content.', 'frontend-text-edit' ), array( 'status' => 403 ) );
		}

		return true;
	}

	/**
	 * Return editable text items.
	 */
	public static function rest_items( WP_REST_Request $request ): WP_REST_Response {
		$post_id = absint( $request->get_param( 'post_id' ) );
		$post    = get_post( $post_id );
		if ( ! $post ) {
			return rest_ensure_response( array( 'success' => false, 'items' => array() ) );
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'post_id' => $post_id,
				'items'   => self::public_items( self::items_for_content( (string) $post->post_content ) ),
			)
		);
	}

	/**
	 * Save one frontend text edit back into the stored block tree.
	 */
	public static function rest_update( WP_REST_Request $request ): WP_REST_Response {
		$post_id = absint( $request->get_param( 'post_id' ) );
		$post    = get_post( $post_id );
		if ( ! $post || ! self::post_type_allowed( (string) $post->post_type ) ) {
			return rest_ensure_response( self::error( 'This content cannot be edited from the frontend.' ) );
		}

		$text      = self::sanitize_text( $request->get_param( 'text' ) );
		$selection = self::parse_item_path( (string) $request->get_param( 'path' ) );
		if ( empty( $selection['path'] ) ) {
			return rest_ensure_response( self::error( 'Invalid block path.' ) );
		}

		$blocks  = parse_blocks( (string) $post->post_content );
		$updated = self::update_block_at_path( $blocks, $selection['path'], $text, (string) $request->get_param( 'hash' ), $selection['segment_index'] );
		if ( ! $updated['success'] ) {
			return rest_ensure_response( $updated );
		}

		$content = self::normalize_gutenberg_content_for_storage( serialize_blocks( $blocks ) );
		$safety  = self::gutenberg_saved_markup_integrity( $content );
		if ( ! empty( $safety['issues'] ) ) {
			return rest_ensure_response(
				array(
					'success' => false,
					'message' => 'Gutenberg storage guardrails rejected the edited content.',
					'code'    => 'gutenberg_storage_guardrails_failed',
					'issues'  => $safety['issues'],
				)
			);
		}

		$result = wp_update_post(
			wp_slash(
				array(
					'ID'           => $post_id,
					'post_content' => $content,
				)
			),
			true
		);
		if ( is_wp_error( $result ) ) {
			return rest_ensure_response( self::error( $result->get_error_message() ) );
		}

		clean_post_cache( $post_id );
		do_action(
			'frontend_text_edit_updated',
			$post_id,
			array(
				'item'      => $updated['item'],
				'text'      => $text,
				'selection' => $selection,
			)
		);

		return rest_ensure_response(
			array(
				'success' => true,
				'post_id' => $post_id,
				'item'    => $updated['item'],
			)
		);
	}

	/**
	 * Sanitize edited text as plain visible copy.
	 */
	public static function sanitize_text( $text ): string {
		$text = is_scalar( $text ) ? (string) $text : '';
		$text = wp_unslash( $text );
		$text = wp_strip_all_tags( $text );
		$text = preg_replace( '/[ \t]+/u', ' ', $text );
		$text = preg_replace( '/\R+/u', "\n", (string) $text );

		return trim( (string) $text );
	}

	/**
	 * Whether the current frontend request can use text edit.
	 */
	private static function context_allowed(): bool {
		if ( is_admin() || ! is_singular() ) {
			return false;
		}

		$post_id = (int) get_queried_object_id();
		$post    = $post_id ? get_post( $post_id ) : null;
		if ( ! $post || ! self::post_type_allowed( (string) $post->post_type ) ) {
			return false;
		}

		$allowed = current_user_can( 'edit_post', $post_id );
		return (bool) apply_filters( 'frontend_text_edit_context_allowed', $allowed, $post_id, $post );
	}

	/**
	 * Whether a post type can expose frontend text editing.
	 */
	private static function post_type_allowed( string $post_type ): bool {
		if ( '' === $post_type ) {
			return false;
		}

		$post_type_object = get_post_type_object( $post_type );
		$allowed          = $post_type_object && is_post_type_viewable( $post_type_object );
		$allowed          = (bool) apply_filters( 'frontend_text_edit_post_type_allowed', $allowed, $post_type );

		return $allowed;
	}

	/**
	 * Find supported simple text blocks in a Gutenberg document.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private static function items_for_content( string $content ): array {
		$items = array();
		self::collect_items( parse_blocks( $content ), array(), $items );

		return $items;
	}

	/**
	 * Strip internal matching markup from API responses.
	 *
	 * @param array<int,array<string,mixed>> $items Internal item rows.
	 * @return array<int,array<string,mixed>>
	 */
	private static function public_items( array $items ): array {
		return array_map(
			static function ( array $item ): array {
				unset( $item['html'] );
				unset( $item['segment'] );
				return $item;
			},
			$items
		);
	}

	/**
	 * Recursively collect editable block text.
	 *
	 * @param array<int,array<string,mixed>> $blocks Parsed blocks.
	 * @param array<int,int>                 $path   Current block path.
	 * @param array<int,array<string,mixed>> $items  Collected items.
	 */
	private static function collect_items( array $blocks, array $path, array &$items ): void {
		foreach ( $blocks as $index => $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}
			$current_path = array_merge( $path, array( (int) $index ) );
			$item         = self::item_for_block( $block, $current_path );
			if ( $item ) {
				$items[] = $item;
			}
			foreach ( self::segment_items_for_block( $block, $current_path ) as $segment_item ) {
				$items[] = $segment_item;
			}
			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				self::collect_items( $block['innerBlocks'], $current_path, $items );
			}
		}
	}

	/**
	 * Build an editable item for one supported block.
	 *
	 * @param array<string,mixed> $block Parsed block.
	 * @param array<int,int>      $path  Stable path in parsed block tree.
	 * @return array<string,mixed>
	 */
	private static function item_for_block( array $block, array $path ): array {
		$block_name = (string) ( $block['blockName'] ?? '' );
		$html       = (string) ( $block['innerHTML'] ?? '' );
		$text       = self::plain_text( $html );
		if ( '' === $text || ! self::block_supported( $block_name, $html ) ) {
			return array();
		}

		return array(
			'path'       => implode( '.', $path ),
			'blockName'  => $block_name,
			'label'      => self::block_label( $block_name ),
			'text'       => $text,
			'hash'       => self::hash( $block_name, $html ),
			'preview'    => self::brief_excerpt( $text, 140 ),
			'html'       => $html,
		);
	}

	/**
	 * Build editable segment items for one supported rich text block.
	 *
	 * @param array<string,mixed> $block Parsed block.
	 * @param array<int,int>      $path  Stable path in parsed block tree.
	 * @return array<int,array<string,mixed>>
	 */
	private static function segment_items_for_block( array $block, array $path ): array {
		$block_name = (string) ( $block['blockName'] ?? '' );
		$html       = (string) ( $block['innerHTML'] ?? '' );
		if ( '' === $html || self::block_supported( $block_name, $html ) ) {
			return array();
		}

		$segments = self::rich_paragraph_segments( $html );
		if ( empty( $segments ) && in_array( $block_name, self::segment_block_names(), true ) ) {
			$segments = self::html_text_segments( $html );
		}
		if ( empty( $segments ) ) {
			return array();
		}

		$items = array();
		foreach ( $segments as $index => $segment ) {
			$text = (string) ( $segment['text'] ?? '' );
			if ( '' === $text ) {
				continue;
			}
			$items[] = array(
				'path'       => implode( '.', $path ) . ':segment:' . (int) $index,
				'blockName'  => $block_name,
				'label'      => self::segment_label( (string) ( $segment['type'] ?? '' ) ),
				'text'       => $text,
				'hash'       => self::hash( $block_name, $html, (int) $index ),
				'preview'    => self::brief_excerpt( $text, 140 ),
				'html'       => $html,
				'segment'    => array(
					'index' => (int) $index,
					'type'  => (string) ( $segment['type'] ?? '' ),
				),
			);
		}

		return $items;
	}

	/**
	 * Update a supported block selected by path.
	 *
	 * @param array<int,array<string,mixed>> $blocks Parsed blocks, by reference.
	 * @param array<int,int>                 $path   Block path.
	 * @return array<string,mixed>
	 */
	private static function update_block_at_path( array &$blocks, array $path, string $text, string $hash, ?int $segment_index = null ): array {
		$block =& self::block_reference_at_path( $blocks, $path );
		if ( ! is_array( $block ) ) {
			return self::error( 'The selected block no longer exists.' );
		}

		$block_name = (string) ( $block['blockName'] ?? '' );
		$html       = (string) ( $block['innerHTML'] ?? '' );
		if ( $hash !== self::hash( $block_name, $html, $segment_index ) ) {
			return self::error( 'The selected text changed before save. Reload the page and try again.' );
		}
		if ( null !== $segment_index ) {
			$new_html = self::replace_segment_text( $block_name, $html, $segment_index, $text );
			if ( '' === $new_html || $new_html === $html ) {
				return self::error( 'No text change to save.' );
			}
		} elseif ( ! self::block_supported( $block_name, $html ) ) {
			return self::error( 'This block is not safe for frontend text editing.' );
		} else {
			$new_html = self::replace_block_text( $block_name, $html, $text );
			if ( '' === $new_html || $new_html === $html ) {
				return self::error( 'No text change to save.' );
			}
		}

		$block['innerHTML'] = $new_html;
		$context            = array(
			'block_name'    => $block_name,
			'old_html'      => $html,
			'new_html'      => $new_html,
			'text'          => $text,
			'segment_index' => $segment_index,
		);
		$block              = apply_filters( 'frontend_text_edit_updated_block', $block, $context );

		if ( ! empty( $block['innerContent'] ) && is_array( $block['innerContent'] ) ) {
			foreach ( $block['innerContent'] as $index => $chunk ) {
				if ( is_string( $chunk ) && $chunk === $html ) {
					$block['innerContent'][ $index ] = (string) ( $block['innerHTML'] ?? $new_html );
					break;
				}
			}
		}

		$item = null !== $segment_index
			? self::segment_item_by_index( $block, $path, $segment_index )
			: self::item_for_block( $block, $path );

		return array(
			'success' => true,
			'item'    => self::public_items( array( $item ) )[0],
		);
	}

	/**
	 * Add the data attributes used by the inline frontend editor.
	 *
	 * @param array<string,mixed> $item Editable item.
	 */
	private static function mark_html( string $html, array $item ): string {
		$attributes = self::marker_attributes( $item );

		if ( in_array( (string) ( $item['blockName'] ?? '' ), self::button_block_names(), true ) ) {
			return (string) preg_replace( '/<a\b/i', '<a' . $attributes, $html, 1 );
		}

		return (string) preg_replace( '/^<([a-z][a-z0-9]*)(\s|>)/i', '<$1' . $attributes . '$2', trim( $html ), 1 );
	}

	/**
	 * Data attributes used by the inline frontend editor.
	 *
	 * @param array<string,mixed> $item Editable item.
	 */
	private static function marker_attributes( array $item ): string {
		return sprintf(
			' data-frontend-text-edit-path="%s" data-frontend-text-edit-hash="%s" data-frontend-text-edit-label="%s"',
			esc_attr( (string) ( $item['path'] ?? '' ) ),
			esc_attr( (string) ( $item['hash'] ?? '' ) ),
			esc_attr( (string) ( $item['label'] ?? '' ) )
		);
	}

	/**
	 * Whether an editable item targets a rich-text segment inside a block.
	 *
	 * @param array<string,mixed> $item Editable item.
	 */
	public static function item_is_segment( array $item ): bool {
		return isset( $item['segment'] ) && is_array( $item['segment'] );
	}

	/**
	 * Return the rich-text segment type for an editable item.
	 *
	 * @param array<string,mixed> $item Editable item.
	 */
	public static function item_segment_type( array $item ): string {
		$segment = isset( $item['segment'] ) && is_array( $item['segment'] ) ? $item['segment'] : array();
		return (string) ( $segment['type'] ?? '' );
	}

	/**
	 * Return the block-level key shared by segment items from the same block.
	 *
	 * @param array<string,mixed> $item Editable item.
	 */
	public static function item_segment_block_key( array $item ): string {
		$path = (string) ( $item['path'] ?? '' );
		$path = (string) preg_replace( '/:segment:\d+$/', '', $path );
		return $path . "\n" . (string) ( $item['html'] ?? '' );
	}

	/**
	 * Mark a rendered rich-text segment inside a supported block.
	 *
	 * @param array<string,mixed> $item Editable item.
	 */
	private static function mark_rendered_segment_text( string $content, array $item ): string {
		$segment = isset( $item['segment'] ) && is_array( $item['segment'] ) ? $item['segment'] : array();
		$type    = (string) ( $segment['type'] ?? '' );
		$text    = (string) ( $item['text'] ?? '' );
		if ( '' === $type || '' === $text ) {
			return $content;
		}

		if ( 'strong' === $type ) {
			return self::mark_rendered_strong_segment( $content, $item );
		}
		if ( 'after_break' === $type ) {
			return self::mark_rendered_after_break_segment( $content, $item );
		}
		if ( 'html_text' === $type ) {
			return self::mark_rendered_html_text_segment( $content, $item );
		}

		return $content;
	}

	/**
	 * Mark a rendered strong segment with text-edit attributes.
	 *
	 * @param array<string,mixed> $item Editable item.
	 */
	private static function mark_rendered_strong_segment( string $content, array $item ): string {
		$text    = (string) ( $item['text'] ?? '' );
		$pattern = '/<strong\b(?![^>]*\bdata-frontend-text-edit-path=)[^>]*>[^<]*<\/strong>/is';
		if ( ! preg_match_all( $pattern, $content, $matches, PREG_OFFSET_CAPTURE ) ) {
			return $content;
		}

		foreach ( $matches[0] as $match ) {
			$candidate = (string) $match[0];
			if ( self::plain_text( $candidate ) !== $text ) {
				continue;
			}

			$marked = (string) preg_replace( '/<strong\b/i', '<strong' . self::marker_attributes( $item ), $candidate, 1 );
			return substr_replace( $content, $marked, (int) $match[1], strlen( $candidate ) );
		}

		return $content;
	}

	/**
	 * Wrap and mark a rendered text segment that follows a paragraph line break.
	 *
	 * @param array<string,mixed> $item Editable item.
	 */
	private static function mark_rendered_after_break_segment( string $content, array $item ): string {
		$text    = (string) ( $item['text'] ?? '' );
		$pattern = '/<p\b(?![^>]*\bdata-frontend-text-edit-path=)[^>]*>.*?<\/p>/is';
		if ( ! preg_match_all( $pattern, $content, $matches, PREG_OFFSET_CAPTURE ) ) {
			return $content;
		}

		foreach ( $matches[0] as $match ) {
			$candidate = (string) $match[0];
			$segments  = self::rich_paragraph_segments( $candidate );
			if ( empty( $segments[1] ) || (string) $segments[1]['text'] !== $text ) {
				continue;
			}

			$marked = self::wrap_rich_paragraph_after_break_segment( $candidate, $item );
			if ( '' === $marked || $marked === $candidate ) {
				return $content;
			}

			return substr_replace( $content, $marked, (int) $match[1], strlen( $candidate ) );
		}

		return $content;
	}

	/**
	 * Mark a dynamically-rendered equivalent of a stored text block.
	 *
	 * Dynamic blocks can render markup that is semantically the same as stored
	 * block HTML while not being byte-for-byte identical. Keep the editable
	 * surface narrow by requiring a stable generated class and matching visible
	 * text before adding edit attributes.
	 *
	 * @param array<string,mixed> $item Editable item.
	 */
	private static function mark_rendered_equivalent( string $content, array $item ): string {
		$html         = (string) ( $item['html'] ?? '' );
		$text         = (string) ( $item['text'] ?? '' );
		$tag_name     = self::first_tag_name( $html );
		$stable_class = self::stable_render_class( $html );
		if ( '' === $html || '' === $text || '' === $tag_name || '' === $stable_class ) {
			return $content;
		}

		$pattern = sprintf(
			'/<%1$s\b(?=[^>]*\bclass\s*=\s*["\'][^"\']*\b%2$s\b[^"\']*["\'])[^>]*>.*?<\/%1$s>/is',
			preg_quote( $tag_name, '/' ),
			preg_quote( $stable_class, '/' )
		);
		if ( ! preg_match_all( $pattern, $content, $matches, PREG_OFFSET_CAPTURE ) ) {
			return $content;
		}

		foreach ( $matches[0] as $match ) {
			$candidate = (string) $match[0];
			if ( self::plain_text( $candidate ) !== $text ) {
				continue;
			}

			$marked = self::mark_html( $candidate, $item );
			if ( '' === $marked || $marked === $candidate ) {
				return $content;
			}

			return substr_replace( $content, $marked, (int) $match[1], strlen( $candidate ) );
		}

		return $content;
	}

	/**
	 * Mark a simple rendered text block when exact HTML and generated class matching both miss.
	 *
	 * @param array<string,mixed> $item Editable item.
	 */
	private static function mark_rendered_simple_text( string $content, array $item ): string {
		$block_name = (string) ( $item['blockName'] ?? '' );
		if ( ! in_array( $block_name, self::supported_block_names(), true ) ) {
			return $content;
		}

		$html      = (string) ( $item['html'] ?? '' );
		$text      = (string) ( $item['text'] ?? '' );
		$tag_names = self::rendered_match_tag_names( $block_name, $html );
		if ( '' === $html || '' === $text || empty( $tag_names ) ) {
			return $content;
		}

		$pattern = sprintf(
			'/<(%1$s)\b(?![^>]*\bdata-frontend-text-edit-path=)[^>]*>.*?<\/\1>/is',
			implode(
				'|',
				array_map(
					static function ( string $tag_name ): string {
						return preg_quote( $tag_name, '/' );
					},
					$tag_names
				)
			)
		);
		if ( ! preg_match_all( $pattern, $content, $matches, PREG_OFFSET_CAPTURE ) ) {
			return $content;
		}

		foreach ( $matches[0] as $match ) {
			$candidate = (string) $match[0];
			if ( self::plain_text( $candidate ) !== $text ) {
				continue;
			}

			$marked = self::mark_html( $candidate, $item );
			if ( '' === $marked || $marked === $candidate ) {
				return $content;
			}

			return substr_replace( $content, $marked, (int) $match[1], strlen( $candidate ) );
		}

		return $content;
	}

	/**
	 * Mark one editable text-node segment in rendered markup.
	 *
	 * @param array<string,mixed> $item Editable item.
	 */
	private static function mark_rendered_html_text_segment( string $content, array $item ): string {
		$html = (string) ( $item['html'] ?? '' );
		if ( '' === $html ) {
			return $content;
		}

		if ( false !== strpos( $content, $html ) ) {
			$marked = self::wrap_html_text_segment( $html, $item );
			if ( '' !== $marked && $marked !== $html ) {
				return self::replace_first_string( $html, $marked, $content );
			}
		}

		$next_content = self::mark_rendered_selector_segment( $content, $item );
		if ( $next_content !== $content ) {
			return $next_content;
		}

		$next_content = self::mark_rendered_equivalent_segment( $content, $item );
		if ( $next_content !== $content ) {
			return $next_content;
		}

		return self::mark_rendered_simple_segment( $content, $item );
	}

	/**
	 * Mark all editable text-node segments from one rendered HTML block at once.
	 *
	 * @param array<int,array<string,mixed>> $items Segment items from the same block.
	 */
	private static function mark_rendered_html_text_segment_group( string $content, array $items ): string {
		if ( empty( $items ) ) {
			return $content;
		}

		if ( self::segment_group_prefers_selector_marking( $items ) ) {
			foreach ( $items as $item ) {
				$content = self::mark_rendered_selector_segment( $content, $item );
			}
			return $content;
		}

		$first = $items[0];
		$html  = (string) ( $first['html'] ?? '' );
		if ( '' === $html || false === strpos( $content, $html ) ) {
			foreach ( $items as $item ) {
				$content = self::mark_rendered_html_text_segment( $content, $item );
			}
			return $content;
		}

		usort(
			$items,
			static function ( array $a, array $b ): int {
				$a_segment = isset( $a['segment'] ) && is_array( $a['segment'] ) ? $a['segment'] : array();
				$b_segment = isset( $b['segment'] ) && is_array( $b['segment'] ) ? $b['segment'] : array();
				return (int) ( $b_segment['index'] ?? 0 ) <=> (int) ( $a_segment['index'] ?? 0 );
			}
		);

		$marked = $html;
		foreach ( $items as $item ) {
			$next_marked = self::wrap_html_text_segment( $marked, $item );
			if ( '' !== $next_marked ) {
				$marked = $next_marked;
			}
		}

		return $marked === $html ? $content : self::replace_first_string( $html, $marked, $content );
	}

	/**
	 * Whether a segment group should be marked on existing rendered elements.
	 *
	 * @param array<int,array<string,mixed>> $items Segment items from the same block.
	 */
	private static function segment_group_prefers_selector_marking( array $items ): bool {
		foreach ( $items as $item ) {
			if ( ! empty( self::rendered_segment_selectors( $item ) ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Mark a rendered segment using adapter-provided tag/class selectors.
	 *
	 * @param array<string,mixed> $item Editable item.
	 */
	private static function mark_rendered_selector_segment( string $content, array $item ): string {
		$selectors = self::rendered_segment_selectors( $item );
		if ( empty( $selectors ) ) {
			return $content;
		}

		$text = (string) ( $item['text'] ?? '' );
		if ( '' === $text ) {
			return $content;
		}

		foreach ( $selectors as $selector ) {
			$selector = is_array( $selector ) ? $selector : array();
			$tag      = isset( $selector['tag'] ) ? strtolower( (string) $selector['tag'] ) : '';
			$class    = isset( $selector['class'] ) ? (string) $selector['class'] : '';
			if ( '' === $tag || ! preg_match( '/^[a-z][a-z0-9]*$/', $tag ) ) {
				continue;
			}

			$class_assertion = '' === $class ? '' : sprintf(
				'(?=[^>]*\bclass\s*=\s*["\'][^"\']*\b%1$s\b[^"\']*["\'])',
				preg_quote( $class, '/' )
			);
			$pattern = sprintf( '/<%1$s\b(?![^>]*\bdata-frontend-text-edit-path=)%2$s[^>]*>.*?<\/%1$s>/is', preg_quote( $tag, '/' ), $class_assertion );
			if ( ! preg_match_all( $pattern, $content, $matches, PREG_OFFSET_CAPTURE ) ) {
				continue;
			}

			foreach ( $matches[0] as $match ) {
				$candidate = (string) $match[0];
				if ( self::plain_text( $candidate ) !== $text ) {
					continue;
				}

				$marked = self::mark_selector_child( $candidate, $selector, $item, $text );
				if ( '' !== $marked && $marked !== $candidate ) {
					return substr_replace( $content, $marked, (int) $match[1], strlen( $candidate ) );
				}

				$marked = (string) preg_replace( '/^<' . preg_quote( $tag, '/' ) . '\b/i', '<' . $tag . self::marker_attributes( $item ), $candidate, 1 );
				if ( '' !== $marked && $marked !== $candidate ) {
					return substr_replace( $content, $marked, (int) $match[1], strlen( $candidate ) );
				}
			}
		}

		return $content;
	}

	/**
	 * Mark a matching child element inside an adapter-selected container.
	 *
	 * @param array<string,mixed> $selector Adapter selector.
	 * @param array<string,mixed> $item     Editable item.
	 */
	private static function mark_selector_child( string $candidate, array $selector, array $item, string $text ): string {
		$child_tag = isset( $selector['mark_child_tag'] ) ? strtolower( (string) $selector['mark_child_tag'] ) : '';
		if ( '' === $child_tag || ! preg_match( '/^[a-z][a-z0-9]*$/', $child_tag ) ) {
			return '';
		}

		$pattern = sprintf( '/<%1$s\b(?![^>]*\bdata-frontend-text-edit-path=)[^>]*>.*?<\/%1$s>/is', preg_quote( $child_tag, '/' ) );
		if ( ! preg_match_all( $pattern, $candidate, $matches, PREG_OFFSET_CAPTURE ) ) {
			return '';
		}

		foreach ( $matches[0] as $match ) {
			$child = (string) $match[0];
			if ( self::plain_text( $child ) !== $text ) {
				continue;
			}

			$marked_child = (string) preg_replace( '/^<' . preg_quote( $child_tag, '/' ) . '\b/i', '<' . $child_tag . self::marker_attributes( $item ), $child, 1 );
			if ( '' !== $marked_child && $marked_child !== $child ) {
				return substr_replace( $candidate, $marked_child, (int) $match[1], strlen( $child ) );
			}
		}

		return '';
	}

	/**
	 * Return adapter/default rendered selectors for one text segment.
	 *
	 * @param array<string,mixed> $item Editable item.
	 * @return array<int,array<string,string>>
	 */
	private static function rendered_segment_selectors( array $item ): array {
		$block_name = (string) ( $item['blockName'] ?? '' );
		$selectors  = array();
		if ( 'core/list-item' === $block_name && false !== strpos( (string) ( $item['html'] ?? '' ), '<a ' ) ) {
			$selectors[] = array( 'tag' => 'a', 'class' => '' );
		}

		$selectors = apply_filters( 'frontend_text_edit_rendered_segment_selectors', $selectors, $block_name, $item );
		if ( ! is_array( $selectors ) ) {
			return array();
		}

		return array_values( array_filter( $selectors, 'is_array' ) );
	}

	/**
	 * Mark a text-node segment in a dynamically-rendered equivalent block.
	 *
	 * @param array<string,mixed> $item Editable item.
	 */
	private static function mark_rendered_equivalent_segment( string $content, array $item ): string {
		$html         = (string) ( $item['html'] ?? '' );
		$text         = (string) ( $item['text'] ?? '' );
		$block_text   = self::plain_text( $html );
		$tag_name     = self::first_tag_name( $html );
		$stable_class = self::stable_render_class( $html );
		if ( '' === $html || '' === $text || '' === $block_text || '' === $tag_name || '' === $stable_class ) {
			return $content;
		}

		$pattern = sprintf(
			'/<%1$s\b(?=[^>]*\bclass\s*=\s*["\'][^"\']*\b%2$s\b[^"\']*["\'])[^>]*>.*?<\/%1$s>/is',
			preg_quote( $tag_name, '/' ),
			preg_quote( $stable_class, '/' )
		);
		if ( ! preg_match_all( $pattern, $content, $matches, PREG_OFFSET_CAPTURE ) ) {
			return $content;
		}

		foreach ( $matches[0] as $match ) {
			$candidate = (string) $match[0];
			if ( self::plain_text( $candidate ) !== $block_text ) {
				continue;
			}

			$marked = self::wrap_html_text_segment( $candidate, $item );
			if ( '' !== $marked && $marked !== $candidate ) {
				return substr_replace( $content, $marked, (int) $match[1], strlen( $candidate ) );
			}
		}

		return $content;
	}

	/**
	 * Mark a text-node segment in a rendered block with matching tag and text.
	 *
	 * @param array<string,mixed> $item Editable item.
	 */
	private static function mark_rendered_simple_segment( string $content, array $item ): string {
		$block_name = (string) ( $item['blockName'] ?? '' );
		$html       = (string) ( $item['html'] ?? '' );
		$block_text = self::plain_text( $html );
		$tag_names  = self::rendered_match_tag_names( $block_name, $html );
		if ( '' === $html || '' === $block_text || empty( $tag_names ) ) {
			return $content;
		}

		$pattern = sprintf(
			'/<(%1$s)\b(?![^>]*\bdata-frontend-text-edit-path=)[^>]*>.*?<\/\1>/is',
			implode(
				'|',
				array_map(
					static function ( string $tag_name ): string {
						return preg_quote( $tag_name, '/' );
					},
					$tag_names
				)
			)
		);
		if ( ! preg_match_all( $pattern, $content, $matches, PREG_OFFSET_CAPTURE ) ) {
			return $content;
		}

		foreach ( $matches[0] as $match ) {
			$candidate = (string) $match[0];
			if ( self::plain_text( $candidate ) !== $block_text ) {
				continue;
			}

			$marked = self::wrap_html_text_segment( $candidate, $item );
			if ( '' !== $marked && $marked !== $candidate ) {
				return substr_replace( $content, $marked, (int) $match[1], strlen( $candidate ) );
			}
		}

		return $content;
	}

	/**
	 * Candidate rendered tags that can safely represent one editable text block.
	 *
	 * @return array<int,string>
	 */
	private static function rendered_match_tag_names( string $block_name, string $html ): array {
		if ( in_array( $block_name, self::button_block_names(), true ) ) {
			return array( 'a' );
		}
		if ( 'core/list-item' === $block_name ) {
			return array( 'li' );
		}

		$tag_name = self::first_tag_name( $html );
		if ( '' === $tag_name ) {
			return array();
		}

		return array( $tag_name );
	}

	/**
	 * Return the first HTML tag name from stored block markup.
	 */
	private static function first_tag_name( string $html ): string {
		if ( ! preg_match( '/^\s*<([a-z][a-z0-9]*)\b/i', $html, $match ) ) {
			return '';
		}

		return strtolower( (string) $match[1] );
	}

	/**
	 * Return a class specific enough to match a rendered dynamic block safely.
	 */
	private static function stable_render_class( string $html ): string {
		if ( ! preg_match( '/^\s*<[a-z][a-z0-9]*\b[^>]*\bclass=(["\'])(.*?)\1/is', $html, $match ) ) {
			return '';
		}

		$classes = preg_split( '/\s+/', trim( (string) $match[2] ) );
		if ( ! is_array( $classes ) ) {
			return '';
		}

		$adapter_class = apply_filters( 'frontend_text_edit_stable_render_class', '', $html, $classes );
		if ( is_string( $adapter_class ) && '' !== $adapter_class ) {
			return $adapter_class;
		}

		foreach ( $classes as $class ) {
			$class = trim( (string) $class );
			if ( preg_match( '/^wp-block-[a-z0-9-]+$/i', $class ) ) {
				return $class;
			}
		}

		return '';
	}

	/**
	 * Replace the first exact occurrence of a string.
	 */
	private static function replace_first_string( string $search, string $replace, string $subject ): string {
		$position = strpos( $subject, $search );
		if ( false === $position ) {
			return $subject;
		}

		return substr_replace( $subject, $replace, $position, strlen( $search ) );
	}

	/**
	 * Get a parsed block by path.
	 *
	 * @param array<int,array<string,mixed>> $blocks Parsed blocks.
	 */
	private static function &block_reference_at_path( array &$blocks, array $path ) {
		$null = null;
		if ( empty( $path ) ) {
			return $null;
		}

		$cursor =& $blocks;
		$last   = count( $path ) - 1;
		foreach ( $path as $depth => $index ) {
			if ( ! is_array( $cursor ) || ! array_key_exists( $index, $cursor ) || ! is_array( $cursor[ $index ] ) ) {
				return $null;
			}
			if ( $depth === $last ) {
				return $cursor[ $index ];
			}
			if ( ! isset( $cursor[ $index ]['innerBlocks'] ) || ! is_array( $cursor[ $index ]['innerBlocks'] ) ) {
				return $null;
			}
			$cursor =& $cursor[ $index ]['innerBlocks'];
		}

		return $null;
	}

	/**
	 * Parse a dot-separated block path.
	 *
	 * @return array<int,int>
	 */
	private static function parse_path( string $path ): array {
		if ( ! preg_match( '/^\d+(?:\.\d+)*$/', $path ) ) {
			return array();
		}

		return array_map( 'absint', explode( '.', $path ) );
	}

	/**
	 * Parse a block path with an optional rich-text segment suffix.
	 *
	 * @return array{path:array<int,int>,segment_index:?int}
	 */
	private static function parse_item_path( string $path ): array {
		$segment_index = null;
		if ( preg_match( '/^(\d+(?:\.\d+)*):segment:(\d+)$/', $path, $match ) ) {
			$path          = (string) $match[1];
			$segment_index = absint( $match[2] );
		}

		return array(
			'path'          => self::parse_path( $path ),
			'segment_index' => $segment_index,
		);
	}

	/**
	 * @return array<int,string>
	 */
	private static function supported_block_names(): array {
		$names = array( 'core/paragraph', 'core/heading', 'core/list-item', 'core/button' );
		$names = apply_filters( 'frontend_text_edit_supported_block_names', $names );

		return is_array( $names ) ? array_values( array_unique( array_map( 'strval', $names ) ) ) : array( 'core/paragraph', 'core/heading', 'core/list-item', 'core/button' );
	}

	/**
	 * @return array<int,string>
	 */
	private static function button_block_names(): array {
		$names = array( 'core/button' );
		$names = apply_filters( 'frontend_text_edit_button_block_names', $names );

		return is_array( $names ) ? array_values( array_unique( array_map( 'strval', $names ) ) ) : array( 'core/button' );
	}

	/**
	 * Blocks where individual visible text nodes can be edited when the whole
	 * block is too rich for a single plain-text replacement.
	 *
	 * @return array<int,string>
	 */
	private static function segment_block_names(): array {
		$names = array( 'core/paragraph', 'core/list-item' );
		$names = apply_filters( 'frontend_text_edit_segment_block_names', $names );

		return is_array( $names ) ? array_values( array_unique( array_map( 'strval', $names ) ) ) : array( 'core/paragraph', 'core/list-item' );
	}

	/**
	 * Whether this block can be edited by replacing plain text only.
	 */
	private static function block_supported( string $block_name, string $html ): bool {
		if ( ! in_array( $block_name, self::supported_block_names(), true ) ) {
			return false;
		}
		if ( '' === self::plain_text( $html ) ) {
			return false;
		}

		if ( in_array( $block_name, self::button_block_names(), true ) ) {
			return 1 === preg_match( '/<a\b[^>]*>[^<]*<\/a>/is', $html );
		}

		return 1 === preg_match( '/^<([a-z][a-z0-9]*)\b[^>]*>[^<]*<\/\1>$/is', trim( $html ) );
	}

	/**
	 * Replace text while preserving the wrapper element and attributes.
	 */
	private static function replace_block_text( string $block_name, string $html, string $text ): string {
		$escaped = esc_html( $text );
		if ( in_array( $block_name, self::button_block_names(), true ) ) {
			return (string) preg_replace( '/(<a\b[^>]*>)[^<]*(<\/a>)/is', '$1' . $escaped . '$2', $html, 1 );
		}

		return (string) preg_replace( '/^(<([a-z][a-z0-9]*)\b[^>]*>)[^<]*(<\/\2>)$/is', '$1' . $escaped . '$3', trim( $html ), 1 );
	}

	/**
	 * Replace one safe rich-text segment while preserving the block wrapper.
	 */
	private static function replace_segment_text( string $block_name, string $html, int $segment_index, string $text ): string {
		$segments = self::rich_paragraph_segments( $html );
		if ( ! empty( $segments[ $segment_index ] ) ) {
			$escaped = esc_html( $text );
			if ( 0 === $segment_index ) {
				return (string) preg_replace_callback(
					self::rich_paragraph_pattern(),
					static function ( array $match ) use ( $escaped ): string {
						return (string) $match['p_open'] . (string) $match['prefix'] . (string) $match['strong_open'] . $escaped . (string) $match['strong_close'] . (string) $match['between'] . (string) $match['body'] . (string) $match['p_close'];
					},
					trim( $html ),
					1
				);
			}

			if ( 1 === $segment_index ) {
				return (string) preg_replace_callback(
					self::rich_paragraph_pattern(),
					static function ( array $match ) use ( $escaped ): string {
						return (string) $match['p_open'] . (string) $match['prefix'] . (string) $match['strong_open'] . (string) $match['strong_text'] . (string) $match['strong_close'] . (string) $match['between'] . $escaped . (string) $match['p_close'];
					},
					trim( $html ),
					1
				);
			}
		}

		if ( ! in_array( $block_name, self::segment_block_names(), true ) ) {
			return '';
		}

		return self::replace_html_text_segment( $html, $segment_index, $text );
	}

	/**
	 * Return one segment item after a successful rich-text update.
	 *
	 * @param array<string,mixed> $block Parsed block.
	 * @param array<int,int>      $path  Stable path in parsed block tree.
	 */
	private static function segment_item_by_index( array $block, array $path, int $segment_index ): array {
		foreach ( self::segment_items_for_block( $block, $path ) as $item ) {
			if ( isset( $item['segment']['index'] ) && (int) $item['segment']['index'] === $segment_index ) {
				return $item;
			}
		}

		return array();
	}

	/**
	 * Pattern for a safe rich paragraph with a strong intro and body after break.
	 */
	private static function rich_paragraph_pattern(): string {
		return '/^(?P<p_open><p\b[^>]*>)(?P<prefix>\s*)(?P<strong_open><strong\b[^>]*>)(?P<strong_text>[^<]*)(?P<strong_close><\/strong>)(?P<between>\s*<br\s*\/?>\s*)(?P<body>[^<]*)(?P<p_close><\/p>)$/is';
	}

	/**
	 * Safe editable segments from a paragraph that uses strong intro + break.
	 *
	 * @return array<int,array{type:string,text:string}>
	 */
	private static function rich_paragraph_segments( string $html ): array {
		if ( ! preg_match( self::rich_paragraph_pattern(), trim( $html ), $match ) ) {
			return array();
		}

		$strong_text = self::plain_text( (string) $match['strong_text'] );
		$body_text   = self::plain_text( (string) $match['body'] );
		if ( '' === $strong_text || '' === $body_text ) {
			return array();
		}

		return array(
			array(
				'type' => 'strong',
				'text' => $strong_text,
			),
			array(
				'type' => 'after_break',
				'text' => $body_text,
			),
		);
	}

	/**
	 * Safe visible text-node segments from richer stored block HTML.
	 *
	 * @return array<int,array{type:string,text:string,offset:int,length:int}>
	 */
	private static function html_text_segments( string $html ): array {
		$segments = array();
		if ( '' === trim( $html ) || ! preg_match_all( '/<[^>]+>|[^<]+/s', $html, $matches, PREG_OFFSET_CAPTURE ) ) {
			return $segments;
		}

		foreach ( $matches[0] as $match ) {
			$token = (string) $match[0];
			if ( '' === $token || '<' === $token[0] ) {
				continue;
			}
			$text = self::plain_text( $token );
			if ( '' === $text ) {
				continue;
			}

			$segments[] = array(
				'type'   => 'html_text',
				'text'   => $text,
				'offset' => (int) $match[1],
				'length' => strlen( $token ),
			);
		}

		return $segments;
	}

	/**
	 * Replace one text-node segment while preserving all surrounding markup.
	 */
	private static function replace_html_text_segment( string $html, int $segment_index, string $text ): string {
		$segments = self::html_text_segments( $html );
		if ( empty( $segments[ $segment_index ] ) ) {
			return '';
		}

		$segment = $segments[ $segment_index ];
		$offset  = (int) ( $segment['offset'] ?? -1 );
		$length  = (int) ( $segment['length'] ?? 0 );
		if ( $offset < 0 || $length < 1 ) {
			return '';
		}

		return substr_replace( $html, esc_html( $text ), $offset, $length );
	}

	/**
	 * Wrap one text-node segment with frontend edit marker attributes.
	 *
	 * @param array<string,mixed> $item Editable item.
	 */
	private static function wrap_html_text_segment( string $html, array $item ): string {
		$segment = isset( $item['segment'] ) && is_array( $item['segment'] ) ? $item['segment'] : array();
		$index   = isset( $segment['index'] ) ? (int) $segment['index'] : -1;
		if ( $index < 0 ) {
			return '';
		}

		$segments = self::html_text_segments( $html );
		if ( empty( $segments[ $index ] ) ) {
			return '';
		}

		$target = $segments[ $index ];
		$offset = (int) ( $target['offset'] ?? -1 );
		$length = (int) ( $target['length'] ?? 0 );
		if ( $offset < 0 || $length < 1 ) {
			return '';
		}

		$raw_text = substr( $html, $offset, $length );
		if ( self::plain_text( $raw_text ) !== (string) ( $item['text'] ?? '' ) ) {
			return '';
		}

		$wrapped = '<span' . self::marker_attributes( $item ) . '>' . $raw_text . '</span>';
		return substr_replace( $html, $wrapped, $offset, $length );
	}

	/**
	 * Wrap the paragraph body segment after a line break with edit marker attributes.
	 *
	 * @param array<string,mixed> $item Editable item.
	 */
	private static function wrap_rich_paragraph_after_break_segment( string $html, array $item ): string {
		return (string) preg_replace_callback(
			self::rich_paragraph_pattern(),
			static function ( array $match ) use ( $item ): string {
				$body = (string) $match['body'];
				return (string) $match['p_open']
					. (string) $match['prefix']
					. (string) $match['strong_open']
					. (string) $match['strong_text']
					. (string) $match['strong_close']
					. (string) $match['between']
					. '<span' . Frontend_Text_Edit::marker_attributes( $item ) . '>'
					. $body
					. '</span>'
					. (string) $match['p_close'];
			},
			trim( $html ),
			1
		);
	}

	/**
	 * Plain visible block text.
	 */
	private static function plain_text( string $html ): string {
		$text = html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES | ENT_HTML5, get_bloginfo( 'charset' ) ?: 'UTF-8' );
		$text = preg_replace( '/\s+/u', ' ', (string) $text );

		return trim( (string) $text );
	}

	/**
	 * Stable optimistic-concurrency hash for one block's editable surface.
	 */
	private static function hash( string $block_name, string $html, ?int $segment_index = null ): string {
		return hash( 'sha256', $block_name . "\n" . $html . "\n" . ( null === $segment_index ? 'block' : 'segment:' . $segment_index ) );
	}

	/**
	 * Human label for the editable item list.
	 */
	private static function block_label( string $block_name ): string {
		switch ( $block_name ) {
			case 'core/heading':
				return __( 'Heading', 'frontend-text-edit' );
			case 'core/button':
				return __( 'Button', 'frontend-text-edit' );
			case 'core/list-item':
				return __( 'List item', 'frontend-text-edit' );
			default:
				return __( 'Paragraph', 'frontend-text-edit' );
		}
	}

	/**
	 * Human label for an editable rich-text segment.
	 */
	private static function segment_label( string $segment_type ): string {
		if ( 'strong' === $segment_type ) {
			return __( 'Paragraph heading', 'frontend-text-edit' );
		}
		if ( 'after_break' === $segment_type ) {
			return __( 'Paragraph body', 'frontend-text-edit' );
		}

		return __( 'Paragraph', 'frontend-text-edit' );
	}

	/**
	 * Short excerpt without changing storage.
	 */
	private static function brief_excerpt( string $text, int $limit ): string {
		$text = trim( preg_replace( '/\s+/u', ' ', $text ) ?: '' );
		if ( '' === $text || strlen( $text ) <= $limit ) {
			return $text;
		}

		return rtrim( substr( $text, 0, max( 0, $limit - 1 ) ) ) . '...';
	}

	/**
	 * Normalize serialized Gutenberg content before storage.
	 */
	private static function normalize_gutenberg_content_for_storage( string $content ): string {
		$content = str_replace( "\r\n", "\n", $content );
		$content = preg_replace( "/\n{3,}/", "\n\n", (string) $content );

		return trim( (string) $content );
	}

	/**
	 * Basic integrity guardrail for Gutenberg serialized markup.
	 *
	 * @return array{issues:array<int,string>}
	 */
	private static function gutenberg_saved_markup_integrity( string $content ): array {
		$issues = array();
		if ( '' !== $content ) {
			$blocks = parse_blocks( $content );
			if ( ! is_array( $blocks ) ) {
				$issues[] = 'parse_blocks_failed';
			}
			if ( false !== strpos( $content, '<!-- wp:' ) && false === strpos( serialize_blocks( $blocks ), '<!-- wp:' ) ) {
				$issues[] = 'serialize_blocks_lost_block_comments';
			}
		}

		return array( 'issues' => $issues );
	}

	/**
	 * Error payload.
	 */
	private static function error( string $message ): array {
		return array(
			'success' => false,
			'message' => $message,
		);
	}
}

require_once __DIR__ . '/addons/generateblocks.php';
require_once __DIR__ . '/addons/rankmath.php';

Frontend_Text_Edit::init();
