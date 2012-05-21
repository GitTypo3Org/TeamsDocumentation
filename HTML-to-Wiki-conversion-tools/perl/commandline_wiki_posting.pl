#!/usr/bin/perl
#
# Author: Sebastian Kurfuerst  <sebastian at garbage-group.de>
#         Sylvain Viart (sylvain at ledragon.net)
# Date: Tue Nov 23 19:54:29 2004 UTC
# Modif: $Date: 2004/12/28 10:24:46 $
# Version: 0.1
# 
# This script posts a MediaWiki formated text to a MediaWiki engine.
#
# Usage:
# Reads wiki code from stdin
#
# perl commandline_wiki_posting.pl PAGENAME "comment" < WIKI_CONTENT
# 
# The flag -d can be prepended first to any argument to activate debug output.
# perl commandline_wiki_posting.pl -d PAGENAME "comment" < WIKI_CONTENT
#
# Needed debian package: libwww-perl perl

use HTTP::Request::Common qw(POST);
use LWP::UserAgent;
$ua = new LWP::UserAgent;
use LWP::Simple;

# Fetch command line argument.

if($ARGV[0] eq '-d')
{
	$debug = 1;
	print "debug enabled\n";
	shift @ARGV;
}

if($#ARGV != 1)
{
	print <<EOT;
perl commandline_wiki_posting.pl PAGENAME "comment" < WIKI_CONTENT
EOT
	die("not enough arguments\n");
}

# $page = name of the page to create in the wiki
# $comment = the Wiki comment on page creation
# @contents = array of line read from standard input
($page,$comment) = @ARGV;
@contents = <STDIN>;

# Configuration variable
$wiki_URL = 'http://wiki.typo3.org';

$url = "$wiki_URL/index.php?title=$page&action=submit";
$url_time = "$wiki_URL/index.php?title=$page&action=edit";

# Get time from edit page
# Fetch the Page within the edit form on the Wiki. Which could be empty if the
# page doesn't exist yet. But the wiki should always return a page. In both
# cases.
# You need the date because else the wiki won't resolve conflicts and won't
# accept the page. You really have to use the current date else it
# doesn't work.
$document = get($url_time);
unless (defined $document) {
	# No document returned, existing
	print "ERROR\n";
	exit;
}

# Extract the date (time stamp) from the HTML code.
$document =~ m/(<input type='hidden' value="(.*?)" name="wpEdittime")/;
$date = $2;

print "time fetched: '$date' \n" if $debug;

################################################ sending  form
# Joining input content in one string
$wiki_content = join '', @contents;
my $req = POST $url,
             [ wpTextbox1 => $wiki_content,
				   wpSummary => $comment,
					wpSave => "Save page",
					wpSection => '',
					wpEdittime => $date ];

#print $req->headers->as_string() , "\n";
#print $req->content() ,"\n";					 
if($debug)
{
	$HTML_returned =  $ua->request($req)->as_string;

	# Testing wiki conflict report. The wiki may output a page with the title 
	# Edit conflict: PostingTest - Preview - TYPO3wiki.
	# If we won't match any content, we guess it's just fine.
	if($HTML_returned !~ m@<title>([^<]+)</title>@)
	{
		print "Wiki post successful\n";
	}
	else
	{
		print "$1\n";
	}
}
else
{
	$ua->request($req);
}
