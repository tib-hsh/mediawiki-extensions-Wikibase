<?php

namespace Wikibase\Repo;

use ValueFormatters\FormatterOptions;
use ValueFormatters\ValueFormatter;
use Wikibase\LanguageFallbackChain;
use Wikibase\Lib\OutputFormatSnakFormatterFactory;
use Wikibase\Lib\SnakFormatter;
use Wikibase\Lib\Store\LabelDescriptionLookup;
use Wikibase\View\HtmlSnakFormatterFactory;

/**
 * An HtmlSnakFormatterFactory implementation using an OutputFormatSnakFormatterFactory
 *
 * @author Adrian Heine < adrian.heine@wikimedia.de >
 */
class WikibaseHtmlSnakFormatterFactory implements HtmlSnakFormatterFactory {

	/**
	 * @var OutputFormatSnakFormatterFactory
	 */
	private $snakFormatterFactory;

	/**
	 * @param OutputFormatSnakFormatterFactory $snakFormatterFactory
	 */
	public function __construct( OutputFormatSnakFormatterFactory $snakFormatterFactory ) {
		$this->snakFormatterFactory = $snakFormatterFactory;
	}

	/**
	 * @param string $languageCode
	 * @param LanguageFallbackChain $languageFallbackChain
	 * @param LabelDescriptionLookup $labelDescriptionLookup
	 *
	 * @return FormatterOptions
	 */
	private function getFormatterOptions(
		$languageCode,
		LanguageFallbackChain $languageFallbackChain,
		LabelDescriptionLookup $labelDescriptionLookup
	) {
		$formatterOptions = new FormatterOptions( array(
			ValueFormatter::OPT_LANG => $languageCode,
			'languages' => $languageFallbackChain,
			'LabelDescriptionLookup' => $labelDescriptionLookup,
		) );
		return $formatterOptions;
	}

	/**
	 * @param string $languageCode
	 * @param LanguageFallbackChain $languageFallbackChain
	 * @param LabelDescriptionLookup $labelDescriptionLookup
	 *
	 * @return SnakFormatter
	 */
	public function getSnakFormatter(
		$languageCode,
		LanguageFallbackChain $languageFallbackChain,
		LabelDescriptionLookup $labelDescriptionLookup
	) {
		$formatterOptions = $this->getFormatterOptions( $languageCode, $languageFallbackChain, $labelDescriptionLookup );

		return $this->snakFormatterFactory->getSnakFormatter(
			SnakFormatter::FORMAT_HTML_WIDGET,
			$formatterOptions
		);
	}

}
