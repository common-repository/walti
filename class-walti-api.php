<?php

class Walti_Api
{
	private $credentials;

	/**
	 * APIオブジェクトを生成する
	 *
	 * @param Walti_Credentials $credentials 認証情報
	 * @return self
	 */
	public function __construct( Walti_Credentials $credentials ) {
		$this->credentials = $credentials;
	}

	/**
	 * 認証情報を検証する
	 *
	 * @return book 認証OKの場合はtrue、そうでない場合はfalseを返す
	 */
	public function authenticate() {
		$args = array(
			'headers' => $this->makeHeaders(),
			'method' => 'GET',
			'timeout' => WALTI_API_TIMEOUT,
		);

		$res = wp_remote_get( WALTI_API_URL . '/v1/me', $args );
		if ( is_wp_error( $res ) ) {
			throw new Exception( "通信時にエラーが発生しました。 code: {$res->get_error_code()}, message: {$res->get_error_message()}" );
		}

		$status = wp_remote_retrieve_response_code( $res );
		$body = wp_remote_retrieve_body( $res );
		if ( '403' == $status && false !== strpos( $body, 'Error 1013' ) && function_exists( 'curl_version' ) ) {
			throw new Exception( 'SNIで指定されたServerNameがHTTPのHostヘッダと一致しませんでした。システムにインストールされているライブラリのバグが原因となっている可能性があるため、curlのアップデートをお試しください。 参考URL: https://github.com/curl/curl/commit/958d2ffb' );
		}

		$result = new Walti_ApiResult( $res );
		if ( ! $result->authSucceeds() ) {
			return false;
		}
		if ( ! $result->isSucceeded() ) {
			throw new Exception( "認証情報取得時のレスポンスが不正です。 code: {$result->getCode()}, body: {$result->getBody()}" );
		}
		return true;
	}

	/**
	 * 指定されたエンドポイントに対してGETリクエストを実行する
	 *
	 * @param string $path エンドポイント
	 * @return Walti_ApiResult リクエスト結果
	 */
	public function get( $path ) {
		return $this->request( 'GET', $path );
	}

	/**
	 * 指定されたエンドポイントに対してPOSTリクエストを実行する
	 *
	 * @param string $path エンドポイント
	 * @param array $params APIに渡すパラメータの連想配列
	 * @return Walti_ApiResult リクエスト結果
	 */
	public function post( $path, $params = array() ) {
		return $this->request( 'POST', $path, $params );
	}

	/**
	 * 指定されたエンドポイントに対してPUTリクエストを実行する
	 *
	 * @param string $path エンドポイント
	 * @param array $params APIに渡すパラメータの連想配列
	 * @return Walti_ApiResult リクエスト結果
	 */
	public function put( $path, $params = array() ) {
		return $this->request( 'PUT', $path, $params );
	}

	/**
	 * 指定されたエンドポイントに対してリクエストを実行する
	 *
	 * @param string $method 使用するHTTPメソッド
	 * @param string $path エンドポイント
	 * @param array $params APIに渡すパラメータの連想配列
	 * @throws WaltiAuthException 認証情報に不備がある場合にスローされる
	 * @return Walti_ApiResult リクエスト結果
	 */
	public function request( $method, $path, $params = array() ) {
		$args['headers'] = $this->makeHeaders();
		$args['method'] = strtoupper( $method );
		$args['timeout'] = WALTI_API_TIMEOUT;
		if ( ! empty( $params ) ) {
			$args['body'] = $params;
		}
		$res = wp_remote_request( WALTI_API_URL . $path, $args );
		if ( is_wp_error( $res ) ) {
			throw new Exception( "通信時にエラーが発生しました。 code: {$res->get_error_code()}, message: {$res->get_error_message()}" );
		}

		$status = wp_remote_retrieve_response_code( $res );
		$body = wp_remote_retrieve_body( $res );
		if ( '403' == $status && false !== strpos( $body, 'Error 1013' ) && function_exists( 'curl_version' ) ) {
			throw new Exception( 'SNIで指定されたServerNameがHTTPのHostヘッダと一致しませんでした。システムにインストールされているライブラリのバグが原因となっている可能性があるため、curlのアップデートをお試しください。 参考URL: https://github.com/curl/curl/commit/958d2ffb' );
		}

		$result = new Walti_ApiResult( $res );
		if ( ! $result->authSucceeds() ) {
			throw new WaltiAuthException( 'APIキーまたはAPIシークレットが正しくありません。設定画面から正しい値を入力してください。' );
		}
		return $result;
	}

	/**
	 * APIへのリクエストの際に付与するヘッダを生成する
	 *
	 * @return array
	 */
	protected function makeHeaders() {
		$headers = array( 'Api-Key' => $this->credentials->getKey(), 'Api-Secret' => $this->credentials->getSecret() );
		if ( WP_DEBUG && defined( 'WALTI_BASIC_AUTH_USER' ) && defined( 'WALTI_BASIC_AUTH_PASS' ) ) {
			$headers['Authorization'] = 'Basic ' . base64_encode( WALTI_BASIC_AUTH_USER . ':' . WALTI_BASIC_AUTH_PASS );
		}
		return $headers;
	}
}
