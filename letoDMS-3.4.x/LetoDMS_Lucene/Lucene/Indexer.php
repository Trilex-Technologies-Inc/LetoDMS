<?php
/**
 * Implementation of lucene index
 *
 * @category   DMS
 * @package    LetoDMS_Lucene
 * @license    GPL 2
 * @version    @version@
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2010, Uwe Steinmann
 * @version    Release: @package_version@
 */


/**
 * Class for managing a lucene index.
 *
 * @category   DMS
 * @package    LetoDMS_Lucene
 * @version    @version@
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2011, Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_Lucene_Indexer {
	/**
	 * @var string $indexname name of lucene index
	 * @access protected
	 */
	protected $indexname;

	function open($luceneDir) { /* {{{ */
		try {
			$index = Zend_Search_Lucene::open($luceneDir);
			return($index);
		} catch (Exception $e) {
			return null;
		}
	} /* }}} */

	function create($luceneDir) { /* {{{ */
		$index = Zend_Search_Lucene::create($luceneDir);
		return($index);
	} /* }}} */

	/**
	 * Do some initialization
	 *
	 */
	function init($stopWordsFile='') { /* {{{ */
		$analyzer = new Zend_Search_Lucene_Analysis_Analyzer_Common_Utf8_CaseInsensitive();
		if($stopWordsFile && file_exists($stopWordsFile)) {
			$stopWordsFilter = new Zend_Search_Lucene_Analysis_TokenFilter_StopWords();
			$stopWordsFilter->loadFromFile($stopWordsFile);
			$analyzer->addFilter($stopWordsFilter);
		}
		 
		Zend_Search_Lucene_Analysis_Analyzer::setDefault($analyzer);
	} /* }}} */


}
?>
