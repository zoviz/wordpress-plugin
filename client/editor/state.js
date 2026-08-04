/**
 * A single-slot request channel between the places that ask Zoviz to
 * process an image (the core/image BlockControls dropdown, the featured
 * image panel) and the sidebar that actually runs a service. v1 avoids
 * adding a @wordpress/data store for one signal — a plain subscription is
 * enough since only the sidebar ever listens.
 *
 * @typedef {Object} ZovizRunRequest
 * @property {string}  serviceId Service id to preselect.
 * @property {?Object} source    { attachmentId, url, title } or null.
 * @property {?Object} target    { type: 'block', clientId } |
 *                               { type: 'featured' } | null (insert new).
 */

let listener = null;

/**
 * Subscribes to run requests. Only the sidebar should call this.
 *
 * @param {Function} callback Called with a ZovizRunRequest.
 * @return {Function} Unsubscribe.
 */
export function onRunRequested( callback ) {
	listener = callback;

	return () => {
		if ( listener === callback ) {
			listener = null;
		}
	};
}

/**
 * Asks the sidebar to open and run a service against a source image.
 *
 * @param {ZovizRunRequest} request The request.
 */
export function requestRun( request ) {
	if ( listener ) {
		listener( request );
	}
}
