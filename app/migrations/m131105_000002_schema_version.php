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
class m131105_000002_schema_version extends BaseMigration
{
	/**
	 * Any migration code in here is wrapped inside of a transaction.
	 *
	 * @return bool
	 */
	public function safeUp()
	{
		$infoTable = $this->dbConnection->schema->getTable('{{info}}');

		if ($infoTable->getColumn('schemaVersion') === null)
		{
			$versionColumn = array('column' => ColumnType::Varchar, 'length' => 15, 'null' => false);
			$this->alterColumn('info', 'version', $versionColumn);
			$this->addColumnAfter('info', 'schemaVersion', $versionColumn, 'build');
		}

		return true;
	}
}
