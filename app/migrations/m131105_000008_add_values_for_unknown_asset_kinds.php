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
class m131105_000008_add_values_for_unknown_asset_kinds extends BaseMigration
{
	/**
	 * Any migration code in here is wrapped inside of a transaction.
	 *
	 * @return bool
	 */
	public function safeUp()
	{
		// Get all of the offending asset IDs
		$assetIDs = craft()->db->createCommand()
			->select("id")
			->from("assetfiles")
			->where("kind is null")
			->queryAll();

		if ($assetIDs)
		{
			Craft::log('Found '.count($assetIDs).' assets that have a null value for kind.', LogLevel::Info, true);
			$this->update('assetfiles', array('kind' => 'unknown'), 'kind is null');
			Craft::log('Updated '.count($assetIDs).' asset kinds to \'unknown\'.', LogLevel::Info, true);
		}
		else
		{
			Craft::log('No assets found with unknown kind.', LogLevel::Info, true);
		}

		Craft::log('Changing kind column to varchar, maxLength 50, required and a default of unknown', LogLevel::Info, true);
		$this->alterColumn('assetfiles', 'kind', array('column' => ColumnType::Varchar, 'maxLength' => 50, 'required' => true, 'default' => 'unknown'));

		Craft::log('Fixing any truncated illustrators.', LogLevel::Info, true);
		$this->update('assetfiles', array('kind' => 'illustrator'), array('kind' => 'illustrato'));

		return true;
	}
}
