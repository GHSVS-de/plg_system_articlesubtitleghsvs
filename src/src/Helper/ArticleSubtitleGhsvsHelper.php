<?php

namespace Joomla\Plugin\System\ArticleSubtitleGhsvs\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Utilities\ArrayHelper;
use Joomla\Registry\Registry;
use Joomla\CMS\Router\Route;
use Joomla\Component\Content\Site\Helper\RouteHelper;

class ArticleSubtitleGhsvsHelper
{
	/** Bastelt Arrays $item->autorenaliase und $item->AutorenNamesConcated.
	*
	* @param object $item. E.g. $article.
	* @param integer $contactCatId. Id of com_contact category.
	* @return boolean true if arrays filled.
	*/
	public static function getAutorenNamesFromContactAliase(&$item,
		\Joomla\Registry\Registry $plgParams)
	{
		if (isset($item->autorenaliase))
		{
			return;
		}

		$contactCatId = (int) $plgParams->get('contact_category_autorbeschreibung');

		// Ist ein Array. Mit Contact ids.
		$autorenaliase = $item->params->get('autorenaliase');

		// kommaseparierte Namen
		$item->AutorenNamesConcated = '';

		$item->autorenaliase = [];

		if ($contactCatId < 1 || !is_array($autorenaliase) || !count($autorenaliase)
		){
			return false;
		}

  	$autorenaliase = ArrayHelper::toInteger($autorenaliase);

		$db = Factory::getDBO();
		$select = $db->qn(array('id', 'name', 'alias', 'misc', 'webpage', 'image'));
		$query = $db->getQuery(true)
			->select($select)
			->from($db->qn('#__contact_details'))
			->where('`id` IN (' . implode(',', $autorenaliase) . ')')
			->where('`published` >= 1')
			->where('`catid` = ' . $contactCatId);

		try
		{
			$db->setQuery($query);
			$autorenaliase = $db->loadObjectList();
		}
		catch (\RuntimeException $e)
		{
			return false;
		}

		$item->autorenaliase = $autorenaliase;

		// Relikt für templates\wohnmichl\html\com_content\category\listghsvs_articles.php
		// Aber jetzt auch im JLayout
		$item->AutorenNamesConcated = implode(', ', ArrayHelper::getColumn(
				$item->autorenaliase, 'name'));

		return true;
	}

	static function clearPluginPlaceholders(&$text)
	{
		if (strpos($text, '{') === false && strpos($text, '}') === false)
		{
			return;
		}

		// get {blah}xyz{/blah}
		$muster = '/{.+?}([^{]*)?{\/.+?}/';
		preg_match_all($muster, $text, $matches);

		if (is_array($matches[0]))
		{
			$text = str_replace($matches[0], '', $text);
		}

		if (strpos($text, '{') === false && strpos($text, '}') === false)
		{
			return;
		}

		// get {blah}, get {blah this=xyz}
		$muster = '/{.+?}/';
		preg_match_all($muster, $text, $matches);

		if (is_array($matches[0]))
		{
			$text = str_replace($matches[0], '', $text);
		}
	}

	/*
	Liefert $Item->combinedCatsGhsvs
	Fasst kategorie und übergeordnete Kategorie in einen String zusammen mit Separator.
	concat categories
	*/
	public static function combineCats(&$Item)
	{
		if (!empty($Item->combinedCatsGhsvs))
		{
		return;
		}

		$Item->combinedCatsGhsvs='';

		// Nicht anzuzeigende Parentkategorien:
		$templateParams = Factory::getApplication()->getTemplate(true)->params;
		$exclude_parent_category_ids = $templateParams->get(
			'exclude_parent_category_ids',
			[],
			'ARRAY'
		);

		$doParent_id = (
			isset($Item->parent_id)
			&& !in_array($Item->parent_id, $exclude_parent_category_ids)
		);

		if (
			$Item->params->get('show_category')
			|| ($Item->params->get('show_parent_category') && $doParent_id)
		){
			$collect = [];

			if (
				(
					$Item->params->get('link_parent_category')
						|| $Item->params->get('link_category')
				)
				&& !class_exists('ContentHelperRoute')
			){
				//require_once JPATH_SITE . '/components/com_content/helpers/route.php';
			}

			if (
				$doParent_id
				&& $Item->params->get('show_parent_category')
				&& trim($Item->parent_title)
				&& !empty($Item->parent_slug)
			){
				$title = '<span class="parent-category-name">'
					. $Item->parent_title . '</span>';

				if (
					$Item->params->get('link_parent_category')
					&& !empty($Item->parent_slug)
				){
					$collect[] = '<a href="' . Route::_(
						\ContentHelperRoute::getCategoryRoute($Item->parent_slug)) . '">'
						. $title . '</a>';
				}
				else
				{
					$collect[] = $title;
				}
			}

			if($Item->params->get('show_category'))
			{
				$title = '<span class="category-name">' . $Item->category_title
					. '</span>';

				if ($Item->params->get('link_category') && $Item->catid)
				{
					if (version_compare(JVERSION, '4', 'ge'))
					{
						$collect[] = '<a href="' . Route::_(
							\ContentHelperRoute::getCategoryRoute($Item->catid,
							$Item->category_language))
							. '">' . $title . '</a>';
					}
					else
					{
						$collect[] = '<a href="' . Route::_(
							\ContentHelperRoute::getCategoryRoute($Item->catslug))
							. '">' . $title . '</a>';
					}
				}
				else
				{
					$collect[] = $title;
				}
			}

			$Item->combinedCatsGhsvs = trim(implode(
				'<span class="icon-arrow-right-3"></span>', $collect
			));
		}
	}

	/*
	2015-01-20 für about-africa
	Setzt voraus, dass $item->tags->itemTags schon vorhanden
	Liefert $item->concatedTagsGhsvs
	$catTags: Zusätzlich KategorieTags ermitteln?
	*/
	public static function setTagsNamesToItem(&$item, $catTags = false)
	{
		$item->concatedTagsGhsvs = '';

		if (empty($item->tags))
		{
			return false;
		}

		// Fügt Property text hinzu. Warum nicht einfach title nehmen? Weil es auch verschachtelte Tags geben kann. text bildet das ab.
		$item->tags->convertPathsToNames($item->tags->itemTags);
		$collector = [];

		foreach($item->tags->itemTags AS $tag){
			$collector[] = $tag->text;
		}

		sort($collector);
		$item->concatedTagsGhsvs = implode(', ', $collector);
		return true;
	}

	// Frage ist, ob das nicht viel einfacher per CSS geht.
	public static function replaceHxByParagraph(&$text)
	{
		for ($i = 1; $i <= 6; $i++)
		{
			if ( stripos($text, '</h' . $i . '>') !== false)
			{
				$text = preg_replace('/<h([1-6])([^>]*)>(.*?)<\/h\1>/', "<p$2>$3</p>",
					$text);
				break;
			}
		}
	}

	/*
	2015-01-08
	Entfernt alle IMG-Tags aus $txt.
	*/
	public static function ClearIMGTag(&$txt)
	{
		$txt = preg_replace('/<img[^>]*>/', '', $txt);
	}
}
