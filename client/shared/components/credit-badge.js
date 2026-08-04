/**
 * Small badge showing the workspace credit balance for the active key.
 * Shown on every surface so users always know where they stand.
 */
import { Button, Spinner, Tooltip } from '@wordpress/components';
import { __, sprintf, _n } from '@wordpress/i18n';

import { bootData } from '../api/client';
import { useCredits } from '../hooks/use-credits';

export function CreditBadge( { keyId = '' } ) {
	const { credits, error, isLoading, refresh } = useCredits( keyId );

	if ( isLoading && ! credits ) {
		return (
			<span className="zoviz-credit-badge">
				<Spinner />
			</span>
		);
	}

	if ( error ) {
		if ( error.code === 'zoviz_no_api_key' && bootData().isAdmin ) {
			return (
				<a
					className="zoviz-credit-badge is-error"
					href={ bootData().settingsUrl }
				>
					{ __( 'Add your Zoviz API key', 'zoviz-ai-studio' ) }
				</a>
			);
		}

		return (
			<span className="zoviz-credit-badge is-error">
				{ error.message }
			</span>
		);
	}

	if ( ! credits ) {
		return null;
	}

	const label = sprintf(
		/* translators: %s: number of credits. */
		_n( '%s credit', '%s credits', credits.credit, 'zoviz-ai-studio' ),
		new Intl.NumberFormat().format( credits.credit )
	);

	const badge = (
		<span
			className={
				credits.credit > 0
					? 'zoviz-credit-badge'
					: 'zoviz-credit-badge is-empty'
			}
		>
			{ label }
			<Button
				size="small"
				variant="tertiary"
				icon="update"
				label={ __( 'Refresh credit balance', 'zoviz-ai-studio' ) }
				onClick={ refresh }
			/>
		</span>
	);

	if ( credits.reserved_credit > 0 ) {
		return (
			<Tooltip
				text={ sprintf(
					/* translators: %s: number of reserved credits. */
					__(
						'%s credits are reserved by jobs in progress.',
						'zoviz-ai-studio'
					),
					new Intl.NumberFormat().format( credits.reserved_credit )
				) }
			>
				{ badge }
			</Tooltip>
		);
	}

	return badge;
}
