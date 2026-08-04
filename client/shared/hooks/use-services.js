/**
 * Loads the service catalog once per page and caches it module-wide.
 */
import { useEffect, useState } from '@wordpress/element';

import { getServices } from '../api/client';

let cache = null;
let inflight = null;

export function useServices() {
	const [ services, setServices ] = useState( cache );
	const [ error, setError ] = useState( null );

	useEffect( () => {
		if ( cache ) {
			return;
		}

		if ( ! inflight ) {
			inflight = getServices();
		}

		let cancelled = false;

		inflight
			.then( ( result ) => {
				cache = result;
				if ( ! cancelled ) {
					setServices( result );
				}
			} )
			.catch( ( err ) => {
				inflight = null;
				if ( ! cancelled ) {
					setError( err );
				}
			} );

		return () => {
			cancelled = true;
		};
	}, [] );

	return { services, error, isLoading: ! services && ! error };
}
