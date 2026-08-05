/**
 * WooCommerce product metabox: a trimmed Workspace scoped to one product,
 * defaulting to the product's current image as the source and Product
 * Photography as the starting service. Results can be set as the product
 * image or added to the gallery — MediaImporter::assign() does the actual
 * WooCommerce write server-side, so this stays feature-detected and never
 * touches Woo data directly from JS.
 */
import { Button, Flex, FlexItem, Notice, Spinner } from '@wordpress/components';
import domReady from '@wordpress/dom-ready';
import { createRoot, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import '../shared/styles.scss';
import { bootData, saveJob, submitJob } from '../shared/api/client';
import { CreditBadge } from '../shared/components/credit-badge';
import { ApiErrorNotice } from '../shared/components/insufficient-credits-notice';
import { JobRunner } from '../shared/components/job-runner';
import { KeyPicker } from '../shared/components/key-picker';
import {
	ServiceForm,
	scalarFieldsComplete,
} from '../shared/components/service-form';
import { SourcePicker } from '../shared/components/source-picker';
import { useServices } from '../shared/hooks/use-services';
import { serviceIcon } from '../shared/service-icons';

const DEFAULT_SERVICE = 'product-photography';

function productSource() {
	const product = bootData().product || {};

	if ( ! product.imageId ) {
		return null;
	}

	return {
		attachmentId: product.imageId,
		file: null,
		url: product.imageUrl,
		title: product.title,
	};
}

function WooProductApp() {
	const product = bootData().product || {};
	const { services, isLoading } = useServices();

	const [ serviceId, setServiceId ] = useState( DEFAULT_SERVICE );
	const [ source, setSource ] = useState( productSource );
	const [ values, setValues ] = useState( { prompt: product.title || '' } );
	const [ keyId, setKeyId ] = useState( '' );
	const [ jobId, setJobId ] = useState( 0 );
	const [ submitError, setSubmitError ] = useState( null );
	const [ isSubmitting, setIsSubmitting ] = useState( false );
	const [ isJobRunning, setIsJobRunning ] = useState( false );
	const [ assignError, setAssignError ] = useState( null );
	const [ assignNotice, setAssignNotice ] = useState( '' );

	if ( isLoading ) {
		return <Spinner />;
	}

	const service =
		( services || [] ).find( ( entry ) => entry.id === serviceId ) || null;
	const needsSource = service && service.capabilities.source !== 'none';

	const selectService = ( id ) => {
		setServiceId( id );
		setJobId( 0 );
		setSubmitError( null );
		setAssignNotice( '' );
		setValues(
			id === 'image-generator-2' ? { prompt: product.title || '' } : {}
		);
		setSource( id === 'image-generator-2' ? null : productSource() );
	};

	const canSubmit =
		!! service &&
		! isSubmitting &&
		! isJobRunning &&
		scalarFieldsComplete( service, values ) &&
		( ! needsSource || !! source );

	const submit = async () => {
		setIsSubmitting( true );
		setSubmitError( null );
		setJobId( 0 );
		setAssignNotice( '' );

		const fields = { ...values, key_id: keyId, context: 'woo-product' };
		const files = {};

		if ( needsSource && source ) {
			if ( source.attachmentId ) {
				fields.attachment_id = source.attachmentId;
			} else if ( source.file ) {
				files.image = source.file;
			}
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

	const assign = async ( type ) => {
		setAssignError( null );

		try {
			await saveJob( jobId, { assign: { type, post_id: product.id } } );
			setAssignNotice(
				'product_image' === type
					? __(
							'Set as the product image. Reload the page to see it.',
							'zoviz-ai-studio'
					  )
					: __(
							'Added to the product gallery. Reload the page to see it.',
							'zoviz-ai-studio'
					  )
			);
		} catch ( error ) {
			setAssignError( error );
		}
	};

	return (
		<div className="zoviz-app zoviz-woocommerce">
			<Flex justify="flex-end" gap={ 2 }>
				<FlexItem>
					<KeyPicker value={ keyId } onChange={ setKeyId } />
				</FlexItem>
				<FlexItem>
					<CreditBadge keyId={ keyId } />
				</FlexItem>
			</Flex>

			<Flex
				wrap
				gap={ 1 }
				className="zoviz-woocommerce__services zoviz-service-nav zoviz-service-nav--compact"
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
			</Flex>

			{ !! service && (
				<>
					<p className="description">{ service.description }</p>

					{ needsSource && (
						<SourcePicker
							source={ source }
							onChange={ setSource }
							label={ __( 'Pick from media', 'zoviz-ai-studio' ) }
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
						isBusy={ isSubmitting || isJobRunning }
						onClick={ submit }
					>
						{ __( 'Run', 'zoviz-ai-studio' ) }
					</Button>

					{ !! assignError && (
						<ApiErrorNotice
							error={ assignError }
							onDismiss={ () => setAssignError( null ) }
						/>
					) }

					{ !! assignNotice && (
						<Notice
							status="success"
							isDismissible
							onRemove={ () => setAssignNotice( '' ) }
						>
							{ assignNotice }
						</Notice>
					) }

					<JobRunner
						jobId={ jobId }
						sourceUrl={ source ? source.url : null }
						onBusyChange={ setIsJobRunning }
						extraActions={
							!! product.id && (
								<Flex gap={ 2 }>
									<Button
										variant="secondary"
										onClick={ () =>
											assign( 'product_image' )
										}
									>
										{ __(
											'Set as product image',
											'zoviz-ai-studio'
										) }
									</Button>
									<Button
										variant="secondary"
										onClick={ () =>
											assign( 'product_gallery' )
										}
									>
										{ __(
											'Add to gallery',
											'zoviz-ai-studio'
										) }
									</Button>
								</Flex>
							)
						}
					/>
				</>
			) }
		</div>
	);
}

domReady( () => {
	const rootElement = document.getElementById( 'zoviz-woocommerce-root' );

	if ( rootElement ) {
		createRoot( rootElement ).render( <WooProductApp /> );
	}
} );
