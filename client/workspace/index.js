/**
 * Workspace: the full-featured home for every Zoviz service. Other
 * surfaces deep-link here with ?service=…&attachment=….
 */
import apiFetch from '@wordpress/api-fetch';
import { Button, Flex, FlexItem, Spinner } from '@wordpress/components';
import domReady from '@wordpress/dom-ready';
import { createRoot, useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { getQueryArg } from '@wordpress/url';

import '../shared/styles.scss';
import { submitJob } from '../shared/api/client';
import { CreditBadge } from '../shared/components/credit-badge';
import { ApiErrorNotice } from '../shared/components/insufficient-credits-notice';
import { JobRunner } from '../shared/components/job-runner';
import { KeyPicker } from '../shared/components/key-picker';
import { LogoMark } from '../shared/components/logo-mark';
import { MaskCanvas } from '../shared/components/mask-canvas';
import {
	ServiceForm,
	scalarFieldsComplete,
} from '../shared/components/service-form';
import { SourcePicker } from '../shared/components/source-picker';
import { serviceIcon } from '../shared/service-icons';
import { useServices } from '../shared/hooks/use-services';

function WorkspaceApp() {
	const { services, isLoading } = useServices();

	const [ serviceId, setServiceId ] = useState(
		getQueryArg( window.location.href, 'service' ) || ''
	);
	const [ source, setSource ] = useState( null );
	const [ mask, setMask ] = useState( null );
	const [ values, setValues ] = useState( {} );
	const [ keyId, setKeyId ] = useState( '' );
	const [ jobId, setJobId ] = useState( 0 );
	const [ submitError, setSubmitError ] = useState( null );
	const [ isSubmitting, setIsSubmitting ] = useState( false );

	// Deep link: preload an attachment passed by another surface.
	useEffect( () => {
		const attachmentId = parseInt(
			getQueryArg( window.location.href, 'attachment' ) || '0',
			10
		);

		if ( ! attachmentId ) {
			return;
		}

		apiFetch( { path: `/wp/v2/media/${ attachmentId }` } )
			.then( ( media ) =>
				setSource( {
					attachmentId,
					file: null,
					url: media.source_url,
					title: media.title ? media.title.rendered : '',
				} )
			)
			.catch( () => {} );
	}, [] );

	if ( isLoading ) {
		return <Spinner />;
	}

	const service =
		( services || [] ).find( ( entry ) => entry.id === serviceId ) || null;
	const needsSource = service && service.capabilities.source !== 'none';
	const needsMask = service && service.capabilities.mask;

	const selectService = ( id ) => {
		setServiceId( id );
		setMask( null );
		setValues( {} );
		setJobId( 0 );
		setSubmitError( null );
	};

	const canSubmit =
		!! service &&
		! isSubmitting &&
		scalarFieldsComplete( service, values ) &&
		( ! needsSource || !! source ) &&
		( ! needsMask || !! mask );

	const submit = async () => {
		setIsSubmitting( true );
		setSubmitError( null );
		setJobId( 0 );

		const fields = {
			...values,
			key_id: keyId,
			context: 'workspace',
		};
		const files = {};

		if ( needsSource && source ) {
			if ( source.attachmentId ) {
				fields.attachment_id = source.attachmentId;
			} else if ( source.file ) {
				files[
					service.capabilities.source === 'sketch'
						? 'sketch'
						: 'image'
				] = source.file;
			}
		}

		if ( needsMask && mask ) {
			files.mask = mask;
		}

		try {
			const job = await submitJob( service.id, fields, files );
			setJobId( job.id );
		} catch ( error ) {
			setSubmitError( error );
		} finally {
			setIsSubmitting( false );
		}
	};

	return (
		<div className="zoviz-app">
			<div className="zoviz-app__header">
				<div className="zoviz-app__title">
					<LogoMark />
					<h1>{ __( 'Zoviz AI Studio', 'zoviz-ai-studio' ) }</h1>
				</div>
				<Flex justify="flex-end" gap={ 3 }>
					<FlexItem>
						<KeyPicker value={ keyId } onChange={ setKeyId } />
					</FlexItem>
					<FlexItem>
						<CreditBadge keyId={ keyId } />
					</FlexItem>
				</Flex>
			</div>

			<div className="zoviz-workspace">
				<nav
					className="zoviz-workspace__services zoviz-service-nav"
					aria-label={ __( 'Zoviz services', 'zoviz-ai-studio' ) }
				>
					{ ( services || [] ).map( ( entry ) => (
						<Button
							key={ entry.id }
							icon={ serviceIcon( entry.id ) }
							variant={
								entry.id === serviceId ? 'primary' : 'tertiary'
							}
							onClick={ () => selectService( entry.id ) }
						>
							{ entry.label }
						</Button>
					) ) }
				</nav>

				<div className="zoviz-workspace__panel">
					{ ! service && (
						<p>
							{ __(
								'Pick a service to get started.',
								'zoviz-ai-studio'
							) }
						</p>
					) }

					{ !! service && (
						<>
							<p className="description">
								{ service.description }
							</p>

							{ needsSource && (
								<SourcePicker
									source={ source }
									onChange={ ( next ) => {
										setSource( next );
										setMask( null );
										setJobId( 0 );
									} }
								/>
							) }

							{ needsMask && !! source && (
								<MaskCanvas
									imageUrl={ source.url }
									onMaskChange={ setMask }
								/>
							) }

							<ServiceForm
								service={ service }
								values={ values }
								onChange={ setValues }
							/>

							{ !! submitError && (
								<ApiErrorNotice
									error={ submitError }
									onDismiss={ () => setSubmitError( null ) }
								/>
							) }

							<Button
								variant="primary"
								disabled={ ! canSubmit }
								isBusy={ isSubmitting }
								onClick={ submit }
							>
								{ __( 'Run', 'zoviz-ai-studio' ) }
								{ service.credit_cost > 1
									? ` (${ service.credit_cost } ${ __(
											'credits',
											'zoviz-ai-studio'
									  ) })`
									: '' }
							</Button>

							<JobRunner
								jobId={ jobId }
								sourceUrl={ source ? source.url : null }
							/>
						</>
					) }
				</div>
			</div>
		</div>
	);
}

domReady( () => {
	const rootElement = document.getElementById( 'zoviz-workspace-root' );

	if ( rootElement ) {
		createRoot( rootElement ).render( <WorkspaceApp /> );
	}
} );
