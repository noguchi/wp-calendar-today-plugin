<?php
/**
 * Plugin Name:       WP Today Schedule
 * Plugin URI:        https://github.com/noguchi/wp-calendar-today-plugin
 * Description:       カレンダープラグイン「Event Calendar Maker（無料版）」に登録した今日の予定を表示するショートコード。
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            Shingo Noguchi
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-today-schedule-plugin
 *
 * @package WP_Today_Schedule
 */

defined( 'ABSPATH' ) || exit;

const WP_TODAY_SCHEDULE_VERSION = '0.1.0';

require_once __DIR__ . '/includes/class-calendar-client.php';
require_once __DIR__ . '/includes/class-template.php';

add_action( 'admin_notices', 'wp_today_schedule_admin_notice' );
add_action( 'init', 'wp_today_schedule_register_shortcode' );

/**
 * カレンダープラグインが有効でないとき、管理者に通知する。
 */
function wp_today_schedule_admin_notice(): void {
	if ( class_exists( 'LimitedCalendar8bit' ) || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	echo '<div class="notice notice-warning"><p>' . esc_html( 'WP Today Schedule：カレンダープラグイン（Event Calendar Maker）が有効になっていません。' ) . '</p></div>';
}

/**
 * ショートコードを登録する。
 */
function wp_today_schedule_register_shortcode(): void {
	add_shortcode( 'today_schedule', 'wp_today_schedule_shortcode' );
}

/**
 * [today_schedule] を展開する。
 *
 * @param array<string, string>|string $atts    ショートコードの属性.
 * @param string|null                  $content 開始タグと終了タグの間の HTML.
 * @return string
 */
function wp_today_schedule_shortcode( $atts, $content = '' ): string {
	$atts  = shortcode_atts(
		array(
			'hash'  => '',
			'link'  => '',
			'limit' => 0,
		),
		$atts,
		'today_schedule'
	);
	$hash  = sanitize_text_field( (string) $atts['hash'] );
	$link  = esc_url_raw( (string) $atts['link'] );
	$limit = absint( $atts['limit'] );

	$client = new WP_Today_Schedule_Calendar_Client();
	$today  = $client->today( $hash, current_datetime() );
	if ( is_wp_error( $today ) ) {
		return wp_today_schedule_notice( $today->get_error_message() );
	}

	$template = wp_today_schedule_template( (string) $content );
	if ( '' === $template ) {
		wp_today_schedule_enqueue_style();
		return WP_Today_Schedule_Template::render( WP_Today_Schedule_Template::builtin( $link ), $today, $limit );
	}

	$html       = WP_Today_Schedule_Template::render( $template, $today, $limit );
	$post       = get_post();
	$author     = $post ? (int) $post->post_author : 0;
	$unfiltered = $author > 0 && user_can( $author, 'unfiltered_html' );
	return $unfiltered ? $html : wp_kses_post( $html );
}

/**
 * 開始タグと終了タグの間を、テンプレートとして使うかを判定する。
 *
 * 前後の空白と <p>、</p>、<br> を除いて空なら空文字を、そうでなければ元の文字列を返す。
 *
 * @param string $content 開始タグと終了タグの間の HTML.
 * @return string
 */
function wp_today_schedule_template( string $content ): string {
	$rest = preg_replace( '#<\s*/?\s*p\s*>|<\s*br\s*/?\s*>#i', '', $content );
	return '' === trim( (string) $rest ) ? '' : $content;
}

/**
 * 管理者にだけ原因を表示する。
 *
 * @param string $message 原因.
 * @return string
 */
function wp_today_schedule_notice( string $message ): string {
	if ( ! current_user_can( 'manage_options' ) ) {
		return '';
	}
	wp_today_schedule_enqueue_style();
	return '<div class="wp-today-schedule wp-today-schedule--notice"><p>' . esc_html( '本日の予定：' . $message ) . '</p><p>' . esc_html( 'このメッセージは管理者にだけ表示しています。' ) . '</p></div>';
}

/**
 * プラグインの CSS を読み込む。ショートコードの展開は wp_head の後なので、フッターで出力される。
 */
function wp_today_schedule_enqueue_style(): void {
	wp_enqueue_style( 'wp-today-schedule-plugin', plugins_url( 'assets/css/wp-today-schedule-plugin.css', __FILE__ ), array(), WP_TODAY_SCHEDULE_VERSION );
}
