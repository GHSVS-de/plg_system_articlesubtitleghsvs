<?php
defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Utilities\ArrayHelper;
use Joomla\CMS\Layout\LayoutHelper;

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
	protected $activeChecks = [];

	// In welchen Templates Plugin verwenden? Siehe Plugineinstellung.
	protected $templates;

	// Bildoptimierer generell aktiv?
	protected $imageoptimizer = 0;

	// new Optimiererklasse.
	protected $resizer = false;
	protected $isBlogGhsvsListe = false;

	// Damit Templatehelper-Ladewarnung nur 1x angezeigt wird:
	private static $TEMPLATEHELPERMESSAGESENT = false;
	private static $TMPLHELPERLOADED = false;

	function __construct(&$subject, $config = array())
	{
		parent::__construct($subject, $config);

		// Brauch ich in ein paar meiner Menüs. Zu faul, das jedesmal neu zu laden.
		// 2015-04-24: Versteh nicht ganz. Vermutlich habe ich COM_CONTENT-Sprachplatzhalter irgendwo?
		if ($this->app->isClient('administrator'))
		{
			$lang = JFactory::getLanguage();
			$lang->load('com_content');
		}

		$this->templates = $this->params->get('load_in_templates', array(), 'ARRAY');

		// Bildoptimierer, -Resizer aktiv?
		$this->imageoptimizer = $this->params->get('imageoptimizer', 0, 'INT');
	}

	protected function TplHelpGhsvsLoader()
	{
		if (
		 !self::$TEMPLATEHELPERMESSAGESENT &&
			!self::$TMPLHELPERLOADED
		)
		{
			$template = JFactory::getApplication()->getTemplate();

			if(
				!($TplHelpGhsvs = JLoader::import(
					"templates.$template.html.helpersghsvs.TplHelpGhsvs",
					JPATH_SITE
				))
			){
				$TplHelpGhsvsError =
					'Could not load template helper: '.str_replace(
						'.', '/',
						"templates.$template.html.helpersghsvs.TplHelpGhsvs"
					).' in Plugin '.$this->_name;
				JFactory::getApplication()->enqueueMessage($TplHelpGhsvsError);
				#throw new RuntimeException($error, 500);
				self::$TEMPLATEHELPERMESSAGESENT = true;
				self::$TMPLHELPERLOADED = false;
				return false;
			}
			else
			{
			 self::$TMPLHELPERLOADED = true;
				return true;
			}
		}
	}

	/*
	checkt, ob Autorbeschreibungen, Zitierweise abgewickelt werden sollen in Einzelbeitrag. => $this->ContinueFE
	2015-01-24: Checkt zusätzlich, ob Kategorieanssicht mit Beiträgen (Blog, Featured, Tag). => $this->ContinueFECat
	Eigentlich wollte ich nur nicht in jeder Methode diese Option-View-Abfrage.
	*/
	protected function getContinueFE($context, $article)
	{
		// Ist aktuelles Template eines von denen, die im Plugin gewählt wurden?
		// Wenn nicht, dann ENDE.
		if (
			count($this->templates) &&
			!in_array(JFactory::getApplication()->getTemplate(), $this->templates)
		)
		{
			$this->ContinueFE = $this->ContinueFECat = false;
			return;
		}

		// Erst mal Defaultwerte setzen. Sowohl im Plugin als auch Beitrag kann das überschrieben werden.
		// Im Plugin muss auf JA stehen, damit per Beitrag überschrieben werden kann!
		$this->activeChecks = [
			'zitierweise_active' => 1,
			'autorbeschreibung_active' => 1,
		];

		// Ergibt z.B. $this->zitierweise_active. Falls Plugin nicht gespeichert wurde, obige Default-Werte nehmen.
		foreach ($this->activeChecks as $key => $default)
		{
			$this->$key = $this->params->get($key, $default);
		}

		$option = $this->app->input->get('option', '', 'cmd');
		$view = $this->app->input->get('view', '', 'cmd');

		//z.B. wohnmichl:blogghsvs
		$layout = $this->app->input->get('layout', '', 'STRING');

		$this->context = $context;

		$allowed_context = array('com_content.article');
		$allowed_contextCat = array('com_content.category', 'com_content.featured');

		if (
			$view == 'article'
		){
			// Fremde Plugin-Platzhalter in Article-Views NICHT löschen.
			$this->params->set('clear_plugin_placeholders', 0);

			// Images in Article-Views NICHT löschen.
			$this->params->set('clear_images', 0);

			// Hx-Headertags NICHT durch P ersetzen.
			$this->params->set('replace_hx', 0);
		}

		// Article-View?
		if ($this->app->isClient('site') && in_array($context, $allowed_context)
			&& $option == 'com_content' && $view == 'article' && $this->ContinueFE
			&& isset($article->params) && $article->params instanceof JRegistry
		){
			$this->ContinueFE = true;
			$this->ContinueFECat = false;
			self::mergeAttribsToParams($article);

			// Im Beitrag gewählte Autoren aus Kontaktkomponente.
			$this->autorenaliase = $article->params->get('autorenaliase', false);

			// Beitragseinstellungen (z.B. zitierweise_active) überschreiben jetzt erst globale Plugineinstellungen.
			foreach ($this->activeChecks as $key => $default)
			{
				// , falls im Plugin JA (= 1) eingestellt:
				if ($this->$key)
				{
					$this->$key = $article->params->get($key, $default);
				}
			}

			// Wenn im Beitrag keine Autorenaliase gewählt, kann es auch keine Beschreibung zu Autoren geben.
			if (!is_array($this->autorenaliase) || !count($this->autorenaliase))
			{
				$this->autorenaliase = false;
				$this->autorbeschreibung_active = false;
			}

			// Wir haben Autorenaliase-IDs im Beitrag gefunden.
			else
			{
				$this->autorenaliase = ArrayHelper::toInteger($this->autorenaliase);
		    $query = $this->db->getQuery(true);

				// Datenbankabfrage der Autorenkategorie in der Kontaktkopmonente. Diese wird im Plugin gewählt.

				// Erzeugt gequotetes Array, was ebenfalls in select(...) verwendet werden kann:
				$select = $this->db->qn(['id', 'name', 'alias', 'misc', 'webpage', 'image']);

				$query->select($select)
				->from($this->db->qn('#__contact_details'))
				->where('`id` IN (' . implode(',', $this->autorenaliase) . ')')
				->where('`published` >= 1')
				->where('`catid` = '
					. $this->params->get('contact_category_autorbeschreibung', -99999, 'INT'));
				$this->db->setQuery($query);
				$this->autorenaliase = $this->db->loadObjectList();

				// Keine Autoren gefunden.
			 if (!is_array($this->autorenaliase) || !count($this->autorenaliase))
			 {
			  // Also nix zu tun bzgl. z.B. zitierweise_active.
					foreach ($this->activeChecks as $key => $default)
					{
						$this->$key = false;
					}
				}
			}
		}

		// Kategorie/Hauptbeiträge-View?
		elseif
		(
		 $this->app->isClient('site') &&
			in_array($context, $allowed_contextCat) &&
			$option == 'com_content' &&
			in_array($context, $allowed_contextCat) &&
			$this->ContinueFECat &&
			// Da auch einzelne Fragmente über content.prepare laufen können, bspw. per JHtml.
   isset($article->params) &&
			$article->params instanceof JRegistry
		){
			$this->ContinueFE = false;
			$this->ContinueFECat = true;
			self::mergeAttribsToParams($article);

			// Sehr unflexible Krücke, um zu ermitteln, ob es sich um ein Listenlayout handelt in meinen eigenen Category-Views mit BLOGLISTTOGGLER-Button. load_in_templates
			// Geprüft wurde schon, dass wir in featured/category-View sind.
			// 2015-07-28: Wenigstens schon mal Check über alle aktivierten Templates hinzu.
			foreach ($this->templates as $templ)
			{
				if ($layout == $templ.':blogghsvs')
				{
					$chckIsBlogGhsvsListe = true;
					break;
				}
			}
			if (isset($chckIsBlogGhsvsListe))
			{
				$node = 'jshopghsvs';
				$session = JFactory::getSession();
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
			$this->ContinueFE = $this->ContinueFECat = false;
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
			 1 !== $article->params->get('pluginarticlesubtitleghsvs', 0, 'INT') ||
				// Für alte Beiträge.
				!is_array($article->params->get('autorenaliase'))
			)
			{
				// Beitragsattribs.
				$Attribs = new JRegistry();
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

		// Wird im FE NICHT angezeigt, aber auch nicht versehentlich überschrieben/gelöscht.
		// Also auf nicht required, damit beim Speichern nicht blockiert.
		if ($this->app->isClient('site'))
		{
			$form->setFieldAttribute('autorenaliase', 'required', 'false', 'attribs');
		}

		// Wenn im Plugin deaktiviert, im Beitrag das Feld auf readonly + Hinweis im Label!
		foreach ($this->activeChecks as $key => $notneeded)
		{
		 if (!$this->$key)
		 {
		  $form->setFieldAttribute($key, 'readonly', 'true', 'attribs');
				$oldLabel = JText::_($form->getFieldAttribute(
				 $key,
					'label',
					'PLG_SYSTEM_ARTICLESUBTITLEGHSVS_'.strtoupper($key).'_LBL',
					'attribs')
				);
				$newLabel = $oldLabel.' ('.JText::_('PLG_SYSTEM_ARTICLESUBTITLEGHSVS_DEACTIVATED_BY_PLUGIN').')';
				$form->setFieldAttribute($key, 'label', $newLabel, 'attribs');
		 }
		}

		return true;
	} # onContentPrepareForm

	/*
	$this->item->event->beforeDisplayContent;
	Termine
	*/
	public function onContentBeforeDisplay($context, &$article, &$params, $limitstart = 0)
	{
		// Termine eingegeben?
		return trim(LayoutHelper::render('ghsvs.termin',
				['articleId' => $article->id]));
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
					'pluginThis' => $this,
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

		// BILDOPTIMIERER
		if ($this->imageoptimizer)
		{
			// In Blog, Hauptbeiträge sind nur Einleitungsbilder relevant, falls normale Bilder
			// entfernt wurden. Siehe clean_images.
		 if (
			 ($this->ContinueFECat)
				&& !$this->isBlogGhsvsListe
				&& ($image_intro = $article->Images->get('image_intro', ''))
				&& ($image_intro_size = $this->params->get(
				 'image_intro_size',
					'w=360,quality=60,maxOnly=TRUE'
				))
				&& count($opts = $this->_parseOpts($image_intro_size))
			)
		 {
				// Class schon geladen?
				if (!$this->resizer)
				{
					if(!class_exists('ImgResizeCache'))
					{
						require_once JPATH_PLUGINS . '/'.$this->_type.'/'.$this->_name.'/resize.php';
					}
					$this->resizer = new ImgResizeCache();
				}
				$image_intro = $this->resizer->resize($image_intro, $opts);
				$article->Images->set('image_intro', $image_intro);
				$article->images = $article->Images->toString();
			}
			// Beitragsbilder in Artikeleinzelansicht.
		 if (
			 $this->ContinueFE
				&& ($image_full = $article->Images->get('image_fulltext', ''))
				&& ($image_full_size = $this->params->get(
				 'image_full_size',
					'w=550,quality=70,maxOnly=TRUE'
				))
				&& count($opts = $this->_parseOpts($image_full_size))
			)
		 {
				// Class schon geladen?
				if (!$this->resizer)
				{
					if(!class_exists('ImgResizeCache'))
					{
						require_once JPATH_PLUGINS . '/'.$this->_type.'/'.$this->_name.'/resize.php';
					}
					$this->resizer = new ImgResizeCache();
				}
				$image_full = $this->resizer->resize($image_full, $opts);
				$article->Images->set('image_fulltext', $image_full);
				$article->images = $article->Images->toString();
			}
		} # imageoptimizer

		return true;
	}

	protected function getItemAdditionals(&$article, $context = '')
	{
		self::TplHelpGhsvsLoader();

		if (self::$TMPLHELPERLOADED)
		{
			//	Liefert $item->AutorenNames und $item->AutorenNamesConcated und $item->AutorenAliase
			TplHelpGhsvs::getAutorenNamesFromContactAliase($article);
			// Liefert $Item->combinedCatsGhsvs
			TplHelpGhsvs::combineCats($article);
			//	Liefert $item->concatedTagsGhsvs
			TplHelpGhsvs::setTagsNamesToItem($article);

			//	Liefert $item->concatedCatTagsGhsvs sowie Object $item->tagsCatGhsvs
			TplHelpGhsvs::setCatTagsToItem($article);

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
					TplHelpGhsvs::clearPluginPlaceholders($article->$txtKey);
				}

				if ($replaceHx)
				{
					// Alle Header-Tags Hx im Text durch P ersetzen.
					// Für Artikel oben auf 0 gesetzt.
					TplHelpGhsvs::replaceHxByParagraph($article->$txtKey);
				}

				if ($clearImages)
				{
					// Alle Bilder aus Text entfernen.
					// Für Artikel oben auf 0 gesetzt.
					TplHelpGhsvs::ClearIMGTag($article->$txtKey);
				}
			}
		}

		// Bspw. für Bildoptimierer
		$article->Images = new JRegistry;

		if (!empty($article->images))
		{
			$article->Images->loadString($article->images);
		}
	} # getItemAdditionals

 /*
	ToDo: Ab 3.4.2 sollte das core_params-Problem behoben sein,
	da dieses Scheiß Joomla in
		Version 3.3.6 bspw das core_params nicht ausliest.
		Nachladen der Daten aus #__content
Anmerkung dazu und com_tags:
!!!!core_params muss unbedingt als JRegistry zurückgegeben werden
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

		$article->params = new JRegistry();
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

 /*
 Normally only fired in list views, not in edit views.
 */
 public function onContentChangeState($context, $pks, $value)
	{
	}
	public function onContentPrepareData($context, $data){
	}

	/**
	 * Attribs-Sicherungen in DB löschen.
	 */
	public function onContentAfterDelete($context, $article)
	{
		if (
		 $context != 'com_content.article' ||
			empty($article->attribs) ||
			strpos($article->attribs, '"autorenaliase":["') === false
		){
			return true;
		}
		self::deleteAutoraliase($article);
	} # onContentAfterDelete

 protected function deleteAutoraliase($article)
	{
		// Alte Einträge des $article löschen, auch damit ggf. aufgefrischtes INSERT möglich.
		$query = $this->db->getQuery(true)
		->delete($this->db->qn($this->MapTable))
		->where($this->db->qn('content_id') . ' = ' . (int)$article->id);
		$this->db->setQuery($query);
		$this->db->execute();
	} # deleteAutoraliase

	/*
	Zusätzlich zu autorenaliase (Autorbeschreubung) in $article->attribs
	wird hier noch in extra Tabelle
	gespeichert, da dies die Abfrage in einigen Codes erleichtert.
	*/
	public function onContentAfterSave($context, $article, $isNew)
	{
		if (
		 $context != 'com_content.article' ||
			empty ($article->attribs) ||
			strpos($article->attribs, '"autorenaliase":["') === false
		){
			return true;
		}

		self::deleteAutoraliase($article);

		// Neue aus $article->attribs
		$Paras = new JRegistry();
		$Paras->loadString($article->attribs);
		$autorenaliasIds = $Paras->get('autorenaliase');
		$query = $this->db->getQuery(true)
		->insert($this->db->qn($this->MapTable))
		->columns(
		 array(
			$this->db->qn('content_id'),
			$this->db->qn('contact_id')
			)
		);
		foreach ($autorenaliasIds as $id)
		{
			$query->values(
			 (int)$article->id.','.(int)$id
			);
		}
		$this->db->setQuery($query);
		$this->db->execute();

		return true;
	} # onContentAfterSave

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
		 $this->app->isClient('administrator') &&
			in_array($context, $allowed_context) &&
		 $layout == 'edit'
		)
		{
			$attribs = new JRegistry;
			$attribs->loadString($article->attribs);
			$attribs = $attribs->toObject();
			if (
			 !is_array($attribs->autorenaliase) ||
				!count($attribs->autorenaliase)
			)
			{
				$this->app->enqueueMessage(JText::_('PLG_SYSTEM_ARTICLESUBTITLEGHSVS_AUTORENALIASE_IS_EMPTY'), 'error');
				return false;
			}
		}
	} # onContentBeforeSave

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
