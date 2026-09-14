<?php
/** Exercise the public item/save protocol with in-memory WordPress storage. */
define('ABSPATH', __DIR__);
$GLOBALS['filters'] = array();
function add_filter($name, $callback, $priority = 10, $args = 1) { $GLOBALS['filters'][$name][] = array($callback, $args); }
function add_action(...$args) {}
function do_action(...$args) {}
function apply_filters($name, $value, ...$args) { foreach ($GLOBALS['filters'][$name] ?? array() as [$callback, $count]) { $value = $callback(...array_slice(array_merge(array($value), $args), 0, $count)); } return $value; }
function __($text, $domain = '') { return $text; }
function absint($value) { return abs((int) $value); }
function wp_unslash($value) { return is_array($value) ? array_map('wp_unslash', $value) : stripslashes($value); }
function wp_slash($value) { return is_array($value) ? array_map('wp_slash', $value) : addslashes($value); }
function wp_strip_all_tags($value) { return strip_tags($value); }
function get_bloginfo($key) { return 'UTF-8'; }
function esc_html($value) { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function get_post_type_object($type) { return (object) array('public' => true); }
function is_post_type_viewable($type) { return true; }
function current_user_can(...$args) { return true; }
function is_wp_error($value) { return false; }
function clean_post_cache($id) {}
class WP_Post { public $ID = 1; public $post_type = 'page'; public $post_content = ''; }
class WP_REST_Request { public function __construct(private array $params) {} public function get_param($name) { return $this->params[$name] ?? null; } }
class WP_REST_Response { public function __construct(public array $data) {} }
function rest_ensure_response($data) { return new WP_REST_Response($data); }
function get_post($id) { return clone $GLOBALS['test_post']; }
function wp_update_post($data, $error = false) { $GLOBALS['test_post']->post_content = wp_unslash($data['post_content']); return 1; }
// These fixtures model WordPress storage, not the plugin's text replacement.
function parse_blocks($content) { return json_decode($content, true); }
function serialize_blocks($blocks) { return json_encode($blocks, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); }
require $argv[1] ?? dirname(__DIR__) . '/frontend-text-edit.php';
$failures = 0;
foreach (array('core/paragraph' => '<p>Old</p>', 'core/heading' => '<h2>Old</h2>', 'core/button' => '<div class="wp-block-button"><a href="/next/">Old</a></div>', 'generateblocks/headline' => '<h2 class="gb-headline-ab12">Old</h2>', 'generateblocks/button' => '<a href="/next/" class="gb-button-ab12">Old</a>') as $name => $html) {
    $post = new WP_Post();
    $post->post_content = serialize_blocks(array(array('blockName' => $name, 'attrs' => array(), 'innerHTML' => $html, 'innerContent' => array($html), 'innerBlocks' => array())));
    $GLOBALS['test_post'] = $post;
    $items = Frontend_Text_Edit::rest_items(new WP_REST_Request(array('post_id' => 1)))->data['items'];
    $text = 'Price $19; literal $1 ${2} and $0 & path C:\docs\1';
    $result = Frontend_Text_Edit::rest_update(new WP_REST_Request(array('post_id' => 1, 'path' => $items[0]['path'], 'hash' => $items[0]['hash'], 'text' => $text)))->data;
    $expected = str_replace('Old', esc_html($text), $html);
    $saved = parse_blocks($GLOBALS['test_post']->post_content)[0];
    $ok = ($result['success'] ?? false) && $saved['innerHTML'] === $expected && $saved['innerContent'][0] === $expected && $result['item']['text'] === $text;
    echo ($ok ? 'PASS ' : 'FAIL ') . $name . " literal text, wrapper and link preserved\n";
    $failures += !$ok;
}
exit($failures ? 1 : 0);
