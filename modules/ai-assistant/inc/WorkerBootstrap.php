<?php
function letoDMSAIWorkerLoadCore($root) {
    // Core's legacy bare includes must resolve to Core/, not the parallel inc/ classes.
    set_include_path($root.'/LetoDMS_Core/Core'.PATH_SEPARATOR.$root.'/inc'.PATH_SEPARATOR.get_include_path());
    require_once $root.'/LetoDMS_Core/Core.php';
}
