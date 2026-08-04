import { render, screen, waitFor } from '@testing-library/react';

import { KeyPicker } from '../../client/shared/components/key-picker';
import { bootData, getKeys } from '../../client/shared/api/client';

jest.mock( '../../client/shared/api/client', () => ( {
	bootData: jest.fn( () => ( {} ) ),
	getKeys: jest.fn(),
} ) );

describe( 'KeyPicker', () => {
	beforeEach( () => {
		bootData.mockReset().mockReturnValue( {} );
		getKeys.mockReset();
	} );

	test( 'renders nothing when fewer than two keys exist', () => {
		bootData.mockReturnValue( { keyCount: 1, defaultKeyId: 'k_a' } );

		const { container } = render(
			<KeyPicker value="" onChange={ () => {} } />
		);

		expect( getKeys ).not.toHaveBeenCalled();
		expect( container ).toBeEmptyDOMElement();
	} );

	test( 'renders a workspace selector when two keys exist', async () => {
		bootData.mockReturnValue( { keyCount: 2, defaultKeyId: 'k_b' } );
		getKeys.mockResolvedValue( [
			{ id: 'k_a', label: 'First', masked: '••••1111' },
			{ id: 'k_b', label: 'Second', masked: '••••2222' },
		] );

		render( <KeyPicker value="" onChange={ () => {} } /> );

		await waitFor( () =>
			expect(
				screen.getByLabelText( 'Zoviz workspace' )
			).toBeInTheDocument()
		);

		expect(
			screen.getByRole( 'option', { name: 'First (••••1111)' } )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'option', { name: 'Second (••••2222)' } )
		).toBeInTheDocument();
		expect( screen.getByLabelText( 'Zoviz workspace' ) ).toHaveValue(
			'k_b'
		);
	} );
} );
