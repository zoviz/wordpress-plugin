<?php

namespace Zoviz\Tests\Integration\Rest;

use Zoviz\DeveloperApi\Rest\NoticesController;
use Zoviz\Tests\Integration\Support\RestTestCase;

class CatalogSettingsNoticesTest extends RestTestCase {

	public function test_services_catalog_lists_all_seven_with_schemas() {
		wp_set_current_user( $this->author_id );

		$response = $this->request( 'GET', '/services' );

		$this->assertSame( 200, $response->get_status() );

		$services = $response->get_data();
		$this->assertCount( 7, $services );

		$by_id = array_column( $services, null, 'id' );

		$this->assertSame( 2, $by_id['image-generator-2']['credit_cost'] );
		$this->assertSame( 'enum', $by_id['image-generator-2']['fields']['dimension']['type'] );
		$this->assertCount( 6, $by_id['image-generator-2']['fields']['dimension']['options'] );
		$this->assertTrue( $by_id['image-editor']['capabilities']['mask'] );
		$this->assertSame( 'sketch', $by_id['sketch-to-image']['capabilities']['source'] );
		$this->assertNotEmpty( $by_id['background-remover']['label'] );
	}

	public function test_services_catalog_requires_upload_capability() {
		wp_set_current_user( $this->subscriber_id );

		$this->assertSame( 403, $this->request( 'GET', '/services' )->get_status() );
	}

	public function test_settings_roundtrip_admin_only() {
		wp_set_current_user( $this->author_id );
		$this->assertSame( 403, $this->request( 'GET', '/settings' )->get_status() );

		wp_set_current_user( $this->admin_id );

		$defaults = $this->request( 'GET', '/settings' )->get_data();
		$this->assertTrue( $defaults['auto_download'] );
		$this->assertSame( 90, $defaults['retention_days'] );

		$updated = $this->request(
			'POST',
			'/settings',
			array(
				'auto_download'  => false,
				'retention_days' => 30,
			)
		)->get_data();

		$this->assertFalse( $updated['auto_download'] );
		$this->assertSame( 30, $updated['retention_days'] );

		$this->assertFalse( $this->request( 'GET', '/settings' )->get_data()['auto_download'] );
	}

	public function test_notice_dismissal_snoozes_per_user() {
		wp_set_current_user( $this->author_id );

		$this->assertFalse( NoticesController::is_snoozed( 'no-credits' ) );

		$response = $this->request( 'POST', '/notices/no-credits/dismiss' );

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( NoticesController::is_snoozed( 'no-credits' ) );

		// Another user is unaffected.
		$this->assertFalse( NoticesController::is_snoozed( 'no-credits', $this->admin_id ) );

		// Snooze expires.
		add_filter( 'zoviz_notice_snooze_seconds', static fn() => -10 );
		$this->request( 'POST', '/notices/no-credits/dismiss' );
		$this->assertFalse( NoticesController::is_snoozed( 'no-credits' ) );
	}

	public function test_notice_dismissal_requires_login() {
		wp_set_current_user( 0 );

		$this->assertSame( 401, $this->request( 'POST', '/notices/no-credits/dismiss' )->get_status() );
	}
}
