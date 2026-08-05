/**
 * Chooses the source image: pick from the Media Library (when the wp.media
 * frame is available) or upload a file. Emits either an attachment
 * ({ attachmentId, url }) or a raw file ({ file, url }).
 */
import { Button, DropZone, FormFileUpload } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const ACCEPT = 'image/jpeg,image/png,image/webp';

export function SourcePicker( { source, onChange, label, hidePreview } ) {
	const pickFile = ( file ) => {
		if ( ! file ) {
			return;
		}

		onChange( {
			file,
			attachmentId: 0,
			url: window.URL.createObjectURL( file ),
			title: file.name,
		} );
	};

	const openMediaLibrary = () => {
		const frame = window.wp.media( {
			title: __( 'Select an image', 'zoviz-ai-studio' ),
			multiple: false,
			library: { type: 'image' },
		} );

		frame.on( 'select', () => {
			const attachment = frame
				.state()
				.get( 'selection' )
				.first()
				.toJSON();

			onChange( {
				file: null,
				attachmentId: attachment.id,
				url: attachment.url,
				title: attachment.title || attachment.filename,
			} );
		} );

		frame.open();
	};

	// When a mask painter is also showing the same image (needs-mask
	// services), skip this preview entirely rather than displaying the
	// source image twice — the mask canvas below shows it, and gets its
	// own "Remove" control instead.
	if ( hidePreview && source && source.url ) {
		return null;
	}

	return (
		<div className="zoviz-source-picker">
			{ source && source.url && (
				<div className="zoviz-source-picker__preview">
					<img src={ source.url } alt={ source.title || '' } />
					<Button
						variant="tertiary"
						isDestructive
						onClick={ () => onChange( null ) }
					>
						{ __( 'Remove', 'zoviz-ai-studio' ) }
					</Button>
				</div>
			) }

			{ ! source && (
				<div className="zoviz-source-picker__controls">
					<DropZone
						onFilesDrop={ ( files ) =>
							pickFile( files && files[ 0 ] )
						}
					/>
					{ !! ( window.wp && window.wp.media ) && (
						<Button
							variant="secondary"
							onClick={ openMediaLibrary }
						>
							{ label ||
								__(
									'Select from Media Library',
									'zoviz-ai-studio'
								) }
						</Button>
					) }
					<FormFileUpload
						accept={ ACCEPT }
						variant="secondary"
						onChange={ ( event ) =>
							pickFile( event.target.files[ 0 ] )
						}
					>
						{ __( 'Upload image', 'zoviz-ai-studio' ) }
					</FormFileUpload>
				</div>
			) }
		</div>
	);
}
