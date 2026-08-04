<?php
/**
 * Transport-level HTTP failure.
 *
 * @package Zoviz
 */

namespace Zoviz\Infrastructure\Http;

/**
 * Thrown when an HTTP request fails at the transport level (DNS, TLS,
 * timeout, ...) — i.e. no HTTP response was received at all. Components map
 * this to their own error types.
 */
final class TransportException extends \RuntimeException {}
