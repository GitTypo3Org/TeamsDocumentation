<?php
/**
 * Diese Funktion wird von der Suche aufgerufen und leitet dann via header-redirect
 * zu der richtigen Seite weiter.
 *
 */

// Je nach Suche wir der Sprachparameter $lang mit übergeben.
// davon abhängig werden dann manche Einstellungen geändert
$lang = '';
if (isset($_GET['lang']) && strlen($_GET['lang']) > 0) { $lang = $_GET['lang']; }
$search = $_GET['search'];

// z.B. TEXT.stdWrap.case
// Vermutlich ist das letzte Element eine Eigenschaft
if (strpos($search,'.') !== false) {
	$arr = explode('.',$search);
	array_pop($arr);
	$search = array_pop($arr);
}

switch ($lang) {
	case 'de':
		$lang = 'De:';
		$langParams['leo'] = '&lang=en';
		$langParams['domain'] = 'de';
	break;	
	default:
		$langParams['leo'] = '&lang=de';
		$langParams['domain'] = 'com';
		$lang = '';
	break;
}

$keywords = array();
include_once('keywords.inc');

$foundInKeywords = false;
foreach ($keywords as $k => $v) {
	if (is_array($v)) {
		foreach ($v as $alias => $target) {
			if (strtolower($alias) == strtolower($search)) { 
				$search = $target; 
				$foundInKeywords = true;
				break 2;
			}
			
		}
	} else {
		if (strtolower($v) == strtolower($search)) { 
			$search = $v; 
			$foundInKeywords = true;
			break 1;
		}
	}
}
// wenn nichts anderes angegeben ist, dann im Wiki suchen
$url = 'http://wiki.typo3.org/'.$lang.'index.php?search='.$search;
// nur wenn der Begriff in der keywords liste auftaucht, dann direkt zur TSref
if ($foundInKeywords) { $url = 'http://wiki.typo3.org/'.$lang.'TSref/'.$search; }

// Extension suche:
// ?tx_terfe_pi1[sword]=
if (substr(strtolower($search),0,4) === 'ext ') {
	$url = 'http://typo3.org/extensions/repository/search/?tx_terfe_pi1[sword]='.strtolower(substr($search,4));
	// $url = 'http://typo3.org/extensions/repository/view/'.strtolower(substr($search,4)).'/';
} elseif (substr(strtolower($search),0,3) === 'mm ') {
	// http://www.typo3.net/index.php?id=185&mmfsearch[searchstring]=tsref
	$url = 'http://www.typo3.net/index.php?id=185&mmfsearch[searchstring]='.strtolower(substr($search,3));
} elseif (substr(strtolower($search),0,5) === 'wiki ') {
	$url = 'http://wiki.typo3.org/'.$lang.'index.php?search='.substr($search,5);
} elseif (substr(strtolower($search),0,6) === 'forge ') {
	$url = 'http://forge.typo3.org/search?q='.strtolower(substr($search,6));
} elseif (substr(strtolower($search),0,4) === 'api ') {
	$url = 'http://typo3.org/fileadmin/typo3api-4.0.0/search.php?query='.strtolower(substr($search,4));
} elseif (substr(strtolower($search),0,4) === 'leo ') {
	$url = 'http://dict.leo.org/?search='.strtolower(substr($search,4));
	$url .= $langParams['leo'];
} elseif (substr(strtolower($search),0,4) === 'wec ') {
	$url = 'http://webempoweredchurch.com/search/?tx_indexedsearch[sword]='.substr($search,4);
} elseif (substr(strtolower($search),0,4) === 'php ') {
	$url = 'http://www.php.net/'.substr($search,4);
} elseif (substr(strtolower($search),0,3) === 'gg ') {
	$url = 'http://www.google.'.$langParams['domain'].'/search?q='.strtolower(substr($search,3));
} elseif (substr(strtolower($search),0,8) === 'comment ') { 
	// comment - Kamikaze - keine Ahnung ob das immer funktionieren kann.
	$fp = fopen('fileadmin/comment.txt','a');
	fwrite($fp, ''.date('Y.m.d. H:i:s').';'.$url.';'.$_GET['lang'].';'.substr($search,0,8)."\r\n");
	fclose($fp);
	$url = 'http://wiki.typo3.org/'.$lang.'OpenSearch#discussion';
}

$fp = fopen('fileadmin/jumpto.txt','a');
fwrite($fp, ''.date('Y.m.d. H:i:s').';'.$_SERVER['REMOTE_ADDR'].';'.$url.';'.$_GET['lang'].';'.$_GET['search']."\r\n");
fclose($fp);

header('Location: '.$url);
exit();

/*
stdWrap
stdWrap.setContentToCurrent
stdWrap.setCurrent
stdWrap.lang
stdWrap.data
stdWrap.field
stdWrap.current
stdWrap.cObject
stdWrap.numRows
stdWrap.filelist
stdWrap.preUserFunc
stdWrap.override
stdWrap.preIfEmptyListNum
stdWrap.ifEmpty
stdWrap.ifBlank
stdWrap.listNum
stdWrap.trim
stdWrap.stdWrap
stdWrap.required
stdWrap.if
stdWrap.fieldRequired
stdWrap.csConv
stdWrap.parseFunc
stdWrap.HTMLparser
stdWrap.split
stdWrap.prioriCalc
stdWrap.char
stdWrap.intval
stdWrap.date
stdWrap.strftime
stdWrap.age
stdWrap.case
stdWrap.bytes
stdWrap.substring
stdWrap.removeBadHTML
stdWrap.stripHtml
stdWrap.crop
stdWrap.rawUrlEncode
stdWrap.htmlSpecialChars
stdWrap.doubleBrTag
stdWrap.br
stdWrap.brTag
stdWrap.encapsLines
stdWrap.keywords
stdWrap.innerWrap
stdWrap.innerWrap2
stdWrap.fontTag
stdWrap.addParams
stdWrap.textStyle
stdWrap.tableStyle
stdWrap.filelink
stdWrap.preCObject
stdWrap.postCObject
stdWrap.wrapAlign
stdWrap.typolink
stdWrap.TCAselectItem
stdWrap.spaceBefore
stdWrap.spaceAfter
stdWrap.space
stdWrap.wrap
stdWrap.noTrimWrap
stdWrap.wrap2
stdWrap.dataWrap
stdWrap.prepend
stdWrap.append
stdWrap.wrap3
stdWrap.outerWrap
stdWrap.insertData
stdWrap.offsetWrap
stdWrap.postUserFunc
stdWrap.postUserFuncInt
stdWrap.prefixComment
stdWrap.editIcons
stdWrap.editPanel
stdWrap.debug
stdWrap.debugFunc
stdWrap.debugData
imgResource
imageLinkWrap
numRows
select
split
if
typolink
textStyle
encapsLines
tableStyle
addParams
filelink
parseFunc
makelinks
tags
HTMLparser
HTMLparser_tags
HTML
TEXT
COA
COA_INT
COBJ_ARRAY
FILE
IMAGE
IMG_RESOURCE
CLEARGIF
CONTENT
RECORDS
HMENU
CTABLE
OTABLE
COLUMNS
HRULER
IMGTEXT
CASE
LOAD_REGISTER
RESTORE_REGISTER
FORM
SEARCHRESULT
USER
USER_INT
PHP_SCRIPT_INT
PHP_SCRIPT_EXT
TEMPLATE
MULTIMEDIA
EDITPANEL

*/


?>