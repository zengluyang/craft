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
 * Stores Matrix blocks
 */
class MatrixBlockRecord extends BaseRecord
{
	/**
	 * @return string
	 */
	public function getTableName()
	{
		return 'matrixblocks';
	}

	/**
	 * @access protected
	 * @return array
	 */
	protected function defineAttributes()
	{
		return array(
			'sortOrder' => AttributeType::SortOrder,
		);
	}

	/**
	 * @return array
	 */
	public function defineRelations()
	{
		return array(
			'element' => array(static::BELONGS_TO, 'ElementRecord', 'id', 'required' => true, 'onDelete' => static::CASCADE),
			'owner'   => array(static::BELONGS_TO, 'ElementRecord', 'required' => true, 'onDelete' => static::CASCADE),
			'field'   => array(static::BELONGS_TO, 'FieldRecord', 'required' => true, 'onDelete' => static::CASCADE),
			'type'    => array(static::BELONGS_TO, 'MatrixBlockTypeRecord', 'onDelete' => static::CASCADE),
		);
	}

	/**
	 * @return array
	 */
	public function defineIndexes()
	{
		return array(
			array('columns' => array('ownerId')),
			array('columns' => array('fieldId')),
			array('columns' => array('typeId')),
			array('columns' => array('sortOrder')),
		);
	}
}
