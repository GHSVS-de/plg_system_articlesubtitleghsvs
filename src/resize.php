<?php
/**
 * @copyright   Copyright (C) 2013 S2 Software di Stefano Storti. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Filesystem\Folder;
use Joomla\CMS\Filesystem\File;

class ImgResizeCache
{
	protected $imagick_process;
	protected $imagick_path_to_convert;
	protected $cache_folder;

	public function __construct($params = array())
	{
		// Load plugin config
		$plugin = JPluginHelper::getPlugin('system', 'articlesubtitleghsvs');

		$pluginParams = class_exists('JParameter') ? new JParameter($plugin->params) : new JRegistry(@$plugin->params);

		$this->imagick_process = isset($params['imagick_process']) ? $params['imagick_process'] : $pluginParams->get('imagick_process', 'class');

		$this->imagick_path_to_convert = isset($params['imagick_path_to_convert']) ? $params['imagick_path_to_convert'] : $pluginParams->get('imagick_path_to_convert', 'convert');

		$this->cache_folder = isset($params['cache_folder']) ? $params['cache_folder'] : $pluginParams->get('cache_folder', 'cache');

		// Make cache folder if not exists (used by resize function)
		if (!file_exists($this->cache_folder))
		{
			//mkdir($this->cache_folder, 0777);
			Folder::create($this->cache_folder, 0777);
		}
		if (!file_exists($this->cache_folder.'/remote'))
		{
			//mkdir($this->cache_folder.'/remote');
			Folder::create($this->cache_folder.'/remote', 0777);
		}
	}

	public function resize($imagePath, $opts)
	{
		if (!$opts) return $imagePath;
		if (!$this->_checkImage($imagePath)) return $imagePath;
		return $this->_resize($imagePath, $opts);
	}

	/**
	 * Avoid errors if image corrupted
	 * @param string $image_path
	 * @return boolean
	 */
	protected function _checkImage($imagePath)
	{
		try
		{
			if (substr($imagePath, 0, 7) == 'http://' || substr($imagePath, 0, 8) == 'https://') //remote
				$imagePath = str_replace(' ', '%20', $imagePath);
			@$size = getimagesize($imagePath);
			if (!$size) return false;
			return true;
		} catch (Exception $e) {
			return false;
		}
	}

	// https://github.com/wes/phpimageresize
	/**
	 * function by Wes Edling .. http://joedesigns.com
	 * feel free to use this in any project, i just ask for a credit in the source code.
	 * a link back to my site would be nice too.
	 *
	 *
	 * Changes:
	 * 2012/01/30 - David Goodwin - call escapeshellarg on parameters going into the shell
	 * 2012/07/12 - Whizzkid - Added support for encoded image urls and images on ssl secured servers [https://]
	 */

	/**
	 * SECURITY:
	 * It's a bad idea to allow user supplied data to become the path for the image you wish to retrieve, as this allows them
	 * to download nearly anything to your server. If you must do this, it's strongly advised that you put a .htaccess file
	 * in the cache directory containing something like the following :
	 * <code>php_flag engine off</code>
	 * to at least stop arbitrary code execution. You can deal with any copyright infringement issues yourself :)
	 */

	/**
	 * @param string $imagePath - a local absolute/relative path.
	 * @param array $opts  (w(pixels), h(pixels), crop(boolean), scale(boolean), thumbnail(boolean), maxOnly(boolean), canvas-color(#abcabc), output-filename(string), cache_http_minutes(int))
	 * @return new URL for resized image.
	 */
	protected function _resize($imagePath,$opts=null){

		// Remote Bilder? 2015-04-25: REMOTEBILDER WERDEN NICHT OPTIMIERT!!!!!!
#DEBUG:
#$imagePathRem = 'http://www.xyz.de/reisepeise/blahblubber images dings%20jkl.jpg?do=this';
		$purl = parse_url($imagePath);
		if (
		 isset($purl['scheme'])
			# && in_array($purl['scheme'], array('http', 'https'))
		)
		{
			return str_replace(' ', '%20', $imagePath);
		}

		$origBild = JPath::clean(trim($imagePath, '\\/'));

		$origBildAbs = JPATH_SITE.'/'.$origBild;

		if(!File::exists($origBildAbs))
		{
			// Egal. Hauptsache falschen Pfad zurück, damit man im FE recherchieren kann.
			return $imagePath;
		}

		$cacheFolder = JPath::clean(trim($this->cache_folder, '\\/'));

		$defaults = array(
		 'crop' => false,
			'scale' => false,
			'thumbnail' => false,
			'maxOnly' => false,
			'canvas-color' => 'transparent',
			'output-filename' => false,
			'cacheFolder' => $cacheFolder,
			'quality' => 80,
			'cache_http_minutes' => 1440,
			'bestfit' => true,
			'fill' => true,
		);

		$opts = array_merge($defaults, $opts);

		$imagesize = getimagesize($origBildAbs);

		if ($opts['maxOnly'])
		{
			if (isset($opts['w']))
			{
				if ($opts['w'] > $imagesize[0])
				{
					$opts['w'] = $imagesize[0];
				}
			}
			if (isset($opts['h']))
			{
				if ($opts['h'] > $imagesize[1])
				{
					$opts['h'] = $imagesize[1];
				}
			}
			$opts['maxOnly'] = false;
		}

		// fix crop: in some cases doesn't work (in exec mode)
		if ($opts['crop'] && $this->imagick_process == 'exec')
		{
			if (
			 $imagesize[0] > $imagesize[1]
				&& $imagesize[0]/$imagesize[1] < $opts['w']/$opts['h']
				|| $imagesize[0] < $imagesize[1] && $imagesize[0]/$imagesize[1] > $opts['w']/$opts['h']
			)
			{
				$opts['crop'] = false;
				$opts['resize'] = true;
			}
		}

		$path_to_convert = $this->imagick_path_to_convert;
		$finfo = pathinfo($origBild);

		$w = $h = false;

		$neuBildPfad = $cacheFolder.'/'.$finfo['dirname'];

		$neuBildName = array();
		$neuBildName[] = $finfo['filename'];
		if (!empty($opts['w']))
		{
			$w = $opts['w'];
			$neuBildName[] = 'w'.$w;
		}
		if (!empty($opts['h']))
		{
			$h = $opts['h'];
			$neuBildName[] = 'h'.$h;
		}
		if ($w && $h)
		{
		 if (!empty($opts['crop']))
		 {
			 $neuBildName[] = 'cp';
		 }
		 if (!empty($opts['scale']))
		 {
			 $neuBildName[] = 'sc';
		 }
		}
		$neuBildName = implode('_', $neuBildName).'.'.$finfo['extension'];

		$neuBild = $neuBildPfad.'/'.$neuBildName;
  $neuBildAbs = JPATH_SITE.'/'.$neuBild;

		if (File::exists($neuBildAbs))
		{
			return $neuBild;
		}

		if (!Folder::exists($neuBildPfad))
		{
			Folder::create($neuBildPfad);
		}
		$create = true;
		if ($this->imagick_process == 'exec')
		{
			if ($w && $h)
			{
				list($width,$height) = getimagesize($origBildAbs);

				$resize = $w;

				if ($width > $height)
				{
					if ($opts['crop']) $resize = "x".$h;
				}
				else
				{
					$resize = "x".$h;
					if ($opts['crop']) $resize = $w;
				}

				if ($opts['scale'])
				{
					$cmd = $path_to_convert ." ". escapeshellarg($origBildAbs) ." -resize ". escapeshellarg($resize) .
					" -quality ". escapeshellarg($opts['quality']) . " " . escapeshellarg($neuBildAbs);
				}
				else
				{
					$cmd = $path_to_convert." ". escapeshellarg($origBildAbs) ." -resize ". escapeshellarg($resize) .
					" -size ". escapeshellarg($w ."x". $h) .
					" xc:". escapeshellarg($opts['canvas-color']) .
					" +swap -gravity center -composite -quality ". escapeshellarg($opts['quality'])." ".escapeshellarg($neuBildAbs);
				}

			} #if ($w && $h)
			else
			{
				$cmd = $path_to_convert." " . escapeshellarg($origBildAbs) .
				" -thumbnail ". (!empty($w) ? $w:'') . 'x' . (!empty($h) ? $h:'') ."".
				(isset($opts['maxOnly']) && $opts['maxOnly'] == true ? "\>" : "") .
				" -quality ". escapeshellarg($opts['quality']) ." ". escapeshellarg($neuBildAbs);
			}
			$c = exec($cmd, $output, $return_code);
			if($return_code != 0) {
				error_log("Tried to execute : $cmd, return code: $return_code, output: " . print_r($output, true));
				return false;
			}
		}
		elseif ($this->imagick_process == 'class' && class_exists('Imagick') && extension_loaded('imagick'))
		{
			if (empty($w)) $w = 0;
			if (empty($h)) $h = 0;

			$imagick = new Imagick(realpath($origBildAbs));

			$imagick->setImageCompressionQuality($opts['quality']);

			if (!empty($opts['canvas-color']))
			{
				$imagick->setimagebackgroundcolor($opts['canvas-color']);
			}
			if($opts['scale'] == true)
			{
				if ($w > 0 && $h > 0)
					$imagick->scaleImage($w, $h, $opts['bestfit']);
				else
					$imagick->scaleImage($w, $h);
			}
			elseif($opts['crop'] == true)
			{
				$imagick->cropThumbnailImage($w, $h);
			}
			else
			{
				if ($w > 0 && $h > 0)
					$imagick->thumbnailImage($w, $h, $opts['bestfit'], $opts['fill']);
				else
					$imagick->thumbnailImage($w, $h);
			}

			$imagick->writeImage($neuBildAbs);

		}
		elseif ($this->imagick_process == 'jimage' && class_exists('JImage') && extension_loaded('gd'))
		{
			if (empty($w)) $w = 0;
			if (empty($h)) $h = 0;

			// Keep proportions if w or h is not defined
			list($width, $height) = getimagesize($origBildAbs);
			if (!$w) $w = ($h / $height) * $width;
			if (!$h) $h = ($w / $width) * $height;

			try {
				$image = new JImage($origBildAbs);
			} catch (Exception $e) {
				return $imagePath;
			}
			if ($opts['crop'] == true)
			{
				$rw = $w;
				$rh = $h;
				if ($width/$height < $rw/$rh) {
					$rw = $w;
					$rh = ($rw / $width) * $height;
				}
				else {
					$rh = $h;
					$rw = ($rh / $height) * $width;
				}
				$resizedImage = $image->resize($rw, $rh)->crop($w, $h);
			}
			else
			{
				$resizedImage = $image->resize($w, $h);
			}

			$properties = JImage::getImageFileProperties($origBildAbs);
			// fix compression level must be 0 through 9 (in case of png)
			$quality = $opts['quality'];
			if ($properties->type == IMAGETYPE_PNG)
			{
				$quality = round(9 - $quality * 9/100);	// 100 quality = 0 compression, 0 quality = 9 compression
			}

			$resizedImage->toFile($neuBildAbs, $properties->type, array('quality' => $quality));

		}
		return $neuBild;
	}
}
