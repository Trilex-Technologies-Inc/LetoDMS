<?php
/**
 * Implementation of an indexed document
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
 * Class for managing an indexed document.
 *
 * @category   DMS
 * @package    LetoDMS_Lucene
 * @version    @version@
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2011, Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_Lucene_IndexedDocument extends Zend_Search_Lucene_Document {
	/**
	 * Constructor. Creates our indexable document and adds all
	 * necessary fields to it using the passed in document
	 */
	public function __construct($dms, $document, $convcmd=null) {
		$_convcmd = array(
			'application/pdf' => 'pdftotext -enc UTF-8 -nopgbrk %s - |sed -e \'s/ [a-zA-Z0-9.]\{1\} / /g\' -e \'s/[0-9.]//g\'',
			'application/msword' => 'catdoc %s',
			'application/vnd.ms-excel' => 'ssconvert -T Gnumeric_stf:stf_csv -S %s fd://1',
			'audio/mp3' => "id3 -l -R %s | egrep '(Title|Artist|Album)' | sed 's/^[^:]*: //g'",
			'audio/mpeg' => "id3 -l -R %s | egrep '(Title|Artist|Album)' | sed 's/^[^:]*: //g'",
			'text/plain' => 'cat %s',
		);
		if($convcmd) {
			$_convcmd = $convcmd;
		}

		$version = $document->getLatestContent();
		$this->addField(Zend_Search_Lucene_Field::Keyword('document_id', $document->getID()));
		if($version) {
			$this->addField(Zend_Search_Lucene_Field::Keyword('mimetype', $version->getMimeType()));
			$this->addField(Zend_Search_Lucene_Field::UnIndexed('created', $version->getDate()));
		}
		$this->addField(Zend_Search_Lucene_Field::Text('title', $document->getName()));
		if($categories = $document->getCategories()) {
			$names = array();
			foreach($categories as $cat) {
				$names[] = $cat->getName();
			}
			$this->addField(Zend_Search_Lucene_Field::Text('category', implode(' ', $names)));
		}
		$owner = $document->getOwner();
		$this->addField(Zend_Search_Lucene_Field::Text('owner', $owner->getLogin()));
		if($keywords = $document->getKeywords()) {
			$this->addField(Zend_Search_Lucene_Field::Text('keywords', $keywords));
		}
		if($comment = $document->getComment()) {
			$this->addField(Zend_Search_Lucene_Field::Text('comment', $comment));
		}
		if($version) {
			$path = $dms->contentDir . $version->getPath();
			$content = '';
			$fp = null;
			$mimetype = $version->getMimeType();
			if(isset($_convcmd[$mimetype])) {
				$cmd = sprintf($_convcmd[$mimetype], $path);
				$fp = popen($cmd, 'r');
				if($fp) {
					$content = '';
					while(!feof($fp)) {
						$content .= fread($fp, 2048);
					}
					pclose($fp);
				}
				if($content) {
					$this->addField(Zend_Search_Lucene_Field::UnStored('content', $content, 'utf-8'));
				}
			}
		}
	}
}
?>
