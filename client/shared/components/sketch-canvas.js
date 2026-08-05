/**
 * Draw-your-own-sketch canvas for the sketch-to-image service: the same
 * brush / eraser / undo / clear interaction as `MaskCanvas`, except there is
 * no source image underneath — strokes are drawn straight onto a blank
 * white square, and the canvas itself (not a mask) becomes the uploaded
 * "sketch" file.
 */
import { Button, ButtonGroup, RangeControl } from '@wordpress/components';
import { useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

const MAX_UNDO = 20;
const CANVAS_SIZE = 1024;

export function SketchCanvas( { onChange } ) {
	const canvasRef = useRef( null );
	const undoStack = useRef( [] );
	const painting = useRef( false );

	const [ brushSize, setBrushSize ] = useState( 10 );
	const [ mode, setMode ] = useState( 'brush' );
	const [ hasStrokes, setHasStrokes ] = useState( false );

	// Start from a blank white square.
	useEffect( () => {
		const canvas = canvasRef.current;
		canvas.width = CANVAS_SIZE;
		canvas.height = CANVAS_SIZE;

		const context = canvas.getContext( '2d' );
		context.fillStyle = '#ffffff';
		context.fillRect( 0, 0, canvas.width, canvas.height );
	}, [] );

	const exportSketch = () => {
		canvasRef.current.toBlob( ( blob ) => {
			if ( ! blob ) {
				return;
			}

			blob.name = 'sketch.png';
			onChange( {
				file: blob,
				attachmentId: 0,
				url: window.URL.createObjectURL( blob ),
				title: blob.name,
			} );
		}, 'image/png' );
	};

	const canvasPoint = ( event ) => {
		const canvas = canvasRef.current;
		const rect = canvas.getBoundingClientRect();

		return {
			x: ( ( event.clientX - rect.left ) / rect.width ) * canvas.width,
			y: ( ( event.clientY - rect.top ) / rect.height ) * canvas.height,
		};
	};

	const pushUndo = () => {
		const canvas = canvasRef.current;
		const context = canvas.getContext( '2d' );

		undoStack.current.push(
			context.getImageData( 0, 0, canvas.width, canvas.height )
		);

		if ( undoStack.current.length > MAX_UNDO ) {
			undoStack.current.shift();
		}
	};

	const paint = ( event ) => {
		const { x, y } = canvasPoint( event );
		const canvas = canvasRef.current;
		const context = canvas.getContext( '2d' );
		const scale = canvas.width / canvas.getBoundingClientRect().width;
		const color = mode === 'eraser' ? '#ffffff' : '#000000';

		context.globalCompositeOperation = 'source-over';
		context.strokeStyle = color;
		context.fillStyle = color;
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
	};

	const onPointerDown = ( event ) => {
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
			exportSketch();
		}
	};

	const clearCanvas = () => {
		const canvas = canvasRef.current;
		const context = canvas.getContext( '2d' );
		context.fillStyle = '#ffffff';
		context.fillRect( 0, 0, canvas.width, canvas.height );
	};

	const undo = () => {
		const snapshot = undoStack.current.pop();

		if ( snapshot ) {
			canvasRef.current.getContext( '2d' ).putImageData( snapshot, 0, 0 );
			exportSketch();
		}

		if ( ! undoStack.current.length ) {
			setHasStrokes( false );
			clearCanvas();
			onChange( null );
		}
	};

	const clear = () => {
		clearCanvas();
		undoStack.current = [];
		setHasStrokes( false );
		onChange( null );
	};

	return (
		<div className="zoviz-mask-canvas zoviz-sketch-canvas">
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
					min={ 2 }
					max={ 60 }
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
					'Draw your sketch, then describe what to turn it into.',
					'zoviz-ai-studio'
				) }
			</p>
			<canvas
				ref={ canvasRef }
				className="zoviz-mask-canvas__surface zoviz-sketch-canvas__surface"
				onPointerDown={ onPointerDown }
				onPointerMove={ onPointerMove }
				onPointerUp={ onPointerUp }
				onPointerLeave={ onPointerUp }
			/>
		</div>
	);
}
