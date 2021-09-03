<?php
defined('_JEXEC') or die;

$autorbeschr = array();

$headline = count($this->autorenaliase) > 1 ? 'Autoren' : 'Autor';

// 2018-04: Auf Wunsch Barlovic entfernt.
#$headline .= count($this->autorenaliase) > 1 ? ', Macher, Organisatoren, Unterstützer' : '';

foreach ($this->autorenaliase as $autor)
{
	$Name = $Misc = $Image = $Webpage = '';
	
 $Name = ($autor->name ? $autor->name : $autor->alias);
	
	// Wegen DSGVO deaktiviert
	//$Misc = ($autor->misc ? $autor->misc : '<p>[Keine Informationen zum Autor verfügbar]</p>');
	
	if ($autor->image && JFile::exists(JPATH_SITE.'/'.$autor->image))
	{
		$Image = JUri::root().$autor->image;
		// Wegen DSGVO deaktiviert
		// $Image = '<p class="autorImage"><img src="'.$Image.'" alt="'.$this->db->escape($Name).'" /></p>';
	}
	
	if ($autor->webpage)
	{
		$Webpage = '<p><a href="'.$autor->webpage.'" target="_blank">'.$autor->webpage.'</a></p>';
	}
	else
	{
		$Webpage = '';
	}
	
	$autorbeschr[] = '
	<div class="autorItem">
	<h6 class="autorName">'.$Name.'</h6>
	'.$Image.$Misc.$Webpage.'
	</div>';
}
?>
 <div class="div4autorbeschreibung alert">
  <a name="autorbeschr" id="autorbeschr"></a>
  <h6><?php echo $headline;?></h6>
<?php
  echo implode("\n", $autorbeschr);
?>
 </div><!--/div4autorbeschreibung-->
