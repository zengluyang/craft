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
 * Handles global set management tasks
 */
class GlobalsController extends BaseController
{
	/**
	 * Saves a global set.
	 */
	public function actionSaveSet()
	{
		$this->requirePostRequest();
		craft()->userSession->requireAdmin();

		$globalSet = new GlobalSetModel();

		// Set the simple stuff
		$globalSet->id     = craft()->request->getPost('setId');
		$globalSet->name   = craft()->request->getPost('name');
		$globalSet->handle = craft()->request->getPost('handle');

		// Set the field layout
		$fieldLayout = craft()->fields->assembleLayoutFromPost(false);
		$fieldLayout->type = ElementType::GlobalSet;
		$globalSet->setFieldLayout($fieldLayout);

		// Save it
		if (craft()->globals->saveSet($globalSet))
		{
			craft()->userSession->setNotice(Craft::t('Global set saved.'));

			// TODO: Remove for 2.0
			if (isset($_POST['redirect']) && mb_strpos($_POST['redirect'], '{setId}') !== false)
			{
				Craft::log('The {setId} token within the ‘redirect’ param on globals/saveSet requests has been deprecated. Use {id} instead.', LogLevel::Warning);
				$_POST['redirect'] = str_replace('{setId}', '{id}', $_POST['redirect']);
			}

			$this->redirectToPostedUrl($globalSet);
		}
		else
		{
			craft()->userSession->setError(Craft::t('Couldn’t save global set.'));
		}

		// Send the global set back to the template
		craft()->urlManager->setRouteVariables(array(
			'globalSet' => $globalSet
		));
	}

	/**
	 * Deletes a global set.
	 */
	public function actionDeleteSet()
	{
		$this->requirePostRequest();
		$this->requireAjaxRequest();
		craft()->userSession->requireAdmin();

		$globalSetId = craft()->request->getRequiredPost('id');

		craft()->globals->deleteSetById($globalSetId);
		$this->returnJson(array('success' => true));
	}

	/**
	 * Edits a global set's content.
	 *
	 * @param array $variables
	 */
	public function actionEditContent(array $variables = array())
	{
		// Make sure a specific global set was requested
		if (empty($variables['globalSetHandle']))
		{
			throw new HttpException(400, Craft::t('Param “{name}” doesn’t exist.', array('name' => 'globalSetHandle')));
		}

		// Get the locales the user is allowed to edit
		$editableLocaleIds = craft()->i18n->getEditableLocaleIds();

		// Editing a specific locale?
		if (isset($variables['localeId']))
		{
			// Make sure the user has permission to edit that locale
			if (!in_array($variables['localeId'], $editableLocaleIds))
			{
				throw new HttpException(404);
			}
		}
		else
		{
			// Are they allowed to edit the current app locale?
			if (in_array(craft()->language, $editableLocaleIds))
			{
				$variables['localeId'] = craft()->language;
			}
			else
			{
				// Just use the first locale they are allowed to edit
				$variables['localeId'] = $editableLocaleIds[0];
			}
		}

		// Get the global sets the user is allowed to edit, in the requested locale
		$variables['globalSets'] = array();

		$criteria = craft()->elements->getCriteria(ElementType::GlobalSet);
		$criteria->locale = $variables['localeId'];
		$globalSets = $criteria->find();

		foreach ($globalSets as $globalSet)
		{
			if (craft()->userSession->checkPermission('editGlobalSet:'.$globalSet->id))
			{
				$variables['globalSets'][$globalSet->handle] = $globalSet;
			}
		}

		if (!$variables['globalSets'] || !isset($variables['globalSets'][$variables['globalSetHandle']]))
		{
			throw new HttpException(404);
		}

		if (!isset($variables['globalSet']))
		{
			$variables['globalSet'] = $variables['globalSets'][$variables['globalSetHandle']];
		}

		// Render the template!
		$this->renderTemplate('globals/_edit', $variables);
	}

	/**
	 * Saves a global set's content.
	 */
	public function actionSaveContent()
	{
		$this->requirePostRequest();

		$globalSetId = craft()->request->getRequiredPost('setId');
		$localeId = craft()->request->getPost('locale', craft()->i18n->getPrimarySiteLocaleId());

		// Make sure the user is allowed to edit this global set and locale
		craft()->userSession->requirePermission('editGlobalSet:'.$globalSetId);

		if (craft()->hasPackage(CraftPackage::Localize))
		{
			craft()->userSession->requirePermission('editLocale:'.$localeId);
		}

		$criteria = craft()->elements->getCriteria(ElementType::GlobalSet);
		$criteria->id = $globalSetId;
		$criteria->locale = $localeId;
		$globalSet = $criteria->first();

		if (!$globalSet)
		{
			throw new Exception(Craft::t('No global set exists with the ID “{id}”.', array('id' => $globalSetId)));
		}

		$fields = craft()->request->getPost('fields');
		$globalSet->getContent()->setAttributes($fields);

		if (craft()->globals->saveContent($globalSet))
		{
			craft()->userSession->setNotice(Craft::t('Globals saved.'));
			$this->redirectToPostedUrl();
		}
		else
		{
			craft()->userSession->setError(Craft::t('Couldn’t save globals.'));
		}

		// Send the global set back to the template
		craft()->urlManager->setRouteVariables(array(
			'globalSet' => $globalSet,
		));
	}
}
