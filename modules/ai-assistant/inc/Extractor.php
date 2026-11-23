<?php
// Shared local extraction remains available when the AI module is disabled or removed.
require_once dirname(__DIR__, 3).'/inc/inc.ClassTextExtractor.php';
class LetoDMS_AIExtractor extends LetoDMS_TextExtractor {}
