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
class m130917_000000_drop_users_enctype extends BaseMigration
{
	/**
	 * Any migration code in here is wrapped inside of a transaction.
	 *
	 * @return bool
	 */
	public function safeUp()
	{
		$usersTable = $this->dbConnection->schema->getTable('{{users}}');

		if ($usersTable)
		{
			if (($encTypeColumn = $usersTable->getColumn('encType')) !== null)
			{
				Craft::log('Removing `encType` column from the `users` table.', LogLevel::Info, true);
				$this->dropColumn('users', 'encType');
				Craft::log('Removed `encType` column from the `users` table.', LogLevel::Info, true);
			}
			else
			{
				Craft::log('Tried to remove `encType` column from the `users` table, but there is none.', LogLevel::Warning);
			}
		}
		else
		{
			Craft::log('Could not find the `users` table. Wut?', LogLevel::Error);
		}

		return true;
	}
}
