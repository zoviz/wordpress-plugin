/**
 * Regression tests for the Workspace app and the shared JobRunner that it
 * (and the editor sidebar) render every result through.
 *
 * - Each service must keep its own source/mask/values so picking an image
 *   (or drawing a sketch) for one service never leaks into another.
 * - Finishing one job and then, without reloading, running a second job
 *   through the same JobRunner instance must show and report the second
 *   job's own result — not the first job's result left over in JobRunner's
 *   local `savedJob` state. (This is what the sidebar hit: run Text to
 *   Image, then — without reloading — run Sketch to Image; the preview
 *   kept showing the Text to Image result even though the sketch job had
 *   actually finished and saved correctly.)
 */
import { fireEvent, render, screen, waitFor } from '@testing-library/react';

jest.mock( '../../client/shared/hooks/use-services', () => ( {
	useServices: () => ( {
		services: [
			{
				id: 'sketch-to-image',
				label: 'Sketch to Image',
				description: '',
				credit_cost: 1,
				fields: {},
				capabilities: { source: 'sketch', mask: false, bulk: false },
			},
			{
				id: 'image-editor',
				label: 'Image Editor',
				description: '',
				credit_cost: 1,
				fields: {},
				capabilities: { source: 'image', mask: false, bulk: false },
			},
		],
		isLoading: false,
	} ),
} ) );

jest.mock( '../../client/shared/components/sketch-canvas', () => ( {
	SketchCanvas: ( { onChange } ) => (
		<button
			onClick={ () =>
				onChange( {
					file: new Blob(),
					attachmentId: 0,
					url: 'blob:sketch',
					title: 'sketch.png',
				} )
			}
		>
			draw-sketch
		</button>
	),
} ) );

jest.mock( '../../client/shared/components/source-picker', () => ( {
	SourcePicker: ( { source } ) => (
		<div data-testid="source-picker">
			{ source ? source.url : 'no-source' }
		</div>
	),
} ) );

jest.mock( '../../client/shared/components/mask-canvas', () => ( {
	MaskCanvas: () => null,
} ) );

jest.mock( '../../client/shared/components/key-picker', () => ( {
	KeyPicker: () => null,
} ) );

jest.mock( '../../client/shared/components/credit-badge', () => ( {
	CreditBadge: () => null,
} ) );

jest.mock( '../../client/shared/components/logo-mark', () => ( {
	LogoMark: () => null,
} ) );

// JobRunner is left un-mocked everywhere in this file — it's the component
// the bug lives in. Only its two dependencies are mocked: job polling
// (resolves synchronously to a known, per-id result — no timers involved)
// and job saving (never hit here since every job below already carries an
// attachment_id, i.e. auto-download succeeded).
jest.mock( '../../client/shared/hooks/use-job-polling', () => ( {
	useJobPolling: jest.fn(),
} ) );
jest.mock( '../../client/shared/api/client', () => ( {
	submitJob: jest.fn(),
	saveJob: jest.fn(),
} ) );

import { useJobPolling } from '../../client/shared/hooks/use-job-polling';
import { JobRunner } from '../../client/shared/components/job-runner';
import { WorkspaceApp } from '../../client/workspace/index';

// One finished job per id, each with its own attachment so a mix-up
// between them is easy to catch.
const JOBS_BY_ID = {
	1: {
		id: 1,
		status: 'succeeded',
		attachment_id: 101,
		attachment_url: 'https://example.com/text-to-image-result.png',
	},
	2: {
		id: 2,
		status: 'succeeded',
		attachment_id: 102,
		attachment_url: 'https://example.com/sketch-to-image-result.png',
	},
};

beforeEach( () => {
	useJobPolling.mockImplementation( ( jobId ) => ( {
		job: JOBS_BY_ID[ jobId ] || null,
		error: null,
		isPolling: false,
	} ) );
} );

describe( 'WorkspaceApp', () => {
	test( 'a source picked for one service does not appear under another', () => {
		render( <WorkspaceApp /> );

		fireEvent.click( screen.getByText( 'Sketch to Image' ) );
		fireEvent.click( screen.getByText( 'draw-sketch' ) );

		fireEvent.click( screen.getByText( 'Image Editor' ) );
		expect( screen.getByTestId( 'source-picker' ) ).toHaveTextContent(
			'no-source'
		);

		fireEvent.click( screen.getByText( 'Sketch to Image' ) );
		// Switching back to the sketch service still shows its own sketch
		// canvas control (source untouched by the detour through Image
		// Editor).
		expect( screen.getByText( 'draw-sketch' ) ).toBeInTheDocument();
	} );
} );

describe( 'JobRunner', () => {
	test( 'a second, different job replaces the first job’s result and re-fires onFinished', async () => {
		const onFinished = jest.fn();

		const { rerender } = render(
			<JobRunner jobId={ 1 } sourceUrl={ null } onFinished={ onFinished } />
		);

		await waitFor( () =>
			expect( screen.getByAltText( 'Result' ) ).toHaveAttribute(
				'src',
				JOBS_BY_ID[ 1 ].attachment_url
			)
		);
		expect( onFinished ).toHaveBeenCalledWith( JOBS_BY_ID[ 1 ] );

		// The parent (e.g. the editor sidebar, opened again for a new run)
		// submits a new, different job without unmounting JobRunner — it
		// just changes the jobId prop on the same instance.
		rerender(
			<JobRunner jobId={ 2 } sourceUrl={ null } onFinished={ onFinished } />
		);

		// The result shown must be the new job (id 2), not a stale copy of
		// the first job (id 1) left over in JobRunner's local state.
		await waitFor( () =>
			expect( screen.getByAltText( 'Result' ) ).toHaveAttribute(
				'src',
				JOBS_BY_ID[ 2 ].attachment_url
			)
		);
		expect( onFinished ).toHaveBeenLastCalledWith( JOBS_BY_ID[ 2 ] );
	} );
} );