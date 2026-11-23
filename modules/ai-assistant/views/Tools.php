<?php
require_once __DIR__.'/../../../views/bootstrap/class.Bootstrap.php';
class LetoDMS_Module_View_AITools extends LetoDMS_Bootstrap_Style {
    private function escape($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
    private function start($action) {
        echo '<form class="well" method="post" action="../op/op.Module.php?module=ai-assistant">'.createHiddenFieldWithKey('ai-assistant').'<input type="hidden" name="action" value="'.$action.'">';
    }
    private function provider() {
        echo '<label>Answer provider <select name="provider" required>';
        foreach ($this->params['providers'] as $id => $label) echo '<option value="'.$this->escape($id).'">'.$this->escape($label).'</option>';
        echo '</select></label>';
    }
    private function finish($label) {
        echo '<button class="btn btn-primary" type="submit">'.$label.'</button></form>';
    }
    private function source($source, $number) {
        $base = '../out/out.Module.php?module=ai-assistant&amp;passage=1&amp;documentid='.$source['document_id'].'&amp;version='.$source['version'].'&amp;page='.$source['page'];
        echo '<div class="well" id="ai-source-'.$number.'"><strong>['.$number.'] <a href="'.$base.'">'.$this->escape($source['name']).' — version '.$source['version'].($source['page'] ? ', page '.$source['page'] : '').'</a></strong> <a href="../out/out.ViewDocument.php?documentid='.$source['document_id'].'">Document</a><p style="white-space:pre-wrap">'.$this->escape(mb_strcut($source['text'], 0, 1600, 'UTF-8')).'</p></div>';
    }
    public function show() {
        $quick = !empty($this->params['quick_search']);
        $this->htmlStartPage($quick ? 'Search results' : 'AI search and document tools'); $this->globalNavigation(); $this->contentStart(); $this->contentHeading($quick ? 'Search results' : 'AI search and document tools');
        if ($quick) echo '<p>AI search results for <strong>'.$this->escape($this->params['searchquery']).'</strong> · <a href="../op/op.Search.php?navBar=1&amp;searchin[]=1&amp;searchin[]=2&amp;searchin[]=3&amp;query='.rawurlencode($this->params['searchquery']).'">Search document names and metadata instead</a></p>';
        else echo '<p>Open a document to ask questions, read summaries, or compare versions.</p>';
        if ($this->params['message']) echo '<div class="alert">'.$this->escape($this->params['message']).'</div>';
        if ($this->params['result']) {
            $result = $this->params['result'];
            echo !empty($this->params['source_view']) ? '<h3>Extracted source text</h3>' : '<h3>Result</h3><p>Review AI answers against the linked sources.</p>';
            echo '<div class="well" style="white-space:pre-wrap">';
            $answer = $this->escape($result['answer']);
            $answer = preg_replace_callback('/\[(\d+)\]/', function ($match) use ($result) {
                return isset($result['sources'][(int)$match[1]]) ? '<a href="#ai-source-'.(int)$match[1].'">'.$match[0].'</a>' : $match[0];
            }, $answer);
            echo $answer.'</div>';
            foreach ($result['sources'] as $number => $source) $this->source($source, $number);
        }
        foreach ($this->params['matches'] as $i => $source) $this->source($source, $i + 1);
        if ($quick) { $this->contentEnd(); $this->htmlEndPage(); return; }
        $this->contentEnd(); $this->htmlEndPage();
    }
}
