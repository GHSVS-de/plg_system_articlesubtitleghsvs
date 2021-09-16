<?php
defined('_JEXEC') or die;

if (version_compare(JVERSION, '4', 'lt'))
{
  JLoader::registerNamespace(
    'Joomla\Plugin\System\ArticleSubtitleGhsvs',
    __DIR__ . '/src',
    false, false, 'psr4'
  );
}

use Joomla\Plugin\System\ArticleSubtitleGhsvs\Helper\ArticleSubtitleGhsvsHelper;

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Utilities\ArrayHelper;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\Registry\Registry;
use Joomla\CMS\Language\Text;

class PlgSystemArticleSubtitleGhsvs extends CMSPlugin
{
	protected $app;
	protected $db;
	protected $autoloadLanguage = true;

		// Eine Paranoia-Sicherungstabelle für Autorenaliase. Falls Plugin mal versehentlich deaktiviert wurde.
	protected $MapTable = '#__autorbeschreibungghsvs_content_map';

	// Im Beitrag gewählte Autoren (ids) aus Kontaktkomponente.
	public $autorenaliase = false;

	// Sozusagen globales ON/OFF für Frontend-Artikel
	protected $ContinueFE = true;

	// Sozusagen globales ON/OFF für Frontend-Artikel-Kategorien und -Hauptbeiträge.
	protected $ContinueFECat = true;

	// Switches. Siehe auch Plugin- sowie Beitrags-Einstellungen.
	public $zitierweise_active;
	public $autorbeschreibung_active;

	// Lediglich, um nicht in allen Methoden wieder Defaultwerte für xyz_active neu eingeben zu müssen.
	protected $activeChecks = [
		'zitierweise_active',
		'autorbeschreibung_active',
	];

	// In welchen Templates Plugin verwenden? Siehe Plugineinstellung.
	protected $templates;

	protected $isBlogGhsvsListe = false;

	function __construct(&$subject, $config = array())
	{
		parent::__construct($subject, $config);

		// Brauch ich in ein paar meiner Menüs. Zu faul, das jedesmal neu zu laden.
		// 2015-04-24: Versteh nicht ganz. Vermutlich habe ich COM_CONTENT-Sprachplatzhalter irgendwo?
		if ($this->app->isClient('administrator'))
		{
			$lang = Factory::getLanguage();
			$lang->load('com_content');
		}

		$this->templates = $this->params->get('load_in_templates', array(), 'ARRAY');
	}

	/*
	checkt, ob Autorbeschreibungen, Zitierweise abgewickelt werden sollen in Einzelbeitrag. => $this->ContinueFE
	2015-01-24: Checkt zusätzlich, ob Kategorieanssicht mit Beiträgen (Blog, Featured, Tag). => $this->ContinueFECat
	Eigentlich wollte ich nur nicht in jeder Methode diese Option-View-Abfrage.
	*/
	protected function getContinueFE($context, $article)
	{
		if (
			!$this->app->isClient('site')
			|| Factory::getDocument()->getType() !== 'html'
			|| (count($this->templates) &&
				!in_array($this->app->getTemplate(), $this->templates))
			|| !(isset($article->params) && $article->params instanceof Registry)
		){
			$this->ContinueFE = $this->ContinueFECat = false;
			return;
		}

		$option = $this->app->input->get('option');
		$view = $this->app->input->get('view');

		//z.B. wohnmichl:blogghsvs
		$layout = $this->app->input->get('layout');

		$allowed_context = ['com_content.article'];
		$allowed_contextCat = ['com_content.category', 'com_content.featured'];

		// Article View?
		if (
			$this->ContinueFE === true
			&& $view === 'article'
			&& $option === 'com_content'
			&& in_array($context, $allowed_context)
		){
			$this->ContinueFECat = false;

			// Fremde Plugin-Platzhalter in Article-Views NICHT löschen.
			$this->params->set('clear_plugin_placeholders', 0);

			// Images in Article-Views NICHT löschen.
			$this->params->set('clear_images', 0);

			// Hx-Headertags NICHT durch P ersetzen.
			$this->params->set('replace_hx', 0);

			self::mergeAttribsToParams($article);

			/* fügt Arrays $article->autorenaliase und $article->AutorenNamesConcated
				hinzu. */
			ArticleSubtitleGhsvsHelper::getAutorenNamesFromContactAliase($article,
				$this->params);

			// Set properties like $article->zitierweise_active.
			foreach ($this->activeChecks as $key)
			{
				$article->$key = 0;

				// If not active in Plugin, no chance for article to override,
				if ((int) $this->params->get($key, 1) === 1)
				{
					// Otherweise take over article settings.
					$article->$key = (int) $article->params->get($key, 1);

					if ($key === 'autorbeschreibung_active' && !$article->autorenaliase)
					{
						$article->$key = 0;
					}
				}
			}
		}

		// Category/Featured View?
		elseif (
			$this->ContinueFECat === true
			&& $option === 'com_content'
			&& in_array($context, $allowed_contextCat)
		){
			$this->ContinueFE = false;
			self::mergeAttribsToParams($article);

			// Sehr unflexible Krücke, um zu ermitteln, ob es sich um ein Listenlayout handelt in meinen eigenen Category-Views mit BLOGLISTTOGGLER-Button. load_in_templates
			// Geprüft wurde schon, dass wir in featured/category-View sind.
			// 2015-07-28: Wenigstens schon mal Check über alle aktivierten Templates hinzu.
			foreach ($this->templates as $templ)
			{
				if ($layout === $templ . ':blogghsvs')
				{
					$chckIsBlogGhsvsListe = true;
					break;
				}
			}

			if (isset($chckIsBlogGhsvsListe))
			{
				$node = 'jshopghsvs';
				$session = Factory::getSession();
				$sessionData = $session->get($node);
				// Jetzt die sicherste Variante wählen:
				if (
				 isset($sessionData['BLOGLISTTOGGLER'])
				 && $sessionData['BLOGLISTTOGGLER'] === 'SHOWBLOG'
				)
				{
					$this->isBlogGhsvsListe = true;
				}
			}
		}
		// Weder Article- noch Cat- oder Featured-View?
		else
		{
			$this->ContinueFE = false;
			$this->ContinueFECat = false;
		}
	}

 /*
	Nachladen von eigenen attribs in params ohne bestehende params zu überschreiben
	*/
	protected function mergeAttribsToParams($article)
	{
		// Einige Situationen, bspw. Featured, braucht diesen check:
		// Attribs zu Params
		if ($this->ContinueFE || $this->ContinueFECat)
		{
			// Da auch einzelne Fragmente über content.prepare laufen können, bspw. per JHtml.
			if (empty($article->params))
			{
			 return;
			}

			// autorenaliase ist einziges Feld, dass ich zur Zeit required habe, da leere Felder beim merge() leider ignoriert werden, also keine Property erzeugt wird, also auch nicht geprüft werden können.
			if (
				// Mittlerweile in XML als hidden nachgetragen
				1 !== $article->params->get('pluginarticlesubtitleghsvs', 0, 'INT')
				// Für alte Beiträge.
				|| !is_array($article->params->get('autorenaliase')))
			{
				// Beitragsattribs.
				$Attribs = new Registry();
				$Attribs->loadString($article->attribs);
				$article->params->merge($Attribs);
			}
		}
	}

	public function onContentPrepareForm($form, $data)
	{
		$view = $this->app->input->get('view', '');
		$layout = $this->app->input->get('layout', '');
		$allowedContext = array(
			'com_content.article',
		);

		if(!in_array($form->getName(), $allowedContext))
		{
			return true;
		}

		$this->ContinueFE = false;
		$this->ContinueFECat = false;

		// 2015-04-24: Nicht ganz klar, warum ich eigentlich aufrufe.
		self::getContinueFE('none', null);

		// Pfad zu plugineigenen XML-Dateien, die article.xml ergänzen.
		JForm::addFormPath(__DIR__ . '/myforms');

		// Erzwinge Metabeschreibung:
		$form->setFieldAttribute('metadesc', 'required', 'true');

		//loads /pluginpath/myforms/articlesubtitle.xml
		$form->loadFile('articlesubtitle', $reset = false, $path = false);

		/* Wird im FE NICHT angezeigt, aber auch nicht versehentlich
			überschrieben/gelöscht.
			Also auf nicht required, damit beim Speichern nicht blockiert. */
		if ($this->app->isClient('site'))
		{
			$form->setFieldAttribute('autorenaliase', 'required', 'false', 'attribs');
		}

		// Wenn im Plugin deaktiviert, im Beitrag das Feld auf readonly + Hinweis im Label!
		foreach ($this->activeChecks as $key)
		{
			if (!$this->params->get($key))
			{
				$form->setFieldAttribute($key, 'readonly', 'true', 'attribs');
				$oldLabel = Text::_($form->getFieldAttribute(
					$key,
					'label',
					'PLG_SYSTEM_ARTICLESUBTITLEGHSVS_' . strtoupper($key) . '_LBL',
					'attribs'
				));
				$newLabel = $oldLabel.' ('
					. Text::_('PLG_SYSTEM_ARTICLESUBTITLEGHSVS_DEACTIVATED_BY_PLUGIN')
					. ')';
				$form->setFieldAttribute($key, 'label', $newLabel, 'attribs');
			}
		}
	}

	/*
	$this->item->event->afterDisplayContent;
	Zitierweise-Text, Autorbeschreibung-Text
	*/
	public function onContentAfterDisplay($context, &$article, &$params, $limitstart = 0)
	{
		self::getContinueFE($context, $article);

		if ($this->ContinueFE)
		{
			return trim(LayoutHelper::render('ghsvs.autorbeschreibung',
				[
					'article' => $article
				]
			));
		}
	}

	/*
	 Achtung! Hier werden auch com_tags durchgeleitet, da dieses Scheiß Joomla in
		Version 3.3.6 bspw das core_params nicht ausliest.
		ToDo: Ab 3.4.2 sollte das Problem gelöst sein.
	*/
	public function onContentPrepare($context, &$article, $params, $page = 0)
	{
		// Dann sind wir wohl in com_tags.
		if (!empty($article->type_alias))
		{
			// Muss eigene Property TypeAliasGhsvs sein!!!!!
			$article->TypeAliasGhsvs = $article->type_alias;
		}
		// Dann sind wir wohl nicht in com_tags.
		else
		{
			// Plumpe Variante. Zu prüfen, ob ausreichend:
			$article->TypeAliasGhsvs = $context;
			$toArticle = ['com_content.featured', 'com_content.category'];

			if (in_array($article->TypeAliasGhsvs, $toArticle))
			{
				$article->TypeAliasGhsvs = 'com_content.article';
			}
		}

		/*
		com_tags, einzelnes ContentItem darin
		Achtung! Lässt ALLE type_alias durch, also auch com_content.category
		*/
		if ($context == 'com_tags.tag' && !empty($article->content_item_id))
		{
			self::completeTagItem($article, $context);
			self::getItemAdditionals($article);
			return true;
		}

		self::getContinueFE($context, $article);

		if ($this->ContinueFE || ($this->ContinueFECat))
		{
			// Entfernt ggf. auch normale Bilder (aber nicht Einleitungsbild, nicht Beitragsbild).
			self::getItemAdditionals($article, $context);
		}

		return true;
	}

	/** Add additional properties to $article.
	*
	*
	*/
	protected function getItemAdditionals(&$article)
	{
		/* fügt Arrays $article->autorenaliase und $article->AutorenNamesConcated
			hinzu. */
		ArticleSubtitleGhsvsHelper::getAutorenNamesFromContactAliase($article,
			$this->params);

		// Liefert $Item->combinedCatsGhsvs
		ArticleSubtitleGhsvsHelper::combineCats($article);

		//	Liefert $item->concatedTagsGhsvs
		ArticleSubtitleGhsvsHelper::setTagsNamesToItem($article);

	// 2015-07-22, da Module ausgelassen wurden.
		$txtKey = !empty($article->text) ? 'text' : (!empty($article->introtext) ? 'introtext' : '');
		$clearPlaceholder = $this->params->get('clear_plugin_placeholders', 1);
		$clearImages = $this->params->get('clear_images', 1);
		$replaceHx = $this->params->get('replace_hx', 1);

		if ($txtKey)
		{
			if ($clearPlaceholder)
			{
				// Alle Pluginplatzhalter aus Text entfernen.
				// Für Artikel oben auf 0 gesetzt.
				ArticleSubtitleGhsvsHelper::clearPluginPlaceholders($article->$txtKey);
			}

			if ($replaceHx)
			{
				// Alle Header-Tags Hx im Text durch P ersetzen.
				// Für Artikel oben auf 0 gesetzt.
				ArticleSubtitleGhsvsHelper::replaceHxByParagraph($article->$txtKey);
			}

			if ($clearImages)
			{
				// Alle Bilder aus Text entfernen.
				// Für Artikel oben auf 0 gesetzt.
				ArticleSubtitleGhsvsHelper::ClearIMGTag($article->$txtKey);
			}
		}
	} # getItemAdditionals

 /*
	ToDo: Ab 3.4.2 sollte das core_params-Problem behoben sein,
	da dieses Scheiß Joomla in
		Version 3.3.6 bspw das core_params nicht ausliest.
		Nachladen der Daten aus #__content
Anmerkung dazu und com_tags:
!!!!core_params muss unbedingt als Registry zurückgegeben werden
(wie es auch ankommt, aber da leer.!!!!!!
Eigentlich wegen eines Bugs in jw_allvideos. Hier landet core_params als $params
in onContentPrepare. Es wird nur auf leeres params geprüft, aber nicht auf die
instance of Jreistry.
	*/
		/*
		Achtung! Lässt ALLE type_alias durch, also auch com_content.category
		*/
 private function completeTagItem(&$article, $context)
	{
		// Soll alles nur in com_tags
  if (!isset($article->type_alias)) return;

  list($option, $area) = explode('.', $article->type_alias);
  if (
		 $option != 'com_content' ||
		 $context != 'com_tags.tag' ||
			empty($article->content_item_id)
		)
		{
			return true;
		}

		$query = $this->db->getQuery(true);

		switch($area){
			case 'article':
			$table = $this->db->qn('#__content', 'a');

			// Was noch aus Content hinzuholen und überführen nach?
			$additionalPicks = array(
		  'attribs' => 'core_params',
			 'fulltext' => 'fulltext',
		 );
   $query->select('c.title AS category_title, c.alias AS category_alias, c.access AS category_access')
   ->join('LEFT', '#__categories AS c on c.id = '.$this->db->qn('a.catid'));
   // Join over the categories to get parent category titles
   $query->select('parent.title as parent_title, parent.id as parent_id, parent.path as parent_route, parent.alias as parent_alias')
  ->join('LEFT', '#__categories as parent ON parent.id = c.parent_id');
			break;

			case 'category':
			$table = $this->db->qn('#__categories', 'a');

			// Was noch aus Content hinzuholen und überführen nach?
			$additionalPicks = array(
		  'params' => 'core_params',
		 );

			$query->select('a.title AS category_title, a.alias AS category_alias, a.access AS category_access');

			$query->select('"" AS '.$this->db->qn('fulltext'));

   // Join over the categories to get parent category titles
   $query->select('parent.title as parent_title, parent.id as parent_id, parent.path as parent_route, parent.alias as parent_alias')
  ->join('LEFT', '#__categories as parent ON parent.id = a.parent_id');
			break;
		}

  foreach ($additionalPicks as $key=>$value)
		{
   if (empty($article->$value))
		 {
			 $query->select($this->db->qn('a.'.$key));
		 }
		}

		$query->from($table)->where('a.id = '.(int)$article->content_item_id);

		$this->db->setQuery($query);

		if ($item = $this->db->loadObject())
		{
			foreach ($item as $key=>$value)
			{
				if (isset($additionalPicks[$key]))
				{
				 $key = $additionalPicks[$key];
				}
				$article->$key = $value;
				if ($key == 'fulltext')
				{
					$article->readmore = strlen(trim($article->fulltext));
				}
			}
		}
		// Muss JsonString bleiben!! Todo:needed anymore?
  $article->attribs = $article->core_params;

		$article->params = new Registry();
		$article->params->loadString($article->core_params);
		// Krücke Todo: Im Tag-View-XML Parameter einrichten
		$article->params->set('ghsvs_combine_categories', 1);
		$article->params->set('show_category', 1);
		$article->params->set('show_parent_category', 1);
		$article->params->set('link_parent_category', 1);
		$article->params->set('link_category', 1);

		// Sonst kollabiert Plugin jw_allvideos
		$article->core_params = clone($article->params);

		$article->tags = new JHelperTags;
		$article->tags->getItemTags($article->type_alias, $article->content_item_id, true);
		$article->tags->convertPathsToNames($article->tags->itemTags);

		$ignore = array('core_content_id', 'core_body', 'core_params');
		foreach ($article as $key=>$value)
		{
			if (!in_array($key, $ignore) && strpos($key, 'core_') === 0)
			{
				$newKey = substr($key, 5);
				$article->$newKey = $value;
			}
		}

		$article->slug = $article->alias ? ($article->content_item_id . ':' . $article->alias) : $article->content_item_id;

  $article->parent_slug	= $article->parent_alias ? ($article->parent_id . ':' . $article->parent_alias) : $article->parent_id;

		switch($area){
			case 'article':

				$article->catslug		= $article->category_alias ? ($article->catid.':'.$article->category_alias) : $article->catid;

			break;

			case 'category':
			// Andere Variante wäre catid im Plugin für Kategorien anders zu belegen, da das dort ebenfalls das Parent ist
				$article->catslug		= $article->category_alias ? ($article->content_item_id.':'.$article->category_alias) : $article->content_item_id;

			break;
		}

		return true;
	} # completeTagItem

	/**
	* Attribs-Sicherungen in DB löschen.
	*/
	public function onContentAfterDelete($context, $article)
	{
		if (
			$context !== 'com_content.article'
			|| empty($article->attribs)
			|| strpos($article->attribs, '"autorenaliase":["') === false
		){
			return true;
		}

		self::deleteAutoraliase($article);
	}

	/*
	* Alte Einträge des $article löschen, damit ggf. aufgefrischtes INSERT möglich.
	*/
	protected function deleteAutoraliase($article)
	{
		//
		$query = $this->db->getQuery(true)
			->delete($this->db->qn($this->MapTable))
			->where($this->db->qn('content_id') . ' = ' . (int) $article->id);
		$this->db->setQuery($query);
		$this->db->execute();
	}

	/*
	Zusätzlich zu autorenaliase (Autorbeschreubung) in $article->attribs
	wird hier noch in extra Tabelle
	gespeichert, da dies die Abfrage in einigen Codes erleichtert.
	*/
	public function onContentAfterSave($context, $article, $isNew)
	{
		if (
			$context !== 'com_content.article'
			|| empty ($article->attribs)
			|| strpos($article->attribs, '"autorenaliase":["') === false
		){
			return true;
		}

		self::deleteAutoraliase($article);

		// Neue aus $article->attribs
		$Paras = new Registry();
		$Paras->loadString($article->attribs);
		$autorenaliasIds = $Paras->get('autorenaliase');
		$query = $this->db->getQuery(true)
			->insert($this->db->qn($this->MapTable))
			->columns([$this->db->qn('content_id'), $this->db->qn('contact_id')]);

		foreach ($autorenaliasIds as $id)
		{
			$query->values((int) $article->id . ',' . (int) $id);
		}

		$this->db->setQuery($query);
		$this->db->execute();

		return true;
	}

	/*
	Eigentlich unnötig, da validate schon per Javascript gemacht wird,
	mittels required="true" in der XML-Datei.
	*/
	public function onContentBeforeSave($context, $article, $isNew)
	{
		$allowed_context = array('com_content.article');
		$option = $this->app->input->get('option', '', 'cmd');
		$layout = $this->app->input->get('layout', '', 'cmd');

		if (
			$this->app->isClient('administrator')
			&& in_array($context, $allowed_context)
			&& $layout == 'edit'
		){
			$attribs = new Registry;
			$attribs->loadString($article->attribs);
			$attribs = $attribs->toObject();

			if (!is_array($attribs->autorenaliase) || !count($attribs->autorenaliase))
			{
				$this->app->enqueueMessage(
					Text::_('PLG_SYSTEM_ARTICLESUBTITLEGHSVS_AUTORENALIASE_IS_EMPTY'),
					'error'
				);
				return false;
			}
		}
	}

	protected function _parseOpts($str)
	{
		// eg. w=700, h=200, crop=TRUE to array('w' => 700, 'h' => 200, 'crop' => TRUE)
		$opts = array();

		$str = str_replace(' ', '', $str);

		if (!$str || strpos($str, '=') === false) return $opts;

		$params = explode(',', $str);
		foreach ($params as $param)
		{
			$split = explode('=', $param);
			if (!empty($split[0]) && !empty($split[1]))
			{
				$opts[trim($split[0])] = trim($split[1]);
				// string to boolean
				if (strcmp($opts[trim($split[0])], 'TRUE') == 0) $opts[trim($split[0])] = true;
				if (strcmp($opts[trim($split[0])], 'FALSE') == 0) $opts[trim($split[0])] = true;
			}
		}
		return $opts;
	}

}
