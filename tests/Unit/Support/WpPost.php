<?php

namespace Zoviz\Tests\Unit\Support;

/**
 * Minimal stand-in for WP_Post: unit tests run without WordPress loaded, so
 * classes hooked to post-shaped filters (media_row_actions,
 * attachment_fields_to_edit, ...) get a plain object with the properties
 * they actually read.
 */
class WpPost {

	/** @var int */
	public $ID;

	/** @var string */
	public $post_mime_type;

	/**
	 * @param int    $id        Post id.
	 * @param string $mime_type MIME type.
	 */
	public function __construct( $id, $mime_type ) {
		$this->ID             = $id;
		$this->post_mime_type = $mime_type;
	}
}
