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
class TagsController extends BaseController
{
	/**
	 * Tag settings index.
	 */
	public function actionIndex()
	{
		craft()->userSession->requireAdmin();

		$tagSets = craft()->tags->getAllTagSets();

		$this->renderTemplate('settings/tags/index', array(
			'tagSets' => $tagSets
		));
	}

	/**
	 * Edit a tag set.
	 *
	 * @param array $variables
	 */
	public function actionEditTagSet(array $variables = array())
	{
		craft()->userSession->requireAdmin();

		// Breadcrumbs
		$variables['crumbs'] = array(
			array('label' => Craft::t('Settings'), 'url' => UrlHelper::getUrl('settings')),
			array('label' => Craft::t('Tags'),  'url' => UrlHelper::getUrl('settings/tags'))
		);

		if (!empty($variables['tagSetId']))
		{
			if (empty($variables['tagSet']))
			{
				$variables['tagSet'] = craft()->tags->getTagSetById($variables['tagSetId']);

				if (!$variables['tagSet'])
				{
					throw new HttpException(404);
				}
			}

			$variables['title'] = $variables['tagSet']->name;
		}
		else
		{
			if (empty($variables['tagSet']))
			{
				$variables['tagSet'] = new TagSetModel();
			}

			$variables['title'] = Craft::t('Create a new tag set');
		}

		$variables['tabs'] = array(
			'settings'    => array('label' => Craft::t('Settings'), 'url' => '#tagset-settings'),
			'fieldLayout' => array('label' => Craft::t('Field Layout'), 'url' => '#tagset-fieldlayout')
		);

		$this->renderTemplate('settings/tags/_edit', $variables);
	}

	/**
	 * Save a tag set.
	 */
	public function actionSaveTagSet()
	{
		$this->requirePostRequest();
		craft()->userSession->requireAdmin();

		$tagSet = new TagSetModel();

		// Set the simple stuff
		$tagSet->id     = craft()->request->getPost('tagSetId');
		$tagSet->name   = craft()->request->getPost('name');
		$tagSet->handle = craft()->request->getPost('handle');

		// Set the field layout
		$fieldLayout = craft()->fields->assembleLayoutFromPost(false);
		$fieldLayout->type = ElementType::Tag;
		$tagSet->setFieldLayout($fieldLayout);

		// Save it
		if (craft()->tags->saveTagSet($tagSet))
		{
			craft()->userSession->setNotice(Craft::t('Tag set saved.'));
			$this->redirectToPostedUrl($tagSet);
		}
		else
		{
			craft()->userSession->setError(Craft::t('Couldn’t save the tag set.'));
		}

		// Send the tag set back to the template
		craft()->urlManager->setRouteVariables(array(
			'tagSet' => $tagSet
		));
	}

	/**
	 * Deletes a tag set.
	 */
	public function actionDeleteTagSet()
	{
		$this->requirePostRequest();
		$this->requireAjaxRequest();
		craft()->userSession->requireAdmin();

		$sectionId = craft()->request->getRequiredPost('id');

		craft()->tags->deleteTagSetById($sectionId);
		$this->returnJson(array('success' => true));
	}

	/**
	 * Searches for tags.
	 */
	public function actionSearchForTags()
	{
		$this->requirePostRequest();
		$this->requireAjaxRequest();

		$search = craft()->request->getPost('search');
		$tagSetId = craft()->request->getPost('tagSetId');
		$excludeIds = craft()->request->getPost('excludeIds', array());

		$notIds = array('and');

		foreach ($excludeIds as $id)
		{
			$notIds[] = 'not '.$id;
		}

		$criteria = craft()->elements->getCriteria(ElementType::Tag);
		$criteria->setId  = $tagSetId;
		$criteria->search = 'name:'.$search.'*';
		$criteria->id     = $notIds;
		$tags = $criteria->find();

		$return = array();
		$exactMatches = array();
		$tagNameLengths = array();
		$exactMatch = false;

		$normalizedSearch = StringHelper::normalizeKeywords($search);

		foreach ($tags as $tag)
		{
			$return[] = array(
				'id'   => $tag->id,
				'name' => $tag->name
			);

			$tagNameLengths[] = mb_strlen($tag->name);

			$normalizedName = StringHelper::normalizeKeywords($tag->name);

			if ($normalizedName == $normalizedSearch)
			{
				$exactMatches[] = 1;
				$exactMatch = true;
			}
			else
			{
				$exactMatches[] = 0;
			}
		}

		array_multisort($exactMatches, SORT_DESC, $tagNameLengths, $return);

		$this->returnJson(array(
			'tags'       => $return,
			'exactMatch' => $exactMatch
		));
	}

	/**
	 * Edits a tag's content.
	 */
	public function actionEditTagContent()
	{
		$this->requireLogin();
		$this->requireAjaxRequest();

		$requestId = craft()->request->getPost('requestId', 0);
		$tagId = craft()->request->getRequiredPost('elementId');
		$tag = craft()->tags->getTagById($tagId);

		if (!$tag)
		{
			throw new Exception(Craft::t('No tag exists with the ID “{id}”.', array('id' => $tagId)));
		}

		$html = craft()->templates->render('_includes/edit_element', array(
			'element'     => $tag,
			'hasTitle'    => false,
			'fieldLayout' => $tag->getSet()->getFieldLayout()
		));

		$this->returnJson(array(
			'requestId' => $requestId,
			'headHtml' => craft()->templates->getHeadHtml(),
			'bodyHtml' => $html,
			'footHtml' => craft()->templates->getFootHtml(),
		));
	}

	/**
	 * Saves a tag's content.
	 */
	public function actionSaveTagContent()
	{
		$this->requireLogin();
		$this->requireAjaxRequest();

		$tagId = craft()->request->getRequiredPost('elementId');

		$tag = craft()->tags->getTagById($tagId);

		if (!$tag)
		{
			throw new Exception(Craft::t('No tag exists with the ID “{id}”.', array('id' => $tagId)));
		}

		$fieldNamespace = craft()->request->getPost('fieldNamespace');
		$fields = craft()->request->getPost($fieldNamespace);
		$tag->getContent()->setAttributes($fields);

		$success = craft()->tags->saveTagContent($tag);

		$this->returnJson(array(
			'success' => true,
			'title'   => (string) $tag
		));
	}
}
