/**
 * Jobs history: every job with status, credits used, and re-download.
 * Results already saved locally can be reused forever; unsaved results
 * can be fetched again while the remote copy has not expired.
 */
import {
	Button,
	Card,
	CardBody,
	Spinner,
	ToggleControl,
} from '@wordpress/components';
import domReady from '@wordpress/dom-ready';
import {
	createRoot,
	useCallback,
	useEffect,
	useRef,
	useState,
} from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

import '../shared/styles.scss';
import {
	bootData,
	deleteJob,
	getJob,
	getJobs,
	saveJob,
} from '../shared/api/client';
import { CreditBadge } from '../shared/components/credit-badge';
import { LogoMark } from '../shared/components/logo-mark';
import { ApiErrorNotice } from '../shared/components/insufficient-credits-notice';

const PER_PAGE = 20;
const PENDING = [ 'pending_submit', 'queued', 'running' ];
const REFRESH_INTERVAL = 5000;

function StatusBadge( { status } ) {
	const labels = {
		pending_submit: __( 'Waiting', 'zoviz-ai-studio' ),
		queued: __( 'Queued', 'zoviz-ai-studio' ),
		running: __( 'Running', 'zoviz-ai-studio' ),
		succeeded: __( 'Succeeded', 'zoviz-ai-studio' ),
		failed: __( 'Failed', 'zoviz-ai-studio' ),
		expired: __( 'Expired', 'zoviz-ai-studio' ),
	};

	return (
		<span className={ `zoviz-status is-${ status }` }>
			{ labels[ status ] || status }
		</span>
	);
}

function JobsApp() {
	const [ jobs, setJobs ] = useState( null );
	const [ page, setPage ] = useState( 1 );
	const [ scopeAll, setScopeAll ] = useState( false );
	const [ error, setError ] = useState( null );
	const [ busyJob, setBusyJob ] = useState( 0 );
	const timer = useRef( null );

	const load = useCallback( () => {
		return getJobs( {
			page,
			per_page: PER_PAGE,
			scope: scopeAll ? 'all' : 'mine',
		} )
			.then( setJobs )
			.catch( setError );
	}, [ page, scopeAll ] );

	useEffect( () => {
		load();
	}, [ load ] );

	// While jobs are pending, poll a handful of them (which refreshes their
	// remote status server-side) and re-fetch the list.
	useEffect( () => {
		const pending = ( jobs || [] ).filter( ( job ) =>
			PENDING.includes( job.status )
		);

		if ( ! pending.length ) {
			return undefined;
		}

		timer.current = setTimeout( async () => {
			await Promise.all(
				pending
					.slice( 0, 5 )
					.map( ( job ) => getJob( job.id ).catch( () => null ) )
			);
			load();
		}, REFRESH_INTERVAL );

		return () => clearTimeout( timer.current );
	}, [ jobs, load ] );

	const save = async ( job ) => {
		setBusyJob( job.id );
		setError( null );

		try {
			await saveJob( job.id );
			await load();
		} catch ( err ) {
			setError( err );
			await load();
		} finally {
			setBusyJob( 0 );
		}
	};

	const remove = async ( job ) => {
		// eslint-disable-next-line no-alert -- Simple confirm is appropriate for a destructive admin action.
		const confirmed = window.confirm(
			__(
				'Remove this job from the history? Saved images stay in the Media Library.',
				'zoviz-ai-studio'
			)
		);

		if ( ! confirmed ) {
			return;
		}

		setBusyJob( job.id );
		await deleteJob( job.id ).catch( setError );
		setBusyJob( 0 );
		await load();
	};

	if ( ! jobs ) {
		return error ? <ApiErrorNotice error={ error } /> : <Spinner />;
	}

	return (
		<div className="zoviz-app">
			<div className="zoviz-app__header">
				<div className="zoviz-app__title">
					<LogoMark />
					<h1>{ __( 'Zoviz Jobs', 'zoviz-ai-studio' ) }</h1>
				</div>
				<CreditBadge />
			</div>

			<Card className="zoviz-card">
				<CardBody>
					{ bootData().isAdmin && (
						<ToggleControl
							__nextHasNoMarginBottom
							label={ __(
								'Show jobs from all users',
								'zoviz-ai-studio'
							) }
							checked={ scopeAll }
							onChange={ ( value ) => {
								setPage( 1 );
								setScopeAll( value );
							} }
						/>
					) }

					{ !! error && (
						<ApiErrorNotice
							error={ error }
							onDismiss={ () => setError( null ) }
						/>
					) }

					<table className="widefat striped zoviz-jobs-table">
						<thead>
							<tr>
								<th>{ __( 'Service', 'zoviz-ai-studio' ) }</th>
								<th>{ __( 'Status', 'zoviz-ai-studio' ) }</th>
								<th>
									{ __( 'Created (UTC)', 'zoviz-ai-studio' ) }
								</th>
								<th>{ __( 'Credits', 'zoviz-ai-studio' ) }</th>
								<th>{ __( 'Result', 'zoviz-ai-studio' ) }</th>
								<th />
							</tr>
						</thead>
						<tbody>
							{ ! jobs.length && (
								<tr>
									<td colSpan={ 6 }>
										{ __(
											'No jobs yet.',
											'zoviz-ai-studio'
										) }
									</td>
								</tr>
							) }
							{ jobs.map( ( job ) => (
								<tr key={ job.id }>
									<td>{ job.service }</td>
									<td>
										<StatusBadge status={ job.status } />
										{ job.status === 'failed' &&
											!! job.error_message && (
												<p className="description">
													{ job.error_message }
												</p>
											) }
									</td>
									<td>{ job.created_at }</td>
									<td>{ job.credits_used ?? '—' }</td>
									<td>
										{ job.attachment_id > 0 &&
											job.attachment_exists !== false && (
												<a
													href={ `${ window.location.origin }/wp-admin/post.php?post=${ job.attachment_id }&action=edit` }
												>
													{ __(
														'View in Media Library',
														'zoviz-ai-studio'
													) }
												</a>
											) }
										{ job.attachment_id > 0 &&
											job.attachment_exists === false && (
												<span className="description">
													{ __(
														'File manually removed',
														'zoviz-ai-studio'
													) }
												</span>
											) }
										{ ! job.attachment_id &&
											job.status === 'succeeded' &&
											( job.downloadable ? (
												<Button
													variant="secondary"
													size="small"
													isBusy={
														busyJob === job.id
													}
													onClick={ () =>
														save( job )
													}
												>
													{ __(
														'Download to Media Library',
														'zoviz-ai-studio'
													) }
												</Button>
											) : (
												<span className="description">
													{ __(
														'No longer available',
														'zoviz-ai-studio'
													) }
												</span>
											) ) }
									</td>
									<td>
										<Button
											variant="link"
											isDestructive
											disabled={ busyJob === job.id }
											onClick={ () => remove( job ) }
										>
											{ __(
												'Remove',
												'zoviz-ai-studio'
											) }
										</Button>
									</td>
								</tr>
							) ) }
						</tbody>
					</table>

					<p className="zoviz-jobs-pagination">
						<Button
							variant="tertiary"
							disabled={ page <= 1 }
							onClick={ () => setPage( page - 1 ) }
						>
							{ __( '← Newer', 'zoviz-ai-studio' ) }
						</Button>
						<span>
							{ sprintf(
								/* translators: %d: page number. */
								__( 'Page %d', 'zoviz-ai-studio' ),
								page
							) }
						</span>
						<Button
							variant="tertiary"
							disabled={ jobs.length < PER_PAGE }
							onClick={ () => setPage( page + 1 ) }
						>
							{ __( 'Older →', 'zoviz-ai-studio' ) }
						</Button>
					</p>
				</CardBody>
			</Card>
		</div>
	);
}

domReady( () => {
	const rootElement = document.getElementById( 'zoviz-jobs-root' );

	if ( rootElement ) {
		createRoot( rootElement ).render( <JobsApp /> );
	}
} );
