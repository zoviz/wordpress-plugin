<?php
/**
 * Downloaded result file value object.
 *
 * @package Zoviz
 */

namespace Zoviz\DeveloperApi\Api;

/**
 * A binary job result downloaded to a local temporary file.
 */
final class DownloadedFile {

	/**
	 * Absolute path of the downloaded file.
	 *
	 * @var string
	 */
	private $path;

	/**
	 * Content type reported by the API.
	 *
	 * @var string
	 */
	private $content_type;

	/**
	 * Constructor.
	 *
	 * @param string $path         Absolute file path.
	 * @param string $content_type Content type.
	 */
	public function __construct( $path, $content_type ) {
		$this->path         = (string) $path;
		$this->content_type = (string) $content_type;
	}

	/**
	 * Absolute file path.
	 *
	 * @return string
	 */
	public function path() {
		return $this->path;
	}

	/**
	 * Content type.
	 *
	 * @return string
	 */
	public function content_type() {
		return $this->content_type;
	}
}
