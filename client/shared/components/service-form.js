/**
 * Renders a service's scalar fields straight from the REST catalog schema.
 * Adding a PHP service produces its form automatically — no per-service
 * UI code unless a field needs a special control.
 */
import {
	SelectControl,
	TextControl,
	TextareaControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

function fieldLabel( name, schema, required ) {
	const label = schema.label || name;

	return required
		? label
		: `${ label } (${ __( 'optional', 'zoviz-ai-studio' ) })`;
}

export function ServiceForm( { service, values = {}, onChange } ) {
	if ( ! service ) {
		return null;
	}

	const update = ( name ) => ( value ) =>
		onChange( { ...values, [ name ]: value } );

	const controls = Object.entries( service.fields || {} )
		.filter( ( [ , schema ] ) => schema.type !== 'file' )
		.map( ( [ name, schema ] ) => {
			const required = !! schema.required;
			const label = fieldLabel( name, schema, required );
			const value = values[ name ] ?? schema.default ?? '';

			if ( schema.type === 'enum' ) {
				return (
					<SelectControl
						__nextHasNoMarginBottom
						key={ name }
						label={ label }
						value={ String( value ) }
						options={ ( schema.options || [] ).map(
							( option ) => ( {
								value: option,
								label: option,
							} )
						) }
						onChange={ update( name ) }
					/>
				);
			}

			if ( schema.type === 'integer' ) {
				return (
					<TextControl
						__nextHasNoMarginBottom
						key={ name }
						type="number"
						label={ label }
						value={ value === '' ? '' : String( value ) }
						min={ schema.min }
						max={ schema.max }
						required={ required }
						onChange={ update( name ) }
					/>
				);
			}

			// Long-text heuristic: prompts get a textarea.
			if ( name === 'prompt' ) {
				return (
					<TextareaControl
						__nextHasNoMarginBottom
						key={ name }
						label={ label }
						value={ String( value ) }
						rows={ 3 }
						required={ required }
						onChange={ update( name ) }
					/>
				);
			}

			return (
				<TextControl
					__nextHasNoMarginBottom
					key={ name }
					label={ label }
					value={ String( value ) }
					required={ required }
					onChange={ update( name ) }
				/>
			);
		} );

	return <div className="zoviz-service-form">{ controls }</div>;
}

/**
 * Whether all required scalar fields have values (files are validated by
 * the caller, which owns the pickers).
 *
 * @param {Object} service Service catalog entry.
 * @param {Object} values  Current field values.
 * @return {boolean} Ready to submit.
 */
export function scalarFieldsComplete( service, values = {} ) {
	return Object.entries( service?.fields || {} )
		.filter( ( [ , schema ] ) => schema.type !== 'file' && schema.required )
		.every( ( [ name, schema ] ) => {
			const value = values[ name ] ?? schema.default ?? '';

			return String( value ).trim() !== '';
		} );
}
