/**
 * @license GPL-2.0+
 * @author H. Snater < mediawiki@snater.com >
 */
( function( wb, QUnit ) {
'use strict';

QUnit.module( 'wikibase.serialization.StatementDeserializer' );

var testSets = [
	[
		{
			mainsnak: {
				snaktype: 'novalue',
				property: 'P1'
			},
			type: 'statement',
			rank: 'normal'
		},
		new wb.datamodel.Statement(
			new wb.datamodel.Claim( new wb.datamodel.PropertyNoValueSnak( 'P1' ) ),
			null,
			wb.datamodel.Statement.RANK.NORMAL
		)
	], [
		{
			mainsnak: {
				snaktype: 'novalue',
				property: 'P1'
			},
			references: [ {
				snaks: {},
				'snaks-order': []
			} ],
			type: 'statement',
			rank: 'preferred'
		},
		new wb.datamodel.Statement(
			new wb.datamodel.Claim( new wb.datamodel.PropertyNoValueSnak( 'P1' ) ),
			new wb.datamodel.ReferenceList( [ new wb.datamodel.Reference() ] ),
			wb.datamodel.Statement.RANK.PREFERRED
		)
	]
];

QUnit.test( 'deserialize()', function( assert ) {
	assert.expect( 2 );
	var statementDeserializer = new wb.serialization.StatementDeserializer();

	for( var i = 0; i < testSets.length; i++ ) {
		assert.ok(
			statementDeserializer.deserialize( testSets[i][0] ).equals( testSets[i][1] ),
			'Test set #' + i + ': Deserializing successful.'
		);
	}
} );

}( wikibase, QUnit ) );
