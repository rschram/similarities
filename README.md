**READ ME** and hear my tale of a half-finished plugin for Dokuwiki that
identifies similar pages in a wiki...

This is my first attempt to create code to be shared. While this
plugin works for me, it may not for you. There are no
guarantees. Also, I'm new to sharing code on Github so this may not be
set up in a form you can use. While I welcome suggestions and
feedback, I can't promise anything.

By the same token, if you want to fork this repository and use it as a
starting point for your own page-similarity plugin, by all means do
so. As you can see, I make use of a cosine similarity algorithm
written by someone else. More experienced developers will have much
better chances of making something more generally useful and
efficient, and so my goal of having a page-recommendation enging for
DW is still served by people branching off and doing their own things.

The purpose of this plugin for Dokuwiki is two-fold:

(1) It creates a matrix of pages as TFIDF vectors and for each page,
    uses this matrix to calculate the degree of similarity for each
    page with every other page (i.e. the cosine similarity of the two
    pages as TFIDF vectors), finds the intersection of terms and their
    TFIDF weights for each pair of pages, and stores this for later
    use;
    
(2) It provides a tag that inserts a list of a page's top six most
    similar pages and for each, the terms shared between the current
    page and these similar target pages sorted by their prominence
    (highest TFIDF) on each target page. This is displayed as a header
    and nested bullet list in Markdown text (and hence this plugin is
    not fully compatible with Dokuwiki versions after Greebo as of
    July 2021, since there is no working Markdown plugin that is
    compatible with later versions).

The plugin has been tested on Dokuwiki versions Greebo and
Hogfather. While it executes without error on these, it has not been
thoroughly tested. Since my site runs Greebo and I have no immediate
plans to upgrade, I won't be testing further on Hogfather for now. 

Moreover there are several design problems. For one, to run at all,
the site needs to be completely reindexed twice so that the TFIDF
matrix is complete and correct when the most recent index is
updated. Also, I have only tested or used this plugin on a wiki of
about 300 pages. The resulting index of TFIDF vectors was 2 Mb, and so
larger wiki would generate larger indexes. This matrix must be updated
every time the index is updated, e.g. when a page is added or
edited. This software will read the whole TFIDF matrix into
memory. (Insertion of the results list accesses the page metadata of
an individual article using built-in Dokuwiki functions.)

You can see this version of the plugin in use at
http://anthro.rschram.org, my site for teaching resources.

Thanks for reading me, and best wishes,  
Ryan Schram  
July 9, 2021  
