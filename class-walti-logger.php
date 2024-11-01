<?php

class Walti_Logger
{
	/**
	 * ログを出力する
	 *
	 * @param mixed $content
	 */
	public static function log( $content ) {
		$msg = '';
		if ( $content instanceof Exception ) {
			$msg = $content->getMessage() . PHP_EOL . $content->getTraceAsString();
		} else {
			$msg = $content;
		}
		error_log( $msg );
	}
}
