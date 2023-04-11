<?php
/*
Stellt ein Formularfeld mit Kontakteinträgen (com_contact) bereit.
Für Plugin autorbeschreibungghsvs, dass die Zitierweise unter Beiträgen anzeigt.

<option value="plattee">plattee-Dr. Editha Platte</option>

2015-07-07: Bugfix Bindestrich aus z.B. plattee-Dr. Editha Platte entfernt,
da Chosen sonst Dr. nicht findet.

*/
defined('_JEXEC') or die;
JFormHelper::loadFieldClass('list');
class JFormFieldAutorenaliase extends JFormFieldList
{

	public $type = 'autorenaliase';

	protected static $options = array();

	protected function getOptions()
	{

  // Kategorie-Alias in Kontakte-Komponente
  $abCatAlias = $this->element['abCatAlias'] ? (string) $this->element['abCatAlias'] : (string) 'autorbeschreibungghsvs';
		$options = array();
  $db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->from($db->qn('#__contact_details', 'd'))
		// ->select('distinct(d.name) as value')
		->select($db->qn('d.id', 'value'));

		$query->select('CONCAT(d.alias, " ", d.name) as text')
		->join('LEFT', $db->qn('#__categories', 'c').' ON c.id = d.catid')
		->where('d.published = 1')
		->where('c.alias = '.$db->q($abCatAlias))
		->where('c.extension = '.$db->q('com_contact'))
		->order('d.alias ASC')
		;

  $blist = array(JHTML::_('select.option', '', JText::_('--Alias wählen--')));
  $db->setQuery($query);

  // Return the result
  if ($options = $db->loadObjectList())
  {
   $options = array_merge(parent::getOptions(), $options);
  }
		return $options;
	}
}
