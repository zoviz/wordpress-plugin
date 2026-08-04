/**
 * Credit balance for an API key's workspace. Refreshes when a Zoviz job
 * finishes anywhere on the page (zoviz:job-finished event).
 */
import { useCallback, useEffect, useState } from '@wordpress/element';

import { getCredits } from '../api/client';

export const JOB_FINISHED_EVENT = 'zoviz:job-finished';

export function useCredits( keyId = '' ) {
	const [ credits, setCredits ] = useState( null );
	const [ error, setError ] = useState( null );
	const [ isLoading, setIsLoading ] = useState( true );

	const load = useCallback(
		( force = false ) => {
			setIsLoading( true );
			setError( null );

			return getCredits( keyId, force )
				.then( ( result ) => setCredits( result ) )
				.catch( ( err ) => setError( err ) )
				.finally( () => setIsLoading( false ) );
		},
		[ keyId ]
	);

	useEffect( () => {
		load();

		const onFinished = () => load( true );
		window.addEventListener( JOB_FINISHED_EVENT, onFinished );

		return () =>
			window.removeEventListener( JOB_FINISHED_EVENT, onFinished );
	}, [ load ] );

	return { credits, error, isLoading, refresh: () => load( true ) };
}

/**
 * Announces a finished job so credit badges refresh.
 */
export function announceJobFinished() {
	window.dispatchEvent( new window.Event( JOB_FINISHED_EVENT ) );
}
