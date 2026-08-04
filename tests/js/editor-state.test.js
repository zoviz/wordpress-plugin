import { onRunRequested, requestRun } from '../../client/editor/state';

describe( 'editor run-request channel', () => {
	it( 'delivers a request to the current subscriber', () => {
		const received = [];
		onRunRequested( ( request ) => received.push( request ) );

		requestRun( {
			serviceId: 'background-remover',
			source: null,
			target: null,
		} );

		expect( received ).toHaveLength( 1 );
		expect( received[ 0 ].serviceId ).toBe( 'background-remover' );
	} );

	it( 'does nothing when no one is subscribed', () => {
		expect( () =>
			requestRun( { serviceId: 'image-upscaler' } )
		).not.toThrow();
	} );

	it( 'stops delivering after unsubscribing', () => {
		const received = [];
		const unsubscribe = onRunRequested( ( request ) =>
			received.push( request )
		);

		unsubscribe();
		requestRun( { serviceId: 'image-editor' } );

		expect( received ).toHaveLength( 0 );
	} );

	it( 'the newest subscriber replaces the previous one', () => {
		const first = [];
		const second = [];

		onRunRequested( ( request ) => first.push( request ) );
		onRunRequested( ( request ) => second.push( request ) );

		requestRun( { serviceId: 'object-remover' } );

		expect( first ).toHaveLength( 0 );
		expect( second ).toHaveLength( 1 );
	} );
} );
