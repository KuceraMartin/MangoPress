<?php

/*
Plugin Name: zzz-imgproxy
Description: Dynamic image resizing
Author: manGoweb / Mikulas Dite
Version: 1.0
Author URI: https://www.mangoweb.cz
*/

function flog() {
	$raw = json_encode(func_get_args());
	file_put_contents('/dev/ttys008', date('Y-m-d H:i:s') . ": $raw\n", FILE_APPEND);
}

add_action( 'plugins_loaded', 'imgproxy_init' );

require_once ABSPATH . WPINC . '/class-wp-image-editor.php';
require_once ABSPATH . WPINC . '/class-wp-image-editor-imagick.php';

function imgproxy_init() {
	add_filter('wp_image_editors', 'imgproxy_noop_editor', 50, 1);
	add_filter('image_downsize', 'imgproxy_image_downsize', 99, 3 );
}

function imgproxy_image_downsize($param, $id, $size = 'medium') {
	if ($size === 'full') {
		return false;
	}

	// get dimensions for requested size
	$width = get_option( "${size}_size_w" );
	$height = get_option( "${size}_size_h" );

	// get original url
	$url = wp_get_attachment_image_url($id, 'full', false);

	return [improxy_url($url, $width, $height), $width, $height, true];
}

function improxy_url($url, $width, $height) {
	$key = '0aa4c34cb6636fb8d4deacd150b1c7b4';
	$salt = '20030cf6ef8fc43168d77a7c05f4cd31';
	$keyBin = pack("H*" , $key);
	if(empty($keyBin)) {
		die('Key expected to be hex-encoded string');
	}
	$saltBin = pack("H*" , $salt);
	if(empty($saltBin)) {
		die('Salt expected to be hex-encoded string');
	}
	$resize = 'fill';
	$gravity = 'no';
	$enlarge = 1;
	$extension = 'jpg';
	$encodedUrl = rtrim(strtr(base64_encode($url), '+/', '-_'), '=');
	$path = sprintf("/%s/%d/%d/%s/%d/%s.%s", $resize, $width, $height, $gravity, $enlarge, $encodedUrl, $extension);
	$signature = rtrim(strtr(base64_encode(hash_hmac('sha256', $saltBin.$path, $keyBin, true)), '+/', '-_'), '=');
	return 'https://imgproxy.mangoweb.org' . sprintf("/%s%s", $signature, $path);
}

function imgproxy_noop_editor($editors) {
	return ['WP_Image_Editor_Noop'];
}

// trigger function autoload
try {
	new ReflectionClass('S3_Uploads_Image_Editor_Imagick');
} catch (Throwable $_) {}
if (class_exists('S3_Uploads_Image_Editor_Imagick')) {
	class Imgproxy_Parent extends S3_Uploads_Image_Editor_Imagick {}
} else {
	class Imgproxy_Parent extends WP_Image_Editor_Imagick {}
}

class WP_Image_Editor_Noop extends Imgproxy_Parent
{

	public function save($destfilename = null, $mime_type = null)
	{
		flog(__FUNCTION__);
		$x = parent::save($destfilename, $mime_type);
		flog($x);
		return $x;
	}


	// Dummy method that instead of resizing only returns
	// the metadata, which is later send to imgproxy.
	public function multi_resize($sizes)
	{
		flog(__FUNCTION__, xdebug_get_stack_depth());
		$return = [];
		foreach ($sizes as $size => $info) {
			$return[$size] = [
				'path' => 'http://path' . $this->file,
				'file' => 'http://file' . $this->file,
				'width' => $info['width'],
				'height' => $info['height'],
				'mime-type' => $this->mime_type,
			];
		}
		return $return;
	}

}
