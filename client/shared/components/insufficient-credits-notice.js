/**
 * The 402 companion: a clear message plus the buy-credits deep link
 * (attributed to the plugin via navigation_source=wordpress).
 */
import { ExternalLink, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { bootData } from '../api/client';

export const FALLBACK_BUY_URL =
	'https://zoviz.com/app/pricing/credit?navigation_source=wordpress';

export function InsufficientCreditsNotice( { error, onDismiss } ) {
	const buyUrl =
		( error && error.buyUrl ) || bootData().pricingUrl || FALLBACK_BUY_URL;

	return (
		<Notice
			className="zoviz-insufficient-credits"
			status="warning"
			isDismissible={ !! onDismiss }
			onRemove={ onDismiss }
		>
			{ ( error && error.message ) ||
				__(
					'Your Zoviz workspace does not have enough credits for this request.',
					'zoviz-ai-studio'
				) }{ ' ' }
			<ExternalLink href={ buyUrl }>
				{ __( 'Buy more credits', 'zoviz-ai-studio' ) }
			</ExternalLink>
		</Notice>
	);
}

// Renders the right notice for any normalized API error.
export function ApiErrorNotice( { error, onDismiss } ) {
	if ( ! error ) {
		return null;
	}

	if ( error.code === 'zoviz_insufficient_credits' ) {
		return (
			<InsufficientCreditsNotice
				error={ error }
				onDismiss={ onDismiss }
			/>
		);
	}

	return (
		<Notice
			status="error"
			isDismissible={ !! onDismiss }
			onRemove={ onDismiss }
		>
			{ error.message ||
				__(
					'Something went wrong. Please try again.',
					'zoviz-ai-studio'
				) }
		</Notice>
	);
}
