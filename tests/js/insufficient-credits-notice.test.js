import { render, screen } from '@testing-library/react';

import {
	ApiErrorNotice,
	FALLBACK_BUY_URL,
	InsufficientCreditsNotice,
} from '../../client/shared/components/insufficient-credits-notice';

describe( 'InsufficientCreditsNotice', () => {
	test( 'links to the buy-credits page with wordpress attribution', () => {
		render( <InsufficientCreditsNotice error={ null } /> );

		const link = screen.getByRole( 'link', { name: /buy more credits/i } );

		expect( link ).toHaveAttribute(
			'href',
			'https://zoviz.com/app/pricing/credit?navigation_source=wordpress'
		);
		expect( FALLBACK_BUY_URL ).toContain( 'navigation_source=wordpress' );
	} );

	test( 'prefers the buy URL delivered by the API error', () => {
		render(
			<InsufficientCreditsNotice
				error={ {
					message: 'Not enough credits.',
					buyUrl: 'https://zoviz.com/app/pricing/credit?navigation_source=wordpress&x=1',
				} }
			/>
		);

		expect(
			screen.getByRole( 'link', { name: /buy more credits/i } )
		).toHaveAttribute(
			'href',
			'https://zoviz.com/app/pricing/credit?navigation_source=wordpress&x=1'
		);
		expect( screen.getByText( 'Not enough credits.' ) ).toBeInTheDocument();
	} );
} );

describe( 'ApiErrorNotice', () => {
	test( 'routes 402 errors to the credits notice', () => {
		render(
			<ApiErrorNotice
				error={ {
					code: 'zoviz_insufficient_credits',
					message: 'Empty.',
				} }
			/>
		);

		expect(
			screen.getByRole( 'link', { name: /buy more credits/i } )
		).toBeInTheDocument();
	} );

	test( 'renders a plain error notice otherwise', () => {
		render(
			<ApiErrorNotice
				error={ { code: 'zoviz_network_error', message: 'Offline.' } }
			/>
		);

		// Notices also announce via an a11y live region, hence *AllBy*.
		expect( screen.getAllByText( 'Offline.' ).length ).toBeGreaterThan( 0 );
		expect( screen.queryByRole( 'link' ) ).not.toBeInTheDocument();
	} );
} );
