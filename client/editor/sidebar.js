/**
 * The Zoviz editor sidebar: a trimmed Workspace scoped to the current
 * post. Opens itself when the block toolbar or featured-image panel
 * requests a run, and applies the result the way it was asked to —
 * replace a block's image, set the featured image, or insert a fresh
 * standard core/image block. Content it produces is plain core blocks, so
 * it survives the plugin being deactivated.
 */
import { store as blockEditorStore } from '@wordpress/block-editor';
import { createBlock } from '@wordpress/blocks';
import {
	Button,
	Flex,
	FlexItem,
	PanelBody,
	Spinner,
} from '@wordpress/components';
import { dispatch, select, useDispatch } from '@wordpress/data';
import {
	PluginSidebar,
	PluginSidebarMoreMenuItem,
	store as editorStore,
} from '@wordpress/editor';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import { submitJob } from '../shared/api/client';
import { CreditBadge } from '../shared/components/credit-badge';
import { ApiErrorNotice } from '../shared/components/insufficient-credits-notice';
import { JobRunner } from '../shared/components/job-runner';
import { KeyPicker } from '../shared/components/key-picker';
import { MaskCanvas } from '../shared/components/mask-canvas';
import {
	ServiceForm,
	scalarFieldsComplete,
} from '../shared/components/service-form';
import { SketchCanvas } from '../shared/components/sketch-canvas';
import { SourcePicker } from '../shared/components/source-picker';
import { useServices } from '../shared/hooks/use-services';
import { serviceIcon } from '../shared/service-icons';
import { onRunRequested } from './state';

const PLUGIN_NAME = 'zoviz-ai-studio';
const SIDEBAR_TARGET = 'sidebar';
const SIDEBAR_NAME = `${ PLUGIN_NAME }/${ SIDEBAR_TARGET }`;

/**
 * Opens the Zoviz sidebar. The host (post editor vs. site editor) owns a
 * different store for this, so try both rather than importing edit-post.
 */
function openSidebar() {
	[ 'core/edit-post', 'core/edit-site' ].forEach( ( store ) => {
		const actions = dispatch( store );

		if ( actions && typeof actions.openGeneralSidebar === 'function' ) {
			actions.openGeneralSidebar( SIDEBAR_NAME );
		}
	} );
}

function ZovizSidebarBody() {
	const { services, isLoading } = useServices();

	const [ serviceId, setServiceId ] = useState( '' );
	const [ source, setSource ] = useState( null );
	const [ mask, setMask ] = useState( null );
	const [ values, setValues ] = useState( {} );
	const [ keyId, setKeyId ] = useState( '' );
	const [ jobId, setJobId ] = useState( 0 );
	const [ target, setTarget ] = useState( null );
	const [ submitError, setSubmitError ] = useState( null );
	const [ isSubmitting, setIsSubmitting ] = useState( false );

	const { insertBlocks, updateBlockAttributes } =
		useDispatch( blockEditorStore );
	const { editPost } = useDispatch( editorStore );

	useEffect( () => {
		return onRunRequested( ( request ) => {
			setServiceId( request.serviceId || '' );
			setSource( request.source || null );
			setMask( null );
			setValues( request.values || {} );
			setJobId( 0 );
			setSubmitError( null );
			setTarget( request.target || null );
			openSidebar();
		} );
	}, [] );

	if ( isLoading ) {
		return <Spinner />;
	}

	const service =
		( services || [] ).find( ( entry ) => entry.id === serviceId ) || null;
	const needsSource = service && service.capabilities.source !== 'none';
	const needsSketch = service && service.capabilities.source === 'sketch';
	const needsMask = service && service.capabilities.mask;

	const selectService = ( id ) => {
		setServiceId( id );
		setSource( null );
		setMask( null );
		setValues( {} );
		setJobId( 0 );
		setSubmitError( null );
		setTarget( null );
	};

	const canSubmit =
		!! service &&
		! isSubmitting &&
		scalarFieldsComplete( service, values ) &&
		( ! needsSource || !! source ) &&
		( ! needsMask || !! mask );

	// Applies a finished job the way the request that opened the sidebar
	// asked for: replace the source block's image, set the featured
	// image, drop the new image right after the block the prompt text
	// came from, or — opened directly from the sidebar menu — insert a
	// new standard core/image block at the end.
	const applyResult = ( job ) => {
		if ( ! job || ! job.attachment_id ) {
			return;
		}

		if ( target && 'block' === target.type && target.clientId ) {
			updateBlockAttributes( target.clientId, {
				id: job.attachment_id,
				url: job.attachment_url,
			} );
			return;
		}

		if ( target && 'featured' === target.type ) {
			editPost( { featured_media: job.attachment_id } );
			return;
		}

		const imageBlock = createBlock( 'core/image', {
			id: job.attachment_id,
			url: job.attachment_url,
		} );

		if ( target && 'after-block' === target.type && target.clientId ) {
			const { getBlockIndex, getBlockRootClientId } =
				select( blockEditorStore );
			const index = getBlockIndex( target.clientId );
			const rootClientId = getBlockRootClientId( target.clientId );

			insertBlocks(
				imageBlock,
				index === -1 ? undefined : index + 1,
				rootClientId
			);
			return;
		}

		insertBlocks( imageBlock );
	};

	const submit = async () => {
		setIsSubmitting( true );
		setSubmitError( null );
		setJobId( 0 );

		const fields = { ...values, key_id: keyId, context: 'editor' };
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
		<PluginSidebar
			name={ SIDEBAR_TARGET }
			title={ __( 'Zoviz AI Studio', 'zoviz-ai-studio' ) }
			icon="art"
		>
			<PanelBody className="zoviz-app zoviz-editor-sidebar">
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
					className="zoviz-editor-sidebar__services zoviz-service-nav zoviz-service-nav--compact"
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

						{ needsSource && needsSketch && (
							<SketchCanvas
								onChange={ ( next ) => {
									setSource( next );
									setJobId( 0 );
								} }
							/>
						) }

						{ needsSource && ! needsSketch && (
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
								defaultBrushSize={
									service.id === 'image-editor' ? 10 : 40
								}
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
						</Button>

						<JobRunner
							jobId={ jobId }
							sourceUrl={ source ? source.url : null }
							onFinished={ applyResult }
						/>
					</>
				) }
			</PanelBody>
		</PluginSidebar>
	);
}

export function ZovizEditorPlugin() {
	return (
		<>
			<PluginSidebarMoreMenuItem target={ SIDEBAR_TARGET } icon="art">
				{ __( 'Zoviz AI Studio', 'zoviz-ai-studio' ) }
			</PluginSidebarMoreMenuItem>
			<ZovizSidebarBody />
		</>
	);
}
