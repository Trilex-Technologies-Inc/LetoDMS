<?php
/**
 * Implementation of Info view
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
 * Class which outputs the html page for Info view
 *
 * @category   DMS
 * @package    LetoDMS
 * @author     Markus Westphal, Malcolm Cowe, Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010-2012 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_View_Info extends LetoDMS_Bootstrap_Style {

	function show() { /* {{{ */
		$version = $this->params['version'];
		$extensions = get_loaded_extensions();
		sort($extensions, SORT_NATURAL | SORT_FLAG_CASE);

		$this->htmlStartPage(getMLText("version_info"));
		$this->globalNavigation();
		$this->contentStart();
		$this->pageNavigation(getMLText("admin_tools"), "admin_tools");

		echo '<div class="info-page-header">';
		echo '<div><span class="info-eyebrow">System</span><h1>' . getMLText("version_info") . '</h1>';
		echo '<p>Application and server details for diagnostics and maintenance.</p></div>';
		echo '<span class="info-status"><i></i> System online</span>';
		echo '</div>';

		echo '<div class="info-summary">';
		$this->summaryCard('Application', $version->banner(), '&#9638;', 'blue');
		$this->summaryCard('PHP version', PHP_VERSION, '&#60;/&#62;', 'purple');
		$this->summaryCard('Server API', PHP_SAPI, '&#9881;', 'green');
		$this->summaryCard('Extensions', count($extensions) . ' loaded', '&#43;', 'orange');
		echo '</div>';

		echo '<div class="info-grid">';
		echo '<section class="well info-panel"><div class="info-panel-heading"><span class="info-panel-icon">&#128187;</span><div><h2>Runtime environment</h2><p>Current PHP and web-server configuration</p></div></div>';
		echo '<dl class="info-details">';
		$this->detailRow('Operating system', php_uname('s') . ' ' . php_uname('r'));
		$this->detailRow('Architecture', php_uname('m'));
		$this->detailRow('Server software', isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : 'Not available');
		$this->detailRow('Memory limit', ini_get('memory_limit'));
		$this->detailRow('Upload limit', ini_get('upload_max_filesize'));
		$this->detailRow('POST limit', ini_get('post_max_size'));
		$this->detailRow('Execution time', ini_get('max_execution_time') . ' seconds');
		$this->detailRow('Timezone', date_default_timezone_get());
		echo '</dl></section>';

		echo '<section class="well info-panel"><div class="info-panel-heading"><span class="info-panel-icon">&#128274;</span><div><h2>PHP capabilities</h2><p>Important features available to the application</p></div></div>';
		echo '<div class="info-capabilities">';
		$this->capability('File uploads', filter_var(ini_get('file_uploads'), FILTER_VALIDATE_BOOLEAN));
		$this->capability('Session support', extension_loaded('session'));
		$this->capability('Multibyte strings', extension_loaded('mbstring'));
		$this->capability('Image processing', extension_loaded('gd'));
		$this->capability('ZIP archives', extension_loaded('zip'));
		$this->capability('OpenSSL', extension_loaded('openssl'));
		echo '</div></section>';
		echo '</div>';

		echo '<section class="well info-panel info-extensions-panel"><div class="info-panel-heading"><span class="info-panel-icon">&#9881;</span><div><h2>Loaded extensions</h2><p>PHP modules currently available on this server</p></div></div>';
		echo '<div class="info-extension-list">';
		foreach ($extensions as $extension)
			echo '<span>' . htmlspecialchars($extension) . '</span>';
		echo '</div></section>';

		$this->contentEnd();
		$this->htmlEndPage();
	} /* }}} */

	private function summaryCard($label, $value, $icon, $color) { /* {{{ */
		echo '<div class="info-summary-card info-summary-' . $color . '"><div><span>' . htmlspecialchars($label) . '</span><strong>' . htmlspecialchars((string)$value) . '</strong></div><i aria-hidden="true">' . $icon . '</i></div>';
	} /* }}} */

	private function detailRow($label, $value) { /* {{{ */
		echo '<div><dt>' . htmlspecialchars($label) . '</dt><dd>' . htmlspecialchars((string)$value) . '</dd></div>';
	} /* }}} */

	private function capability($label, $available) { /* {{{ */
		echo '<div><span>' . htmlspecialchars($label) . '</span><strong class="' . ($available ? 'available' : 'unavailable') . '">' . ($available ? 'Available' : 'Unavailable') . '</strong></div>';
	} /* }}} */
}
?>
