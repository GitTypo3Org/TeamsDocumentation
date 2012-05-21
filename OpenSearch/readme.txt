# "Documentation"

Damit die Suche eingebunden werden kann, muss Sie dem Browser zur Verfügung gestellt werden.

Das geht zum einen über:

<link rel="search" type="application/opensearchdescription+xml" href="http://www.martinholtz.de/tsrefsearch_en.src" title="Add wiki.typo3.org/TSref " />

zum anderen via Javascript:

<script type="text/javascript">
function addSearchEngine(url) {
  try { 
        window.external.AddSearchProvider(url); 
        return false;
  } catch (e) { 
        alert('sorry, works only with FF2 or IE'); 
        return false;
  }
}
</script>


So können unterschiedliche XML-Dateien angeboten werden, z.B. tsrefsearch_de.src.

Dort wird ein Link für die suggestions angeboten. Dort wird eine GET-Anfrage
hingeschickt und ein JSON formatiertes Array zurückgegeben. Theoretisch
gibt es dort auch ausführliche Beschreibungen, aber das habe ich noch nicht
hinbekommen.

Dann gibt es den Link für die eigentliche Suchanfrage, die wird in diesem
Fall vom Script jumpto.php ausgewertet und an die entsprechende Suchmaschine
weitergeleitet.