<?php
/**
 * Implementation of a document in the document management system
 *
 * @category   DMS
 * @package    LetoDMS_Core
 * @license    GPL2
 * @author     Markus Westphal, Malcolm Cowe, Matteo Lucarelli,
 *             Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal, 2006-2008 Malcolm Cowe,
 *             2010 Matteo Lucarelli, 2010-2012 Uwe Steinmann
 * @version    Release: @package_version@
 */

/**
 * @uses LetoDMS_DatabaseAccess
 */
define('USE_PDO', 1);
if(defined('USE_PDO'))
	require_once('Core/inc.DBAccessPDO.php');
else
	require_once('Core/inc.DBAccess.php');

/**
 * @uses LetoDMS_DMS
 */
require_once('Core/inc.ClassDMS.php');

/**
 * @uses LetoDMS_Object
 */
require_once('Core/inc.ClassObject.php');

/**
 * @uses LetoDMS_Folder
 */
require_once('Core/inc.ClassFolder.php');

/**
 * @uses LetoDMS_Document
 */
require_once('Core/inc.ClassDocument.php');

/**
 * @uses LetoDMS_Attribute
 */
require_once('Core/inc.ClassAttribute.php');

/**
 * @uses LetoDMS_Group
 */
require_once('Core/inc.ClassGroup.php');

/**
 * @uses LetoDMS_User
 */
require_once('Core/inc.ClassUser.php');

/**
 * @uses LetoDMS_KeywordCategory
 */
require_once('Core/inc.ClassKeywords.php');

/**
 * @uses LetoDMS_DocumentCategory
 */
require_once('Core/inc.ClassDocumentCategory.php');

/**
 * @uses LetoDMS_Notification
 */
require_once('Core/inc.ClassNotification.php');

/**
 * @uses LetoDMS_UserAccess
 * @uses LetoDMS_GroupAccess
 */
require_once('Core/inc.ClassAccess.php');

/**
 * @uses LetoDMS_Workflow
 */
require_once('Core/inc.ClassWorkflow.php');

/**
 */
require_once('Core/inc.AccessUtils.php');

/**
 * @uses LetoDMS_File
 */
require_once('Core/inc.FileUtils.php');

?>
