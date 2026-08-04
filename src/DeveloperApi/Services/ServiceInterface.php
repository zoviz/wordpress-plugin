<?php
/**
 * Developer API service contract.
 *
 * @package Zoviz
 */

namespace Zoviz\DeveloperApi\Services;

/**
 * Describes one remote Zoviz Developer API service declaratively. The same
 * field schema drives REST argument validation AND the JS form rendering,
 * so adding a service is normally just one class plus a registry call.
 */
interface ServiceInterface {

	/**
	 * Unique service id used across REST, JS, and job rows,
	 * e.g. 'background-remover'.
	 *
	 * @return string
	 */
	public function id();

	/**
	 * Translated human-readable name.
	 *
	 * @return string
	 */
	public function label();

	/**
	 * Translated short description.
	 *
	 * @return string
	 */
	public function description();

	/**
	 * Remote endpoint path after /api/v1/, e.g. 'remove-background'.
	 *
	 * @return string
	 */
	public function endpoint();

	/**
	 * Request encoding: 'multipart' or 'json'.
	 *
	 * @return string
	 */
	public function request_format();

	/**
	 * Credits consumed per request.
	 *
	 * @return int
	 */
	public function credit_cost();

	/**
	 * Accepted input MIME types for file fields.
	 *
	 * @return string[]
	 */
	public function accepted_mimes();

	/**
	 * Possible result content types.
	 *
	 * @return string[]
	 */
	public function output_content_types();

	/**
	 * Declarative request field schema. Keyed by field name; each entry:
	 * - 'type'     (string) 'file' | 'string' | 'enum' | 'integer'.
	 * - 'required' (bool).
	 * - 'label'    (string) translated label for form rendering.
	 * - 'options'  (string[]) allowed values, for 'enum'.
	 * - 'default'  (mixed) optional default.
	 * - 'min'/'max' (int) bounds, for 'integer'.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function fields();

	/**
	 * Surface capabilities:
	 * - 'bulk'   (bool)   suitable for unattended bulk processing.
	 * - 'mask'   (bool)   requires a painted mask.
	 * - 'source' (string) 'image' | 'sketch' | 'none' — the primary input kind.
	 *
	 * @return array<string, mixed>
	 */
	public function capabilities();

	/**
	 * Validates raw input against fields() and returns the normalized
	 * request payload.
	 *
	 * @param array<string, mixed>                                               $params Scalar field values.
	 * @param array<string, array{path: string, filename: string, mime: string}> $files File inputs keyed by field name.
	 * @return array{fields: array<string, string>, files: array<string, array{path: string, filename: string, mime: string}>}
	 * @throws \Zoviz\DeveloperApi\Exception\ValidationException When input is invalid.
	 */
	public function prepare_request( array $params, array $files );
}
