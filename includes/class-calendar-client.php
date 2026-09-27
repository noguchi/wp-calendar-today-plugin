<?php
/**
 * カレンダープラグインの REST から今日の予定を取る。
 *
 * @package WP_Today_Schedule
 */

defined( 'ABSPATH' ) || exit;

/**
 * 今月分を REST で取って transient に保存し、今日の要素だけを返す。
 */
class WP_Today_Schedule_Calendar_Client {

	const TRANSIENT_PREFIX = 'wp_today_schedule_';
	const CACHE_TTL        = 5;
	const ROUTE            = '/8bit-limited-calendars/v1/calendar';

	/**
	 * 今日の祝日と予定を返す。
	 *
	 * 取得の失敗は予定 0 件として返す。カレンダープラグインの停止、hash の未指定、hash の誤りは WP_Error で返す。
	 *
	 * @param string            $hash  カレンダーのフィールド名.
	 * @param DateTimeImmutable $today サイトのタイムゾーンでの今日.
	 * @return array{date: string, holiday: string, events: array<int, array{name: string, description: string, url: string, color: string}>}|WP_Error
	 */
	public function today( string $hash, DateTimeImmutable $today ): array|WP_Error {
		if ( ! class_exists( 'LimitedCalendar8bit' ) ) {
			return new WP_Error( 'plugin_inactive', 'カレンダープラグイン（Event Calendar Maker）が有効になっていません。' );
		}
		if ( '' === $hash ) {
			return new WP_Error( 'missing_hash', 'hash 属性が指定されていません。' );
		}

		$date   = $today->format( 'Y-m-d' );
		$result = array(
			'date'    => $date,
			'holiday' => '',
			'events'  => array(),
		);
		$data   = $this->fetch_month( $hash, $today );
		if ( is_wp_error( $data ) ) {
			return 'unknown_hash' === $data->get_error_code() ? $data : $result;
		}

		$days = $data['calendars'][0]['calendar'] ?? array();
		foreach ( is_array( $days ) ? $days : array() as $day ) {
			if ( ! is_array( $day ) || ( $day['date'] ?? '' ) !== $date ) {
				continue;
			}
			$holiday           = $day['holiday'] ?? '';
			$result['holiday'] = is_string( $holiday ) ? $holiday : '';
			$events            = $day['events'] ?? array();
			foreach ( is_array( $events ) ? $events : array() as $event ) {
				$name = is_array( $event ) && is_scalar( $event['event_name'] ?? null ) ? trim( (string) $event['event_name'] ) : '';
				if ( '' === $name ) {
					continue;
				}
				$description        = $event['event_description'] ?? '';
				$url                = $event['link_text'] ?? '';
				$color              = $event['color'] ?? '';
				$color              = is_string( $color ) ? strtolower( trim( $color ) ) : '';
				$result['events'][] = array(
					'name'        => $name,
					'description' => is_string( $description ) ? trim( $description ) : '',
					'url'         => is_string( $url ) && preg_match( '#\Ahttps?://#i', $url ) ? $url : '',
					'color'       => preg_match( '/\A[a-z0-9_-]+\z/', $color ) ? $color : '',
				);
			}
			break;
		}
		return $result;
	}

	/**
	 * 今日を含む月の応答を、transient か REST から返す。
	 *
	 * @param string            $hash  カレンダーのフィールド名.
	 * @param DateTimeImmutable $today サイトのタイムゾーンでの今日.
	 * @return array<string, mixed>|WP_Error
	 */
	private function fetch_month( string $hash, DateTimeImmutable $today ): array|WP_Error {
		$key    = self::transient_key( $hash, $today );
		$cached = get_transient( $key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$failed = static function ( string $reason ): WP_Error {
			error_log( 'wp-today-schedule-plugin: ' . $reason ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- 取得の失敗を記録する。
			return new WP_Error( 'fetch_failed', $reason );
		};
		try {
			$request = new WP_REST_Request( 'GET', self::ROUTE );
			$request->set_query_params(
				array(
					'hash'  => $hash,
					'year'  => (int) $today->format( 'Y' ),
					'month' => (int) $today->format( 'n' ),
				)
			);
			$response = rest_do_request( $request );
		} catch ( Throwable $e ) {
			return $failed( 'REST の呼び出しで例外が起きました：' . $e->getMessage() );
		}
		if ( $response->is_error() || 200 !== $response->get_status() ) {
			return $failed( 'REST がステータス ' . $response->get_status() . ' を返しました。' );
		}

		$data = json_decode( (string) wp_json_encode( $response->get_data() ), true );
		if ( ! is_array( $data ) || ! is_array( $data['calendars'] ?? null ) ) {
			return $failed( 'REST の応答に calendars がありません。' );
		}
		if ( array() === $data['calendars'] ) {
			return new WP_Error( 'unknown_hash', 'hash「' . $hash . '」のカレンダーが見つかりません。' );
		}
		set_transient( $key, $data, self::CACHE_TTL );
		return $data;
	}

	/**
	 * 月ごとの応答を保存する transient のキーを返す。
	 *
	 * @param string            $hash  カレンダーのフィールド名.
	 * @param DateTimeImmutable $today サイトのタイムゾーンでの今日.
	 * @return string
	 */
	public static function transient_key( string $hash, DateTimeImmutable $today ): string {
		return self::TRANSIENT_PREFIX . md5( $hash ) . '_' . $today->format( 'Ym' );
	}
}
