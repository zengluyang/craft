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
 * Tag element type
 */
class TagElementType extends BaseElementType
{
	/**
	 * Returns the element type name.
	 *
	 * @return string
	 */
	public function getName()
	{
		return Craft::t('Tags');
	}

	/**
	 * Returns whether this element type has content.
	 *
	 * @return bool
	 */
	public function hasContent()
	{
		return true;
	}

	/**
	 * Returns this element type's sources.
	 *
	 * @param string|null $context
	 * @return array|false
	 */
	public function getSources($context = null)
	{
		$sources = array();

		foreach (craft()->tags->getAllTagSets() as $tagSet)
		{
			$key = 'tagset:'.$tagSet->id;

			$sources[$key] = array(
				'label'    => $tagSet->name,
				'criteria' => array('setId' => $tagSet->id)
			);
		}

		return $sources;
	}

	/**
	 * Defines which model attributes should be searchable.
	 *
	 * @return array
	 */
	public function defineSearchableAttributes()
	{
		return array('name');
	}

	/**
	 * Returns the attributes that can be shown/sorted by in table views.
	 *
	 * @param string|null $source
	 * @return array
	 */
	public function defineTableAttributes($source = null)
	{
		return array(
			'name' => Craft::t('Name'),
		);
	}

	/**
	 * Defines any custom element criteria attributes for this element type.
	 *
	 * @return array
	 */
	public function defineCriteriaAttributes()
	{
		return array(
			'name'  => AttributeType::String,
			'set'   => AttributeType::Mixed,
			'setId' => AttributeType::Mixed,
			'order' => array(AttributeType::String, 'default' => 'name asc'),
		);
	}

	/**
	 * Modifies an element query targeting elements of this type.
	 *
	 * @param DbCommand $query
	 * @param ElementCriteriaModel $criteria
	 * @return mixed
	 */
	public function modifyElementsQuery(DbCommand $query, ElementCriteriaModel $criteria)
	{
		$query
			->addSelect('tags.setId, tags.name')
			->join('tags tags', 'tags.id = elements.id');

		if ($criteria->name)
		{
			$query->andWhere(DbHelper::parseParam('tags.name', $criteria->name, $query->params));
		}

		if ($criteria->setId)
		{
			$query->andWhere(DbHelper::parseParam('tags.setId', $criteria->setId, $query->params));
		}

		if ($criteria->set)
		{
			$query->join('tagsets tagsets', 'tagsets.id = tags.setId');
			$query->andWhere(DbHelper::parseParam('tagsets.handle', $criteria->set, $query->params));
		}
	}

	/**
	 * Populates an element model based on a query result.
	 *
	 * @param array $row
	 * @return array
	 */
	public function populateElementModel($row)
	{
		return TagModel::populateModel($row);
	}
}
