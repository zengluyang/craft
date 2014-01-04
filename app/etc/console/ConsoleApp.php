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
 *
 */
class ConsoleApp extends \CConsoleApplication
{
	public $componentAliases;

	/**
	 *
	 */
	public function init()
	{
		// Set default timezone to UTC
		date_default_timezone_set('UTC');

		foreach ($this->componentAliases as $alias)
		{
			Craft::import($alias);
		}

		craft()->getComponent('log');

		// Attach our Craft app behavior.
		$this->attachBehavior('AppBehavior', new AppBehavior());

		// Set our own custom runtime path.
		$this->setRuntimePath(craft()->path->getRuntimePath());

		// No need for these.
		craft()->log->removeRoute('WebLogRoute');
		craft()->log->removeRoute('ProfileLogRoute');

		// Load the plugins
		craft()->plugins->loadPlugins();

		parent::init();
	}

	/**
	 * @return ConsoleCommandRunner
	 */
	protected function createCommandRunner()
	{
		return new ConsoleCommandRunner();
	}

	/**
	 * Returns whether we are executing in the context on a console app.
	 *
	 * @return bool
	 */
	public function isConsole()
	{
		return true;
	}
}
