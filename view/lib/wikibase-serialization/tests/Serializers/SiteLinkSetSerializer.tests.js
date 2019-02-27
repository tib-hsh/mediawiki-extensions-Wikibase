/**
 * @license GPL-2.0+
 * @author H. Snater < mediawiki@snater.com >
 */
( function( wb, QUnit ) {
'use strict';

QUnit.module( 'wikibase.serialization.SiteLinkSetSerializer' );

var testSets = [
	[
		new wb.datamodel.SiteLinkSet(),
		{}
	], [
		new wb.datamodel.SiteLinkSet( [ new wb.datamodel.SiteLink( 'site', 'page' ) ] ),
		{
			site: {
				site: 'site',
				title: 'page',
				badges: []
			}
		}
	]
];

QUnit.test( 'serialize()', function( assert ) {
	assert.expect( 2 );
	var siteLinkSetSerializer = new wb.serialization.SiteLinkSetSerializer();

	for( var i = 0; i < testSets.length; i++ ) {
		assert.deepEqual(
			siteLinkSetSerializer.serialize( testSets[i][0] ),
			testSets[i][1],
			'Test set #' + i + ': Serializing successful.'
		);
	}
} );

}( wikibase, QUnit ) );
