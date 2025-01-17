=== Walti ===
Contributors: walti_io
Tags: security, walti
Requires at least: 3.1
Tested up to: 4.6
Stable tag: 1.0.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Making Server-side Security Scans More Accessible.

== Description ==

This plugin enables you to run security scan via the [Walti](https://walti.io) web service.

= How To Use =

1. If you do not have a Walti account, you need to [sign up](https://console.walti.io/login) first to get your API credentials.
2. Enter your API credentials from _Walti Settings_ screen. There should be a link to the screen under _Settings_ category on your admin menu.
3. Register the host which WordPress is running on as a scan target. This plugin puts an authentication file automatically if WordPress has write permission for docroot, otherwise you have to fetch and place the file manually.
4. Now you can run scans and watch results from _Walti Scan_ screen.

= For Japanese users =

WordPressの管理画面から [Walti](https://walti.io) のセキュリティスキャンの実行、最新結果の確認ができるプラグインです。
スキャンを実行するためには、Waltiに [サインアップ](https://console.walti.io/login) し、APIキー・シークレットを取得する必要があります。
WordPressの実行ユーザがドキュメントルートに対して書き込み権限を持っている場合、スキャンターゲットの登録・認証ファイルの設置を1クリック行うことができます。

== Installation ==

1. You can install Walti plugin either via the WordPress.org plugin directory, or by uploading the files to your server.
2. Activate the plugin through _Plugins_ screen on WordPress admin panel.
3. Along the steps described at _Description_ section, enter your API credentials.

== Changelog ==

= 1.0.2

* Improve error handling.

= 1.0.1

* Remove scan type validation which blocks new scan types.
* Fix some bugs.

= 1.0.0

* Refine wording.
* Display appropriate message after target activation and reload until activateion is completed.

= 0.9.3

* Fix contributor metadata.

= 0.9.2

* Fix a bug that API credentials become useless when update this plugin.

= 0.9.1

* Open Walti sign-up page in a new tab when click a link in welcome message.

= 0.9.0

* Initial Release
