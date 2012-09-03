/**
 * JavaScript for user interface related stuff of the 'Wikibase' extension.
 * @see https://www.mediawiki.org/wiki/Extension:Wikibase
 *
 * @file
 * @ingroup Wikibase
 *
 * @licence GNU GPL v2+
 * @author Daniel Werner
 * @author H. Snater
 */
( function( mw, wb, $, undefined ) {
'use strict';

/**
 * Module for 'Wikibase' extensions user interface functionality.
 * Make sure this won't be overridden when loading two ui modules
 * in parallel without loading base module.
 * @var Object
 * @since 0.1
 */
wb.ui = wb.ui || {};
$.extend( wb.ui, {
	/**
	 * @const states of element groups
	 * @enum number
	 */
	ELEMENT_STATE: {
		ENABLED: 1, // all elements are enabled
		DISABLED: 2, // all elements are disabled
		MIXED: 3
	}
} );

} )( mediaWiki, wikibase, jQuery );
