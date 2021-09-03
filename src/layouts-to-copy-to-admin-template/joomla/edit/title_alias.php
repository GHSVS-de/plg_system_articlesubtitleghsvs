<?php
/**
 * @package     Joomla.Cms
 * @subpackage  Layout
 *
 * @copyright   Copyright (C) 2005 - 2014 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('JPATH_BASE') or die;

$form = $displayData->getForm();

$title = $form->getField('title') ? 'title' : ($form->getField('name') ? 'name' : '');

$subtitlename1 = 'articlesubtitle2';

?>
<div class="form-inline form-inline-header">
	<?php
	echo $title ? $form->renderField($title) : '';
	echo $form->renderField('alias');
	if ( ($articlesubtitleghsvs = $form->getField($subtitlename1, 'attribs')) )
	{
		echo '<br />'.$form->renderField($subtitlename1, 'attribs');
	}
	?>
</div>
