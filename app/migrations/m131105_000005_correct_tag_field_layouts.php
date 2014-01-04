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
 * The class name is the UTC timestamp in the format of mYYMMDD_HHMMSS_migrationName
 */
class m131105_000005_correct_tag_field_layouts extends BaseMigration
{
	/**
	 * Any migration code in here is wrapped inside of a transaction.
	 *
	 * @return bool
	 */
	public function safeUp()
	{
		// Get all of the tag set field layout IDs
		$fieldLayoutIds = craft()->db->createCommand()
			->select('fieldLayoutId')
			->from('tagsets')
			->where('fieldLayoutId is not null')
			->queryColumn();

		$this->update('fieldlayouts', array('type' => 'Tag'), array('in', 'id', $fieldLayoutIds));

		return true;
	}
}
