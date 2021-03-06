<?php
/**
 * DokuWiki Plugin similarities (Syntax Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Ryan Schram <dokuwiki@rschram.org>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) {
    die();
}

class syntax_plugin_similarities_similar extends DokuWiki_Syntax_Plugin
{
     
     
    /**
     * Get the type of syntax this plugin defines.
     *
     * @param none
     * @return String <tt>'substition'</tt> (i.e. 'substitution').
     * @public
     * @static
     */
    function getType(){
        return 'substition';
    }
     
    /**
     * Define how this plugin is handled regarding paragraphs.
     *
     * <p>
     * This method is important for correct XHTML nesting. It returns
     * one of the following values:
     * </p>
     * <dl>
     * <dt>normal</dt><dd>The plugin can be used inside paragraphs.</dd>
     * <dt>block</dt><dd>Open paragraphs need to be closed before
     * plugin output.</dd>
     * <dt>stack</dt><dd>Special case: Plugin wraps other paragraphs.</dd>
     * </dl>
     * @param none
     * @return String <tt>'block'</tt>.
     * @public
     * @static
     */
    function getPType(){
        return 'block';
    }
     
    /**
     * Where to sort in?
     *
     * @param none
     * @return Integer <tt>6</tt>.
     * @public
     * @static
     */
    function getSort(){
        return 100;
    }
     
     
    /**
     * Connect lookup pattern to lexer.
     *
     * @param $aMode String The desired rendermode.
     * @return none
     * @public
     * @see render()
     */
    function connectTo($mode) {
        $this->Lexer->addSpecialPattern('~~SIMILAR~~',$mode,'plugin_similarities_similar');
    }
     
   
    function handle($match, $state, $pos, Doku_Handler $handler) {
        global $ID;
        global $conf;
        $similarpages = p_get_metadata($ID, 'similarities similarpages'); 
        return $similarpages; 
        
    }
     
    function render($mode, Doku_Renderer $renderer, $data) {
        $result = "### Similar pages\n\n";
        if($mode == 'xhtml' && !empty($data)) {
            $results = $data;
            arsort($results);
            foreach (array_slice($results, 0, 6) as $page => $data) {
                if (auth_quickaclcheck($page) >= AUTH_READ) {
                    $result .= "\t* [[:" . $page . "]] (" . round($data['costheta'],4) * 100 . "%)\n";
                    if (!empty($data['keywords'])) {
                        arsort($data['keywords']);
                        foreach (array_slice($data['keywords'], 0, 5) as $page => $score) {
                            $result .= "\t\t- [[:" . $page . "|" . $page . "]] (" . round($score,2) . ")\n";
                        }
                    }  else { next; }
                } else { next; }
            }
            $result .= "\n\n";
            /* code to expire the cached xhtml of page ns:page */ 
            global $ID; 
            $data = array('cache' => 'expire');  // the metadata being added
            $render = false;                              // no need to re-render metadata now
            $persistent = false;                         // this change doesn't need to persist passed the next metadata render.       
            p_set_metadata($ID, $data, $render, $persistent);
        }
        else {
            $result .= "No similar pages found.\n";
        }
        $info = array(); 
        $instructions = p_get_instructions($result); 
        $formatted = p_render('xhtml', $instructions, $info);
        $renderer->doc .= "$formatted";
        return true; 
    }
}



