/**
 * @license GPL-2.0+
 * @author H. Snater < mediawiki@snater.com >
 */
( function( wb, QUnit ) {
'use strict';

QUnit.module( 'wikibase.serialization.MultiTermSerializer' );

var testSets = [
	[
		new wb.datamodel.MultiTerm( 'en', [ 'test1', 'test2' ] ),
		[ { language: 'en', value: 'test1' }, { language: 'en', value: 'test2' } ]
	]
];

QUnit.test( 'serialize()', function( assert ) {
	assert.expect( 1 );
	var multiTermSerializer = new wb.serialization.MultiTermSerializer();

	for( var i = 0; i < testSets.length; i++ ) {
		assert.deepEqual(
			multiTermSerializer.serialize( testSets[i][0] ),
			testSets[i][1],
			'Test set #' + i + ': Serializing successful.'
		);
	}
} );

}( wikibase, QUnit ) );
