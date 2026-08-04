import { render, screen } from '@testing-library/react';

import {
	ServiceForm,
	scalarFieldsComplete,
} from '../../client/shared/components/service-form';

const GENERATOR = {
	id: 'image-generator-2',
	label: 'Image Generator',
	fields: {
		prompt: { type: 'string', required: true, label: 'Prompt' },
		dimension: {
			type: 'enum',
			required: false,
			label: 'Dimensions',
			default: '1024x1024',
			options: [
				'1024x1024',
				'1152x896',
				'896x1152',
				'1344x768',
				'768x1344',
				'1536x640',
			],
		},
	},
	capabilities: { source: 'none', mask: false, bulk: false },
};

const UPSCALER = {
	id: 'image-upscaler',
	fields: {
		image: { type: 'file', required: true, label: 'Image' },
		target_width: {
			type: 'integer',
			required: true,
			min: 1,
			max: 8192,
			label: 'Target width (px)',
		},
		target_height: {
			type: 'integer',
			required: true,
			min: 1,
			max: 8192,
			label: 'Target height (px)',
		},
	},
	capabilities: { source: 'image', mask: false, bulk: true },
};

describe( 'ServiceForm', () => {
	test( 'renders controls from the catalog schema', () => {
		render(
			<ServiceForm
				service={ GENERATOR }
				values={ {} }
				onChange={ () => {} }
			/>
		);

		// Prompt becomes a textarea; dimension a select with all six options.
		expect( screen.getByLabelText( 'Prompt' ) ).toBeInstanceOf(
			window.HTMLTextAreaElement
		);

		const select = screen.getByLabelText( /Dimensions/ );
		expect( select ).toBeInstanceOf( window.HTMLSelectElement );
		expect( select.querySelectorAll( 'option' ) ).toHaveLength( 6 );
		expect( select ).toHaveValue( '1024x1024' );
	} );

	test( 'skips file fields (handled by pickers) and renders number inputs', () => {
		render(
			<ServiceForm
				service={ UPSCALER }
				values={ {} }
				onChange={ () => {} }
			/>
		);

		expect( screen.queryByLabelText( 'Image' ) ).not.toBeInTheDocument();
		expect( screen.getByLabelText( 'Target width (px)' ) ).toHaveAttribute(
			'type',
			'number'
		);
	} );
} );

describe( 'scalarFieldsComplete', () => {
	test( 'requires non-empty values for required scalar fields', () => {
		expect( scalarFieldsComplete( GENERATOR, {} ) ).toBe( false );
		expect( scalarFieldsComplete( GENERATOR, { prompt: '   ' } ) ).toBe(
			false
		);
		expect(
			scalarFieldsComplete( GENERATOR, { prompt: 'A lighthouse' } )
		).toBe( true );
	} );

	test( 'defaults satisfy required fields and files are ignored', () => {
		expect(
			scalarFieldsComplete( UPSCALER, {
				target_width: 2048,
				target_height: 2048,
			} )
		).toBe( true );
		expect( scalarFieldsComplete( UPSCALER, { target_width: 2048 } ) ).toBe(
			false
		);
	} );
} );
