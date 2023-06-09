( () => {
	"use strict";

	const body = document.body;
	const sections = document.querySelectorAll( '.site-section' );

	async function onPageLoad() {
		await document.fonts.ready;

		sectionsInview();
		bannerAnimations();
	}

	function sectionsInview() {
		const sectionsObserver = new IntersectionObserver( ( [ entry ], observer ) => {
			if ( entry.isIntersecting ) {
				observer.unobserve( entry.target );
				entry.target.classList.add( 'lqd-is-in-view' );
			}
		}, { threshold: [ 0.2, 0.3, 0.4 ] } );

		sections.forEach( section => {
			sectionsObserver.observe( section );
		} );
	}

	function bannerAnimations() {
		const banner = document.querySelector( '#banner' );

		if ( !banner ) return;
	}

	const loadTimeout = setTimeout( () => {
		document.documentElement.classList.remove( 'overflow-hidden' );
		body.classList.add( 'page-loaded' );
		onPageLoad();
		clearTimeout( loadTimeout );
	}, 350 );

} )();