" typo3_2_wiki.vim
"
" Author: Sylvain Viart  <sylvain (at) ledragon.net>
" Date: August 2004
" Vim Version: 6.2
"
" Convert an HTML document from typo3.org to MediaWiki text format
"
" How to use:
" Copy the HTML source content from typo3.org into vim
" source the script, (:so typo3_2_wiki.vim)
" Copy and paste the result where you want in the wiki :-)
"
" Unsupported formating:
"  Some formating are not supported by this script, because I've not handled
"  them in this code. Here is the list of formating which are removed by the
"  conversion process:
"   - nested list
"   - text coloring
"   - css class or rendering
"

" ==================================== Actions 
" unindent content
%s@^\s\+@@
" remove DOS CR if any (hex = 0d, dec= 13)
exe "%s@\x0d@@ge"

" ignoring case
set ic
" disable search wrapping the end of the buffer to the beginning
set nows

" ================================= removing unwanted content
" remove HTML header
1|1,/<body/ d
" remove anything from the beginning to the start of the page content
1|1,/<div id="contentarea"/ d 

" ======================================= SXW top bottom section
" remove bottom navigation for SXW imported document
exe "norm 1G/<p class=\"tx-extrepmgm-pi1-addcomment\">\nvG$x"
" remove top navigation for SXW imported document
exe "norm 1Gv/div class=\"tx-extrepmgm-pi1-cnt\"/\nf>x"

" ========================================== HTML conversion
" During the conversion some wiki tag are converted to /TAG/ and later when
" all HTML are removed from the buffer they are changed back to their HTML
" equivalent.
" TODO: fix header level to be related to header numbering level
"%s@<h[0-9][^>]*>[0-9.]\+ 
" converting HTML heading into wiki heading
%s@<h\([0-9]\)[^>]*>\(.\{-}\)</h[0-9]>@/EQUAL/\1 \2 /EQUAL/\1 @g
%s@/EQUAL/1 @= @ge
%s@/EQUAL/2 @== @ge
%s@/EQUAL/3 @=== @ge
%s@/EQUAL/4 @==== @ge
%s@/EQUAL/5 @===== @ge
%s@/EQUAL/6 @====== @ge
%s@/EQUAL/7 @======= @ge
%s@/EQUAL/8 @======== @ge
%s@/EQUAL/9 @========= @ge
" ensure that section header are on a single line (exe because of the CR)
" Could have problem with header level 1 (one = character) but not handled by
" this rules.
exe '%s@\([^=]\)\(=\{2,} [^=]\+=\{2,}\)@\1'."\<CR>".'\2@ge'
exe '%s@^\(=\{2,} [^=]\+=\{2,}\)\([^=]\)@\1'."\<CR>".'\2@ge'
" remove trailing title space
%s@= $@=@ge
" remove numering in section header ex: 1.2 or 1)
" TODO: could be used to adjust heder level, because they're not at section
" level in subpage a TOC H3 level if alone in the page will be presented as a
" HTML <h2> content.
%s@^\(=\+ \)[0-9.)]\+ \=\([^=]\+\)@\1\2@e

" Keeping annotation, if annotation (SXW) are present split the code if any
%s@<p class="code"><strong>Code Listing:</strong></p>@\r/PARA/\r&@ge

" change italic tag
%s@<\/\=\(i\|em\)>@''@ge
" change bold tag
%s@<\/\=\(b\|strong\)>@'''@ge

" make unordered list
%s@<li>@* @ge
%s@</li>@@ge

" convert link to wiki link
" replace square bracket by URL encoded value, in URL
%s@\(href="[^"]*\)\[\([^"]*"\)@\1%5b\2@ge
%s@\(href="[^"]*\)]\([^"]*"\)@\1%5d\2@ge
" replace square bracket, in text link, with double quote
%s@\(<a[^>]\+>[^[]\{-}\)[[]\([^<]*</a>\)@\1"\2@ge
%s@\(<a[^>]\+>[^\]]\{-}\)[\]]\([^<]*</a>\)@\1"\2@ge
" dirty patch, to match it to times :-\
%s@\(<a[^>]\+>[^[]\{-}\)[[]\([^<]*</a>\)@\1"\2@ge
%s@\(<a[^>]\+>[^\]]\{-}\)[\]]\([^<]*</a>\)@\1"\2@ge
" match URL absolute link
%s@<a href="\(http://[^"]*\)"[^>]*>\([^<]*\)</a>@[\1 \2]@ge
" match other URL
%s@<a href="\([^"]*\)"[^>]*>\([^<]*\)</a>@[http://typo3.org/\1 \2]@ge
" correct some spacing error on wiki link
%s@ ]@] @ge

" image processing
" remove clear.gif
%s@<img src="clear\.gif"[^>]*>@@ge

" replace <img>-tag
%s@<img src="\([^"]*\)"[^>]*>@http://typo3.org/\1@ge

" replace <br>
"exe '%s@<br>@'."\<CR>".'@ge'
%s@<br>@/BR/@ge

" make ordered list
let @q = "/<ol>\nv/<\\/ol>\n:s@\\(<ol>\\)\\=\\*@#@ge\n@q"
norm 1G@q

" handling of preformated text
" match the block and convert it in wiki indented text, See Conv_pre() bellow
let @q = "/<pre style=\nms" .
	\"/<\\/pre>\\n\\=<\\(pre\\)\\@!\nme:call Conv_pre()\n@q"

function! Conv_pre()
	" be carefull not to remove the mark, should be ok, no line removing
	's,'es@&nbsp;@/NBSP/@ge
	's,'es@<span style="color: \(#\x\+\);">@/TAGLT/font color="\1"/TAGGT/@ge
	's,'es@</span>@/TAGLT//font/TAGGT/@ge
	"'s,'es@$@/TAGLT/br //TAGGT/@e
	's,'es@<pre[^>]*>@@ge
	's,'es@</pre>@@ge
	"exe "norm `sO/TAGLT/tt/TAGGT/\n\e`eo/TAGLT//tt/TAGGT/\e"
	's,'es@^@/PRE/@ge
endf
norm 1G@q

" keep some paragraph break
%s@^<p class="tx-oodocs-TB">&nbsp;</p>$@/PARA/@
%s@\(</p>\n\=\)\(<p class="tx-oodocs-TB">\)@\1/PARA/\r\2@

" Keep some TABLE tag
%s@<\(\/\=\(table\|tr\|th\|td\)\( [^>]\+\)\=\)>@/TAGLT/\1/TAGGT/@ge
" activate table border
%s@/TAGLT/table border=0@/TAGLT/table border=1@ge

" ================================================= Start removing tags
" remove <p>-tag
%s@<\/\=p[^>]*>@@ge

" !!!!!!!!!!!!!!!!! remove any HTML tag left
" remove other tag 
%s@</\=[^>]*>@TTTT@ge
" remove full line tag replaced by TTTT previously
g@^T\{4,}$@ d
" finally remove TTTT mark
%s@TTTT@@ge


" convert wrong html entities
%s@&nbsp\([^;]\|$\)@&;\1@ge 
" convert some html entities
%s@&nbsp;@ @ge 
%s@&amp;@\&@ge 
%s@&quot;@"@ge 
%s@&copy;@©@ge 
%s@&lt;@<@ge 
%s@&gt;@>@ge 

" remove line containing only space
g/^\s\+$/ d

" remove unwanted space
%s@\s\+$@@e 
%s@^\s\+@@e 

" rewrite tag
%s@/BR/@<br />@ge
%s@/TAGLT/@<@ge
%s@/TAGGT/@>@ge
%s@/NBSP/@\&nbsp;@ge

" insert a line break, which is a wiki paragraph, after a word [A-Za-z]
" followed by a colon at the end of a line. Secial condition, the text should
" not be in /PRE/ mode, nor prefixed by a list marker
let @q = '/^\(\/PRE\/\|\*\)\@!.*\a:$'."\nA\n\e@q"
norm 1G@q
%s@/PARA/@@ge

" convert /PRE/ text
%s@/PRE/@ @ge

set noic
" correct TYPO3 case spelling
" Match: Typo3 not followed by .org or [0-9A-Za-z_] but could be followed
" by a dot and a word boundary.
" Case is respected.
%s@Typo3\(\(\.org\|\i\)\@!\|\.\>\)@TYPO3@g
