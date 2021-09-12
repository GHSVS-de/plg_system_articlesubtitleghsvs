<?php
defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\Utilities\ArrayHelper;
use Joomla\CMS\Uri\Uri;

/**
*
* $article
*/

extract($displayData);

if (empty($article->autorenaliase))
{
	return;
}

$autorbeschreibung_active = !empty($article->autorbeschreibung_active);
$zitierweise_active = !empty($article->zitierweise_active);
?>
<?php
if ($autorbeschreibung_active || $zitierweise_active)
{ ?>
<div class="autorZitierweise">
	<?php
	if ($autorbeschreibung_active)
	{ ?>
	<div class="alert alert-info">
		<h3><?php echo (count($article->autorenaliase) > 1 ? 'Autoren' : 'Autor'); ?></h3>
		<ul>
			<?php
			foreach ($article->autorenaliase as $autor)
			{ ?>
				<li>
					<?php echo (($autor->name ?: $autor->alias)); ?>
					<?php echo ($autor->webpage ? ' (' .
						HTMLHelper::_('link', $autor->webpage, $autor->webpage) . ')' : '');
					?>
				</li>
			<?php
			}
			?>
		</ul>
	</div>
	<?php
	} ?>
	<?php
	if ($zitierweise_active)
	{
		$zitierweise = implode('; ',
		[
			$article->title,
			$article->AutorenNamesConcated,
			HTMLHelper::_('date', $article->created, 'Y'),
			Uri::getInstance()->current()
		]);
	?>
	<div class="alert alert-warning">
		<h3>Verpflichtende Zitierweise und Urheberrechte</h3>
		<ul>
			<li>Nennung der Quelle: <i><?php echo $zitierweise; ?></i></li>
			<li>Beachten Sie die Rechte des/der Urheber! Wenn Sie größere Teile von
			Artikeln übernehmen	wollen, fragen Sie zuvor nach!</li>
			<li class=alert-danger>Bilder und andere multimediale Inhalte bedürfen immer
			der Freigabe durch den/die Urheber.</li>
		</ul>
	</div>
	<?php
	} ?>
</div><!--/autorZitierweise-->
<?php
}
