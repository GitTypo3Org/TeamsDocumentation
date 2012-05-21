TYPO3 DocTEAM vim script conversion tools

Author: Sylvain Viart (sylvain at ledragon.net)
Date: Sunday, November 07 2004 - 09:28:00 CET
Last Modified: $Date: 2004/11/10 08:43:15 $
Version: 0.1

Thoses scripts have been developed to convert HTML documents available on
typo3.org into MediaWiki Syntax.

* import_full_doc_typo3.vim
* typo3_2_wiki.vim

= How to use it =

There are 2 scripts:
- import_full_doc_typo3.vim: For processing a full document which is spread in multiple
HTML pages on typo3.org.
- typo3_2_wiki.vim: Dooes the Wiki conversion on only one HTML document.

== Using import_full_doc_typo3.vim ==

* create a new folder, and cd to it.
* start vim, with no document open.
* Open the remote TOC document with vim, for example:
** :Nread http://typo3.org/documentation/document-library/doc_tut_backend/
** !!  You will need a working Network support for vim (needs wget). !!
* alternatively you can copy the HTML source manually into Vim.
* source (execute) the script, (:so import_full_doc_typo3.vim).
** this script needs typo3_2_wiki.vim too. It must be located in the same folder.
* this will generate a new file named import_URL in the current vim folder
* run that file with the command line interpreter
**  Linux:   sh import_URL
**  Windows:
***          ren import_URL *.bat
***          import_URL
**  Vim:     :!sh %
* go back in import_URL vim buffer and hit the key: Q
** If you have closed vim, no problem.
** just reopen import_URL with vim and execute these 2 vim commands:
*** :let skip_fetch = 1
*** :so import_full_doc_typo3.vim
* stand by, and see the magic to achieve itself. ;-)
** Vim will display a lot of messages and warning errors; it's normal.
* At the end of the conversion you will have 2 windows in Vim.
** one with import_URL, which now only contains section names.
** and a 'document' window which holds the whole wiki-converted document.
* Copy and paste the result where ever you want in the wiki. :-)
* Have fun!
