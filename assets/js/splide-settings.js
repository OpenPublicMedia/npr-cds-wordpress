var elms = document.getElementsByClassName( 'splide' );
for ( var i = 0; i < elms.length; i++ ) {
	new Splide( elms[ i ], {
		type: 'fade',
		width: '100%',
		heightRatio: 0.9,
		lazyLoad: 'sequential'
	} ).mount();
}