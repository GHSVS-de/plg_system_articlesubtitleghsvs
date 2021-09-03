<?php
// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die('Restricted access');
 
//jimport('joomla.form.formfield');

require_once JPATH_SITE . '/plugins/system/articlesubtitleghsvs/resize.php';

class JFormFieldTestImagick extends JFormField {
	
	protected $type = 'TestImagick';
	
	/*public function getLabel() {
		return JText::_('');
	}*/
	
	public function getInput() {
		$cache_folder = JPATH_SITE.'/plugins/system/articlesubtitleghsvs/test/cache';
		$image_uri =  JPATH_SITE.'/plugins/system/articlesubtitleghsvs/test/test.png';
		
		$imagick_process = str_replace('test_imagick_', '', $this->fieldname);
		$resizer = new ImgResizeCache(array('imagick_process' => $imagick_process, 'cache_folder' => $cache_folder));
		
		// problem creating folder?
		if (!file_exists($cache_folder))
		{
			return '<i class="icon-cancel"></i> '.JText::_('PLG_IMGRESIZECACHE_TESTIMAGICK_KO_FOLDER');
		}
		
		// empty cache folder
		foreach (glob($cache_folder.'/*') as $f) @unlink($f);
		
		// TEST Imagick exec
		$cached_uri = $resizer->resize($image_uri, array('w' => 100));
		
		if (file_exists($cached_uri) || file_exists($_SERVER['DOCUMENT_ROOT'].$cached_uri)/*fix rewrite URL to subfolder eg. "cms" folder*/)
			return '<i class="icon-ok"></i> '.JText::_('PLG_IMGRESIZECACHE_TESTIMAGICK_OK');
		else
			return '<i class="icon-cancel"></i> '.JText::_('PLG_IMGRESIZECACHE_TESTIMAGICK_KO');
		return '';
	}
}