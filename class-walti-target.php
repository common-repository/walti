<?php

class Walti_Target
{
	const STATUS_ACTIVE = 'active';
	const STATUS_UNCHECKED = 'unchecked';
	const STATUS_ARCHIVED = 'archived';

	const OWNERSHIP_UNKNOWN = 'unknown';
	const OWNERSHIP_QUEUED = 'queued';
	const OWNERSHIP_CONFIRMED = 'confirmed';

	/** @var string ステータス */
	private $status;

	/** @var string ホスト名 */
	private $hostname;

	/** @var Walti_Api APIオブジェクト */
	private $api;

	/** @var string 所有確認ファイルのURL */
	private $ownership_url;

	/** @var string 所有確認のステータス */
	private $ownership;

	/** @var Walti_Plugin[] このターゲットで実行可能なプラグイン */
	private $plugins;

	/**
	 * Waltiからターゲット情報を取得する
	 *
	 * @param Walti_Api $api APIオブジェクト
	 * @param string $hostname ホスト名
	 * @return mixed ターゲット情報。ターゲットが存在しない場合はfalseを返す。
	 */
	public static function fetch( Walti_Api $api, $hostname ) {
		$result = $api->get( "/v1/targets/{$hostname}" );
		if ( 400 == $result->getCode() ) {
			// ターゲットが存在しない場合
			return false;
		} elseif ( ! $result->isSucceeded() ) {
			throw new Exception( "ターゲット情報の取得時にエラーが発生しました。 code: {$result->getCode()}, body: {$result->getBody()}" );
		}
		$body = $result->getDecodedBody();
		$target = new self();
		$target->api = $api;
		$target->status = $body->status;
		$target->hostname = $body->name;
		$target->ownership_url = $body->ownership_url;
		$target->ownership = $body->ownership;
		foreach ( $body->plugins as $plugin ) {
			$plugin = Walti_Plugin::createFromApiResult( $plugin );
			$target->plugins[ $plugin->getName() ] = $plugin;
		}
		return $target;
	}

	/**
	 * Waltiにターゲット情報を登録する
	 *
	 * @param Walti_Api $api APIオブジェクト
	 * @param string $hostname ホスト名
	 */
	public static function register( Walti_Api $api, $hostname ) {
		$result = $api->post( '/v1/targets', array(
			'target[name]' => $hostname,
			'target[description]' => 'WordPress',
		) );
		if ( ! $result->isSucceeded() ) {
			throw new Exception( "ターゲット情報の登録時にエラーが発生しました。 code: {$result->getCode()}, body: {$result->getBody()}" );
		}
	}

	/**
	 * APIオブジェクトをセットする
	 *
	 * @param Walti_Api $api APIオブジェクト
	 */
	public function setApi( Walti_Api $api ) {
		$this->api = $api;
	}

	/**
	 * このターゲットが所有確認済かどうか判定する
	 *
	 * @return bool 所有確認済の場合はtrue、そうでない場合はfalseを返す
	 */
	public function isActivated() {
		return self::STATUS_ACTIVE == $this->getStatus();
	}

	/**
	 * このターゲットがアーカイブ済かどうか判定する
	 *
	 * @return bool アーカイブ済の場合はtrue、そうでない場合はfalseを返す
	 */
	public function isArchived() {
		return self::STATUS_ARCHIVED == $this->getStatus();
	}

	/**
	 * 所有確認のステータスを取得する
	 *
	 * @return string 所有確認のステータス
	 */
	public function getOwnershipStatus() {
		return $this->ownership;
	}

	/**
	 * アクティベーションを実行する
	 *
	 * @param string $root_dir ドキュメントルートのディレクトリ
	 */
	public function activate( $root_dir ) {
		if ( ! $this->isApiSet() ) {
			throw new Exception( 'APIオブジェクトがセットされていません。' );
		}
		// 認証ファイルがなければ設置
		if ( ! $this->existsOwnerFile( $root_dir ) ) {
			if ( ! is_writable( $root_dir ) ) {
				throw new Exception( 'ドキュメントルートに認証ファイルを作成する権限がありません。Walti.ioにログインし、画面の指示に従って手動で所有者確認を実行してください。' );
			}
			$this->fetchOwnershipFile( $root_dir );
		}
		$this->doActivate();
	}

	/**
	 * このターゲットのホスト名を取得する
	 *
	 * @return string ホスト名
	 */
	public function getName() {
		return $this->hostname;
	}

	/**
	 * 認証ファイルのURLを取得する
	 *
	 * @return string 認証ファイルのURL
	 */
	public function getOwnershipUrl() {
		return $this->ownership_url;
	}

	/**
	 * ターゲットの状態を取得する
	 *
	 * @return int 所有未確認/所有確認済/アーカイブ のいずれかを示す定数
	 */
	public function getStatus() {
		return $this->status;
	}

	/**
	 * ステータスの文字列表現を取得する
	 *
	 * @return string ステータス文字列
	 */
	public function getStatusString() {
		switch ( $this->getStatus() ) {
			case self::STATUS_ARCHIVED:
			return 'アーカイブ';
			case self::STATUS_ACTIVE:
			return 'アクティブ';
			case self::STATUS_UNCHECKED:
				if ( self::OWNERSHIP_QUEUED == $this->ownership ) {
					return '所有確認中';
				}
			return '所有未確認';
			default:
			throw new Exception( "不明なステータス status={$this->getStatus()}" );
		}
	}

	/**
	 * 認証ファイルの名前を取得する
	 *
	 * @return string 認証ファイル名
	 */
	public function getOwnershipFileName() {
		$query_str = parse_url( $this->getOwnershipUrl(), PHP_URL_QUERY );
		parse_str( $query_str, $query );
		return 'walti' . $query['key'] . '.html';
	}

	/**
	 * スキャンを登録する
	 *
	 * @param	string	$scan_type		スキャンの種類
	 * @return Walti_ApiResult API結果
	 */
	public function queueScan( $scan_type ) {
		if ( ! $this->isApiSet() ) {
			throw new Exception( 'APIオブジェクトがセットされていない' );
		}

		$params = array();
		if ( 'skipfish' == $scan_type ) {
			$params['force'] = 'true';
		}

		$result = $this->api->post( sprintf( '/v1/targets/%s/plugins/%s/scans', urlencode( $this->getName() ), urlencode( $scan_type ) ), $params );

		if ( '402' != $result->getCode() && ! $result->isSucceeded() ) {
			throw new Exception( "スキャンの登録時にエラーが発生しました。 code: {$result->getCode()}, body: {$result->getBody()}" );
		}
		return $result;
	}

	/**
	 * プラグイン情報を取得する
	 *
	 * @param string $plugin_name プラグイン名
	 * @return Walti_Plugin
	 */
	public function getPlugin( $plugin_name ) {
		if ( ! isset( $this->plugins[ $plugin_name ] ) ) {
			throw new Exception( "プラグイン:{$plugin_name}の情報がありませんでした。" );
		}
		return $this->plugins[ $plugin_name ];
	}

	/**
	 * このターゲットで利用可能なプラグインを取得する
	 *
	 * @return Walti_Plugin[] 利用可能なプラグイン情報の配列
	 */
	public function getPlugins() {
		return $this->plugins;
	}

	/**
	 * APIをコールしてスキャン結果を取得する
	 *
	 * @return self
	 */
	public function fetchScans() {
		$this->scans = array();
		$result = $this->api->get( sprintf( '/v1/targets/%s/scans', $this->getName() ) );
		if ( ! $result->isSucceeded() ) {
			throw new Exception( "スキャン結果取得時にエラーが発生しました。 code: {$result->getCode()}, body: {$result->getBody()}" );
		}
		$scans = $result->getDecodedBody();
		foreach ( $scans as $scan ) {
			$scan_obj = Walti_Scan::createFromApiResult( $scan );
			$this->scans[ $scan_obj->getPluginName() ][] = $scan_obj;
		}
		return $this;
	}

	/**
	 * プラグイン毎に直近のスキャン結果を返す
	 *
	 * @return Walti_Scan[] プラグイン毎のスキャン結果を格納した連想配列(キーはプラグイン名)
	 */
	public function getLatestScans() {
		$latest_scans = array();
		foreach ( array_keys( $this->scans ) as $plugin_name ) {
			$latest_scans[ $plugin_name ] = $this->getLatestScan( $plugin_name );
		}
		return $latest_scans;
	}

	/**
	 * 指定されたプラグインの直近のスキャン結果を返す
	 *
	 * @param string $plugin_name プラグイン名
	 * @return array 直近のスキャン結果
	 */
	public function getLatestScan( $plugin_name ) {
		if ( ! isset( $this->scans[ $plugin_name ] ) ) {
			return Walti_Scan::createEmpty( $plugin_name );
		}
		return reset( $this->scans[ $plugin_name ] );
	}

	/**
	 * APIオブジェクトがセットされているかどうか
	 *
	 * @return bool
	 */
	private function isApiSet() {
		return isset( $this->api );
	}

	/**
	 * 認証ファイルをサーバから取得する
	 *
	 * @param string $root_dir 保存先パス
	 */
	public function fetchOwnershipFile( $root_dir ) {
		$args = array();
		$res = wp_remote_get( $this->getOwnershipUrl(), $args );

		if ( is_wp_error( $res ) ) {
			throw new Exception( "認証ファイルの取得時にエラーが発生しました。 code: {$res->get_error_code()}" );
		}

		$status = wp_remote_retrieve_response_code( $res );
		$body = wp_remote_retrieve_body( $res );
		if ( '403' == $status && false !== strpos( $body, 'Error 1013' ) && function_exists( 'curl_version' ) ) {
			throw new Exception( 'SNIで指定されたServerNameがHTTPのHostヘッダと一致しませんでした。システムにインストールされているライブラリのバグが原因となっている可能性があるため、curlのアップデートをお試しください。 参考URL: https://github.com/curl/curl/commit/958d2ffb' );
		} elseif ( '200' != $status ) {
			throw new Exception( "認証ファイルの取得時にエラーが発生しました。 status: {$status}, body: {$body}" );
		}

		$save_path = rtrim( $root_dir, '/' ) . '/' . $this->getOwnershipFileName();
		if ( false === file_put_contents( $save_path, $body ) ) {
			throw new Exception( '認証ファイルの保存に失敗しました。' );
		}
	}

	/**
	 * 所有確認ファイルが存在するかどうか
	 *
	 * @param string $root_dir ドキュメントルートのディレクトリ
	 */
	public function existsOwnerFile( $root_dir ) {
		$owner_file = rtrim( $root_dir, '/' ) . '/' . $this->getOwnershipFileName();
		return file_exists( $owner_file );
	}

	/**
	 * 認証ファイルを削除する
	 *
	 */
	public function deleteOwnershipFile( $root_dir ) {
		if ( ! is_writable( $root_dir ) ) {
			throw new Exception( '認証ファイルを削除する権限がありません。' );
		}
		$owner_file = rtrim( $root_dir, '/' ) . '/' . $this->getOwnershipFileName();
		if ( false === unlink( $owner_file ) ) {
			throw new Exception( '認証ファイルの削除に失敗しました。' );
		}
	}

	/**
	 * スキャンスケジュールを更新する
	 *
	 */
	public function updateSchedules() {
		foreach ( $this->getPlugins() as $plugin ) {
			$result = $this->api->put( "/v1/targets/{$this->getName()}/plugins/{$plugin->getName()}", array( 'plugin[schedule]' => $plugin->getSchedule() ) );
			if ( ! $result->isSucceeded() ) {
				throw new Exception( "スキャンスケジュールの登録時にエラーが発生しました。 code: {$result->getCode()}, body: {$result->getBody()}" );
			}
		}
	}

	/**
	 * 所有確認APIをコールする
	 *
	 */
	private function doActivate() {
		$result = $this->api->post( "/v1/targets/{$this->getName()}/activate" );
		// TODO 認証ファイルの確認に失敗した時は422が返る
		if ( ! $result->isSucceeded() ) {
			throw new Exception( "所有確認実行時にエラーが発生しました。 code: {$result->getCode()}, body: {$result->getBody()}" );
		}
	}
}
