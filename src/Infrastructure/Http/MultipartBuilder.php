<?php
/**
 * Multipart/form-data body builder.
 *
 * @package Zoviz
 */

namespace Zoviz\Infrastructure\Http;

/**
 * Builds a multipart/form-data request body from scalar fields and files.
 *
 * The body is assembled in memory (the WordPress HTTP API cannot stream
 * request bodies), so callers must enforce an upload size limit before
 * calling body().
 */
final class MultipartBuilder {

	/**
	 * Multipart boundary.
	 *
	 * @var string
	 */
	private $boundary;

	/**
	 * Scalar fields: list of [name, value].
	 *
	 * @var array<int, array{0: string, 1: string}>
	 */
	private $fields = array();

	/**
	 * Files: list of [name, path, filename, mime].
	 *
	 * @var array<int, array{0: string, 1: string, 2: string, 3: string}>
	 */
	private $files = array();

	/**
	 * Constructor.
	 *
	 * @param string|null $boundary Optional fixed boundary (tests); random otherwise.
	 */
	public function __construct( $boundary = null ) {
		$this->boundary = null !== $boundary ? $boundary : 'zoviz-' . bin2hex( random_bytes( 16 ) );
	}

	/**
	 * Adds a scalar field.
	 *
	 * @param string $name  Field name.
	 * @param string $value Field value.
	 * @return MultipartBuilder
	 */
	public function add_field( $name, $value ) {
		$this->fields[] = array( (string) $name, (string) $value );

		return $this;
	}

	/**
	 * Adds a file part.
	 *
	 * @param string $name     Field name.
	 * @param string $path     Absolute path of the file to send.
	 * @param string $filename Filename presented to the server.
	 * @param string $mime     MIME type of the file.
	 * @return MultipartBuilder
	 */
	public function add_file( $name, $path, $filename, $mime ) {
		$this->files[] = array( (string) $name, (string) $path, (string) $filename, (string) $mime );

		return $this;
	}

	/**
	 * The Content-Type header value for this body.
	 *
	 * @return string
	 */
	public function content_type() {
		return 'multipart/form-data; boundary=' . $this->boundary;
	}

	/**
	 * Total size in bytes of all attached files.
	 *
	 * @return int
	 */
	public function files_size() {
		$total = 0;

		foreach ( $this->files as $file ) {
			$size   = @filesize( $file[1] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Missing file handled explicitly in body().
			$total += false === $size ? 0 : (int) $size;
		}

		return $total;
	}

	/**
	 * Assembles the multipart body.
	 *
	 * @return string
	 * @throws \RuntimeException When an attached file cannot be read.
	 */
	public function body() {
		$body = '';

		foreach ( $this->fields as $field ) {
			$body .= '--' . $this->boundary . "\r\n";
			$body .= 'Content-Disposition: form-data; name="' . $this->escape( $field[0] ) . "\"\r\n\r\n";
			$body .= $field[1] . "\r\n";
		}

		foreach ( $this->files as $file ) {
			$contents = @file_get_contents( $file[1] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local temp file read; failure is thrown below.

			if ( false === $contents ) {
				throw new \RuntimeException(
					sprintf( 'Cannot read file "%s" for multipart upload.', esc_html( $file[1] ) )
				);
			}

			$body .= '--' . $this->boundary . "\r\n";
			$body .= 'Content-Disposition: form-data; name="' . $this->escape( $file[0] ) . '"; filename="' . $this->escape( $file[2] ) . "\"\r\n";
			$body .= 'Content-Type: ' . $file[3] . "\r\n\r\n";
			$body .= $contents . "\r\n";
		}

		$body .= '--' . $this->boundary . "--\r\n";

		return $body;
	}

	/**
	 * Escapes quotes and strips CR/LF from header parameter values.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	private function escape( $value ) {
		return str_replace( array( '"', "\r", "\n" ), array( '\\"', '', '' ), $value );
	}
}
