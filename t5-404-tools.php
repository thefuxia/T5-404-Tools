<?php # -*- coding: utf-8 -*-
declare ( encoding = 'UTF-8' );
/**
 * Plugin Name: T5 404 Tools
 * Plugin URI:  https://github.com/toscho/T5-404-Tools
 * Description: Sends an email to the admin email adress for each 404 request not coming from Google or Yahoo. Serves a 404 image for image requests.
 * Version:     2012.03.04
 * Required:    3.3
 * Author:      Thomas Scholz <info@toscho.de>
 * Author URI:  http://toscho.de
 * License:     MIT
 * License URI: http://www.opensource.org/licenses/mit-license.php
 *
 * Copyright (c) 2012 Thomas Scholz
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

// Not a WordPress context? Stop.
! defined( 'ABSPATH' ) and exit;

// Wait until last useful moment.
add_filter( '404_template', array ( 'T5_404_Tools', 'init' ) );
add_filter( 'plugin_row_meta', array( 'T5_404_Tools', 'add_feedback_link' ), 10, 2 );
/**
 * Enhance 404 request handling. Besides that, just a poor man’s namespace. :)
 *
 * @author Thomas Scholz, <info@toscho.de>
 */
class T5_404_Tools
{
	/**
	 * Creates a new instance.
	 *
	 * @wp-hook 404_template
	 * @param  string $template The template file. We pass this through.
	 * @see    __construct()
	 * @return string $template
	 */
	public static function init( $template )
	{
		new self;
		return $template;
	}

	/**
	 * Constructor. Does all the work.
	 *
	 * @wp-hook 404_template
	 * @return void
	 */
	public function __construct()
	{
		! $this->is_crawler_test() and $this->send_notice();
		$this->is_image_request()  and $this->serve_404_image();
	}

	/**
	 * Sends an email.
	 *
	 * @wp-hook 404_template
	 * @uses   apply_filters() 't5_404_recipient' and 't5_404_subject'
	 * @return void
	 */
	protected function send_notice()
	{
		$to      = get_option( 'admin_email' );
		$to      = apply_filters( 't5_404_recipient', $to );
		$blog    = get_bloginfo( 'name' );
		$subject = "404 $blog: " . $_SERVER['REQUEST_URI'];
		$subject = apply_filters( 't5_404_subject', $subject );

		wp_mail( $to, $subject, $this->mail_body(), $this->mail_headers() );
	}

	/**
	 * Create basic mail headers.
	 *
	 * @wp-hook 404_template
	 * @uses   apply_filters() 't5_404_mail_headers'
	 * @return array
	 */
	protected function mail_headers()
	{
		$headers = array (
			'From:Error-Messenger <' . get_option( 'admin_email' ) . '>',
			// For software with automatic mailing list detection like Opera.
			'List-Id:"404" 404.List'
		);
		$headers = apply_filters( 't5_404_mail_headers', $headers );
		return $headers;
	}

	/**
	 * Collect request data and create a message body.
	 *
	 * @wp-hook 404_template
	 * @uses   apply_filters() 't5_404_mail_body'
	 * @return string
	 */
	protected function mail_body()
	{
		$msg_data = array (
			'Time' => date(
				get_option( 'date_format') . ' ' . get_option( 'time_format' )
			)
		);

		$msg_data = array_merge(
			$msg_data,
			$this->get_ip_data(),
			$this->get_server_data()
		);

		$msg = $_SERVER['REQUEST_METHOD'] . ' <' . $this->get_request_uri()
			. ">\r\n\r\n";

		foreach ( $msg_data as $key => $data )
		{
			// Some vertical alignment. :)
			$msg .= str_pad( "$key:", 23, ' ' ) . "$data\r\n";
		}

		$msg = apply_filters( 't5_404_mail_body',  trim( $msg ) );
		return $msg;
	}

	/**
	 * Full, clickable 404 URI.
	 *
	 * @wp-hook 404_template
	 * @return string
	 */
	protected function get_request_uri()
	{
		$scheme = 'http' . ( is_ssl() ? '' : 's' ) . '://';
		return $scheme . $_SERVER['HTTP_HOST']
			. rawurldecode( $_SERVER['REQUEST_URI'] );
	}

	/**
	 * Collect data from different server variables.
	 *
	 * @wp-hook 404_template
	 * @uses   apply_filters() 't5_404_server_fields'
	 * @return array
	 */
	protected function get_server_data()
	{
		$fields = array (
			'HTTP_REFERER',
			'HTTP_USER_AGENT'
		);
		$fields = apply_filters( 't5_404_server_fields', $fields );

		$out = array ();
		foreach ( $fields as $field )
		{
			$out[ $field ] = isset ( $_SERVER[ $field ] )
				? $_SERVER[ $field ]
				: '(no value)';
		}

		return $out;
	}

	/**
	 * Collect data from different IP address fields.
	 *
	 * @wp-hook 404_template
	 * @uses   apply_filters() 't5_404_ip_fields' and 't5_404_ip_check_url'
	 * @return array
	 */
	protected function get_ip_data()
	{
		$fields = array (
			'HTTP_X_FORWARDED_FOR',
			'REMOTE_ADDR',
			'HTTP_CLIENT_IP'
		);
		$fields = apply_filters( 't5_404_ip_fields', $fields );
		$url    = apply_filters(
			't5_404_ip_check_url',
			'http://ip.toscho.de/?ip=%s'
		);

		$out = array ();
		foreach ( $fields as $field )
		{
			isset ( $_SERVER[ $field ] )
				and $out[ $field ] = sprintf( $url, $_SERVER[ $field ] );
		}

		return $out;
	}

	/**
	 * Loads and sends a 404 image. Stops any further processing.
	 *
	 * @wp-hook 404_template
	 * @uses   apply_filters() 't5_404_img_path' and 't5_404_img_type'
	 * @return void
	 */
	protected function serve_404_image()
	{
		// Try to find a 404 image in the current theme.
		$path = locate_template( array ( '404.png', '404.jpg', '404.gif' ) );

		if ( '' == $path )
		{
			$path = apply_filters( 't5_404_img_path', __DIR__ . '/404.png' );
			$type = apply_filters( 't5_404_img_type', 'png' );
		}
		else
		{
			$type = pathinfo( $path, PATHINFO_EXTENSION );
		}

		header( "Content-Type: image/$type" );
		require $path;
		exit;
	}

	/**
	 * Checks $_SERVER['REQUEST_URI'] if it searches for an image.
	 *
	 * @wp-hook 404_template
	 * @return bool
	 */
	protected function is_image_request()
	{
		return ! empty ( $_SERVER['REQUEST_URI'] )
			&& preg_match(
				'~\.(jpe?g|png|gif|svg|bmp)(\?.*)?$~i',
				$_SERVER['REQUEST_URI']
			);
	}

	/**
	 * Checks if a crawler from Google or Yahoo is testing your 404 status header.
	 *
	 * @wp-hook 404_template
	 * @return bool
	 */
	protected function is_crawler_test()
	{
			// Google Webmaster Tools checks if you really serve a 404 status
			// for non-existing files. No need to send a notice.
		if ( FALSE !== strpos( $_SERVER['REQUEST_URI'], 'noexist' )
			// same for Yahoo!
			or FALSE !== strpos( $_SERVER['REQUEST_URI'], 'SlurpConfirm' )
		)
		{
			return TRUE;
		}
	}

	/**
	 * Adds a link to the GitHub bug tracker.
	 *
	 * @wp-hook plugin_row_meta
	 * @param  array  $links Already existing links.
	 * @return string
	 */
	public static function add_feedback_link( $links, $file )
	{
		static $base_name = '';
		'' == $base_name and $base_name = plugin_basename( __FILE__ );

		if ( $base_name == $file )
		{
			$links[]  = "<a href='https://github.com/toscho/T5-404-Tools/issues'>Send feedback</a>";
		}

		return $links;
	}
}