/**
 * Settings: API key manager (validate-before-save, masked display,
 * newest-key-becomes-default) and plugin options.
 */
import {
	Button,
	Notice,
	Spinner,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import domReady from '@wordpress/dom-ready';
import { createRoot, useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

import '../shared/style.scss';
import {
	addKey,
	deleteKey,
	getKeys,
	getSettings,
	updateKey,
	updateSettings,
} from '../shared/api/client';
import { CreditBadge } from '../shared/components/credit-badge';
import { ApiErrorNotice } from '../shared/components/insufficient-credits-notice';

function KeysManager( { onKeysChanged } ) {
	const [ keys, setKeys ] = useState( null );
	const [ label, setLabel ] = useState( '' );
	const [ secret, setSecret ] = useState( '' );
	const [ error, setError ] = useState( null );
	const [ success, setSuccess ] = useState( '' );
	const [ isBusy, setIsBusy ] = useState( false );

	const reload = () =>
		getKeys()
			.then( ( result ) => {
				setKeys( result );
				onKeysChanged( result );
			} )
			.catch( setError );

	useEffect( () => {
		reload();
	}, [] ); // eslint-disable-line react-hooks/exhaustive-deps

	const submit = async () => {
		setIsBusy( true );
		setError( null );
		setSuccess( '' );

		try {
			const key = await addKey( label, secret );
			setSuccess(
				sprintf(
					/* translators: %s: API key label. */
					__(
						'Key "%s" was validated and saved. It is now the default.',
						'zoviz-ai-studio'
					),
					key.label
				)
			);
			setLabel( '' );
			setSecret( '' );
			await reload();
		} catch ( err ) {
			setError( err );
		} finally {
			setIsBusy( false );
		}
	};

	const makeDefault = async ( id ) => {
		await updateKey( id, { is_default: true } ).catch( setError );
		await reload();
	};

	const remove = async ( key ) => {
		// eslint-disable-next-line no-alert -- Simple confirm is appropriate for a destructive admin action.
		const confirmed = window.confirm(
			sprintf(
				/* translators: %s: API key label. */
				__(
					'Delete the key "%s"? Jobs created with it remain in the history.',
					'zoviz-ai-studio'
				),
				key.label
			)
		);

		if ( ! confirmed ) {
			return;
		}

		await deleteKey( key.id ).catch( setError );
		await reload();
	};

	if ( ! keys ) {
		return <Spinner />;
	}

	return (
		<section>
			<h2>{ __( 'API keys', 'zoviz-ai-studio' ) }</h2>
			<p className="description">
				{ __(
					'Each key belongs to a Zoviz workspace and its credit balance. The most recently added key becomes the default automatically.',
					'zoviz-ai-studio'
				) }
			</p>

			{ !! error && (
				<ApiErrorNotice
					error={ error }
					onDismiss={ () => setError( null ) }
				/>
			) }
			{ !! success && (
				<Notice status="success" onRemove={ () => setSuccess( '' ) }>
					{ success }
				</Notice>
			) }

			{ keys.length > 0 && (
				<table className="widefat striped zoviz-keys-table">
					<thead>
						<tr>
							<th>{ __( 'Label', 'zoviz-ai-studio' ) }</th>
							<th>{ __( 'Key', 'zoviz-ai-studio' ) }</th>
							<th>{ __( 'Status', 'zoviz-ai-studio' ) }</th>
							<th>{ __( 'Credits', 'zoviz-ai-studio' ) }</th>
							<th>{ __( 'Default', 'zoviz-ai-studio' ) }</th>
							<th />
						</tr>
					</thead>
					<tbody>
						{ keys.map( ( key ) => (
							<tr key={ key.id }>
								<td>{ key.label }</td>
								<td>
									<code>{ key.masked }</code>
								</td>
								<td>
									{ key.is_valid ? (
										__( 'Valid', 'zoviz-ai-studio' )
									) : (
										<span className="zoviz-key-invalid">
											{ __(
												'Invalid — please replace this key',
												'zoviz-ai-studio'
											) }
										</span>
									) }
								</td>
								<td>
									<CreditBadge keyId={ key.id } />
								</td>
								<td>
									{ key.is_default ? (
										<strong>
											{ __(
												'Default',
												'zoviz-ai-studio'
											) }
										</strong>
									) : (
										<Button
											variant="link"
											onClick={ () =>
												makeDefault( key.id )
											}
										>
											{ __(
												'Make default',
												'zoviz-ai-studio'
											) }
										</Button>
									) }
								</td>
								<td>
									<Button
										variant="link"
										isDestructive
										onClick={ () => remove( key ) }
									>
										{ __( 'Delete', 'zoviz-ai-studio' ) }
									</Button>
								</td>
							</tr>
						) ) }
					</tbody>
				</table>
			) }

			<h3>{ __( 'Add a key', 'zoviz-ai-studio' ) }</h3>
			<p className="description">
				{ __(
					'The key is validated against the Zoviz API before it is saved, and it is stored encrypted.',
					'zoviz-ai-studio'
				) }
			</p>
			<div className="zoviz-service-form">
				<TextControl
					__nextHasNoMarginBottom
					label={ __( 'Label', 'zoviz-ai-studio' ) }
					help={ __(
						'For example the workspace name.',
						'zoviz-ai-studio'
					) }
					value={ label }
					onChange={ setLabel }
				/>
				<TextControl
					__nextHasNoMarginBottom
					label={ __( 'API key', 'zoviz-ai-studio' ) }
					type="password"
					value={ secret }
					onChange={ setSecret }
				/>
				<div>
					<Button
						variant="primary"
						isBusy={ isBusy }
						disabled={ isBusy || ! label.trim() || ! secret.trim() }
						onClick={ submit }
					>
						{ __( 'Validate & save', 'zoviz-ai-studio' ) }
					</Button>
				</div>
			</div>
		</section>
	);
}

function PluginSettings() {
	const [ settings, setSettings ] = useState( null );
	const [ isSaving, setIsSaving ] = useState( false );
	const [ saved, setSaved ] = useState( false );
	const [ error, setError ] = useState( null );

	useEffect( () => {
		getSettings().then( setSettings ).catch( setError );
	}, [] );

	if ( ! settings ) {
		return error ? <ApiErrorNotice error={ error } /> : <Spinner />;
	}

	const save = async () => {
		setIsSaving( true );
		setSaved( false );

		try {
			setSettings( await updateSettings( settings ) );
			setSaved( true );
		} catch ( err ) {
			setError( err );
		} finally {
			setIsSaving( false );
		}
	};

	return (
		<section>
			<h2>{ __( 'Options', 'zoviz-ai-studio' ) }</h2>

			{ !! error && (
				<ApiErrorNotice
					error={ error }
					onDismiss={ () => setError( null ) }
				/>
			) }
			{ saved && (
				<Notice status="success" onRemove={ () => setSaved( false ) }>
					{ __( 'Settings saved.', 'zoviz-ai-studio' ) }
				</Notice>
			) }

			<div className="zoviz-service-form">
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __(
						'Automatically save finished results to the Media Library',
						'zoviz-ai-studio'
					) }
					help={ __(
						'Recommended: Zoviz keeps results available for a limited time only. Saving immediately means you never lose one.',
						'zoviz-ai-studio'
					) }
					checked={ !! settings.auto_download }
					onChange={ ( value ) =>
						setSettings( { ...settings, auto_download: value } )
					}
				/>
				<TextControl
					__nextHasNoMarginBottom
					type="number"
					min={ 1 }
					label={ __(
						'Keep job history for (days)',
						'zoviz-ai-studio'
					) }
					value={ String( settings.retention_days ) }
					onChange={ ( value ) =>
						setSettings( { ...settings, retention_days: value } )
					}
				/>
				<div>
					<Button
						variant="primary"
						isBusy={ isSaving }
						onClick={ save }
					>
						{ __( 'Save settings', 'zoviz-ai-studio' ) }
					</Button>
				</div>
			</div>
		</section>
	);
}

function SettingsApp() {
	const [ , setKeys ] = useState( [] );

	return (
		<div className="zoviz-app">
			<div className="zoviz-app__header">
				<h1>{ __( 'Zoviz Settings', 'zoviz-ai-studio' ) }</h1>
			</div>
			<KeysManager onKeysChanged={ setKeys } />
			<hr />
			<PluginSettings />
		</div>
	);
}

domReady( () => {
	const rootElement = document.getElementById( 'zoviz-settings-root' );

	if ( rootElement ) {
		createRoot( rootElement ).render( <SettingsApp /> );
	}
} );
