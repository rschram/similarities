<?

use dokuwiki\Search\Indexer;


// must be run within Dokuwiki
if (!defined('DOKU_INC')) {
    die();
}

class action_plugin_similarities_pageSimilarities extends DokuWiki_Action_Plugin
{


    public function register(Doku_Event_Handler $controller)
    {
        /*
          The action component of this plugin hooks into three events.
          When the index is updated, it calls a function to compute
          the TFIDFs of every term in every document and stores
          this. When the Indexer runs on a page, it uses this TFIDF
          matrix to calculate the page's similarities with all other
          pages. It also strips some information from the text to be
          indexed.
         */
        $controller->register_hook('INDEXER_PAGE_ADD', 'AFTER', $this, 'handle_compute_tfidfs');
        $controller->register_hook('INDEXER_TASKS_RUN', 'AFTER', $this, 'handle_compute_page_similarities');
        $controller->register_hook('INDEXER_TEXT_PREPARE', 'AFTER', $this, 'handle_text_prepare');
    }

    public $Indexer;
    public $wordlistdir;
    public $TD;
    public $pageNames; 
    
    public function __construct() {

        global $conf;
        global $_SERVER;
        if (!defined($_SERVER['DOCUMENT_ROOT']))
           { $root = '/Users/ryanschram/Desktop/dokuwiki/greebo'; }
        
        $this->wordlistdir = $root . "/inc/lang/" . $conf['lang'];
        $this->TD          = $this->numRecs($conf['indexdir'] . '/page.idx');
        $this->pageNames   = file($conf['indexdir'] . '/page.idx');

        if (is_callable('dokuwiki\Search\Indexer::getInstance')) {
            $this->Indexer = Indexer::getInstance();
        } elseif (class_exists('Doku_Indexer')) {
            $this->Indexer = idx_get_indexer();
        } else {
            // Failed to clear index. Your DokuWiki is older than
            // release 2011-05-25 "Rincewind"
            exit;
        }

    }

    public function handle_compute_tfidfs(Doku_Event $event, $param)
        /* 
           Collects the i<n>.idx files and calls a function to compute
           the TFIDF value of each indexed term in each document in
           which it appears
        */ 
    {
        
        global $conf;
        $idx_files = glob($conf['indexdir'] . "/i*.idx");

        $event->preventDefault();
        $event->stopPropagation();
        
        $tfidf = $this->getTFIDFs($idx_files);

        file_put_contents($conf['indexdir'] . '/tfidf.idx', serialize($tfidf));
        
    }
    public function handle_compute_page_similarities(Doku_Event $event, $param)
    {
        /*
          Accesses the TFIDF matrix to compare the current page to all
          other pages, and then for each, identify the intersection of
          their terms. A list of all pages and their "cosine
          similarity" to the current page, and the intersecting terms
          and the TFIDF weight of each on the compared page are saved
          in the current page's metadata.
         */
        global $conf;
        global $ID; 
        $page1id = $ID; 

        $tfidf = unserialize(file_get_contents($conf['indexdir'] . '/tfidf.idx'));
        $pageSimilarities['similarities']['similarpages'] = $this->getSimilarPages($page1id,$tfidf[$page1id],$tfidf);
        foreach ($pageSimilarities['similarities']['similarpages'] as $page2id => $costheta) {
            $keywords = $this->getSharedKeywords($page1id,$page2id,$tfidf);
            $pageSimilarities['similarities']['similarpages'][$page2id]['keywords'] = $keywords;
        }
        p_set_metadata($page1id,$pageSimilarities);
        
    }
    public function handle_text_prepare(Doku_Event $event, $param)
    {
        /* 
           Strips angle-bracket tags several patterns from a page's
           text before it is tokenized for indexing to avoid finding
           high TFIDFs for single letters (s and t in contractions) or
           unnecessary metatext.
         */ 
        $patterns = array(
            '/[~\{]{2}[^\s]+[~\}]{2}/',  // wiki directives
            '/http[^\s]+/',              // URLs
            '/[^\s\@]+\@[^\s\@]+/',      // email addresses
            '/<[^>]+>/',                 // Angle-bracket tags
            "/'/",                       // apostrophes
            '/\-/'                       // hyphens
        );
        
        $event->data = preg_replace($patterns,'',$event->data);

    }


    
    public function getTFIDFs($files) {
        /* 
           For each `i<n>.idx`, capture the line number (from 0), 
           explode each line by `:` into `PID*<frequency>` pairs, and 
           explode. The line is $termid, PID is $page, the number of pairs 
           is the raw doc frequency ($df) (and log($TD/$df) is the $idf 
           for a term). $rtf is the raw frequency. 
        */
        $rtfidf = array();
        $tfidf  = array();
        $pagetf = array(); 

        foreach ($files as $file) {
            $TD = $this->TD;
            global $conf; 
            preg_match('|/i([0-9]+).idx|',$file,$matches);
            $n = $matches[1];
            $wordfile = $conf['indexdir'] . "/w$n.idx";
            $ln = 0;

            $idx = file($file);
            $terms = file($wordfile); 
            foreach($idx as $i => $line){
                $pairs = explode(':',$line);
                $df = count($pairs);                
                $idf = log($TD/$df);
                $term = chop($terms[$i]);       
                $gram = count(explode(' ',$term)); 
                foreach ($pairs as $pair) {
                    list($pid,$rtf) = explode('*',chop($pair));
                    $page = $this->Indexer->getPageFromPID($pid); 
                    $rtfidf[$page][$gram][$term] = $rtf * $idf; // To be divided by total words in $page.
                }
            
                $ln++; 
            }
        }


        foreach(array_keys($rtfidf) as $page) {
            $pagetf[$page] = count(array_keys($rtfidf[$page]));
        }
        foreach($pagetf as $page => $tw) {
            foreach($rtfidf[$page] as $gram => $terms) {
                foreach($terms as $term => $val) {
                    $tfidf[$page][$gram][$term] = $val / $tw;

                }
            }
        }
        return $tfidf;
        
    }



    public function getSimilarPages($page1id,$page1,$tfidf) {
        /* 
           Calls the function cosineSimilarity for each pair of pages
           as vectors of TFIDF values and stores the cosine of theta,
           the angle between the two vectors, a measure of similarity
           between 1 (identical) and 0 (no shared terms).
        */ 
        $results = array();
        $source = $page1[1]; 
        foreach ($tfidf as $page2id => $page2) {
            $target = $page2[1];
            if ($page1id !== $page2id && sizeof($source) > 0 && sizeof($target) > 0) { 
                $costheta = $this->cosineSimilarity($source,$target);
                $results[$page2id]['costheta'] = $costheta;         
            }
        }
        return $results; 
    }

    public function getSharedKeywords($page1id,$page2id,$tfidf) {
        $source = $tfidf[$page1id][1];
        $target = $tfidf[$page2id][1]; 
        $results = $this->findKeywords($source,$target);
        return $results; 
    }




    public function cosineSimilarity(&$tokensA, &$tokensB) { 
        /* 
           This algorithm for calculating the cosine similarity of two
           TFIDF vectors was written by Ahmet Yildirim (Github:
           RnDeveloper) and is available at
           <https://github.com/RnDevelover/SimpleCosineSimilarityPHP>. It
           constructs a sparse matrix of terms in order to perform matrix
           multiplication.
        */
        $a = $b = $c = 0;
        $uniqueTokensA = $uniqueTokensB = array();
        $uniqueMergedTokens = array_merge($tokensA, $tokensB);
        foreach ($tokensA as $token=>$val) $uniqueTokensA[$token] = $val;
        foreach ($tokensB as $token=>$val) $uniqueTokensB[$token] = $val;
        $x2=0;
        $y2=0;
        $xArray=array();
        $yArray=array();
        $address=0;
        foreach ($uniqueMergedTokens as $token=>$v) {
            $xArray[$address] = isset($tokensA[$token]) ?  $tokensA[$token]: 0;
            $yArray[$address] = isset($tokensB[$token]) ?  $tokensB[$token]: 0;
            $x2+=$xArray[$address]*$xArray[$address];
            $y2+=$yArray[$address]*$yArray[$address];
            $address++;
        }
        $x2=sqrt($x2);
        $y2=sqrt($y2);
        for($k=0;$k<$address;$k++)
        {
            $xArray[$k]/=$x2;
            $yArray[$k]/=$y2;
            $a+=$xArray[$k]*$yArray[$k];
            $b+=$xArray[$k]*$xArray[$k];
            $c+=$yArray[$k]*$yArray[$k];
        }
        return $b * $c != 0 ? $a / sqrt($b * $c) : 0;
    }


    public function findKeywords(&$source,&$target) {
        /*
          Taking off from Yildirim's cosineSimilarity(), this function
          identifies the intersection of terms in a pair of TFIDF
          vectors and returns a list of terms and their TFIDF on the
          target page.
         */ 
        if ($source && $target) { 
            $keywords= array(); 
            $uniqueSharedTokens = array_intersect(array_keys($source),array_keys($target));
            foreach ($uniqueSharedTokens as $token) { $keywords[$token] = $target[$token]; }
            return $keywords;
        }
    }


    public function numRecs($file) {
        /* 
           Get the total number of documents in the corpus, each 
           listed on a separate line in `page.idx`. (The line number 
           starting from zero is the ID of each page in other index 
           files. Hence [0..$td-1] is the range of page IDs.) 
        */
        $TD = count(file($file));
        return $TD;
    }

}

?>
