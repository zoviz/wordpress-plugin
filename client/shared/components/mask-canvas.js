/**
 * Brush-based mask painter for the object remover and image editor.
 * Scope is deliberately locked to brush / eraser / undo / clear; the
 * export is a black-and-white PNG at the image's natural resolution
 * (white marks the region to edit or remove).
 */
import { Button, ButtonGroup, RangeControl } from '@wordpress/components';
import { useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

const MAX_UNDO = 20;
const OVERLAY = 'rgba(233, 30, 99, 0.55)'; // Display-only tint; export is white.

export function MaskCanvas( {
	imageUrl,
	onMaskChange,
	onRemoveSource,
	defaultBrushSize = 40,
} ) {
	const displayRef = useRef( null );
	const maskRef = useRef( document.createElement( 'canvas' ) );
	const imageRef = useRef( null );
	const undoStack = useRef( [] );
	const painting = useRef( false );

	const [ brushSize, setBrushSize ] = useState( defaultBrushSize );
	const [ mode, setMode ] = useState( 'brush' );
	const [ hasStrokes, setHasStrokes ] = useState( false );
	const [ ready, setReady ] = useState( false );

	// Load the image and size both canvases to its natural resolution.
	useEffect( () => {
		setReady( false );
		setHasStrokes( false );
		undoStack.current = [];

		if ( ! imageUrl ) {
			return;
		}

		const image = new window.Image();
		image.crossOrigin = 'anonymous';
		image.onload = () => {
			imageRef.current = image;

			const mask = maskRef.current;
			mask.width = image.naturalWidth;
			mask.height = image.naturalHeight;
			mask.getContext( '2d' ).clearRect( 0, 0, mask.width, mask.height );

			const display = displayRef.current;
			if ( display ) {
				display.width = image.naturalWidth;
				display.height = image.naturalHeight;
			}

			setReady( true );
		};
		image.src = imageUrl;
	}, [ imageUrl ] );

	// Redraws the display canvas: image + tinted mask.
	const redraw = () => {
		const display = displayRef.current;
		const image = imageRef.current;

		if ( ! display || ! image ) {
			return;
		}

		const context = display.getContext( '2d' );
		context.clearRect( 0, 0, display.width, display.height );
		context.drawImage( image, 0, 0 );

		// Tint the painted area for visibility.
		const mask = maskRef.current;
		const tint = document.createElement( 'canvas' );
		tint.width = mask.width;
		tint.height = mask.height;

		const tintContext = tint.getContext( '2d' );
		tintContext.drawImage( mask, 0, 0 );
		tintContext.globalCompositeOperation = 'source-in';
		tintContext.fillStyle = OVERLAY;
		tintContext.fillRect( 0, 0, tint.width, tint.height );

		context.drawImage( tint, 0, 0 );
	};

	useEffect( redraw, [ ready ] );

	const exportMask = () => {
		const mask = maskRef.current;
		const output = document.createElement( 'canvas' );
		output.width = mask.width;
		output.height = mask.height;

		const context = output.getContext( '2d' );
		context.fillStyle = '#000000';
		context.fillRect( 0, 0, output.width, output.height );
		context.drawImage( mask, 0, 0 );

		output.toBlob( ( blob ) => {
			if ( blob ) {
				blob.name = 'mask.png';
				onMaskChange( blob );
			}
		}, 'image/png' );
	};

	const canvasPoint = ( event ) => {
		const display = displayRef.current;
		const rect = display.getBoundingClientRect();

		return {
			x: ( ( event.clientX - rect.left ) / rect.width ) * display.width,
			y: ( ( event.clientY - rect.top ) / rect.height ) * display.height,
		};
	};

	const pushUndo = () => {
		const mask = maskRef.current;
		const context = mask.getContext( '2d' );

		undoStack.current.push(
			context.getImageData( 0, 0, mask.width, mask.height )
		);

		if ( undoStack.current.length > MAX_UNDO ) {
			undoStack.current.shift();
		}
	};

	const paint = ( event ) => {
		const { x, y } = canvasPoint( event );
		const mask = maskRef.current;
		const context = mask.getContext( '2d' );
		const scale =
			mask.width / displayRef.current.getBoundingClientRect().width;

		context.globalCompositeOperation =
			mode === 'eraser' ? 'destination-out' : 'source-over';
		context.strokeStyle = '#ffffff';
		context.fillStyle = '#ffffff';
		context.lineWidth = brushSize * scale;
		context.lineCap = 'round';
		context.lineJoin = 'round';

		if ( painting.current === 'started' ) {
			context.beginPath();
			context.arc( x, y, ( brushSize * scale ) / 2, 0, 2 * Math.PI );
			context.fill();
			context.beginPath();
			context.moveTo( x, y );
			painting.current = { x, y };
		} else if ( painting.current ) {
			context.beginPath();
			context.moveTo( painting.current.x, painting.current.y );
			context.lineTo( x, y );
			context.stroke();
			painting.current = { x, y };
		}

		redraw();
	};

	const onPointerDown = ( event ) => {
		if ( ! ready ) {
			return;
		}

		event.target.setPointerCapture( event.pointerId );
		pushUndo();
		painting.current = 'started';
		paint( event );
		setHasStrokes( true );
	};

	const onPointerMove = ( event ) => {
		if ( painting.current ) {
			paint( event );
		}
	};

	const onPointerUp = () => {
		if ( painting.current ) {
			painting.current = false;
			exportMask();
		}
	};

	const undo = () => {
		const snapshot = undoStack.current.pop();

		if ( snapshot ) {
			maskRef.current.getContext( '2d' ).putImageData( snapshot, 0, 0 );
			redraw();
			exportMask();
		}

		if ( ! undoStack.current.length ) {
			setHasStrokes( false );
			onMaskChange( null );
		}
	};

	const clear = () => {
		const mask = maskRef.current;
		mask.getContext( '2d' ).clearRect( 0, 0, mask.width, mask.height );
		undoStack.current = [];
		setHasStrokes( false );
		redraw();
		onMaskChange( null );
	};

	if ( ! imageUrl ) {
		return null;
	}

	return (
		<div className="zoviz-mask-canvas">
			<div className="zoviz-mask-canvas__toolbar">
				<ButtonGroup>
					<Button
						variant={ mode === 'brush' ? 'primary' : 'secondary' }
						onClick={ () => setMode( 'brush' ) }
					>
						{ __( 'Brush', 'zoviz-ai-studio' ) }
					</Button>
					<Button
						variant={ mode === 'eraser' ? 'primary' : 'secondary' }
						onClick={ () => setMode( 'eraser' ) }
					>
						{ __( 'Eraser', 'zoviz-ai-studio' ) }
					</Button>
				</ButtonGroup>
				<RangeControl
					__nextHasNoMarginBottom
					label={ __( 'Brush size', 'zoviz-ai-studio' ) }
					value={ brushSize }
					onChange={ setBrushSize }
					min={ 5 }
					max={ 150 }
				/>
				<Button
					variant="tertiary"
					onClick={ undo }
					disabled={ ! hasStrokes }
				>
					{ __( 'Undo', 'zoviz-ai-studio' ) }
				</Button>
				<Button
					variant="tertiary"
					isDestructive
					onClick={ clear }
					disabled={ ! hasStrokes }
				>
					{ __( 'Clear', 'zoviz-ai-studio' ) }
				</Button>
			</div>
			<p className="description">
				{ __(
					'Paint over the area you want to change. The painted area is sent to Zoviz as the mask.',
					'zoviz-ai-studio'
				) }
			</p>
			{ !! onRemoveSource && (
				<Button
					variant="tertiary"
					isDestructive
					className="zoviz-mask-canvas__remove"
					onClick={ onRemoveSource }
				>
					{ __( 'Remove', 'zoviz-ai-studio' ) }
				</Button>
			) }
			<canvas
				ref={ displayRef }
				className="zoviz-mask-canvas__surface"
				onPointerDown={ onPointerDown }
				onPointerMove={ onPointerMove }
				onPointerUp={ onPointerUp }
				onPointerLeave={ onPointerUp }
			/>
		</div>
	);
}
