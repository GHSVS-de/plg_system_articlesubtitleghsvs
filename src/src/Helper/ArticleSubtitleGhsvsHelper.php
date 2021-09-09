<?php

namespace Joomla\Plugin\System\ArticleSubtitleGhsvs\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Utilities\ArrayHelper;
use Joomla\Registry\Registry;

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
}
