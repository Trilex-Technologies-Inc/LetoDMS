<?php
/**
 * Implementation of Help view
 *
 * @category   DMS
 * @package    LetoDMS
 * @license    GPL 2
 * @version    @version@
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010-2012 Uwe Steinmann
 * @version    Release: @package_version@
 */

/**
 * Include parent class
 */
require_once("class.Bootstrap.php");

/**
 * Class which outputs the html page for Help view
 *
 * @category   DMS
 * @package    LetoDMS
 * @author     Markus Westphal, Malcolm Cowe, Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010-2012 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_View_Help extends LetoDMS_Bootstrap_Style {

	function show() { /* {{{ */
		$dms = $this->params['dms'];
		$user = $this->params['user'];

		$this->htmlStartPage(getMLText("help"));
		$this->globalNavigation();
		$this->contentStart();
		$this->pageNavigation(getMLText("help"), "");

		$this->contentContainerStart();

		$helpFile = "../languages/".$user->getLanguage()."/help.htm";
		if (!file_exists($helpFile)) {
			// Fallback to English if help file doesn't exist for user's language
			$helpFile = "../languages/English/help.htm";
		}
		
		if (file_exists($helpFile)) {
			readfile($helpFile);
		} else {
			echo "<p>Help is not available for this language.</p>";
		}

		$this->contentContainerEnd();
		$this->htmlEndPage();
	} /* }}} */
}
?>
