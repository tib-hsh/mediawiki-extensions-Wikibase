<?php

namespace Wikibase\Lib\Tests\Store\Sql;

use InvalidArgumentException;
use Psr\Log\NullLogger;
use Title;
use Wikibase\DataAccess\DataAccessSettings;
use Wikibase\DataAccess\EntitySource;
use Wikibase\DataAccess\UnusableEntitySource;
use Wikibase\DataModel\Entity\BasicEntityIdParser;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\Property;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Services\EntityId\EntityIdComposer;
use Wikibase\DataModel\Services\EntityId\PrefixMappingEntityIdParser;
use Wikibase\ItemContent;
use Wikibase\Lib\Store\EntityNamespaceLookup;
use Wikibase\Lib\Store\Sql\SqlEntityInfoBuilder;
use Wikibase\Lib\Tests\Store\EntityInfoBuilderTestCase;
use Wikibase\PropertyContent;
use Wikibase\WikibaseSettings;
use Wikipage;

/**
 * @covers \Wikibase\Lib\Store\Sql\SqlEntityInfoBuilder
 *
 * @group Wikibase
 * @group WikibaseStore
 * @group WikibasePropertyInfo
 * @group Database
 *
 * @license GPL-2.0-or-later
 * @author Daniel Kinzler
 */
class SqlEntityInfoBuilderTest extends EntityInfoBuilderTestCase {

	const REPOSITORY_PREFIX_BASED_FEDERATION = false;
	const ENTITY_SOURCE_BASED_FEDERATION = true;

	const ITEM_NAMESPACE_ID = 120;
	const PROPERTY_NAMESPACE_ID = 122;

	protected function setUp() {
		parent::setUp();

		if ( !WikibaseSettings::isRepoEnabled() ) {
			$this->markTestSkipped( 'Entity info tables are not available locally on the client' );
		}

		$this->tablesUsed[] = 'wb_property_info';
		$this->tablesUsed[] = 'wb_terms';
		$this->tablesUsed[] = 'page';
		$this->tablesUsed[] = 'redirect';
		$this->tablesUsed[] = 'revision';
		$this->tablesUsed[] = 'ip_changes';

		$termRows = [];
		$infoRows = [];
		$redirectRows = [];

		foreach ( $this->getKnownEntities() as $entity ) {
			$this->createPage( $entity );

			$id = $entity->getId();
			$labels = $entity->getLabels()->toTextArray();
			$descriptions = $entity->getDescriptions()->toTextArray();
			$aliases = $entity->getAliasGroups()->toTextArray();

			$termRows = array_merge( $termRows, $this->getTermRows( $id, 'label', $labels ) );
			$termRows = array_merge( $termRows, $this->getTermRows( $id, 'description', $descriptions ) );
			$termRows = array_merge( $termRows, $this->getTermRows( $id, 'alias', $aliases ) );

			if ( $entity instanceof Property ) {
				$infoRows[] = [
					$id->getNumericId(),
					$entity->getDataTypeId(),
					'{"type":"' . $entity->getDataTypeId() . '"}'
				];
			}
		}

		foreach ( $this->getKnownRedirects() as $from => $toId ) {
			$fromId = new ItemId( $from );

			$page = $this->createPage( new Item( $fromId ) );
			$redirectRows[] = [
				$page->getId(),
				$this->getEntityNamespaceLookup()->getEntityNamespace( $fromId->getEntityType() ),
				$toId->getSerialization()
			];
		}

		$this->insertRows(
			'wb_terms',
			[
				'term_entity_type',
				'term_entity_id',
				'term_full_entity_id',
				'term_type',
				'term_language',
				'term_text',
				'term_search_key'
			],
			$termRows );

		$this->insertRows(
			'wb_property_info',
			[ 'pi_property_id', 'pi_type', 'pi_info' ],
			$infoRows );

		$redirectColumns = [ 'rd_from', 'rd_namespace', 'rd_title' ];

		$this->insertRows(
			'redirect',
			$redirectColumns,
			$redirectRows );
	}

	private function getTermRows( EntityId $id, $termType, $terms ) {
		$rows = [];

		foreach ( $terms as $lang => $langTerms ) {
			$langTerms = (array)$langTerms;

			foreach ( $langTerms as $term ) {
				$rows[] = [
					$id->getEntityType(),
					0,
					$id->getSerialization(),
					$termType,
					$lang,
					$term,
					$term
				];
			}
		}

		return $rows;
	}

	/**
	 * @param EntityDocument $entity
	 *
	 * @return Wikipage|null
	 */
	private function createPage( EntityDocument $entity ) {

		if ( $entity->getType() == Item::ENTITY_TYPE ) {
			$empty = new Item( $entity->getId() );
			$content = ItemContent::newFromItem( $empty );
		} elseif ( $entity->getType() == Property::ENTITY_TYPE ) {
			$empty = new Property( $entity->getId(), null, $entity->getDataTypeId() );
			$content = PropertyContent::newFromProperty( $empty );
		} else {
			return null;
		}
		$page = WikiPage::factory( Title::newFromText(
			$entity->getId()->getSerialization(),
			$this->getEntityNamespaceLookup()->getEntityNamespace( $entity->getType() )
		) );
		$page->doEditContent( $content, 'testing', EDIT_NEW );

		return $page;
	}

	private function insertRows( $table, array $fields, array $rows ) {
		$dbw = wfGetDB( DB_MASTER );

		foreach ( $rows as $row ) {
			$row = array_slice( $row, 0, count( $fields ) );

			$dbw->insert(
				$table,
				array_combine( $fields, $row ),
				__METHOD__,
				// Just ignore insertation errors... if similar data already is in the DB
				// it's probably good enough for the tests (as this is only testing for UNIQUE
				// fields anyway).
				[ 'IGNORE' ]
			);
		}
	}

	/**
	 * @return SqlEntityInfoBuilder
	 */
	protected function newEntityInfoBuilder() {
		return new SqlEntityInfoBuilder(
			new BasicEntityIdParser(),
			new EntityIdComposer( [
				'item' => function( $repositoryName, $uniquePart ) {
					return new ItemId( 'Q' . $uniquePart );
				},
				'property' => function( $repositoryName, $uniquePart ) {
					return new PropertyId( 'P' . $uniquePart );
				},
			] ),
			$this->getEntityNamespaceLookup(),
			new NullLogger(),
			new EntitySource( 'source', false, [], '', '' ),
			new DataAccessSettings( 100, false, false, self::REPOSITORY_PREFIX_BASED_FEDERATION )
		);
	}

	/**
	 * @return EntityIdComposer
	 */
	private function getIdComposer() {
		return $this->getMockBuilder( EntityIdComposer::class )
			->disableOriginalConstructor()
			->getMock();
	}

	/**
	 * @return EntityNamespaceLookup
	 */
	private function getEntityNamespaceLookup() {
		return new EntityNamespaceLookup( [ 'item' => self::ITEM_NAMESPACE_ID, 'property' => self::PROPERTY_NAMESPACE_ID ] );
	}

	public function provideInvalidConstructorArguments() {
		return [
			'neither string nor false as database name (int)' => [ 100, '' ],
			'neither string nor false as database name (null)' => [ null, '' ],
			'neither string nor false as database name (true)' => [ true, '' ],
			'not a string as a repository name' => [ false, 1000 ],
			'string containing colon as a repository name' => [ false, 'foo:oo' ],
		];
	}

	/**
	 * @dataProvider provideInvalidConstructorArguments
	 */
	public function testGivenInvalidArguments_constructorThrowsException( $databaseName, $repositoryName ) {
		$this->setExpectedException( InvalidArgumentException::class );

		new SqlEntityInfoBuilder(
			new BasicEntityIdParser(),
			$this->getIdComposer(),
			$this->getEntityNamespaceLookup(),
			new NullLogger(),
			new UnusableEntitySource(),
			new DataAccessSettings( 100, false, false, self::REPOSITORY_PREFIX_BASED_FEDERATION ),
			$databaseName,
			$repositoryName
		);
	}

	public function testIgnoresEntityIdsFromOtherRepositories() {
		$itemId = new ItemId( 'Q1' );
		$propertyId = new PropertyId( 'foo:P1' );

		$builder = new SqlEntityInfoBuilder(
			new BasicEntityIdParser(),
			$this->getIdComposer(),
			$this->getEntityNamespaceLookup(),
			new NullLogger(),
			new EntitySource( 'source', false, [], '', '' ),
			new DataAccessSettings( 100, false, false, self::REPOSITORY_PREFIX_BASED_FEDERATION ),
			false,
			''
		);

		$entityInfo = $builder->collectEntityInfo( [ $itemId, $propertyId ], [] );

		$this->assertTrue( $entityInfo->hasEntityInfo( $itemId ) );
		$this->assertFalse( $entityInfo->hasEntityInfo( $propertyId ) );
	}

	public function testIgnoresEntityIdsFromOtherEntitySources() {
		$itemId = new ItemId( 'Q1' );
		$propertyId = new PropertyId( 'P2' );

		$builder = new SqlEntityInfoBuilder(
			new BasicEntityIdParser(),
			$this->getIdComposer(),
			$this->getEntityNamespaceLookup(),
			new NullLogger(),
			new EntitySource( 'source', false, [ 'item' => [ 'namespaceId' => self::ITEM_NAMESPACE_ID, 'slot' => 'main' ] ], '', '' ),
			new DataAccessSettings( 100, false, false, self::ENTITY_SOURCE_BASED_FEDERATION )
		);

		$entityInfo = $builder->collectEntityInfo( [ $itemId, $propertyId ], [] );

		$this->assertTrue( $entityInfo->hasEntityInfo( $itemId ) );
		$this->assertFalse( $entityInfo->hasEntityInfo( $propertyId ) );
	}

	private function saveFakeForeignItemTermUsingFullItemId( ItemId $itemId, $termType, $termLanguage, $termText ) {
		// Insert any existing data for the item ID (might collide because of insert done in setUp
		$db = wfGetDB( DB_MASTER );
		$db->delete(
			'wb_terms',
			[
				'term_full_entity_id' => $itemId->getLocalPart(),
				'term_type' => $termType,
				'term_language' => $termLanguage
			]
		);

		// Inserting a dummy label for item with numeric ID part equal to 1.
		// In this test local database is used to pretend to be a databse of
		// repository "foo". Terms fetched from the database should be
		// matched to entity IDs using correct repository prefixes (this
		// builder's responsibility as this information is not stored in wb_terms table).
		$this->insertRows(
			'wb_terms',
			[
				'term_entity_type',
				'term_entity_id',
				'term_full_entity_id',
				'term_type',
				'term_language',
				'term_text',
				'term_search_key'
			],
			[
				[
					$itemId->getEntityType(),
					$itemId->getNumericId(),
					$itemId->getLocalPart(),
					$termType,
					$termLanguage,
					$termText,
					$termText
				]
			]
		);
	}

	public function testEntityIdsArePrefixedWithRepositoryName() {
		$itemId = new ItemId( 'foo:Q1' );

		$label = 'dummy label';
		$languageCode = 'en';

		$this->saveFakeForeignItemTermUsingFullItemId( $itemId, 'label', $languageCode, $label );

		$builder = new SqlEntityInfoBuilder(
			new PrefixMappingEntityIdParser( [ '' => 'foo' ], new BasicEntityIdParser() ),
			new EntityIdComposer( [
				'item' => function( $repositoryName, $uniquePart ) {
					return ItemId::newFromRepositoryAndNumber( $repositoryName, $uniquePart );
				},
			] ),
			$this->getEntityNamespaceLookup(),
			new NullLogger(),
			new EntitySource( 'source', false, [], '', '' ),
			new DataAccessSettings( 100, false, false, self::REPOSITORY_PREFIX_BASED_FEDERATION ),
			false,
			'foo'
		);

		$entityInfo = $builder->collectEntityInfo( [ $itemId ], [ $languageCode ] )->getEntityInfo( $itemId );

		$this->assertSame( $label, $entityInfo['labels'][$languageCode]['value'] );
	}

	public function testGivenEmptyIdList_returnsEmptyEntityInfo_entitySourceBasedAccess() {
		$builder = $this->newEntityInfoBuilderForSourceBasedAccess();

		$this->assertEmpty( $builder->collectEntityInfo( [], [] )->asArray() );
	}

	public function testGivenDuplicateIds_eachIdsOnlyIncludedOnceInResult_entitySourceBasedAccess() {
		$id = new ItemId( 'Q1' );

		$builder = $this->newEntityInfoBuilderForSourceBasedAccess();

		$info = $builder->collectEntityInfo( [ $id, $id ], [] )->asArray();

		$this->assertCount( 1, array_keys( $info ) );
		$this->assertArrayHasKey( 'Q1', $info );
	}

	public function testGivenEmptyLanguageCodeList_returnsNoLabelsAndDescriptionsInEntityInfo_entitySourceBasedAccess() {
		$id = new ItemId( 'Q1' );

		$builder = $this->newEntityInfoBuilderForSourceBasedAccess();

		$info = $builder->collectEntityInfo( [ $id ], [] )->asArray();

		$this->assertEmpty( $info['Q1']['labels'] );
		$this->assertEmpty( $info['Q1']['descriptions'] );
	}

	public function testGivenLanguageCode_returnsOnlyTermsInTheLanguage_entitySourceBasedAccess() {
		$id = new ItemId( 'Q1' );

		$builder = $this->newEntityInfoBuilderForSourceBasedAccess();

		$info = $builder->collectEntityInfo( [ $id ], [ 'de' ] )->asArray();

		$this->assertEquals( $this->makeLanguageValueRecords( [ 'de' => 'label:Q1/de' ] ), $info['Q1']['labels'] );
		$this->assertEquals(
			$this->makeLanguageValueRecords( [ 'de' => 'description:Q1/de' ] ),
			$info['Q1']['descriptions']
		);
	}

	public function testGivenMultipleLanguageCodes_returnsTermsInTheLanguagesGiven_entitySourceBasedAccess() {
		$id = new ItemId( 'Q1' );

		$builder = $this->newEntityInfoBuilderForSourceBasedAccess();

		$info = $builder->collectEntityInfo( [ $id ], [ 'en', 'de' ] )->asArray();

		$this->assertEquals(
			$this->makeLanguageValueRecords(
				[ 'en' => 'label:Q1/en', 'de' => 'label:Q1/de' ]
			),
			$info['Q1']['labels']
		);
		$this->assertEquals(
			$this->makeLanguageValueRecords(
				[ 'en' => 'description:Q1/en', 'de' => 'description:Q1/de' ]
			),
			$info['Q1']['descriptions']
		);
	}

	public function testGivenRedirectId_returnsTermsOfTheTarget_entitySourceBasedAccess() {
		$redirectId = new ItemId( self::REDIRECT_SOURCE_ID );

		$builder = $this->newEntityInfoBuilderForSourceBasedAccess();

		$info = $builder->collectEntityInfo( [ $redirectId ], [ 'de' ] )->asArray();

		$this->assertEquals( $this->makeLanguageValueRecords( [ 'de' => 'label:Q2/de' ] ), $info[self::REDIRECT_SOURCE_ID]['labels'] );
	}

	public function testGivenRedirect_entityInfoUsesRedirectSourceAsKey_entitySourceBasedAccess() {
		$redirectId = new ItemId( self::REDIRECT_SOURCE_ID );

		$builder = $this->newEntityInfoBuilderForSourceBasedAccess();

		$info = $builder->collectEntityInfo( [ $redirectId ], [] )->asArray();

		$this->assertArrayHasKey( self::REDIRECT_SOURCE_ID, $info );
		$this->assertArrayNotHasKey( self::REDIRECT_TARGET_ID, $info );
	}

	public function testGivenNonExistingIds_nonExistingIdsSkippedInResult_entitySourceBasedAccess() {
		$existingId = new ItemId( 'Q1' );
		$nonExistingId = new ItemId( 'Q1000' );

		$builder = $this->newEntityInfoBuilderForSourceBasedAccess();

		$info = $builder->collectEntityInfo( [ $existingId, $nonExistingId ], [] )->asArray();

		$this->assertArrayHasKey( 'Q1', $info );
		$this->assertArrayNotHasKey( 'Q1000', $info );
	}

	protected function newEntityInfoBuilderForSourceBasedAccess() {
		return new SqlEntityInfoBuilder(
			new BasicEntityIdParser(),
			new EntityIdComposer( [
				'item' => function( $repositoryName, $uniquePart ) {
					return new ItemId( 'Q' . $uniquePart );
				},
			] ),
			$this->getEntityNamespaceLookup(),
			new NullLogger(),
			new EntitySource( 'testsource', false, [ 'item' => [ 'namespaceId' => self::ITEM_NAMESPACE_ID, 'slot' => 'main' ] ], '', '' ),
			new DataAccessSettings( 100, false, false, self::ENTITY_SOURCE_BASED_FEDERATION )
		);
	}

}
