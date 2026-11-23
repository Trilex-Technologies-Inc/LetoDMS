<?php
require_once 'class.Bootstrap.php';
class LetoDMS_View_SearchFulltext extends LetoDMS_Bootstrap_Style
{
    public function show()
    {
        $p=$this->params; $esc=function($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');};
        $this->htmlStartPage('Full-text search'); $this->globalNavigation(); $this->contentStart();
        $this->contentHeading('Full-text search'); $this->contentContainerStart();
        echo '<p>Search words inside locally indexed documents. All words must match; use quotation marks for a phrase. No AI requests are made.</p>';
        echo '<form method="get" action="../op/op.SearchFulltext.php"><label for="fulltext-query">Search document contents</label><input id="fulltext-query" type="search" name="query" maxlength="512" value="'.$esc($p['query']).'" /> <button class="btn" type="submit">Search</button>';
        if ($p['folderid']) echo '<input type="hidden" name="folderid" value="'.(int)$p['folderid'].'">';
        if ($p['ownerid']) echo '<input type="hidden" name="ownerid" value="'.(int)$p['ownerid'].'">';
        foreach ($p['categoryids'] as $cat) echo '<input type="hidden" name="categoryids[]" value="'.(int)$cat.'">';
        echo '</form>';
        if ($p['error']) echo '<div class="alert alert-error">'.$esc($p['error']).'</div>';
        elseif ($p['query']!=='' && !$p['result']['hits']) echo '<div class="alert alert-info">No matching accessible documents in this batch. Check indexing status, try another term, or continue if more results are available.</div>';
        foreach ($p['result']['hits'] as $hit) {
            $url='../out/out.ViewDocument.php?documentid='.(int)$hit['id'];
            echo '<article class="well fulltext-hit"><h3><a href="'.$url.'">'.$esc($hit['name']).'</a></h3><p class="muted">Version '.(int)$hit['version'].($hit['snippet']['page']?' · Page '.(int)$hit['snippet']['page']:'').'</p>';
            echo '<p>'.LetoDMS_Fulltext::highlight($hit['snippet']['text'],$p['result']['words']).'</p></article>';
        }
        if ($p['result']['next']!==null) {
            $args=['query'=>$p['query'],'after'=>$p['result']['next'],'folderid'=>$p['folderid'],'ownerid'=>$p['ownerid'],'categoryids'=>$p['categoryids']];
            echo '<a class="btn" href="../op/op.SearchFulltext.php?'.$esc(http_build_query($args)).'">More results</a>';
        }
        if ($p['user']->isAdmin()) echo '<p><a href="../out/out.Fulltext.php">Indexing dashboard</a></p>';
        $this->contentContainerEnd(); $this->htmlEndPage();
    }
}
