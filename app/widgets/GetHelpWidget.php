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
 * Get Help widget
 */
class GetHelpWidget extends BaseWidget
{
	/**
	 * @access protected
	 * @var bool Whether users should be able to select more than one of this widget type.
	 */
	protected $multi = false;

	/**
	 * Returns the type of widget this is.
	 *
	 * @return string
	 */
	public function getName()
	{
		return Craft::t('Get Help');
	}

	/**
	 * Gets the widget's title.
	 *
	 * @return string
	 */
	public function getTitle()
	{
		return Craft::t('Send a message to Craft Support');
	}

	/**
	 * Returns the widget's body HTML.
	 *
	 * @return string|false
	 */
	public function getBodyHtml()
	{
		$id = $this->model->id;
		$js = "new Craft.GetHelpWidget({$id});";
		craft()->templates->includeJs($js);

		craft()->templates->includeJsResource('js/GetHelpWidget.js');
		craft()->templates->includeTranslations('Message sent successfully.', 'Couldn’t send support request.');


		$message = "Enter your message here.\n\n" .
			"------------------------------\n\n" .
			'Craft version: ' .
			Craft::t('{version} build {build}', array(
				'version' => craft()->getVersion(),
				'build'   => craft()->getBuild()
			))."\n" .
			'Packages: '.implode(', ', craft()->getPackages());

		$plugins = craft()->plugins->getPlugins();

		if ($plugins)
		{
			$pluginNames = array();

			foreach ($plugins as $plugin)
			{
				$pluginNames[] = $plugin->getName().' ('.$plugin->getDeveloper().')';
			}

			$message .= "\nPlugins: ".implode(', ', $pluginNames);
		}

		return craft()->templates->render('_components/widgets/GetHelp/body', array(
			'message' => $message
		));
	}

	/**
	 * @return bool
	 */
	public function isSelectable()
	{
		// Only admins get the Get Help widget.
		if (parent::isSelectable() && craft()->userSession->isAdmin())
		{
			return true;
		}

		return false;
	}
}
