/**
 * Thin wrappers over wp.apiFetch for every zoviz/v1 route, with error
 * normalization in one place. Server-side messages are HTML-escaped at the
 * source (WordPress coding standards), so they are entity-decoded here
 * before display.
 */
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';
import { decodeEntities } from '@wordpress/html-entities';

const NAMESPACE = '/zoviz/v1';

/**
 * Normalizes any apiFetch failure into a consistent shape.
 *
 * @param {Object} error Raw apiFetch error.
 * @return {Object} Error with { code, message, status, buyUrl }.
 */
export function normalizeError( error ) {
	const data = error && error.data ? error.data : {};

	return {
		code: ( error && error.code ) || 'zoviz_unknown_error',
		message: decodeEntities( ( error && error.message ) || '' ),
		status: data.status || 0,
		buyUrl: data.buy_url || null,
	};
}

async function request( options ) {
	try {
		return await apiFetch( options );
	} catch ( error ) {
		throw normalizeError( error );
	}
}

export const getServices = () => request( { path: `${ NAMESPACE }/services` } );

export const getCredits = ( keyId = '', force = false ) =>
	request( {
		path: addQueryArgs( `${ NAMESPACE }/credits`, {
			key_id: keyId,
			force,
		} ),
	} );

export const getKeys = () => request( { path: `${ NAMESPACE }/keys` } );

export const addKey = ( label, secret ) =>
	request( {
		path: `${ NAMESPACE }/keys`,
		method: 'POST',
		data: { label, secret },
	} );

export const updateKey = ( id, data ) =>
	request( { path: `${ NAMESPACE }/keys/${ id }`, method: 'PUT', data } );

export const deleteKey = ( id ) =>
	request( { path: `${ NAMESPACE }/keys/${ id }`, method: 'DELETE' } );

export const getJobs = ( args = {} ) =>
	request( { path: addQueryArgs( `${ NAMESPACE }/jobs`, args ) } );

export const getJob = ( id, refresh = true ) =>
	request( {
		path: addQueryArgs( `${ NAMESPACE }/jobs/${ id }`, { refresh } ),
	} );

export const deleteJob = ( id ) =>
	request( { path: `${ NAMESPACE }/jobs/${ id }`, method: 'DELETE' } );

export const saveJob = ( id, args = {} ) =>
	request( {
		path: `${ NAMESPACE }/jobs/${ id }/save`,
		method: 'POST',
		data: args,
	} );

export const getSettings = () => request( { path: `${ NAMESPACE }/settings` } );

export const updateSettings = ( data ) =>
	request( { path: `${ NAMESPACE }/settings`, method: 'POST', data } );

export const dismissNotice = ( id ) =>
	request( {
		path: `${ NAMESPACE }/notices/${ id }/dismiss`,
		method: 'POST',
	} );

/**
 * Submits a job. Scalar fields and files are sent as multipart form data
 * so painted masks and direct uploads travel in one request.
 *
 * @param {string} service Service id.
 * @param {Object} fields  Scalar fields (prompt, dimension, ...) plus
 *                         key_id / attachment_id / context.
 * @param {Object} files   Map of field name => Blob/File.
 * @return {Promise<Object>} The created job.
 */
export function submitJob( service, fields = {}, files = {} ) {
	const body = new window.FormData();

	body.append( 'service', service );

	Object.entries( fields ).forEach( ( [ key, value ] ) => {
		if ( value !== undefined && value !== null && value !== '' ) {
			body.append( key, value );
		}
	} );

	Object.entries( files ).forEach( ( [ key, file ] ) => {
		if ( file ) {
			body.append( key, file, file.name || `${ key }.png` );
		}
	} );

	return request( { path: `${ NAMESPACE }/jobs`, method: 'POST', body } );
}

/**
 * Boot data printed by the PHP page shell.
 *
 * @return {Object} zovizStudio boot data (empty object outside plugin pages).
 */
export function bootData() {
	return window.zovizStudio || {};
}
