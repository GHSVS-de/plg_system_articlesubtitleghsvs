<?php
defined('_JEXEC') or die;

$Autoren = implode(', ', $this->AutorenNames);
$zitierweise = array();
$zitierweise[] = $this->Titel;
if ($Autoren)
{
	$zitierweise[] = $Autoren; 
}
$zitierweise[] = $this->Datum;
$zitierweise[] = $this->Link;
?>
 <div class="div4autorbeschreibung alert">

		<a name="zitierweise" id="zitierweise"></a>
		
  <h6>Verpflichtende Zitierweise zum Artikel</h6>
		<p class="p4zitierweise"><?php echo implode('; ', $zitierweise)?></em></p>
		<h6>Nutzungsrechte / Urheberrechte</h6>
		<p>
   Beachten Sie die Rechte des / der Urheber!
			Wenn Sie Artikel übernehmen wollen, fragen Sie nach!
			About Africa leitet Ihre Anfrage dann gerne an die/den Urheber weiter.
		</p>
		<p>
		 Bei korrekter Zitierweise ist die Übernahme von kleineren TEXT-Ausschnitten ohne Rückfrage erlaubt.
		</p>
		<p>
   <strong class="alert-danger">
    Bilder und andere multimediale Inhalte bedürfen immer
				der Freigabe durch den/die Urheber.
   </strong>
		</p>
  <h6>Disclaimer</h6>
		<p><strong>Viele Autoren, viele Meinungen! about-africa.de ist nicht verantwortlich für Richtigkeit der angezeigten Inhalte.</strong> Wir entfernen natürlich Falsches oder kommentieren im Text, wenn etwas zu hinterfragen ist, jedoch nur soweit wir es beurteilen können oder uns widersprüchliche Ansichten bekannt sind. Wir sind keine Fachleute und sind nicht in der Lage, Fachwissen im Detail auf Richtigkeit zu prüfen. Wir sind jederzeit bereit, Gegenreden zu veröffentlichen.</p>
 </div><!--/div4autorbeschreibung-->
