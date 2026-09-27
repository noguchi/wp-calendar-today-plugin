<?php
/**
 * Plugin Name:       WP Calendar Today
 * Plugin URI:        https://github.com/noguchi/wp-calendar-today-plugin
 * Description:       カレンダープラグイン「Event Calendar Maker（無料版）」に登録した今日の予定を表示するショートコード。
 * Version:           0.1.2
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            Shingo Noguchi
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-calendar-today-plugin
 *
 * @package WP_Calendar_Today
 */

defined( 'ABSPATH' ) || exit;

const WP_CALENDAR_TODAY_VERSION = '0.1.2';

require_once __DIR__ . '/includes/class-calendar-client.php';
require_once __DIR__ . '/includes/class-template.php';

add_action( 'admin_notices', 'wp_calendar_today_admin_notice' );
add_action( 'init', 'wp_calendar_today_register_shortcode' );

/**
 * カレンダープラグインが有効でないとき、管理者に通知する。
 */
function wp_calendar_today_admin_notice(): void {
	if ( class_exists( 'LimitedCalendar8bit' ) || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	echo '<div class="notice notice-warning"><p>' . esc_html( 'WP Calendar Today：カレンダープラグイン（Event Calendar Maker）が有効になっていません。' ) . '</p></div>';
}

/**
 * ショートコードを登録する。
 */
function wp_calendar_today_register_shortcode(): void {
	add_shortcode( 'today_schedule', 'wp_calendar_today_shortcode' );
}

/**
 * [today_schedule] を展開する。
 *
 * 利用者が書いたテンプレートは、埋めた後の出力に必ず wp_kses_post() を通す。
 * 区間の展開で断片がつながり、保存時の kses が調べていないタグができるためである。
 * 投稿者（post_author）が unfiltered_html を持っていても省略しない。投稿者と本文を最後に保存した人は一致しない。
 *
 * @param array<string, string>|string $atts    ショートコードの属性.
 * @param string|null                  $content 開始タグと終了タグの間の HTML.
 * @return string
 */
function wp_calendar_today_shortcode( $atts, $content = '' ): string {
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

	$client = new WP_Calendar_Today_Calendar_Client();
	$today  = $client->today( $hash, current_datetime() );
	if ( is_wp_error( $today ) ) {
		return wp_calendar_today_notice( $today->get_error_message() );
	}

	$template = wp_calendar_today_template( (string) $content );
	if ( '' === $template ) {
		wp_calendar_today_enqueue_style();
		return WP_Calendar_Today_Template::render( WP_Calendar_Today_Template::builtin( $link ), $today, $limit );
	}

	return wp_kses_post( WP_Calendar_Today_Template::render( $template, $today, $limit ) );
}

/**
 * 開始タグと終了タグの間を、テンプレートとして使うかを判定する。
 *
 * 前後の空白と <p>、</p>、<br> を除いて空なら空文字を、そうでなければ元の文字列を返す。
 *
 * @param string $content 開始タグと終了タグの間の HTML.
 * @return string
 */
function wp_calendar_today_template( string $content ): string {
	$rest = preg_replace( '#<\s*/?\s*p\s*>|<\s*br\s*/?\s*>#i', '', $content );
	return '' === trim( (string) $rest ) ? '' : $content;
}

/**
 * 管理者にだけ原因を表示する。
 *
 * @param string $message 原因.
 * @return string
 */
function wp_calendar_today_notice( string $message ): string {
	if ( ! current_user_can( 'manage_options' ) ) {
		return '';
	}
	wp_calendar_today_enqueue_style();
	return '<div class="wp-calendar-today wp-calendar-today--notice"><p>' . esc_html( '本日の予定：' . $message ) . '</p><p>' . esc_html( 'このメッセージは管理者にだけ表示しています。' ) . '</p></div>';
}

/**
 * プラグインの CSS を読み込む。ショートコードの展開は wp_head の後なので、フッターで出力される。
 */
function wp_calendar_today_enqueue_style(): void {
	wp_enqueue_style( 'wp-calendar-today-plugin', plugins_url( 'assets/css/wp-calendar-today-plugin.css', __FILE__ ), array(), WP_CALENDAR_TODAY_VERSION );
}
