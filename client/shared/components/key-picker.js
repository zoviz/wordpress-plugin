/**
 * API key selector. Renders NOTHING when fewer than two keys exist — the
 * single (default) key is used implicitly, keeping simple setups clean.
 */
import { SelectControl } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import { bootData, getKeys } from '../api/client';

export function KeyPicker( { value, onChange } ) {
	const [ keys, setKeys ] = useState( null );

	useEffect( () => {
		// Boot data already tells us when a picker cannot be needed.
		if ( ( bootData().keyCount || 0 ) < 2 ) {
			return;
		}

		getKeys()
			.then( setKeys )
			.catch( () => setKeys( null ) );
	}, [] );

	if ( ! keys || keys.length < 2 ) {
		return null;
	}

	return (
		<SelectControl
			__nextHasNoMarginBottom
			className="zoviz-key-picker"
			label={ __( 'Zoviz workspace', 'zoviz-ai-studio' ) }
			value={ value || bootData().defaultKeyId || keys[ 0 ].id }
			options={ keys.map( ( key ) => ( {
				value: key.id,
				label: `${ key.label } (${ key.masked })`,
			} ) ) }
			onChange={ onChange }
		/>
	);
}
