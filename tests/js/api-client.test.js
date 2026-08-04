import apiFetch from '@wordpress/api-fetch';

import {
	getCredits,
	normalizeError,
	submitJob,
} from '../../client/shared/api/client';

jest.mock( '@wordpress/api-fetch', () => jest.fn() );

describe( 'api client', () => {
	beforeEach( () => {
		apiFetch.mockReset();
	} );

	test( 'normalizeError decodes HTML entities from escaped server messages', () => {
		const error = normalizeError( {
			code: 'zoviz_invalid_request',
			message: 'The &quot;image&quot; file is required.',
			data: { status: 400 },
		} );

		expect( error.message ).toBe( 'The "image" file is required.' );
		expect( error.code ).toBe( 'zoviz_invalid_request' );
		expect( error.status ).toBe( 400 );
		expect( error.buyUrl ).toBeNull();
	} );

	test( 'normalizeError surfaces the 402 buy URL', async () => {
		apiFetch.mockRejectedValue( {
			code: 'zoviz_insufficient_credits',
			message: 'Not enough credits.',
			data: {
				status: 402,
				buy_url:
					'https://zoviz.com/app/pricing/credit?navigation_source=wordpress',
			},
		} );

		await expect( getCredits() ).rejects.toMatchObject( {
			code: 'zoviz_insufficient_credits',
			status: 402,
			buyUrl: 'https://zoviz.com/app/pricing/credit?navigation_source=wordpress',
		} );
	} );

	test( 'submitJob sends multipart form data with fields and files', async () => {
		apiFetch.mockResolvedValue( { id: 12 } );

		const mask = new window.Blob( [ 'x' ], { type: 'image/png' } );

		await submitJob(
			'object-remover',
			{ attachment_id: 7, key_id: '', context: 'media' },
			{ mask }
		);

		expect( apiFetch ).toHaveBeenCalledTimes( 1 );

		const { path, method, body } = apiFetch.mock.calls[ 0 ][ 0 ];

		expect( path ).toBe( '/zoviz/v1/jobs' );
		expect( method ).toBe( 'POST' );
		expect( body ).toBeInstanceOf( window.FormData );
		expect( body.get( 'service' ) ).toBe( 'object-remover' );
		expect( body.get( 'attachment_id' ) ).toBe( '7' );
		expect( body.get( 'context' ) ).toBe( 'media' );
		// Empty values are omitted, files travel as blobs.
		expect( body.has( 'key_id' ) ).toBe( false );
		expect( body.get( 'mask' ) ).toBeInstanceOf( window.Blob );
	} );
} );
