/**
 * The Hack Repair Guy's Plugin Detective Fix page.
 *
 * Plain JavaScript, no build step, no outside libraries. All text comes from
 * the server (translatable). Site data is only ever inserted as text, never
 * as HTML.
 *
 * @author Jim Walker, The Hack Repair Guy
 */
( function () {
	'use strict';

	const bootEl = document.getElementById( 'hrpd-boot' );
	const root = document.getElementById( 'hrpd-app' );
	const nav = document.getElementById( 'hrpd-nav' );
	if ( ! bootEl || ! root ) {
		return;
	}

	const boot = JSON.parse( bootEl.textContent || '{}' );
	const S = boot.strings || {};

	const state = {
		signedIn: !! boot.signedIn,
		csrf: boot.csrf || '',
		overview: null,
		challenge: '',
		busy: false,
		error: '',
		notice: '',
		view: '',
		whereUrl: boot.initialUrl || boot.home,
		confirming: false,
		confirmDelete: false,
		finished: null,
		restoreFlow: !! boot.restore,
		bg: null,
		report: null,
		desktop: readPref( 'hrpd-desktop', true ),
	};

	function readPref( key, fallback ) {
		try {
			const v = window.localStorage.getItem( key );
			return v === null ? fallback : v === '1';
		} catch ( e ) {
			return fallback;
		}
	}

	function savePref( key, value ) {
		try {
			window.localStorage.setItem( key, value ? '1' : '0' );
		} catch ( e ) {
			// Private window or blocked storage: the choice just isn't remembered.
		}
	}

	/* ---------- helpers ---------- */

	function fmt( text ) {
		const args = Array.prototype.slice.call( arguments, 1 );
		return String( text || '' ).replace( /%(\d+)\$s/g, function ( m, n ) {
			const v = args[ parseInt( n, 10 ) - 1 ];
			return v === undefined ? m : String( v );
		} );
	}

	function h( tag, attrs, children ) {
		const el = document.createElement( tag );
		attrs = attrs || {};
		Object.keys( attrs ).forEach( function ( key ) {
			const val = attrs[ key ];
			if ( val === null || val === undefined || val === false ) {
				return;
			}
			if ( key === 'class' ) {
				el.className = val;
			} else if ( key === 'text' ) {
				el.textContent = val;
			} else if ( key.indexOf( 'on' ) === 0 && typeof val === 'function' ) {
				el.addEventListener( key.slice( 2 ), val );
			} else if ( val === true ) {
				el.setAttribute( key, '' );
			} else {
				el.setAttribute( key, String( val ) );
			}
		} );
		( Array.isArray( children ) ? children : [ children ] ).forEach( function ( child ) {
			if ( child === null || child === undefined || child === false ) {
				return;
			}
			el.appendChild( typeof child === 'string' ? document.createTextNode( child ) : child );
		} );
		return el;
	}

	function button( label, onClick, kind, extra ) {
		return h(
			'button',
			Object.assign( { type: 'button', class: 'hrpd-btn hrpd-btn--' + ( kind || 'secondary' ), onclick: onClick, disabled: state.busy }, extra || {} ),
			label
		);
	}

	function card( title, body, tone ) {
		return h( 'section', { class: 'hrpd-card' + ( tone ? ' hrpd-card--' + tone : '' ) }, [
			title ? h( 'h2', { class: 'hrpd-card__title', text: title } ) : null,
		].concat( body ) );
	}

	function para( text, cls ) {
		return h( 'p', { class: cls || null, text: text } );
	}

	function pluginNames( list ) {
		return ( list || [] ).map( function ( p ) {
			return p.name;
		} );
	}

	function nameList( list, emptyText ) {
		const names = pluginNames( list );
		if ( ! names.length ) {
			return para( emptyText || S.noneOn, 'hrpd-muted' );
		}
		return h( 'ul', { class: 'hrpd-list' }, names.map( function ( n ) {
			return h( 'li', { text: n } );
		} ) );
	}

	/* ---------- server ---------- */

	function api( action, data ) {
		const body = Object.assign( { action: action }, data || {} );
		return fetch( boot.api, {
			method: 'POST',
			credentials: 'same-origin',
			cache: 'no-store',
			headers: {
				'Content-Type': 'application/json',
				'X-HRPD': '1',
				'X-HRPD-CSRF': state.csrf,
			},
			body: JSON.stringify( body ),
		} )
			.then( function ( res ) {
				return res.json().catch( function () {
					throw new Error( S.errorGeneric );
				} );
			} )
			.then( function ( json ) {
				if ( ! json || ! json.ok ) {
					const err = new Error( ( json && json.error && json.error.message ) || S.errorGeneric );
					err.code = json && json.error ? json.error.code : '';
					throw err;
				}
				if ( json.data && typeof json.data.csrf === 'string' && json.data.csrf ) {
					state.csrf = json.data.csrf;
				}
				return json.data;
			} );
	}

	function run( action, data, after ) {
		if ( state.busy ) {
			return;
		}
		state.busy = true;
		state.error = '';
		render();
		return api( action, data )
			.then( function ( result ) {
				state.busy = false;
				if ( after ) {
					after( result );
				}
				render();
			} )
			.catch( function ( err ) {
				state.busy = false;
				if ( err.code === 'hrpd_signed_out' ) {
					state.signedIn = false;
					state.overview = null;
				}
				state.error = err.message || S.errorGeneric;
				render();
			} );
	}

	function setOverview( data ) {
		state.overview = data;
		state.confirming = false;
		state.confirmDelete = false;
	}

	function loadOverview() {
		return run( 'overview', {}, setOverview );
	}

	/* ---------- views ---------- */

	function viewLogin() {
		if ( ! boot.loginEnabled ) {
			return card( S.loginTitle, [
				para( S.loginDisabled ),
				h( 'p', {}, h( 'a', { class: 'hrpd-btn hrpd-btn--primary', href: boot.adminUrl, text: S.goToLogin } ) ),
			] );
		}

		if ( state.challenge ) {
			const code = h( 'input', { id: 'hrpd-code', name: 'code', type: 'text', inputmode: 'numeric', autocomplete: 'one-time-code', required: true, class: 'hrpd-input' } );
			const form = h( 'form', {
				class: 'hrpd-form',
				onsubmit: function ( e ) {
					e.preventDefault();
					run( 'verify_code', { challenge: state.challenge, code: code.value }, afterLogin );
				},
			}, [
				h( 'label', { for: 'hrpd-code', text: S.code } ),
				code,
				h( 'div', { class: 'hrpd-actions' }, [
					h( 'button', { type: 'submit', class: 'hrpd-btn hrpd-btn--primary', disabled: state.busy, text: S.verify } ),
					button( S.startOverLogin, function () {
						state.challenge = '';
						state.error = '';
						render();
					}, 'link' ),
				] ),
			] );
			setTimeout( function () {
				code.focus();
			}, 0 );
			return card( S.codeTitle, [ para( S.codeIntro ), form ] );
		}

		const user = h( 'input', { id: 'hrpd-user', name: 'log', type: 'text', autocomplete: 'username', required: true, class: 'hrpd-input' } );
		const pass = h( 'input', { id: 'hrpd-pass', name: 'pwd', type: 'password', autocomplete: 'current-password', required: true, class: 'hrpd-input' } );
		const form = h( 'form', {
			class: 'hrpd-form',
			onsubmit: function ( e ) {
				e.preventDefault();
				run( 'login', { username: user.value, password: pass.value }, function ( data ) {
					pass.value = '';
					if ( data.challenge ) {
						state.challenge = data.challenge;
						return;
					}
					afterLogin( data );
				} );
			},
		}, [
			h( 'label', { for: 'hrpd-user', text: S.username } ),
			user,
			h( 'label', { for: 'hrpd-pass', text: S.password } ),
			pass,
			h( 'div', { class: 'hrpd-actions' }, h( 'button', { type: 'submit', class: 'hrpd-btn hrpd-btn--primary', disabled: state.busy, text: S.signIn } ) ),
		] );
		setTimeout( function () {
			if ( ! user.value ) {
				user.focus();
			}
		}, 0 );
		return card( S.loginTitle, [ para( S.loginIntro ), para( S.loginWhy, 'hrpd-muted' ), form ] );
	}

	function afterLogin( data ) {
		state.challenge = '';
		state.signedIn = !! data.signedIn;
		state.csrf = data.csrf || state.csrf;
		if ( state.signedIn ) {
			setTimeout( loadOverview, 0 );
		}
	}

	function maintenanceCard() {
		const o = state.overview;
		if ( o.maintenance ) {
			return card( S.maintTitle, [
				para( S.maintText ),
				h( 'div', { class: 'hrpd-actions' }, button( S.maintButton, function () {
					run( 'remove_maintenance', {}, function ( data ) {
						setOverview( data );
						state.notice = S.maintDone;
						state.restoreFlow = false;
					} );
				}, 'primary' ) ),
			], 'warning' );
		}
		if ( state.restoreFlow ) {
			return card( null, [ para( S.maintNone ) ], 'ok' );
		}
		return null;
	}

	function lastResultText( last ) {
		if ( ! last || ! last.result ) {
			return '';
		}
		const map = {
			culprit: fmt( S.resultCulprit, last.culprit_name || last.culprit || '' ),
			not_plugin: S.resultNotPlugin,
			combination: S.resultCombination,
			mixed_answers: S.resultMixed,
		};
		return fmt( S.lastResult, map[ last.result ] || S.resultAbandoned );
	}

	function viewHome() {
		const o = state.overview;
		const count = ( o.plugins || [] ).length;
		const parts = [ maintenanceCard() ];

		const body = [ para( S.homeIntro ), para( S.homeHow ), para( fmt( S.homePluginCount, count ), 'hrpd-muted' ) ];
		if ( ! count ) {
			body.push( para( S.noPlugins ) );
		} else {
			body.push( h( 'div', { class: 'hrpd-actions' }, button( S.startCase, function () {
				state.view = 'where';
				state.error = '';
				render();
			}, 'primary' ) ) );
		}
		const last = lastResultText( o.lastResult );
		if ( last ) {
			body.push( para( last, 'hrpd-muted' ) );
		}
		parts.push( card( S.homeTitle, body ) );
		parts.push( helpCard() );
		parts.push( sitePreview( boot.home ) );
		return parts;
	}

	function siteFrame( url, onLoad ) {
		const frame = h( 'iframe', { class: 'hrpd-frame', src: url, title: S.checkSite } );
		frame.addEventListener( 'load', function () {
			if ( onLoad ) {
				try {
					onLoad( frame.contentWindow.location.href );
				} catch ( e ) {
					// Cross-origin page; nothing to read.
				}
			}
		} );
		return frame;
	}

	// Desktop layout: the page is drawn at a normal desktop width and shrunk to
	// fit the panel, so a problem that only shows on wide screens still shows.
	const DESKTOP_WIDTH = 1280;
	let frameObserver = null;

	function fitFrame( box, frame ) {
		const w = box.clientWidth;
		const hgt = box.clientHeight;
		if ( ! w || ! hgt ) {
			return;
		}
		if ( state.desktop && w < DESKTOP_WIDTH ) {
			const scale = w / DESKTOP_WIDTH;
			frame.style.width = DESKTOP_WIDTH + 'px';
			frame.style.height = Math.ceil( hgt / scale ) + 'px';
			frame.style.transform = 'scale(' + scale + ')';
		} else {
			frame.style.width = '100%';
			frame.style.height = '100%';
			frame.style.transform = '';
		}
	}

	function frameTools( url, frame, box ) {
		const toggle = h( 'button', {
			type: 'button',
			class: 'hrpd-btn hrpd-btn--link',
			'aria-pressed': state.desktop ? 'true' : 'false',
			text: state.desktop ? S.viewNarrow : S.viewDesktop,
			onclick: function () {
				state.desktop = ! state.desktop;
				savePref( 'hrpd-desktop', state.desktop );
				toggle.textContent = state.desktop ? S.viewNarrow : S.viewDesktop;
				toggle.setAttribute( 'aria-pressed', state.desktop ? 'true' : 'false' );
				fitFrame( box, frame );
			},
		} );
		return h( 'div', { class: 'hrpd-frame-tools' }, [
			h( 'strong', { class: 'hrpd-frame-tools__title', text: S.checkSite } ),
			h( 'a', { href: url, target: '_blank', rel: 'noopener', class: 'hrpd-btn hrpd-btn--link', text: S.openNewTab } ),
			button( S.reload, function () {
				try {
					frame.contentWindow.location.reload();
				} catch ( e ) {
					frame.src = url;
				}
			}, 'link' ),
			toggle,
		] );
	}

	function frameWrap( url, frame ) {
		const box = h( 'div', { class: 'hrpd-frame-box' }, frame );
		if ( frameObserver ) {
			frameObserver.disconnect();
		}
		if ( window.ResizeObserver ) {
			frameObserver = new ResizeObserver( function () {
				fitFrame( box, frame );
			} );
			frameObserver.observe( box );
		} else {
			window.addEventListener( 'resize', function () {
				fitFrame( box, frame );
			} );
		}
		return h( 'div', { class: 'hrpd-frame-wrap' }, [
			frameTools( url, frame, box ),
			box,
			h( 'p', { class: 'hrpd-frame-note hrpd-muted hrpd-small', text: S.frameNote } ),
		] );
	}

	function sitePreview( url ) {
		return frameWrap( url, siteFrame( url ) );
	}

	function viewWhere() {
		const address = h( 'input', { id: 'hrpd-address', type: 'url', class: 'hrpd-input', value: state.whereUrl } );
		const frame = siteFrame( state.whereUrl, function ( href ) {
			state.whereUrl = href;
			address.value = href;
		} );
		const go = h( 'form', {
			class: 'hrpd-address',
			onsubmit: function ( e ) {
				e.preventDefault();
				state.whereUrl = address.value;
				frame.src = address.value;
			},
		}, [
			h( 'label', { for: 'hrpd-address', class: 'screen-reader-text', text: S.pageAddress } ),
			address,
			h( 'button', { type: 'submit', class: 'hrpd-btn hrpd-btn--secondary', text: S.go } ),
		] );

		return [
			card( S.whereTitle, [
				para( S.whereText ),
				go,
				h( 'div', { class: 'hrpd-actions' }, [
					button( S.seeProblem, function () {
						run( 'open_case', { url: state.whereUrl }, setOverview );
					}, 'primary' ),
					button( S.cancel, function () {
						state.view = '';
						render();
					}, 'link' ),
				] ),
			] ),
			frameWrap( state.whereUrl, frame ),
		];
	}

	function viewRequired( c ) {
		const boxes = ( c.candidates || [] ).map( function ( p, i ) {
			const id = 'hrpd-req-' + i;
			return h( 'li', {}, h( 'label', { for: id, class: 'hrpd-check' }, [
				h( 'input', { type: 'checkbox', id: id, value: p.file } ),
				h( 'span', { text: p.name } ),
				p.version ? h( 'span', { class: 'hrpd-muted hrpd-small', text: ' ' + p.version } ) : null,
			] ) );
		} );
		const list = h( 'ul', { class: 'hrpd-checklist' }, boxes );

		return [
			card( S.requiredTitle, [
				para( S.requiredText ),
				para( S.requiredSecurity, 'hrpd-callout' ),
				list,
				h( 'div', { class: 'hrpd-actions' }, [
					button( S.startTesting, function () {
						const required = Array.prototype.map.call( list.querySelectorAll( 'input:checked' ), function ( el ) {
							return el.value;
						} );
						run( 'start_case', { required: required }, setOverview );
					}, 'primary' ),
					// Nothing has been switched off yet, so cancelling needs no confirmation.
					button( S.cancelSetup, function () {
						run( 'abandon', {}, function ( data ) {
							setOverview( data );
							state.finished = 'cancelled';
						} );
					}, 'link' ),
				] ),
			] ),
			sitePreview( c.url ),
		];
	}

	function stopButton() {
		return button( S.stopTesting, function () {
			state.confirming = true;
			render();
		}, 'link' );
	}

	function confirmBox() {
		if ( ! state.confirming ) {
			return null;
		}
		return h( 'div', { class: 'hrpd-confirm', role: 'alertdialog', 'aria-label': S.confirmAbandon }, [
			para( S.confirmAbandon ),
			h( 'div', { class: 'hrpd-actions' }, [
				button( S.confirmYes, function () {
					run( 'abandon', {}, function ( data ) {
						setOverview( data );
						state.finished = 'restored';
					} );
				}, 'danger' ),
				button( S.confirmNo, function () {
					state.confirming = false;
					render();
				}, 'secondary' ),
			] ),
		] );
	}

	function viewTesting( c ) {
		const step = c.step || {};
		let text = S.testBaseline;
		if ( step.phase === 'split' ) {
			text = fmt( S.testSplit, ( step.testing || [] ).length, step.remaining );
		} else if ( step.phase === 'confirm' ) {
			text = fmt( S.testConfirm, pluginNames( step.testing ).join( ', ' ) );
		}
		const left = step.roundsLeft <= 1 ? S.roundsLeftOne : fmt( S.roundsLeft, step.roundsLeft );
		const frame = siteFrame( c.checkUrl );

		return [
			card( fmt( S.testTitle, step.round ), [
				para( text ),
				h( 'div', { class: 'hrpd-actions hrpd-actions--answer' }, [
					button( S.answerFixed, function () {
						run( 'answer', { outcome: 'fixed' }, setOverview );
					}, 'good' ),
					button( S.answerBroken, function () {
						run( 'answer', { outcome: 'broken' }, setOverview );
					}, 'bad' ),
				] ),
				para( left, 'hrpd-muted' ),
				h( 'div', { class: 'hrpd-columns' }, [
					h( 'div', {}, [ h( 'h3', { text: S.onNow } ), nameList( step.testing ) ] ),
					h( 'div', {}, [ h( 'h3', { text: S.stayingOn } ), nameList( c.required ) ] ),
				] ),
				para( S.testTip, 'hrpd-muted hrpd-small' ),
				h( 'div', { class: 'hrpd-actions' }, [
					c.answers > 0 ? button( S.undo, function () {
						run( 'undo', {}, setOverview );
					}, 'link' ) : null,
					stopButton(),
				] ),
				confirmBox(),
			] ),
			frameWrap( c.checkUrl, frame ),
		];
	}

	/* ---------- background check and case report ---------- */

	function toneClass( tone ) {
		return { ok: 'ok', warn: 'warning', bad: 'bad', info: '' }[ tone ] || '';
	}

	function fillBackground( box, name ) {
		const bg = state.bg || {};
		box.textContent = '';
		box.className = 'hrpd-card hrpd-bg';
		box.appendChild( h( 'h2', { class: 'hrpd-card__title', text: fmt( S.bgTitle, name ) } ) );

		if ( bg.loading ) {
			box.appendChild( h( 'p', { class: 'hrpd-status hrpd-muted', role: 'status' }, [ h( 'span', { class: 'hrpd-spinner', 'aria-hidden': 'true' } ), S.bgLoading ] ) );
			return;
		}

		const data = bg.data;
		if ( bg.failed || ! data || data.status !== 'ok' ) {
			box.appendChild( para( S.bgFailed, 'hrpd-muted' ) );
			return;
		}

		const tone = toneClass( data.tone );
		if ( tone ) {
			box.classList.add( 'hrpd-card--' + tone );
		}

		box.appendChild( h( 'ul', { class: 'hrpd-bg__lines' }, ( data.lines || [] ).map( function ( line ) {
			return h( 'li', { class: 'hrpd-bg__line hrpd-bg__line--' + line.tone, text: line.text } );
		} ) ) );

		if ( ( data.items || [] ).length ) {
			box.appendChild( h( 'h3', { text: S.bgKnownHoles } ) );
			box.appendChild( h( 'ul', { class: 'hrpd-list hrpd-bg__items' }, data.items.map( function ( item ) {
				const label = item.severity ? item.title + ' (' + item.severity + ')' : item.title;
				return h( 'li', {}, item.link ? h( 'a', { href: item.link, target: '_blank', rel: 'noopener noreferrer', text: label } ) : label );
			} ) ) );
		}

		if ( data.tone === 'bad' ) {
			box.appendChild( h( 'p', {}, [
				document.createTextNode( S.bgBadNews + ' ' ),
				h( 'a', { href: boot.helpUrl, target: '_blank', rel: 'noopener', text: S.helpLink } ),
			] ) );
		}

		const foot = [ document.createTextNode( data.credit || '' ) ];
		if ( data.link ) {
			foot.push( document.createTextNode( ' ' ), h( 'a', { href: data.link, target: '_blank', rel: 'noopener noreferrer', text: S.bgPluginPage } ) );
		}
		box.appendChild( h( 'p', { class: 'hrpd-muted hrpd-small' }, foot ) );
	}

	function backgroundCard( c ) {
		const r = c.result || {};
		if ( ! r.checkable || ! r.culprit ) {
			return null;
		}
		const box = h( 'section', { id: 'hrpd-bg' } );
		if ( ! state.bg || state.bg.caseId !== c.id ) {
			state.bg = { caseId: c.id, loading: true };
			api( 'background', {} )
				.then( function ( data ) {
					state.bg = { caseId: c.id, data: data.background };
					state.report = { caseId: c.id, text: data.report };
				} )
				.catch( function () {
					state.bg = { caseId: c.id, failed: true };
				} )
				.then( function () {
					const live = document.getElementById( 'hrpd-bg' );
					if ( live ) {
						fillBackground( live, r.culprit.name );
					}
					const pre = document.getElementById( 'hrpd-report-text' );
					if ( pre && state.report && state.report.caseId === c.id ) {
						pre.textContent = state.report.text;
					}
				} );
		}
		fillBackground( box, r.culprit.name );
		return box;
	}

	function copyText( text, btn ) {
		const done = function () {
			const before = btn.textContent;
			btn.textContent = S.reportCopied;
			setTimeout( function () {
				btn.textContent = before;
			}, 2000 );
		};
		if ( navigator.clipboard && window.isSecureContext ) {
			navigator.clipboard.writeText( text ).then( done, function () {
				copyFallback( text );
				done();
			} );
			return;
		}
		copyFallback( text );
		done();
	}

	function copyFallback( text ) {
		const area = h( 'textarea', { class: 'hrpd-offscreen', readonly: true } );
		area.value = text;
		document.body.appendChild( area );
		area.select();
		try {
			document.execCommand( 'copy' );
		} catch ( e ) {
			// Nothing else to try; the report is still shown on screen.
		}
		document.body.removeChild( area );
	}

	function downloadText( text ) {
		const blob = new Blob( [ text ], { type: 'text/plain;charset=utf-8' } );
		const url = URL.createObjectURL( blob );
		const a = h( 'a', { href: url, download: 'plugin-detective-report-' + new Date().toISOString().slice( 0, 10 ) + '.txt' } );
		document.body.appendChild( a );
		a.click();
		document.body.removeChild( a );
		setTimeout( function () {
			URL.revokeObjectURL( url );
		}, 1000 );
	}

	function reportCard( getText ) {
		if ( ! getText() ) {
			return null;
		}
		const pre = h( 'pre', { id: 'hrpd-report-text', class: 'hrpd-report', text: getText() } );
		const copy = button( S.reportCopy, function () {
			copyText( getText(), copy );
		}, 'secondary' );
		return card( S.reportTitle, [
			para( S.reportIntro ),
			h( 'div', { class: 'hrpd-actions' }, [
				copy,
				button( S.reportDownload, function () {
					downloadText( getText() );
				}, 'secondary' ),
			] ),
			h( 'details', { class: 'hrpd-report-wrap' }, [ h( 'summary', { text: S.reportShow } ), pre ] ),
			para( S.reportPrivacy, 'hrpd-muted hrpd-small' ),
		] );
	}

	function caseReport( c ) {
		return function () {
			if ( state.report && state.report.caseId === c.id ) {
				return state.report.text;
			}
			return ( c.result && c.result.report ) || '';
		};
	}

	function deleteBox( name ) {
		if ( ! state.confirmDelete ) {
			return null;
		}
		const ask = fmt( S.deleteAsk, name );
		return h( 'div', { class: 'hrpd-confirm', role: 'alertdialog', 'aria-label': ask }, [
			para( ask ),
			h( 'div', { class: 'hrpd-actions' }, [
				button( S.deleteYes, function () {
					state.confirmDelete = false;
					run( 'finish', { mode: 'delete' }, function ( data ) {
						setOverview( data );
						state.finished = 'deleted';
					} );
				}, 'danger' ),
				button( S.deleteNo, function () {
					state.confirmDelete = false;
					render();
				}, 'secondary' ),
			] ),
		] );
	}

	function viewResult( c ) {
		const r = c.result || {};
		const o = state.overview;
		const parts = [];

		if ( r.result === 'culprit' && r.culprit ) {
			const frame = siteFrame( c.checkUrl );
			parts.push( card( fmt( S.culpritTitle, r.culprit.name ), [
				para( fmt( S.culpritText, r.culprit.name ) ),
				para( S.culpritNext ),
				h( 'div', { class: 'hrpd-actions' }, [
					button( S.keepOff, function () {
						run( 'finish', { mode: 'keep' }, function ( data ) {
							setOverview( data );
							state.finished = 'kept';
						} );
					}, 'primary' ),
					button( S.turnBackOn, function () {
						run( 'finish', { mode: 'restore' }, function ( data ) {
							setOverview( data );
							state.finished = 'restored';
						} );
					}, 'secondary' ),
					button( S.deleteIt, function () {
						state.confirmDelete = true;
						render();
					}, 'danger' ),
				] ),
				deleteBox( r.culprit.name ),
			], 'ok' ) );
			parts.push( backgroundCard( c ) );
			parts.push( frameWrap( c.checkUrl, frame ) );
			parts.push( reportCard( caseReport( c ) ) );
			return parts;
		}

		let title = S.mixedTitle;
		const body = [];
		if ( r.result === 'not_plugin' ) {
			title = S.notPluginTitle;
			body.push( para( S.notPluginText ) );
			const items = [];
			if ( o.theme ) {
				items.push( fmt( S.suspectTheme, o.theme ) );
			}
			if ( o.phpVersion ) {
				items.push( fmt( S.suspectPhp, o.phpVersion ) );
			}
			if ( ( c.required || [] ).length ) {
				items.push( S.suspectRequired );
			}
			if ( ( o.muPlugins || [] ).length ) {
				items.push( fmt( S.suspectMu, o.muPlugins.join( ', ' ) ) );
			}
			if ( ( o.dropins || [] ).length ) {
				items.push( fmt( S.suspectDropins, o.dropins.join( ', ' ) ) );
			}
			const hackParts = String( S.suspectHack ).split( '%1$s' );
			items.push(
				S.suspectCache,
				[
					hackParts[ 0 ],
					h( 'a', { href: boot.helpUrl, target: '_blank', rel: 'noopener', title: S.suspectHackTip, text: S.suspectHackLink } ),
					hackParts[ 1 ] || '',
				]
			);
			body.push( h( 'ul', { class: 'hrpd-list' }, items.map( function ( t ) {
				return typeof t === 'string' ? h( 'li', { text: t } ) : h( 'li', {}, t );
			} ) ) );
		} else if ( r.result === 'combination' ) {
			title = S.comboTitle;
			body.push( para( S.comboText ) );
			if ( ( r.lastBroken || [] ).length ) {
				body.push( para( S.comboList ), nameList( r.lastBroken ) );
			}
		} else {
			body.push( para( S.mixedText ) );
		}

		body.push( h( 'div', { class: 'hrpd-actions' }, button( S.finish, function () {
			run( 'finish', { mode: 'restore' }, function ( data ) {
				setOverview( data );
				state.finished = 'restored';
			} );
		}, 'primary' ) ) );

		parts.push( card( title, body, 'warning' ) );
		parts.push( reportCard( caseReport( c ) ) );
		parts.push( helpCard() );
		parts.push( sitePreview( c.url ) );
		return parts;
	}

	function viewDone() {
		return [
			card( S.doneTitle, [
				para( state.finished === 'deleted' ? S.doneDeleted : ( state.finished === 'kept' ? S.doneKept : ( state.finished === 'cancelled' ? S.doneCancelled : S.doneRestored ) ) ),
				h( 'div', { class: 'hrpd-actions' }, [
					button( S.startAgain, function () {
						state.finished = null;
						state.view = '';
						render();
					}, 'primary' ),
					h( 'a', { class: 'hrpd-btn hrpd-btn--secondary', href: boot.toolsUrl, text: S.backToWp } ),
				] ),
			], 'ok' ),
			state.finished !== 'cancelled' && state.overview && state.overview.lastResult ? reportCard( function () {
				return state.overview.lastResult.report || '';
			} ) : null,
			helpCard(),
			sitePreview( boot.home ),
		];
	}

	function helpCard() {
		return h( 'aside', { class: 'hrpd-help' }, [
			boot.mascotUrl ? h( 'img', { class: 'hrpd-mascot', src: boot.mascotUrl, alt: '', width: 48, height: 48 } ) : null,
			h( 'p', {}, [
				h( 'strong', { text: S.helpTitle + ' ' } ),
				document.createTextNode( S.helpText + ' ' ),
				h( 'a', { href: boot.helpUrl, target: '_blank', rel: 'noopener', text: S.helpLink } ),
			] ),
		] );
	}

	/* ---------- render ---------- */

	function renderNav() {
		nav.textContent = '';
		const o = state.overview;
		if ( o && o.user && o.user.name ) {
			nav.appendChild( h( 'span', { class: 'hrpd-top__user', text: fmt( S.signedInAs, o.user.name ) } ) );
		}
		nav.appendChild( h( 'a', { href: boot.toolsUrl, text: S.backToWp } ) );
		if ( state.signedIn && o && o.user && o.user.source === 'troubleshooter' ) {
			nav.appendChild( h( 'button', {
				type: 'button',
				class: 'hrpd-linkbtn',
				text: S.signOut,
				onclick: function () {
					run( 'logout', {}, function () {
						state.signedIn = false;
						state.overview = null;
						state.csrf = '';
					} );
				},
			} ) );
		}
	}

	function currentView() {
		if ( ! state.signedIn ) {
			return viewLogin();
		}
		if ( ! state.overview ) {
			return para( S.working, 'hrpd-muted' );
		}
		if ( state.finished ) {
			return viewDone();
		}
		const c = state.overview.case;
		if ( c ) {
			if ( c.status === 'setup' ) {
				return viewRequired( c );
			}
			if ( c.status === 'testing' ) {
				return viewTesting( c );
			}
			if ( c.status === 'solved' ) {
				return viewResult( c );
			}
		}
		if ( state.view === 'where' ) {
			return viewWhere();
		}
		return viewHome();
	}

	let lastKey = '';

	function render() {
		renderNav();

		// Keep the screen (site preview, typed input) in place while only the busy flag or messages change.
		const c = state.overview && state.overview.case;
		const key = [ state.signedIn, !! state.overview, state.view, state.finished, state.challenge ? 1 : 0, c ? c.status + ':' + c.answers : '-', state.confirming, state.confirmDelete, state.overview ? state.overview.maintenance : '' ].join( '|' );

		if ( key === lastKey ) {
			root.querySelectorAll( 'button' ).forEach( function ( b ) {
				b.disabled = state.busy;
			} );
			renderMessages();
			return;
		}
		lastKey = key;

		root.textContent = '';
		root.classList.toggle( 'is-busy', state.busy );

		// Two panels: what to do next on the left, the live site on the right.
		const work = h( 'div', { class: 'hrpd-pane hrpd-pane--work' }, h( 'div', { id: 'hrpd-messages' } ) );
		const site = h( 'aside', { class: 'hrpd-pane hrpd-pane--site', 'aria-label': S.checkSite } );
		const view = currentView();
		( Array.isArray( view ) ? view : [ view ] ).forEach( function ( el ) {
			if ( ! el ) {
				return;
			}
			( el.classList && el.classList.contains( 'hrpd-frame-wrap' ) ? site : work ).appendChild( el );
		} );
		const split = site.childNodes.length > 0;
		root.classList.toggle( 'hrpd-split', split );
		root.appendChild( work );
		if ( split ) {
			root.appendChild( site );
			const box = site.querySelector( '.hrpd-frame-box' );
			const frame = site.querySelector( '.hrpd-frame' );
			if ( box && frame ) {
				fitFrame( box, frame );
			}
		}
		renderMessages();
	}

	function renderMessages() {
		const box = document.getElementById( 'hrpd-messages' );
		if ( ! box ) {
			return;
		}
		root.classList.toggle( 'is-busy', state.busy );
		box.textContent = '';
		if ( state.busy ) {
			box.appendChild( h( 'div', { class: 'hrpd-status', role: 'status' }, [ h( 'span', { class: 'hrpd-spinner', 'aria-hidden': 'true' } ), S.working ] ) );
		}
		if ( state.error ) {
			box.appendChild( h( 'div', { class: 'hrpd-alert hrpd-alert--error', role: 'alert', text: state.error } ) );
		}
		if ( state.notice ) {
			box.appendChild( h( 'div', { class: 'hrpd-alert hrpd-alert--ok', role: 'status', text: state.notice } ) );
		}
	}

	render();
	if ( state.signedIn ) {
		loadOverview();
	}
}() );
