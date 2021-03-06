( function ( wb ) {
	'use strict';

	wb.entityIdFormatter.testEntityIdHtmlFormatter = {
		all: function ( constructor, getInstance ) {
			this.constructorTests( constructor, getInstance );
			this.formatTests( getInstance );
		},

		constructorTests: function ( constructor, getInstance ) {
			QUnit.test( 'Constructor', function ( assert ) {
				var instance = getInstance();

				assert.ok(
					instance instanceof constructor,
					'Instantiated.'
				);

				assert.ok(
					instance instanceof wb.entityIdFormatter.EntityIdHtmlFormatter,
					'Instance of EntityIdHtmlFormatter'
				);
			} );
		},

		formatTests: function ( getInstance ) {
			QUnit.test( 'format returns some non-empty string', function ( assert ) {
				var instance = getInstance();
				var done = assert.async();

				instance.format( 'Q1' ).done( function ( res ) {
					assert.strictEqual( typeof res, 'string' );
					assert.notEqual( res, '' );
					done();
				} );
			} );
			QUnit.test( 'format correctly escapes ampersands in the entity id', function ( assert ) {
				var instance = getInstance();
				var done = assert.async();

				instance.format( '&' ).done( function ( res ) {
					assert.strictEqual( res.match( /&($|[^a])/ ), null );
					done();
				} );
			} );
			QUnit.test( 'format correctly escapes HTML in the entity id', function ( assert ) {
				var instance = getInstance();
				var done = assert.async();

				instance.format( '<script>' ).done( function ( res ) {
					assert.strictEqual( $( document.createElement( 'span' ) ).html( res ).find( 'script' ).length, 0 );
					done();
				} );
			} );
		}
	};
}( wikibase ) );
