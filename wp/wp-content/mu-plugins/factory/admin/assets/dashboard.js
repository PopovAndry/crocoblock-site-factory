( function () {
	'use strict';

	const config = window.FactoryDashboardConfig || {};
	const root = document.getElementById( 'factory-dashboard-root' );

	if ( ! root ) {
		return;
	}

	const state = {
		doctor: null,
		latest: null,
		runs: [],
		adapters: [],
		selectedRun: null,
		selectedFile: '',
		errors: [],
		loadingDetails: false,
	};

	function endpoint( path ) {
		const base = ( config.restBase || '' ).replace( /\/$/, '' );
		return base + path;
	}

	function request( path ) {
		return window.fetch(
			endpoint( path ),
			{
				credentials: 'same-origin',
				headers: {
					'X-WP-Nonce': config.restNonce || '',
				},
			}
		).then( function ( response ) {
			if ( ! response.ok ) {
				throw new Error( 'Request failed: ' + response.status );
			}

			return response.json();
		} );
	}

	function escapeHtml( value ) {
		return String( value ?? '' )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' )
			.replace( /'/g, '&#039;' );
	}

	function statusValue( value ) {
		const status = String( value || 'unknown' ).toLowerCase();
		return [ 'ok', 'warning', 'error' ].includes( status ) ? status : 'unknown';
	}

	function badge( value ) {
		const status = statusValue( value );
		return '<span class="factory-badge factory-badge-' + status + '">' + escapeHtml( status ) + '</span>';
	}

	function count( value ) {
		return Array.isArray( value ) ? value.length : 0;
	}

	function homeUrl( path ) {
		const base = String( config.homeUrl || '/' ).replace( /\/$/, '' );
		const cleanPath = String( path || '/' ).replace( /^\//, '' );

		return base + '/' + cleanPath;
	}

	function summaryValue( summary, key ) {
		return summary && typeof summary === 'object' ? Number( summary[ key ] || 0 ) : 0;
	}

	function planSummaryText( summary ) {
		return [
			'+' + summaryValue( summary, 'create' ),
			'~' + summaryValue( summary, 'update' ),
			'=' + summaryValue( summary, 'skip' ),
			'!' + summaryValue( summary, 'warning' ),
			'x' + summaryValue( summary, 'error' ),
		].join( ' ' );
	}

	function resultsSummaryText( summary ) {
		return [
			'ok ' + summaryValue( summary, 'ok' ),
			'warning ' + summaryValue( summary, 'warning' ),
			'error ' + summaryValue( summary, 'error' ),
		].join( ' / ' );
	}

	function runFromLatest() {
		return state.latest && state.latest.run ? state.latest.run : {};
	}

	function executionCount( run ) {
		const execution = run && run.execution ? run.execution : {};
		return Number( execution.count ?? count( execution.items ) );
	}

	function validationCount( run ) {
		const validation = run && run.validation ? run.validation : {};
		return Number( validation.count ?? count( validation.checks ) );
	}

	function latestValidationOk() {
		const run = runFromLatest();

		if ( ! run || ! Object.keys( run ).length ) {
			return false;
		}

		if ( statusValue( run.status ) === 'ok' ) {
			return true;
		}

		const validationChecks = run.validation && Array.isArray( run.validation.checks )
			? run.validation.checks
			: [];
		const resultsSummary = run.results && run.results.summary ? run.results.summary : {};
		const hasValidationErrors = validationChecks.some( function ( check ) {
			return statusValue( check.status ) === 'error';
		} );
		const resultErrors = summaryValue( resultsSummary, 'error' );

		return ! hasValidationErrors && resultErrors === 0 && ( validationChecks.length > 0 || Object.keys( resultsSummary ).length > 0 );
	}

	function renderMetric( label, value ) {
		return '<div class="factory-metric"><span>' + escapeHtml( label ) + '</span><strong>' + escapeHtml( value ) + '</strong></div>';
	}

	function renderDemoStatus( label, isReady ) {
		return '<div class="factory-demo-status"><span>' + escapeHtml( label ) + '</span>' + badge( isReady ? 'ok' : 'warning' ) + '</div>';
	}

	function renderHeader() {
		const doctorStatus = state.doctor ? state.doctor.status : 'unknown';
		const latestStatus = runFromLatest().status || 'unknown';

		return [
			'<header class="factory-dashboard-header">',
				'<div>',
					'<h1>Crocoblock Site Factory</h1>',
					'<span class="factory-beta">Read-only beta</span>',
				'</div>',
				'<div class="factory-header-badges">',
					'<span>Doctor ' + badge( doctorStatus ) + '</span>',
					'<span>Latest run ' + badge( latestStatus ) + '</span>',
				'</div>',
			'</header>',
		].join( '' );
	}

	function renderErrors() {
		if ( ! state.errors.length ) {
			return '';
		}

		return '<section class="factory-card factory-card-error"><h2>Load Errors</h2><ul>' +
			state.errors.map( function ( error ) {
				return '<li>' + escapeHtml( error ) + '</li>';
			} ).join( '' ) +
			'</ul></section>';
	}

	function renderSystemStatus() {
		const doctor = state.doctor || {};
		const issues = Array.isArray( doctor.issues ) ? doctor.issues : [];

		return [
			'<section class="factory-card">',
				'<div class="factory-card-heading"><h2>System Status</h2>' + badge( doctor.status ) + '</div>',
				'<dl class="factory-definition-list">',
					'<dt>Latest run</dt><dd>' + escapeHtml( doctor.latest_run || '-' ) + '</dd>',
					'<dt>Prompt</dt><dd>' + escapeHtml( doctor.prompt || '-' ) + '</dd>',
				'</dl>',
				issues.length
					? '<ul class="factory-list">' + issues.map( function ( issue ) {
						return '<li>' + badge( issue.status ) + '<span>' + escapeHtml( issue.message || '' ) + '</span></li>';
					} ).join( '' ) + '</ul>'
					: '<p class="factory-empty">No drift issues reported.</p>',
			'</section>',
		].join( '' );
	}

	function renderRealEstateDemo() {
		const run = runFromLatest();
		const plan = run.plan && run.plan.summary ? run.plan.summary : {};
		const results = run.results && run.results.summary ? run.results.summary : {};
		const siteGenerated = Boolean( run.file ) && executionCount( run ) > 0;
		const doctorOk = statusValue( state.doctor && state.doctor.status ) === 'ok';
		const validationOk = latestValidationOk();

		return [
			'<section class="factory-card factory-card-wide factory-demo-panel">',
				'<div class="factory-demo-header">',
					'<div>',
						'<span class="factory-demo-kicker">Preset flow</span>',
						'<h2>Real Estate Beta Demo</h2>',
						'<p>Read-only proof that the generated Kyiv real estate site is applied, converged, and visible on the frontend.</p>',
					'</div>',
					'<div class="factory-demo-statuses">',
						renderDemoStatus( 'Site generated', siteGenerated ),
						renderDemoStatus( 'Validation OK', validationOk ),
						renderDemoStatus( 'Doctor OK', doctorOk ),
					'</div>',
				'</div>',
				'<div class="factory-demo-grid">',
					'<div>',
						'<h3>Preset Summary</h3>',
						'<ul class="factory-demo-summary">',
							'<li>30 Kyiv properties</li>',
							'<li>Image pools by property type</li>',
							'<li>Polished archive catalog</li>',
							'<li>Polished single property pages</li>',
							'<li>Manifest-backed validation proof</li>',
						'</ul>',
					'</div>',
					'<div>',
						'<h3>Current Convergence Proof</h3>',
						'<div class="factory-metric-grid factory-demo-metrics">',
							renderMetric( 'Create', summaryValue( plan, 'create' ) ),
							renderMetric( 'Update', summaryValue( plan, 'update' ) ),
							renderMetric( 'Skip', summaryValue( plan, 'skip' ) ),
							renderMetric( 'Warning', summaryValue( plan, 'warning' ) ),
							renderMetric( 'Error', summaryValue( plan, 'error' ) ),
						'</div>',
					'</div>',
				'</div>',
				'<div class="factory-demo-grid factory-demo-proof-grid">',
					'<div>',
						'<h3>Apply Proof</h3>',
						'<dl class="factory-definition-list factory-definition-list-wide">',
							'<dt>Run file</dt><dd>' + escapeHtml( run.file || '-' ) + '</dd>',
							'<dt>Prompt</dt><dd>' + escapeHtml( run.prompt || '-' ) + '</dd>',
							'<dt>Execution</dt><dd>' + escapeHtml( executionCount( run ) ) + ' items</dd>',
							'<dt>Validation</dt><dd>' + escapeHtml( validationCount( run ) ) + ' checks</dd>',
							'<dt>Results</dt><dd>' + escapeHtml( resultsSummaryText( results ) ) + '</dd>',
						'</dl>',
					'</div>',
					'<div>',
						'<h3>Open Frontend</h3>',
						'<div class="factory-demo-links">',
							'<a href="' + escapeHtml( homeUrl( '/properties/' ) ) + '" target="_blank" rel="noopener noreferrer">Open Properties Archive</a>',
							'<a href="' + escapeHtml( homeUrl( '/property/turquoise-view-apartment-in-pechersk/' ) ) + '" target="_blank" rel="noopener noreferrer">Open sample Apartment</a>',
							'<a href="' + escapeHtml( homeUrl( '/property/solomianskyi-business-office/' ) ) + '" target="_blank" rel="noopener noreferrer">Open sample Commercial</a>',
						'</div>',
					'</div>',
				'</div>',
			'</section>',
		].join( '' );
	}

	function renderLatestRun() {
		const run = runFromLatest();
		const plan = run.plan && run.plan.summary ? run.plan.summary : {};
		const execution = run.execution || {};
		const validation = run.validation || {};
		const results = run.results && run.results.summary ? run.results.summary : {};

		return [
			'<section class="factory-card">',
				'<div class="factory-card-heading"><h2>Latest Run Summary</h2>' + badge( run.status ) + '</div>',
				'<dl class="factory-definition-list">',
					'<dt>File</dt><dd>' + escapeHtml( run.file || '-' ) + '</dd>',
					'<dt>Timestamp</dt><dd>' + escapeHtml( run.timestamp || '-' ) + '</dd>',
					'<dt>Prompt</dt><dd>' + escapeHtml( run.prompt || '-' ) + '</dd>',
				'</dl>',
				'<div class="factory-metric-grid">',
					renderMetric( 'Plan', planSummaryText( plan ) ),
					renderMetric( 'Execution', execution.count ?? count( execution.items ) ),
					renderMetric( 'Validation', validation.count ?? count( validation.checks ) ),
					renderMetric( 'Results', resultsSummaryText( results ) ),
				'</div>',
			'</section>',
		].join( '' );
	}

	function renderRunsTable() {
		if ( ! state.runs.length ) {
			return '<section class="factory-card factory-card-wide"><h2>Run History</h2><p class="factory-empty">No runs found.</p></section>';
		}

		return [
			'<section class="factory-card factory-card-wide">',
				'<h2>Run History</h2>',
				'<div class="factory-table-wrap">',
					'<table class="factory-table">',
						'<thead><tr>',
							'<th>Status</th><th>Timestamp</th><th>Prompt</th><th>Plan</th><th>Execution</th><th>Validation</th><th>File</th>',
						'</tr></thead>',
						'<tbody>',
							state.runs.map( function ( run ) {
								const selected = run.file && run.file === state.selectedFile ? ' factory-row-selected' : '';
								return '<tr class="factory-run-row' + selected + '" data-file="' + escapeHtml( run.file || '' ) + '">' +
									'<td>' + badge( run.status ) + '</td>' +
									'<td>' + escapeHtml( run.timestamp || '-' ) + '</td>' +
									'<td>' + escapeHtml( run.prompt || '-' ) + '</td>' +
									'<td>' + escapeHtml( planSummaryText( run.plan_summary || {} ) ) + '</td>' +
									'<td>' + escapeHtml( run.execution_count ?? 0 ) + '</td>' +
									'<td>' + escapeHtml( run.validation_count ?? 0 ) + '</td>' +
									'<td><code>' + escapeHtml( run.file || '-' ) + '</code></td>' +
								'</tr>';
							} ).join( '' ),
						'</tbody>',
					'</table>',
				'</div>',
			'</section>',
		].join( '' );
	}

	function renderExecutionItems( items ) {
		if ( ! Array.isArray( items ) || ! items.length ) {
			return '<p class="factory-empty">No execution items.</p>';
		}

		return '<ul class="factory-event-list">' + items.map( function ( item ) {
			const parts = [ item.action, item.type, item.entity ].filter( Boolean ).join( ' ' );
			return '<li>' + badge( item.status ) + '<div><strong>' + escapeHtml( parts || '-' ) + '</strong><span>' + escapeHtml( item.message || '' ) + '</span></div></li>';
		} ).join( '' ) + '</ul>';
	}

	function renderValidationItems( checks ) {
		if ( ! Array.isArray( checks ) || ! checks.length ) {
			return '<p class="factory-empty">No validation checks.</p>';
		}

		return '<ul class="factory-event-list">' + checks.map( function ( check ) {
			return '<li>' + badge( check.status ) + '<span>' + escapeHtml( check.message || '' ) + '</span></li>';
		} ).join( '' ) + '</ul>';
	}

	function renderSelectedRun() {
		if ( state.loadingDetails ) {
			return '<section class="factory-card factory-card-wide"><h2>Selected Run Details</h2><p class="factory-empty">Loading run details...</p></section>';
		}

		const run = state.selectedRun && state.selectedRun.run ? state.selectedRun.run : runFromLatest();

		if ( ! run || ! Object.keys( run ).length ) {
			return '<section class="factory-card factory-card-wide"><h2>Selected Run Details</h2><p class="factory-empty">Select a run to inspect details.</p></section>';
		}

		const plan = run.plan && run.plan.summary ? run.plan.summary : {};
		const executionItems = run.execution && Array.isArray( run.execution.items ) ? run.execution.items : [];
		const validationChecks = run.validation && Array.isArray( run.validation.checks ) ? run.validation.checks : [];
		const results = run.results && run.results.summary ? run.results.summary : {};
		const blueprint = run.blueprint ? JSON.stringify( run.blueprint, null, 2 ) : '';

		return [
			'<section class="factory-card factory-card-wide">',
				'<div class="factory-card-heading"><h2>Selected Run Details</h2>' + badge( run.status ) + '</div>',
				'<dl class="factory-definition-list factory-definition-list-wide">',
					'<dt>File</dt><dd>' + escapeHtml( run.file || '-' ) + '</dd>',
					'<dt>Prompt</dt><dd>' + escapeHtml( run.prompt || '-' ) + '</dd>',
					'<dt>Plan</dt><dd>' + escapeHtml( planSummaryText( plan ) ) + '</dd>',
					'<dt>Results</dt><dd>' + escapeHtml( resultsSummaryText( results ) ) + '</dd>',
				'</dl>',
				'<div class="factory-detail-grid">',
					'<div><h3>Execution Trace</h3>' + renderExecutionItems( executionItems ) + '</div>',
					'<div><h3>Validation Proof</h3>' + renderValidationItems( validationChecks ) + '</div>',
				'</div>',
				blueprint ? '<details class="factory-blueprint"><summary>Blueprint preview</summary><pre></pre></details>' : '',
			'</section>',
		].join( '' );
	}

	function renderAdapters() {
		if ( ! state.adapters.length ) {
			return '<section class="factory-card factory-card-wide"><h2>Adapter Overview</h2><p class="factory-empty">No adapter data available.</p></section>';
		}

		return [
			'<section class="factory-card factory-card-wide">',
				'<h2>Adapter Overview</h2>',
				'<div class="factory-adapter-grid">',
					state.adapters.map( function ( adapter ) {
						const key = adapter.key || adapter.adapter || adapter.class || 'adapter';
						return '<article class="factory-adapter">' +
							'<div class="factory-card-heading"><h3>' + escapeHtml( key ) + '</h3>' + badge( adapter.contract_ready ? 'ok' : 'warning' ) + '</div>' +
							'<code>' + escapeHtml( adapter.class || '-' ) + '</code>' +
							'<div class="factory-adapter-flags">' +
								'<span>register ' + badge( adapter.has_register ? 'ok' : 'unknown' ) + '</span>' +
								'<span>apply ' + badge( adapter.has_apply ? 'ok' : 'unknown' ) + '</span>' +
								'<span>validate ' + badge( adapter.has_validate ? 'ok' : 'unknown' ) + '</span>' +
								'<span>plan ' + badge( adapter.has_plan ? 'ok' : 'unknown' ) + '</span>' +
							'</div>' +
						'</article>';
					} ).join( '' ),
				'</div>',
			'</section>',
		].join( '' );
	}

	function render() {
		root.innerHTML = [
			renderHeader(),
			renderErrors(),
			renderRealEstateDemo(),
			'<div class="factory-grid">',
				renderSystemStatus(),
				renderLatestRun(),
				renderRunsTable(),
				renderSelectedRun(),
				renderAdapters(),
			'</div>',
		].join( '' );

		const blueprintPre = root.querySelector( '.factory-blueprint pre' );
		const selected = state.selectedRun && state.selectedRun.run ? state.selectedRun.run : runFromLatest();

		if ( blueprintPre && selected && selected.blueprint ) {
			blueprintPre.textContent = JSON.stringify( selected.blueprint, null, 2 );
		}

		root.querySelectorAll( '.factory-run-row' ).forEach( function ( row ) {
			row.addEventListener( 'click', function () {
				const file = row.getAttribute( 'data-file' );

				if ( file ) {
					loadRunDetails( file );
				}
			} );
		} );
	}

	function loadRunDetails( file ) {
		const path = ( config.endpoints && config.endpoints.run ? config.endpoints.run : '/run/{file}' )
			.replace( '{file}', encodeURIComponent( file ) );

		state.selectedFile = file;
		state.loadingDetails = true;
		render();

		request( path )
			.then( function ( data ) {
				state.selectedRun = data;
			} )
			.catch( function ( error ) {
				state.errors.push( 'Run details: ' + error.message );
			} )
			.finally( function () {
				state.loadingDetails = false;
				render();
			} );
	}

	function loadDashboard() {
		root.innerHTML = '<div class="factory-dashboard-loading">Loading Factory dashboard...</div>';

		Promise.allSettled( [
			request( config.endpoints?.doctor || '/doctor' ),
			request( config.endpoints?.runs || '/runs?limit=20' ),
			request( config.endpoints?.latest || '/run/latest' ),
			request( config.endpoints?.adapters || '/adapters' ),
		] ).then( function ( results ) {
			const labels = [ 'Doctor', 'Runs', 'Latest run', 'Adapters' ];

			results.forEach( function ( result, index ) {
				if ( result.status === 'rejected' ) {
					state.errors.push( labels[ index ] + ': ' + result.reason.message );
				}
			} );

			if ( results[0].status === 'fulfilled' ) {
				state.doctor = results[0].value;
			}

			if ( results[1].status === 'fulfilled' ) {
				state.runs = Array.isArray( results[1].value.runs ) ? results[1].value.runs : [];
			}

			if ( results[2].status === 'fulfilled' ) {
				state.latest = results[2].value;
				state.selectedRun = results[2].value;
				state.selectedFile = results[2].value.run?.file || '';
			}

			if ( results[3].status === 'fulfilled' ) {
				state.adapters = Array.isArray( results[3].value.adapters ) ? results[3].value.adapters : [];
			}

			render();
		} );
	}

	document.addEventListener( 'DOMContentLoaded', loadDashboard );
}() );
