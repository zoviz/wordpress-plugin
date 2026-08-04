/**
 * The shared submit → poll → result flow every surface uses: progress
 * while the job runs, error notices (with the buy-credits link on 402),
 * and the result preview with save/assign actions.
 */
import { Button, Spinner } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

import { saveJob } from '../api/client';
import { useJobPolling } from '../hooks/use-job-polling';
import { ApiErrorNotice } from './insufficient-credits-notice';

export function JobProgress( { job } ) {
	const statusLabels = {
		pending_submit: __( 'Waiting to start…', 'zoviz-ai-studio' ),
		queued: __( 'Queued at Zoviz…', 'zoviz-ai-studio' ),
		running: __( 'Processing…', 'zoviz-ai-studio' ),
	};

	return (
		<div className="zoviz-job-progress">
			<Spinner />
			<span>
				{ statusLabels[ job?.status ] ||
					__( 'Working…', 'zoviz-ai-studio' ) }
			</span>
		</div>
	);
}

export function ResultPreview( { job, sourceUrl, actions } ) {
	const [ showSource, setShowSource ] = useState( false );

	if ( ! job || job.status !== 'succeeded' ) {
		return null;
	}

	const url = showSource && sourceUrl ? sourceUrl : job.attachment_url;

	return (
		<div className="zoviz-result-preview">
			{ url && (
				<img src={ url } alt={ __( 'Result', 'zoviz-ai-studio' ) } />
			) }

			<div className="zoviz-result-preview__actions">
				{ !! sourceUrl && !! job.attachment_url && (
					<Button
						variant="tertiary"
						onMouseDown={ () => setShowSource( true ) }
						onMouseUp={ () => setShowSource( false ) }
						onMouseLeave={ () => setShowSource( false ) }
					>
						{ __(
							'Hold to compare with original',
							'zoviz-ai-studio'
						) }
					</Button>
				) }
				{ !! job.attachment_id && (
					<span className="zoviz-result-preview__saved">
						{ __(
							'Saved to the Media Library.',
							'zoviz-ai-studio'
						) }{ ' ' }
						{ !! job.attachment_edit && (
							<a href={ job.attachment_edit }>
								{ __( 'View attachment', 'zoviz-ai-studio' ) }
							</a>
						) }
					</span>
				) }
				{ actions }
			</div>
		</div>
	);
}

// Renders the full lifecycle for a submitted job id.
export function JobRunner( { jobId, sourceUrl, onFinished, extraActions } ) {
	const { job, error, isPolling } = useJobPolling( jobId );
	const [ saveError, setSaveError ] = useState( null );
	const [ isSaving, setIsSaving ] = useState( false );
	const [ savedJob, setSavedJob ] = useState( null );

	const current = savedJob || job;

	if ( ! jobId ) {
		return null;
	}

	if ( error ) {
		return <ApiErrorNotice error={ error } />;
	}

	if ( isPolling || ! current ) {
		return <JobProgress job={ current } />;
	}

	if ( current.status === 'failed' ) {
		return (
			<ApiErrorNotice
				error={ {
					code: current.error_code || 'zoviz_job_failed',
					message:
						current.error_message ||
						__(
							'The job failed. No credits were consumed.',
							'zoviz-ai-studio'
						),
				} }
			/>
		);
	}

	if ( current.status === 'expired' ) {
		return (
			<ApiErrorNotice
				error={ {
					code: 'zoviz_result_expired',
					message: __(
						'This result expired before it could be downloaded.',
						'zoviz-ai-studio'
					),
				} }
			/>
		);
	}

	// Succeeded but not auto-saved yet (auto-download disabled): offer save.
	const needsManualSave =
		current.status === 'succeeded' && ! current.attachment_id;

	const save = async () => {
		setIsSaving( true );
		setSaveError( null );

		try {
			const fresh = await saveJob( current.id );
			setSavedJob( fresh );
			if ( onFinished ) {
				onFinished( fresh );
			}
		} catch ( err ) {
			setSaveError( err );
		} finally {
			setIsSaving( false );
		}
	};

	if (
		current.status === 'succeeded' &&
		current.attachment_id &&
		onFinished &&
		! savedJob
	) {
		// Notify once with the final payload.
		setSavedJob( current );
		onFinished( current );
	}

	return (
		<div className="zoviz-job-runner">
			{ !! saveError && (
				<ApiErrorNotice
					error={ saveError }
					onDismiss={ () => setSaveError( null ) }
				/>
			) }

			{ needsManualSave && (
				<Button variant="primary" isBusy={ isSaving } onClick={ save }>
					{ __( 'Save result to Media Library', 'zoviz-ai-studio' ) }
				</Button>
			) }

			<ResultPreview
				job={ current }
				sourceUrl={ sourceUrl }
				actions={ extraActions }
			/>

			{ !! current.credits_used && (
				<p className="description">
					{ sprintf(
						/* translators: %d: number of credits. */
						__( 'This job used %d credit(s).', 'zoviz-ai-studio' ),
						current.credits_used
					) }
				</p>
			) }
		</div>
	);
}
