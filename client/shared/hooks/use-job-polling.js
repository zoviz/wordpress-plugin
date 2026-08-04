/**
 * Polls a job until it reaches a terminal state, backing off from 2s up
 * to 10s between polls. The browser is the primary finalizer; the server
 * cron sweeper only backs it up.
 */
import { useEffect, useRef, useState } from '@wordpress/element';

import { getJob } from '../api/client';
import { announceJobFinished } from './use-credits';

const INITIAL_INTERVAL = 2000;
const MAX_INTERVAL = 10000;
const BACKOFF = 1.5;

export const TERMINAL_STATUSES = [ 'succeeded', 'failed', 'expired' ];

export function useJobPolling( jobId ) {
	const [ job, setJob ] = useState( null );
	const [ error, setError ] = useState( null );
	const timer = useRef( null );

	useEffect( () => {
		setJob( null );
		setError( null );

		if ( ! jobId ) {
			return undefined;
		}

		let cancelled = false;
		let interval = INITIAL_INTERVAL;

		const poll = async () => {
			try {
				const fresh = await getJob( jobId );

				if ( cancelled ) {
					return;
				}

				setJob( fresh );

				if ( TERMINAL_STATUSES.includes( fresh.status ) ) {
					announceJobFinished();
					return;
				}
			} catch ( err ) {
				if ( cancelled ) {
					return;
				}

				// Terminal API errors stop polling; transient ones retry.
				if ( err.status && err.status < 500 ) {
					setError( err );
					return;
				}
			}

			interval = Math.min( MAX_INTERVAL, interval * BACKOFF );
			timer.current = setTimeout( poll, interval );
		};

		poll();

		return () => {
			cancelled = true;
			clearTimeout( timer.current );
		};
	}, [ jobId ] );

	const isFinished = !! job && TERMINAL_STATUSES.includes( job.status );

	return {
		job,
		error,
		isPolling: !! jobId && ! isFinished && ! error,
		isFinished,
	};
}
