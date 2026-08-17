<?php
/**
 * Implementation of preview documents
 *
 * @category   DMS
 * @package    LetoDMS_Preview
 * @license    GPL 2
 * @version    @version@
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2010, Uwe Steinmann
 * @version    Release: @package_version@
 */


/**
 * Class for managing creation of preview images for documents.
 *
 * @category   DMS
 * @package    LetoDMS_Preview
 * @version    @version@
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2011, Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_Preview_Previewer {
	/**
	 * @var string $cacheDir location in the file system where all the
	 *      cached data like thumbnails are located. This should be an
	 *      absolute path.
	 * @access public
	 */
	public $previewDir;

	/**
	 * @var integer $width maximum width/height of resized image
	 * @access protected
	 */
	protected $width;

	function __construct($previewDir, $width=40) {
		if(!is_dir($previewDir)) {
			if (!LetoDMS_Core_File::makeDir($previewDir)) {
				$this->previewDir = '';
			} else {
				$this->previewDir = $previewDir;
			}
		} else {
			$this->previewDir = $previewDir;
		}
		$this->width = intval($width);
	}

	function createPreview($documentcontent, $width=0) { /* {{{ */
		if($width == 0)
			$width = $this->width;
		else
			$width = intval($width);
		if(!$this->previewDir)
			return false;
		$document = $documentcontent->getDocument();
		$dir = $this->previewDir.'/'.$document->getDir();
		if(!is_dir($dir)) {
			if (!LetoDMS_Core_File::makeDir($dir)) {
				return false;
			}
		}
		$file = $document->_dms->contentDir.$documentcontent->getPath();
		if(!file_exists($file))
			return false;
		$target = $dir.'p'.$documentcontent->getVersion().'-'.$width.'.png';
		if(!file_exists($target)) {
			$cmd = '';
			switch($documentcontent->getMimeType()) {
				case "image/png":
				case "image/gif":
				case "image/jpeg":
				case "image/jpg":
					$cmd = 'convert -resize '.$width.'x'.$width.' '.$file.' '.$target;
					break;
				case "application/pdf":
					$cmd = 'convert -density 18 -resize '.$width.'x'.$width.' '.$file.'[0] '.$target;
			}
			if($cmd) {
				system( $cmd);
			}
			return true;
		}
		return true;
			
	} /* }}} */

	function hasPreview($documentcontent, $width=0) { /* {{{ */
		if($width == 0)
			$width = $this->width;
		else
			$width = intval($width);
		if(!$this->previewDir)
			return false;
		$document = $documentcontent->getDocument();
		$dir = $this->previewDir.'/'.$document->getDir();
		$target = $dir.'p'.$documentcontent->getVersion().'-'.$width.'.png';
		if(file_exists($target)) {
			return true;
		}
		return false;
	} /* }}} */

	function getPreview($documentcontent, $width=0) { /* {{{ */
		if($width == 0)
			$width = $this->width;
		else
			$width = intval($width);
		if(!$this->previewDir)
			return false;
		$document = $documentcontent->getDocument();
		$dir = $this->previewDir.'/'.$document->getDir();
		$target = $dir.'p'.$documentcontent->getVersion().'-'.$width.'.png';
		if(file_exists($target)) {
			readfile($target);
		}
	} /* }}} */

	function deletePreview($document, $documentcontent) { /* {{{ */
		if($width == 0)
			$width = $this->width;
		else
			$width = intval($width);
		if(!$this->previewDir)
			return false;
		$dir = $this->previewDir.'/'.$document->getDir();
		$target = $dir.'p'.$documentcontent->getVersion().'-'.$width.'.png';
	} /* }}} */
}
?>
