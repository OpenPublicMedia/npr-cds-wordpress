/**
 * NPR CDS meta box functions and features
 *
 * @since 1.7
 */
document.addEventListener('DOMContentLoaded', () => {
	'use strict';
	var $ = jQuery;

	// contains the inputs
	var $container = $( '#npr-cds-publish-actions' );

	// initialize the form
	$container.find( 'input' ).on( 'change', li_checking );

	// Upon update, do the thing
	li_checking.call( $container.find( '#send_to_api' ) );

	/*
	 * If a checkbox in an li gets unchecked, uncheck and disable its child li
	 * If a checkbox in an li gets checked, enable its child li
	 */
	function li_checking( event ) {
		var checked =  $( this ).prop('checked');
		var $results = $( this ).closest( 'li' ).children( 'ul' ).children( 'li' ); // Only get the first level of list.
		$results.each( function( element ) {
			// Triggering the change event on the child does not work.
			if ( checked ) {
				var recurse = $( this ).children( 'label' ).children( 'input' ).prop( 'disabled', false );
				li_checking.call( recurse );
			} else {
				recurse = $( this ).children( 'label' ).children( 'input' ).prop( 'disabled', true ).prop( 'checked', false );
				li_checking.call( recurse );
			}
		});
	}

	// edit the time selector
	$( '#nprone-expiry-edit' ).on( 'click', function( event ) {
		event.preventDefault();
		$( '#nprone-expiry-form' ).toggleClass( 'hidden' );
		$( this ).toggleClass( 'hidden' );
	});
	// close the time selector
	$( '#nprone-expiry-cancel' ).on( 'click', function( event ) {
		event.preventDefault();
		$( '#nprone-expiry-form' ).toggleClass( 'hidden' );
		$( '#nprone-expiry-edit' ).toggleClass( 'hidden' );
	});
	// save the time selector
	$( '#nprone-expiry-ok' ).on( 'click', function( event ) {
		event.preventDefault();
		$( '#nprone-expiry-form' ).toggleClass( 'hidden' );
		$( '#nprone-expiry-edit' ).toggleClass( 'hidden' );
		var dateTimeLocal = $( '#nprone-expiry-datetime' ).val();
		var d = new Date( dateTimeLocal );
		var string = d.toLocaleString("en-us", { month: "short" })
			+ " "
			+ d.getDate()
			+ ", "
			+ d.getFullYear()
			+ " @ "
			+ d.getHours()
			+ ":"
			+ (d.getMinutes() < 10? '0' : '') + d.getMinutes();

		$( '#nprone-expiry-display time' ).text( string );
	});
});
