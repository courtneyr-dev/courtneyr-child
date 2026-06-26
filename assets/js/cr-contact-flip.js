/**
 * Contact postcard flip — on a successful WS Form submit, turn the postcard over
 * to reveal the scenic "front" (the airmail postcard). WS Form has no reliable
 * success DOM event, so we watch its submit XHR: when /ws-form/v1/submit returns
 * error:false, we flip. A "back to the form" button flips it the other way. The
 * flip is applied as an inline transform (not a CSS state class) so Perfmatters'
 * Remove-Unused-CSS can't strip it.
 */
( function () {
	window.__crFlip = 'loaded';

	function el() {
		return document.querySelector( '.cr-contact__flip' );
	}

	function setFace( flipped ) {
		var f = el();
		if ( ! f ) {
			return;
		}
		var inner = f.querySelector( '.cr-contact__flip-inner' );
		var front = f.querySelector( '.cr-contact__front' );
		var back = f.querySelector( '.cr-contact__postcard' );
		if ( inner ) {
			inner.style.transform = flipped ? 'rotateY(180deg)' : 'rotateY(0deg)';
		}
		if ( flipped ) {
			f.dataset.flipped = '1';
		} else {
			delete f.dataset.flipped;
		}
		if ( front ) {
			front.setAttribute( 'aria-hidden', flipped ? 'false' : 'true' );
		}
		if ( back ) {
			back.setAttribute( 'aria-hidden', flipped ? 'true' : 'false' );
		}
	}

	function flipToFront() {
		var f = el();
		if ( ! f || f.dataset.flipped ) {
			return;
		}
		setFace( true );
		try {
			f.scrollIntoView( { behavior: 'smooth', block: 'center' } );
		} catch ( e ) {}
	}

	function flipToBack() {
		setFace( false );
		var f = el();
		var first = f && f.querySelector( '.cr-contact__postcard textarea, .cr-contact__postcard input' );
		if ( first ) {
			try {
				first.focus();
			} catch ( e ) {}
		}
	}

	// "Back to the form" control on the postcard front.
	document.addEventListener( 'click', function ( e ) {
		if ( e.target.closest( '[data-cr-flip-back]' ) ) {
			e.preventDefault();
			flipToBack();
		}
	} );

	// Warm the postcard image on first interaction so the reveal is instant
	// without forcing every visitor to download it up front.
	var warmed = false;
	document.addEventListener(
		'focusin',
		function ( e ) {
			if ( warmed || ! e.target.closest( '.cr-contact__flip' ) ) {
				return;
			}
			warmed = true;
			var img = document.querySelector( '.cr-front__img' );
			if ( img && img.getAttribute( 'src' ) ) {
				new Image().src = img.getAttribute( 'src' );
			}
		},
		true
	);

	// Flip to the front when WS Form reports a successful submit.
	var open = XMLHttpRequest.prototype.open;
	var send = XMLHttpRequest.prototype.send;
	XMLHttpRequest.prototype.open = function ( method, url ) {
		this.__crUrl = url;
		return open.apply( this, arguments );
	};
	XMLHttpRequest.prototype.send = function () {
		var xhr = this;
		this.addEventListener( 'load', function () {
			if ( String( xhr.__crUrl || '' ).indexOf( 'ws-form/v1/submit' ) === -1 ) {
				return;
			}
			var ok = false;
			try {
				var json = JSON.parse( xhr.responseText );
				ok = json && json.error === false && ! json.error_validation;
			} catch ( e ) {}
			if ( ok ) {
				setTimeout( flipToFront, 200 );
			}
		} );
		return send.apply( this, arguments );
	};
} )();
