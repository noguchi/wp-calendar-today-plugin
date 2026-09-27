<?php
/**
 * テンプレートのプレースホルダーと区間を今日の値で埋める。
 *
 * @package WP_Today_Schedule
 */

defined( 'ABSPATH' ) || exit;

/**
 * テンプレートエンジン。WordPress に依存するのはエスケープ関数だけにする。
 */
class WP_Today_Schedule_Template {

	/**
	 * 組み込みのテンプレートの中身（外枠を除く）。
	 */
	const BUILTIN_BODY = '<time class="wp-today-schedule__date" datetime="{date}">{month}/{day}<span class="wp-today-schedule__dow">（{weekday}{holiday}・祝{/holiday}）</span></time>{has_events}<ul class="wp-today-schedule__events">{events}<li class="wp-today-schedule__event"><span class="wp-today-schedule__label wp-today-schedule__label--{event_color}">{event_name}</span><span class="wp-today-schedule__desc">{event_description}</span></li>{/events}</ul>{/has_events}{more}<span class="wp-today-schedule__more">ほか {more_count} 件</span>{/more}';

	/**
	 * 組み込みのテンプレートを返す。link が空なら外枠を div にする。
	 *
	 * @param string $link バッジのリンク先.
	 * @return string
	 */
	public static function builtin( string $link ): string {
		if ( '' === $link ) {
			return '<div class="wp-today-schedule"><div class="wp-today-schedule__badge">' . self::BUILTIN_BODY . '</div></div>';
		}
		return '<div class="wp-today-schedule"><a class="wp-today-schedule__badge" href="' . esc_url( $link ) . '">' . self::BUILTIN_BODY . '</a></div>';
	}

	/**
	 * テンプレートを今日の値で埋める。
	 *
	 * @param string                                                                                                                         $template テンプレート.
	 * @param array{date: string, holiday: string, events: array<int, array{name: string, description: string, url: string, color: string}>} $today    今日のデータ.
	 * @param int                                                                                                                            $limit    予定の最大件数。0 なら全件.
	 * @return string
	 */
	public static function render( string $template, array $today, int $limit ): string {
		$date     = new DateTimeImmutable( $today['date'] );
		$count    = count( $today['events'] );
		$limit    = $limit > 0 ? $limit : $count;
		$weekdays = array( '日', '月', '火', '水', '木', '金', '土' );
		$values   = array_map(
			'esc_html',
			array(
				'year'         => $date->format( 'Y' ),
				'month'        => $date->format( 'n' ),
				'day'          => $date->format( 'j' ),
				'month_padded' => $date->format( 'm' ),
				'day_padded'   => $date->format( 'd' ),
				'weekday'      => $weekdays[ (int) $date->format( 'w' ) ],
				'weekday_en'   => $date->format( 'D' ),
				'date'         => $date->format( 'Y-m-d' ),
				'holiday_name' => $today['holiday'],
				'event_count'  => (string) $count,
				'more_count'   => (string) max( 0, $count - $limit ),
			)
		);
		$sections = array(
			'has_events' => $count > 0,
			'no_events'  => 0 === $count,
			'more'       => $count > $limit,
			'holiday'    => '' !== $today['holiday'],
		);
		$events   = array();
		foreach ( array_slice( $today['events'], 0, $limit ) as $event ) {
			$url      = '' === $event['url'] ? '' : esc_url( $event['url'] );
			$name     = esc_html( $event['name'] );
			$events[] = array(
				'event_name'        => $name,
				'event_description' => esc_html( $event['description'] ),
				'event_url'         => $url,
				'event_link'        => '' === $url ? $name : '<a href="' . $url . '">' . $name . '</a>',
				'event_color'       => esc_attr( $event['color'] ),
			);
		}
		return self::expand( $template, $values, $sections, $events, null );
	}

	/**
	 * テンプレートを先頭から走査して埋める。埋めた値はもう一度走査しない。
	 *
	 * 区間は、開始タグから同じ範囲の中で最初に現れる同じ名前の終了タグまでとする。
	 * 対のないタグ、知らない名前、区間の外の予定の値は文字のまま残す。
	 *
	 * @param string                            $tpl      テンプレート.
	 * @param array<string, string>             $values   どこにでも書ける値.
	 * @param array<string, bool>               $sections 条件の区間と、出力するか.
	 * @param array<int, array<string, string>> $events   繰り返す予定の値.
	 * @param array<string, string>|null        $event    繰り返しのその回の予定の値.
	 * @return string
	 */
	private static function expand( string $tpl, array $values, array $sections, array $events, ?array $event ): string {
		$out = '';
		$pos = 0;
		while ( preg_match( '/\{(\/?)([a-z_]+)\}/', $tpl, $m, PREG_OFFSET_CAPTURE, $pos ) ) {
			$tag_start = $m[0][1];
			$out      .= substr( $tpl, $pos, $tag_start - $pos );
			$pos       = $tag_start + strlen( $m[0][0] );
			$name      = $m[2][0];
			if ( '/' === $m[1][0] ) {
				$out .= $m[0][0];
				continue;
			}
			if ( array_key_exists( $name, $sections ) || 'events' === $name ) {
				$close = strpos( $tpl, '{/' . $name . '}', $pos );
				if ( false === $close ) {
					$out .= $m[0][0];
					continue;
				}
				$inner = substr( $tpl, $pos, $close - $pos );
				$pos   = $close + strlen( '{/' . $name . '}' );
				if ( 'events' === $name ) {
					foreach ( $events as $ev ) {
						$out .= self::expand( $inner, $values, $sections, $events, $ev );
					}
				} elseif ( $sections[ $name ] ) {
					$out .= self::expand( $inner, $values, $sections, $events, $event );
				}
				continue;
			}
			if ( isset( $values[ $name ] ) ) {
				$out .= $values[ $name ];
			} elseif ( null !== $event && isset( $event[ $name ] ) ) {
				$out .= $event[ $name ];
			} else {
				$out .= $m[0][0];
			}
		}
		return $out . substr( $tpl, $pos );
	}
}
