<?php

namespace Zoviz\Tests\Unit\Infrastructure;

use Zoviz\Infrastructure\Http\MultipartBuilder;
use Zoviz\Tests\Unit\TestCase;

class MultipartBuilderTest extends TestCase {

	private function fixture( string $name ): string {
		return dirname( __DIR__, 2 ) . '/Fixtures/' . $name;
	}

	public function test_content_type_contains_boundary() {
		$builder = new MultipartBuilder( 'BOUNDARY' );

		$this->assertSame( 'multipart/form-data; boundary=BOUNDARY', $builder->content_type() );
	}

	public function test_body_is_byte_exact() {
		$builder = new MultipartBuilder( 'BOUNDARY' );
		$builder->add_field( 'sync_mode', 'false' );
		$builder->add_file( 'image', $this->fixture( 'result-1px.png' ), 'input.png', 'image/png' );

		$png = file_get_contents( $this->fixture( 'result-1px.png' ) );

		$expected  = "--BOUNDARY\r\n";
		$expected .= "Content-Disposition: form-data; name=\"sync_mode\"\r\n\r\n";
		$expected .= "false\r\n";
		$expected .= "--BOUNDARY\r\n";
		$expected .= "Content-Disposition: form-data; name=\"image\"; filename=\"input.png\"\r\n";
		$expected .= "Content-Type: image/png\r\n\r\n";
		$expected .= $png . "\r\n";
		$expected .= "--BOUNDARY--\r\n";

		$this->assertSame( $expected, $builder->body() );
	}

	public function test_header_values_are_escaped() {
		$builder = new MultipartBuilder( 'BOUNDARY' );
		$builder->add_field( "na\"me\r\n", 'value' );

		$body = $builder->body();

		$this->assertStringContainsString( 'name="na\\"me"', $body );
		$this->assertStringNotContainsString( "na\"me\r\n\"", $body );
	}

	public function test_files_size_sums_attached_files() {
		$builder = new MultipartBuilder();
		$builder->add_file( 'image', $this->fixture( 'result-1px.png' ), 'a.png', 'image/png' );

		$this->assertSame( (int) filesize( $this->fixture( 'result-1px.png' ) ), $builder->files_size() );
	}

	public function test_unreadable_file_throws() {
		$builder = new MultipartBuilder();
		$builder->add_file( 'image', '/nonexistent/nope.png', 'nope.png', 'image/png' );

		$this->expectException( \RuntimeException::class );

		$builder->body();
	}

	public function test_random_boundary_is_unique() {
		$this->assertNotSame(
			( new MultipartBuilder() )->content_type(),
			( new MultipartBuilder() )->content_type()
		);
	}
}
