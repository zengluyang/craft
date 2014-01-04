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
 * Entry model class
 */
class EntryModel extends BaseElementModel
{
	protected $elementType = ElementType::Entry;

	private $_ancestors;
	private $_descendants;
	private $_parent;

	const LIVE     = 'live';
	const PENDING  = 'pending';
	const EXPIRED  = 'expired';

	/**
	 * @access protected
	 * @return array
	 */
	protected function defineAttributes()
	{
		return array_merge(parent::defineAttributes(), array(
			'sectionId'  => AttributeType::Number,
			'typeId'     => AttributeType::Number,
			'authorId'   => AttributeType::Number,
			'root'       => AttributeType::Number,
			'lft'        => AttributeType::Number,
			'rgt'        => AttributeType::Number,
			'depth'      => AttributeType::Number,
			'slug'       => AttributeType::String,
			'postDate'   => AttributeType::DateTime,
			'expiryDate' => AttributeType::DateTime,

			// Just used for saving entries
			'parentId'   => AttributeType::Number,
		));
	}

	/**
	 * Returns the reference string to this element.
	 *
	 * @return string|null
	 */
	public function getRef()
	{
		return $this->getSection()->handle.'/'.$this->slug;
	}

	/**
	 * Returns the entry's section.
	 *
	 * @return SectionModel|null
	 */
	public function getSection()
	{
		if ($this->sectionId)
		{
			return craft()->sections->getSectionById($this->sectionId);
		}
	}

	/**
	 * Returns the type of entry.
	 *
	 * @return EntryTypeModel|null
	 */
	public function getType()
	{
		$section = $this->getSection();

		if ($section)
		{
			$sectionEntryTypes = $section->getEntryTypes('id');

			if ($sectionEntryTypes)
			{
				if ($this->typeId && isset($sectionEntryTypes[$this->typeId]))
				{
					return $sectionEntryTypes[$this->typeId];
				}
				else
				{
					// Just return the first one
					return $sectionEntryTypes[array_shift(array_keys($sectionEntryTypes))];
				}
			}
		}
	}

	/**
	 * Returns the entry's author.
	 *
	 * @return UserModel|null
	 */
	public function getAuthor()
	{
		if ($this->authorId)
		{
			return craft()->users->getUserById($this->authorId);
		}
	}

	/**
	 * Returns the element's status.
	 *
	 * @return string|null
	 */
	public function getStatus()
	{
		$status = parent::getStatus();

		if ($status == static::ENABLED && $this->postDate)
		{
			$currentTime = DateTimeHelper::currentTimeStamp();
			$postDate    = $this->postDate->getTimestamp();
			$expiryDate  = ($this->expiryDate ? $this->expiryDate->getTimestamp() : null);

			if ($postDate <= $currentTime && (!$expiryDate || $expiryDate > $currentTime))
			{
				return static::LIVE;
			}
			else if ($postDate > $currentTime)
			{
				return static::PENDING;
			}
			else
			{
				return static::EXPIRED;
			}
		}

		return $status;
	}

	/**
	 * Returns the element's CP edit URL.
	 *
	 * @return string|false
	 */
	public function getCpEditUrl()
	{
		if ($this->getSection())
		{
			return UrlHelper::getCpUrl('entries/'.$this->getSection()->handle.'/'.$this->id);
		}
	}

	/**
	 * Returns the entry's ancestors.
	 *
	 * @param int|null $dist
	 * @return array
	 */
	public function getAncestors($dist = null)
	{
		if (!isset($this->_ancestors))
		{
			if ($this->id)
			{
				$criteria = craft()->elements->getCriteria($this->elementType);
				$criteria->ancestorOf = $this;
				$criteria->locale = $this->locale;
				$this->_ancestors = $criteria->find();
			}
			else
			{
				$this->_ancestors = array();
			}
		}

		if ($dist)
		{
			return array_slice($this->_ancestors, count($this->_ancestors) - $dist);
		}
		else
		{
			return $this->_ancestors;
		}
	}

	/**
	 * Sets the entry's ancestors.
	 *
	 * @param array $ancestors
	 */
	public function setAncestors($ancestors)
	{
		$this->_ancestors = $ancestors;
	}

	/**
	 * Get the entry's parent.
	 *
	 * @return EntryModel|null
	 */
	public function getParent()
	{
		if (!isset($this->_parent))
		{
			$parent = $this->getAncestors(1);

			if ($parent)
			{
				$this->_parent = $parent[0];
			}
			else
			{
				$this->_parent = false;
			}
		}

		if ($this->_parent !== false)
		{
			return $this->_parent;
		}
	}

	/**
	 * Sets the entry's parent.
	 *
	 * @param EntryModel $parent
	 */
	public function setParent($parent)
	{
		$this->_parent = $parent;
	}

	/**
	 * Overrides the (deprecated) BaseElementModel::getParents() so it only works for Channel sections, until it's removed altogether.
	 *
	 * @param mixed $field
	 * @return null|ElementCriteriaModel
	 */
	public function getParents($field = null)
	{
		if ($this->getSection()->type == Sectiontype::Channel)
		{
			return parent::getParents($field);
		}
	}

	/**
	 * Returns all of the entry's siblings.
	 *
	 * @return array
	 */
	public function getSiblings()
	{
		if ($this->depth == 1)
		{
			$criteria = craft()->elements->getCriteria($this->elementType);
			$criteria->depth(1);
			$criteria->id = 'not '.$this->id;
			$criteria->sectionId = $this->sectionId;
			$criteria->locale = $this->locale;
			return $criteria->find();
		}
		else
		{
			$parent = $this->getParent();

			if ($parent)
			{
				$criteria = craft()->elements->getCriteria($this->elementType);
				$criteria->descendantOf($parent);
				$criteria->descendantDist(1);
				$criteria->id = 'not '.$this->id;
				$criteria->locale = $this->locale;
				return $criteria->find();
			}
			else
			{
				return array();
			}
		}
	}

	/**
	 * Returns the entry's previous sibling.
	 *
	 * @return EntryModel|null
	 */
	public function getPrevSibling()
	{
		$criteria = craft()->elements->getCriteria($this->elementType);
		$criteria->prevSiblingOf($this);
		$criteria->locale = $this->locale;
		return $criteria->first();
	}

	/**
	 * Returns the entry's next sibling.
	 *
	 * @return EntryModel|null
	 */
	public function getNextSibling()
	{
		$criteria = craft()->elements->getCriteria($this->elementType);
		$criteria->nextSiblingOf($this);
		$criteria->locale = $this->locale;
		return $criteria->first();
	}

	/**
	 * Overrides the (deprecated) BaseElementModel::getChildren() so that it returns the actual children for entries within Structure sections.
	 *
	 * @param mixed $field
	 * @return array|ElementCriteriaModel
	 */
	public function getChildren($field = null)
	{
		if ($this->getSection()->type == Sectiontype::Channel)
		{
			return parent::getChildren($field);
		}
		else
		{
			$criteria = craft()->elements->getCriteria($this->elementType);
			$criteria->descendantOf($this);
			$criteria->descendantDist(1);
			$criteria->locale = $this->locale;
			return $criteria->find();
		}
	}

	/**
	 * Returns the entry's descendants.
	 *
	 * @param int|null $dist
	 * @return array
	 */
	public function getDescendants($dist = null)
	{
		if (!isset($this->_descendants))
		{
			if ($this->id)
			{
				$criteria = craft()->elements->getCriteria($this->elementType);
				$criteria->descendantOf = $this;
				$criteria->locale = $this->locale;
				$this->_descendants = $criteria->find();
			}
			else
			{
				$this->_descendants = array();
			}
		}

		if ($dist)
		{
			return array_slice($this->_descendants, 0, $dist);
		}
		else
		{
			return $this->_descendants;
		}
	}

	/**
	 * Sets the entry's descendants.
	 *
	 * @param array $descendants
	 */
	public function setDescendants($descendants)
	{
		$this->_descendants = $descendants;
	}

	/**
	 * Returns whether this entry is an ancestor of another one.
	 *
	 * @param EntryModel $entry
	 * @return bool
	 */
	public function isAncestorOf(EntryModel $entry)
	{
		return ($this->lft < $entry->lft && $this->rgt > $entry->rgt);
	}

	/**
	 * Returns whether this entry is a descendant of another one.
	 *
	 * @param EntryModel $entry
	 * @return bool
	 */
	public function isDescendantOf(EntryModel $entry)
	{
		return ($this->lft > $entry->lft && $this->rgt < $entry->rgt);
	}

	/**
	 * Returns whether this entry is a direct parent of another one.
	 *
	 * @param EntryModel $entry
	 * @return bool
	 */
	public function isParentOf(EntryModel $entry)
	{
		return ($this->depth == $entry->depth - 1 && $this->isAncestorOf($entry));
	}

	/**
	 * Returns whether this entry is a direct child of another one.
	 *
	 * @param EntryModel $entry
	 * @return bool
	 */
	public function isChildOf(EntryModel $entry)
	{
		return ($this->depth == $entry->depth + 1 && $this->isDescendantOf($entry));
	}

	/**
	 * Returns whether this entry is a sibling of another one.
	 *
	 * @param EntryModel $entry
	 * @return bool
	 */
	public function isSiblingOf(EntryModel $entry)
	{
		if ($this->depth && $this->depth == $entry->depth)
		{
			if ($this->depth == 1 || $this->isPrevSiblingOf($entry) || $this->isNextSiblingOf($entry))
			{
				return true;
			}
			else
			{
				$parent = $this->getParent();

				if ($parent)
				{
					return $entry->isDescendantOf($parent);
				}
			}
		}

		return false;
	}

	/**
	 * Returns whether this entry is the direct previous sibling of another one.
	 *
	 * @param EntryModel $entry
	 * @return bool
	 */
	public function isPrevSiblingOf(EntryModel $entry)
	{
		return ($this->depth == $entry->depth && $this->rgt == $entry->lft - 1);
	}

	/**
	 * Returns whether this entry is the direct next sibling of another one.
	 *
	 * @param EntryModel $entry
	 * @return bool
	 */
	public function isNextSiblingOf(EntryModel $entry)
	{
		return ($this->depth == $entry->depth && $this->lft == $entry->rgt + 1);
	}
}
