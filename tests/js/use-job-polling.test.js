import { act, render, screen } from '@testing-library/react';

import { useJobPolling } from '../../client/shared/hooks/use-job-polling';
import { getJob } from '../../client/shared/api/client';

jest.mock( '../../client/shared/api/client', () => ( {
	getJob: jest.fn(),
} ) );

function Probe( { jobId } ) {
	const { job, isPolling, isFinished } = useJobPolling( jobId );

	return (
		<output>
			{ JSON.stringify( {
				status: job ? job.status : null,
				isPolling,
				isFinished,
			} ) }
		</output>
	);
}

const readProbe = () => JSON.parse( screen.getByRole( 'status' ).textContent );

describe( 'useJobPolling', () => {
	beforeEach( () => {
		jest.useFakeTimers();
		getJob.mockReset();
	} );

	afterEach( () => {
		jest.useRealTimers();
	} );

	test( 'polls with backoff until the job reaches a terminal state', async () => {
		getJob
			.mockResolvedValueOnce( { id: 5, status: 'queued' } )
			.mockResolvedValueOnce( { id: 5, status: 'running' } )
			.mockResolvedValueOnce( {
				id: 5,
				status: 'succeeded',
				attachment_id: 9,
			} );

		render( <Probe jobId={ 5 } /> );

		// First poll fires immediately.
		await act( async () => {} );
		expect( getJob ).toHaveBeenCalledTimes( 1 );
		expect( readProbe() ).toMatchObject( {
			status: 'queued',
			isPolling: true,
		} );

		// Second poll after the initial 2s × 1.5 backoff (3s).
		await act( async () => {
			jest.advanceTimersByTime( 3000 );
		} );
		expect( getJob ).toHaveBeenCalledTimes( 2 );
		expect( readProbe() ).toMatchObject( { status: 'running' } );

		// Third poll returns the terminal state; polling stops.
		await act( async () => {
			jest.advanceTimersByTime( 4500 );
		} );
		expect( getJob ).toHaveBeenCalledTimes( 3 );
		expect( readProbe() ).toMatchObject( {
			status: 'succeeded',
			isPolling: false,
			isFinished: true,
		} );

		// No further polls are scheduled.
		await act( async () => {
			jest.advanceTimersByTime( 60000 );
		} );
		expect( getJob ).toHaveBeenCalledTimes( 3 );
	} );

	test( 'does nothing without a job id', async () => {
		render( <Probe jobId={ 0 } /> );

		await act( async () => {
			jest.advanceTimersByTime( 30000 );
		} );

		expect( getJob ).not.toHaveBeenCalled();
		expect( readProbe() ).toMatchObject( {
			status: null,
			isPolling: false,
		} );
	} );

	test( 'stops on terminal API errors but retries transient ones', async () => {
		getJob
			.mockRejectedValueOnce( { status: 502, message: 'gateway' } )
			.mockResolvedValueOnce( { id: 5, status: 'succeeded' } );

		render( <Probe jobId={ 5 } /> );

		await act( async () => {} );
		expect( getJob ).toHaveBeenCalledTimes( 1 );

		// A 5xx retries…
		await act( async () => {
			jest.advanceTimersByTime( 3000 );
		} );
		expect( getJob ).toHaveBeenCalledTimes( 2 );
		expect( readProbe() ).toMatchObject( {
			status: 'succeeded',
			isFinished: true,
		} );
	} );
} );
