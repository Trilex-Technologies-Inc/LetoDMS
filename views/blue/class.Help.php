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
require_once("class.BlueStyle.php");

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
class LetoDMS_View_Help extends LetoDMS_Blue_Style {

	function show() { /* {{{ */
		$dms = $this->params['dms'];
		$user = $this->params['user'];

		$this->htmlStartPage(getMLText("help"));
		$this->globalNavigation();
		$this->pageNavigation(getMLText("help"), "");
		echo '<link rel="stylesheet" href="../styles/bootstrap/help.css?v='.filemtime(__DIR__.'/../../styles/bootstrap/help.css').'">';
		echo '<script defer src="../styles/bootstrap/help.js?v='.filemtime(__DIR__.'/../../styles/bootstrap/help.js').'"></script>';

		$this->contentContainerStart();

		$language = $user->getLanguage();
		if (isset($_GET['guide']) && $_GET['guide'] === 'english') $language = 'English';
		if (!is_string($language) || !preg_match('/^[A-Za-z0-9_-]+$/D', $language)) $language = 'English';
		$helpFile = __DIR__.'/../../languages/'.$language.'/help.htm';
		if (!is_file($helpFile)) $helpFile = __DIR__.'/../../languages/English/help.htm';
		if ($language !== 'English') echo '<p><a href="../out/out.Help.php?guide=english">Open the updated, searchable guide (English)</a></p>';
		readfile($helpFile);

		$this->contentContainerEnd();
		$this->htmlEndPage();
	} /* }}} */
}
?>
