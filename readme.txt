=== WP Today Schedule ===
Contributors: shingonoguchi
Tags: calendar, schedule, shortcode, today
Requires at least: 6.5
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

カレンダープラグイン「Event Calendar Maker」に登録した今日の予定を、ショートコードで小さなバッジとして表示します。

== Description ==

Event Calendar Maker（株式会社 8bit）で登録した予定のうち、今日の分だけを取り出して表示するプラグインです。
トップページに置くと、今日の日付と曜日、その日の予定が並びます。

* 予定名は、カレンダーで付けたラベルの色で表示します。
* 予定の説明は、登録した文字のままラベルの後ろに出ます。開館時間などに使えます。
* バッジ全体を、月間カレンダーのページへのリンクにできます。
* 祝日は曜日に「祝」が付きます。
* 予定のない日は、日付と曜日だけを出します。
* 開始タグと終了タグの間に HTML を書くと、自分の HTML で表示できます。

Event Calendar Maker の無料版でも有料版でも動きます。
Event Calendar Maker が有効になっていないと何も表示されず、管理者にだけ原因が出ます。

== Installation ==

1. 「プラグイン」>「新規プラグインを追加」>「プラグインのアップロード」から ZIP をインストールし、有効化します。
2. Event Calendar Maker でカレンダーと予定を登録し、カレンダーの「フィールド名」を控えます。
3. バッジを出したいページに、次のショートコードを置きます。

`[today_schedule hash="フィールド名" link="/calendar/"]`

* `hash`：カレンダーのフィールド名。必須です。
* `link`：バッジを押したときの移動先。省略するとリンクになりません。
* `limit`：並べる予定の数。省略すると全部並べます。

== Frequently Asked Questions ==

= 何も表示されません =

管理者でログインしてそのページを開くと、原因が表示されます。
フィールド名の書き間違いか、Event Calendar Maker が有効になっていないことがほとんどです。

= 登録した予定が出ません =

予定の日付が、サイトの「タイムゾーン」設定での今日になっているか確かめてください。
反映まで数秒かかることがあります。

= 時刻はどこに書きますか =

Event Calendar Maker には時刻の項目がありません。
「開館時間 10:00 – 18:00」のように、予定の「説明」に書くと、その文字がラベルの後ろに出ます。

= 表示の HTML を変えられますか =

ショートコードを `[today_schedule hash="..."]` と `[/today_schedule]` で囲み、その間に HTML を書きます。
`{year}`、`{month}`、`{day}`、`{weekday}`、`{event_name}`、`{event_description}`、`{event_color}` などの場所に今日の値が入ります。
一覧は GitHub リポジトリ（https://github.com/noguchi/wp-calendar-today-plugin）の docs/spec.md にあります。

== Screenshots ==

1. トップページに置いたバッジ。今日の日付と、ラベルの色付きの予定が並ぶ。
2. バッジを押すと移る、Event Calendar Maker の月間カレンダー。

== Changelog ==

= 0.1.0 =
* 最初のリリース。
