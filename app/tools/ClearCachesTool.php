<?php
namespace Craft;

/**
 * Craft by Pixel & Tonic
 *
 * @package   Craft
 * @author    Pixel & Tonic, Inc.
 * @copyright Copyright (c) 2013, Pixel & Tonic, Inc.
 * @license   http://buildwithcraft.com/license Craft License Agreement
 * @link      http://buildwithcraft.com
 */

/**
 * Clear Caches tool
 */
class ClearCachesTool extends BaseTool
{
	/**
	 * Returns the tool name.
	 *
	 * @return string
	 */
	public function getName()
	{
		return Craft::t('Clear Caches');
	}

	/**
	 * Returns the tool's icon value.
	 *
	 * @return string
	 */
	public function getIconValue()
	{
		return 'trash';
	}

	/**
	 * Returns the tool's options HTML.
	 *
	 * @return string
	 */
	public function getOptionsHtml()
	{
		return craft()->templates->render('_includes/forms/checkboxSelect', array(
			'name'    => 'folders',
			'options' => $this->_getFolders()
		));
	}

	/**
	 * Returns the tool's button label.
	 *
	 * @return string
	 */
	public function getButtonLabel()
	{
		return Craft::t('Clear!');
	}

	/**
	 * Performs the tool's action.
	 *
	 * @param array $params
	 * @return void
	 */
	public function performAction($params = array())
	{
		if (!isset($params['folders']))
		{
			return;
		}

		if ($params['folders'] == '*')
		{
			$folders = array_keys($this->_getFolders());
		}
		else
		{
			$folders = $params['folders'];
		}

		$allFolders = array_keys($this->_getFolders(false));

		foreach ($folders as $folder)
		{
			foreach ($allFolders as $allFolder)
			{
				if (md5($allFolder) == $folder)
				{
					IOHelper::clearFolder($allFolder, true);
					break;
				}
			}
		}
	}

	/**
	 * Returns the cache folders we allow to be cleared as well as any plugin cache paths that have used the 'registerCachePaths' hook.
	 *
	 * @access private
	 * @param  bool    $obfuscate If true, will MD5 the path so it will be obfuscated in the template.
	 * @return array
	 */
	private function _getFolders($obfuscate = true)
	{
		$runtimePath = craft()->path->getRuntimePath();

		$folders = array(
			$obfuscate ? md5($runtimePath.'cache') : $runtimePath.'cache'                           => Craft::t('File caches'),
			$obfuscate ? md5($runtimePath.'assets') : $runtimePath.'assets'                         => Craft::t('Asset thumbs'),
			$obfuscate ? md5($runtimePath.'compiled_templates') : $runtimePath.'compiled_templates' => Craft::t('Compiled templates'),
			$obfuscate ? md5($runtimePath.'temp') : $runtimePath.'temp'                             => Craft::t('Temp files'),
		);

		$pluginCachePaths = craft()->plugins->call('registerCachePaths');

		if (is_array($pluginCachePaths) && count($pluginCachePaths) > 0)
		{
			foreach ($pluginCachePaths as $paths)
			{
				foreach ($paths as $path => $label)
				{
					$folders[$obfuscate ? md5($path) : $path] = $label;
				}
			}
		}

		return $folders;
	}
}
