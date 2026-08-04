<?php
/**
 * Base class for async Developer API services.
 *
 * @package Zoviz
 */

namespace Zoviz\DeveloperApi\Services;

use Zoviz\DeveloperApi\Exception\ValidationException;

/**
 * Supplies the common defaults (multipart encoding, one credit, JPEG/PNG/WebP
 * input, PNG output) and a generic schema-driven prepare_request()
 * implementation. Concrete services override only what differs.
 */
abstract class AbstractAsyncService implements ServiceInterface {

	/**
	 * Request encoding.
	 *
	 * @return string
	 */
	public function request_format() {
		return 'multipart';
	}

	/**
	 * Credits per request.
	 *
	 * @return int
	 */
	public function credit_cost() {
		return 1;
	}

	/**
	 * Accepted input MIME types.
	 *
	 * @return string[]
	 */
	public function accepted_mimes() {
		return array( 'image/jpeg', 'image/png', 'image/webp' );
	}

	/**
	 * Possible result content types.
	 *
	 * @return string[]
	 */
	public function output_content_types() {
		return array( 'image/png' );
	}

	/**
	 * Surface capabilities. Merged over sensible defaults.
	 *
	 * @return array<string, mixed>
	 */
	public function capabilities() {
		return array(
			'bulk'   => false,
			'mask'   => false,
			'source' => 'image',
		);
	}

	/**
	 * Generic schema-driven validation.
	 *
	 * @param array<string, mixed>                                               $params Scalar field values.
	 * @param array<string, array{path: string, filename: string, mime: string}> $files File inputs keyed by field name.
	 * @return array{fields: array<string, string>, files: array<string, array{path: string, filename: string, mime: string}>}
	 * @throws ValidationException When input is invalid.
	 */
	public function prepare_request( array $params, array $files ) {
		$out_fields = array();
		$out_files  = array();

		foreach ( $this->fields() as $name => $schema ) {
			$type     = isset( $schema['type'] ) ? $schema['type'] : 'string';
			$required = ! empty( $schema['required'] );

			if ( 'file' === $type ) {
				$file = $this->validate_file_field( $name, $files, $required );

				if ( null !== $file ) {
					$out_files[ $name ] = $file;
				}
				continue;
			}

			$value = $this->validate_scalar_field( $name, $schema, $params, $required );

			if ( null !== $value ) {
				$out_fields[ $name ] = $value;
			}
		}

		return array(
			'fields' => $out_fields,
			'files'  => $out_files,
		);
	}

	/**
	 * Validates one file field.
	 *
	 * @param string                                                             $name     Field name.
	 * @param array<string, array{path: string, filename: string, mime: string}> $files    File inputs.
	 * @param bool                                                               $required Whether the field is required.
	 * @return array{path: string, filename: string, mime: string}|null
	 * @throws ValidationException When the file is missing, unreadable, or of an unsupported type.
	 */
	private function validate_file_field( $name, array $files, $required ) {
		if ( ! isset( $files[ $name ] ) ) {
			if ( $required ) {
				throw new ValidationException(
					esc_html(
						sprintf(
							/* translators: %s: field name. */
							__( 'The "%s" file is required.', 'zoviz-ai-studio' ),
							$name
						)
					)
				);
			}

			return null;
		}

		$file = $files[ $name ];

		if ( empty( $file['path'] ) || ! is_readable( $file['path'] ) ) {
			throw new ValidationException(
				esc_html(
					sprintf(
						/* translators: %s: field name. */
						__( 'The "%s" file could not be read.', 'zoviz-ai-studio' ),
						$name
					)
				)
			);
		}

		if ( ! empty( $file['mime'] ) && ! in_array( $file['mime'], $this->accepted_mimes(), true ) ) {
			throw new ValidationException(
				esc_html(
					sprintf(
						/* translators: 1: field name, 2: comma-separated list of accepted formats. */
						__( 'The "%1$s" file has an unsupported format. Accepted formats: %2$s.', 'zoviz-ai-studio' ),
						$name,
						implode( ', ', $this->accepted_mimes() )
					)
				)
			);
		}

		return $file;
	}

	/**
	 * Validates one scalar field, returning its normalized string value.
	 *
	 * @param string               $name     Field name.
	 * @param array<string, mixed> $schema   Field schema.
	 * @param array<string, mixed> $params   Scalar inputs.
	 * @param bool                 $required Whether the field is required.
	 * @return string|null Null when the optional field is absent.
	 * @throws ValidationException When the value is missing or invalid.
	 */
	private function validate_scalar_field( $name, array $schema, array $params, $required ) {
		$type  = isset( $schema['type'] ) ? $schema['type'] : 'string';
		$value = isset( $params[ $name ] ) ? $params[ $name ] : null;

		if ( ( null === $value || '' === $value ) && isset( $schema['default'] ) ) {
			$value = $schema['default'];
		}

		if ( null === $value || '' === $value ) {
			if ( $required ) {
				throw new ValidationException(
					esc_html(
						sprintf(
							/* translators: %s: field name. */
							__( 'The "%s" field is required.', 'zoviz-ai-studio' ),
							$name
						)
					)
				);
			}

			return null;
		}

		if ( 'enum' === $type ) {
			$options = isset( $schema['options'] ) ? (array) $schema['options'] : array();

			if ( ! in_array( (string) $value, array_map( 'strval', $options ), true ) ) {
				throw new ValidationException(
					esc_html(
						sprintf(
							/* translators: 1: field name, 2: comma-separated list of allowed values. */
							__( 'The "%1$s" field must be one of: %2$s.', 'zoviz-ai-studio' ),
							$name,
							implode( ', ', $options )
						)
					)
				);
			}

			return (string) $value;
		}

		if ( 'integer' === $type ) {
			return $this->validate_integer_field( $name, $schema, $value );
		}

		return (string) $value;
	}

	/**
	 * Validates one integer field against its bounds.
	 *
	 * @param string               $name   Field name.
	 * @param array<string, mixed> $schema Field schema.
	 * @param mixed                $value  Raw value.
	 * @return string Normalized integer as string.
	 * @throws ValidationException When the value is not numeric or out of bounds.
	 */
	private function validate_integer_field( $name, array $schema, $value ) {
		if ( ! is_numeric( $value ) ) {
			throw new ValidationException(
				esc_html(
					sprintf(
						/* translators: %s: field name. */
						__( 'The "%s" field must be a number.', 'zoviz-ai-studio' ),
						$name
					)
				)
			);
		}

		$int = (int) $value;
		$min = isset( $schema['min'] ) ? (int) $schema['min'] : null;
		$max = isset( $schema['max'] ) ? (int) $schema['max'] : null;

		if ( ( null !== $min && $int < $min ) || ( null !== $max && $int > $max ) ) {
			throw new ValidationException(
				esc_html(
					sprintf(
						/* translators: 1: field name, 2: minimum value, 3: maximum value. */
						__( 'The "%1$s" field must be between %2$s and %3$s.', 'zoviz-ai-studio' ),
						$name,
						null !== $min ? $min : '-∞',
						null !== $max ? $max : '∞'
					)
				)
			);
		}

		return (string) $int;
	}
}
