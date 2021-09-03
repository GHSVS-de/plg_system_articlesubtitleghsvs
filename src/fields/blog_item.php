<?php
/**
com_tags/tag

GHSVS 2015-01-01

Anfangs 1:1 blog_item.php in View featured übernommen. Wegen page-header.
Dann default_item.php aus featured für View Tag umgearbeitet.

*/

defined('_JEXEC') or die;
JHtml::addIncludePath(JPATH_COMPONENT.'/helpers/html');

// Sonst funktionieren die /layouts/.../joomla.content.info_block NICHT
$lang = JFactory::getLanguage();
$lang->load('com_content');

/* "core_" raus aus den Item-Properties 
Wird jetzt im Plugin erledigt
*/

// Mein Plugin hat für com_tags evtl. schon slugs gesetzt, da Fehler mit getaggter Kategorie.
// Andere Variante wäre catid im Plugin für Kategorien anders zu belegen, da das dort ebenfalls das Parent ist
if (!isset($this->item->slug))
 $this->item->slug = $this->item->alias ? ($this->item->content_item_id . ':' . $this->item->alias) : $this->item->content_item_id;

if (!isset($this->item->catslug))
 $this->item->catslug		= $this->item->category_alias ? ($this->item->catid.':'.$this->item->category_alias) : $this->item->catid;
	
if (!isset($this->item->parent_slug))
 $this->item->parent_slug	= $this->item->parent_alias ? ($this->item->parent_id . ':' . $this->item->parent_alias) : $this->item->parent_id;

// No link for ROOT category
if ($this->item->parent_alias == 'root')
{
	$this->item->parent_slug = null;
}

$this->item->alternative_readmore = ''; 

// Beitragsparameter. Wird jetzt im Plugin erledigt.
/*$this->item->params = new JRegistry;
$this->item->params->loadString($this->item->core_params);*/
// Menüparameter ($this->params) überschreiben Beitragsparameter
$temp = clone($this->params);
$this->item->params->merge($temp);

// ToDo: Unsicher, ob das Model das evtl. schon abgefangen hat. Darf ich hier einfach auf 1 setzen???
$this->item->params->set('access-view', 1);

// Gibt es nicht, also auf 0 setzen:
$canEdit = $this->item->params->get('access-edit', 0);

$info = $this->item->params->get('info_block_position', 0);

$isCat = $this->item->type_alias == 'com_content.category';

/*echo 'DEBUG-GHSVS<br />';
echo $this->item->AutorenNamesConcated.'<br />';
echo $this->item->combinedCatsGhsvs.'<br />';
echo $this->item->concatedTagsGhsvs.'<br />';
echo $this->item->lastKeepForever.'<br />';
echo $this->item->concatedCatTagsGhsvs.'<br />';*/

?>
<?php

if (
 $this->item->state == 0 ||
	strtotime($this->item->publish_up) > strtotime(JFactory::getDate()) ||
	(
	 ( strtotime($this->item->publish_down) < strtotime(JFactory::getDate() )
	) &&
	 $this->item->publish_down != '0000-00-00 00:00:00'
	)
) : ?>
	<div class="system-unpublished">
<?php endif; ?>

	<?php echo JLayoutHelper::render('ghsvs.page_header_n_icons', array('item' => $this->item, 'print' => false)); ?>



<?php if ($this->item->params->get('show_tags')) :?>
	<?php echo JLayoutHelper::render('ghsvs.tags_n_tagscat', $this->item); ?>
<?php endif; ?>

<?php // Todo Not that elegant would be nice to group the params ?>
<?php 
 $useDefList = (
	$this->item->params->get('show_modify_date') ||
	$this->item->params->get('show_publish_date') ||
	$this->item->params->get('show_create_date') || 
	$this->item->params->get('show_hits') || 
	$this->item->params->get('show_category') || 
	$this->item->params->get('show_parent_category') ||
	$this->item->params->get('show_author') ); ?>

<?php if ($useDefList && ($info == 0 || $info == 2)) : ?>
	<?php echo JLayoutHelper::render('joomla.content.info_block.block', array('item' => $this->item, 'params' => $this->item->params, 'position' => 'above')); ?>
<?php endif; ?>

<?php echo JLayoutHelper::render('joomla.content.intro_image', $this->item); ?>

<?php if (!$this->item->params->get('show_intro')) : ?>
	<?php echo $this->item->event->afterDisplayTitle; ?>
<?php endif; ?>
<?php echo $this->item->event->beforeDisplayContent; ?>

<?php /* GHSVS 2015-01-01: ToDo: introtext_limit irgendwo konfigurierbar im Backend einrichten. Vielleicht im Plugin  */ ?>
<?php #ALT: echo $this->item->introtext; ?>
<?php
$truncated = JHtml::_(
	'string.truncateComplex',
	$this->item->text,
	$this->item->params->get('introtext_limit', 500)
);
// Bisschen plump:
if (!$this->item->readmore && mb_substr($truncated, -3) == '...')
{
	$this->item->readmore = true;
}
// Und jetzt statt Introtext:
echo $truncated;

?>


<?php if ($useDefList && ($info == 1 || $info == 2)) : ?>
	<?php echo JLayoutHelper::render('joomla.content.info_block.block', array('item' => $this->item, 'params' => $this->item->params, 'position' => 'below')); ?>
<?php  endif; ?>

<?php if ($this->item->params->get('show_readmore') && $this->item->readmore) :
	if ($this->item->params->get('access-view')) :
		$link = JRoute::_(ContentHelperRoute::getArticleRoute($this->item->slug, $this->item->catid));
	else :
		$menu = JFactory::getApplication()->getMenu();
		$active = $menu->getActive();
		$itemId = $active->id;
		$link1 = JRoute::_('index.php?option=com_users&view=login&Itemid=' . $itemId);
		$returnURL = JRoute::_(ContentHelperRoute::getArticleRoute($this->item->slug, $this->item->catid));
		$link = new JUri($link1);
		$link->setVar('return', base64_encode($returnURL));
	endif; ?>

	<?php echo JLayoutHelper::render('joomla.content.readmore', array('item' => $this->item, 'params' => $this->item->params, 'link' => $link)); ?>

<?php endif; ?>

<?php if ($this->item->state == 0 || strtotime($this->item->publish_up) > strtotime(JFactory::getDate())
	|| ((strtotime($this->item->publish_down) < strtotime(JFactory::getDate())) && $this->item->publish_down != '0000-00-00 00:00:00' )) : ?>
</div>
<?php endif; ?>

<?php echo $this->item->event->afterDisplayContent; ?>
