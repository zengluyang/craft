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
 * Config functions
 */
class ConfigVariable
{
	/**
	 * Returns whether a config item exists.
	 *
	 * @param string $name
	 * @return bool
	 */
	function __isset($name)
	{
		return isset(craft()->config->generalConfig[$name]);
	}

	/**
	 * Returns a config item.
	 *
	 * @param string $name
	 * @return string
	 */
	function __get($name)
	{
		return craft()->config->get($name);
	}

	/**
	 * Returns whether generated URLs should be formatted using PATH_INFO.
	 *
	 * @return bool
	 */
	public function usePathInfo()
	{
		return craft()->config->usePathInfo();
	}

	/**
	 * Returns whether generated URLs should omit 'index.php'.
	 *
	 * @return bool
	 */
	public function omitScriptNameInUrls()
	{
		return craft()->config->omitScriptNameInUrls();
	}

	/**
	 * Returns the CP resource trigger word.
	 *
	 * @return string
	 */
	public function resourceTrigger()
	{
		if (craft()->request->isCpRequest())
		{
			return 'resources';
		}
		else
		{
			return craft()->config->get('resourceTrigger');
		}
	}
}
