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
class m131122_000000_add_missing_content_and_i18n_rows extends BaseMigration
{
	/**
	 * Any migration code in here is wrapped inside of a transaction.
	 *
	 * @return bool
	 */
	public function safeUp()
	{
		// First make sure that every element has a row in the elements_i18n and content tables
		$this->_addMissingElementRows('content');
		$this->_addMissingElementRows('elements_i18n');

		// Now make sure that neither of those tables have data for a locale that the other one doesn't
		$this->_addMissingLocaleRows('content', 'elements_i18n');
		$this->_addMissingLocaleRows('elements_i18n', 'content');

		return true;
	}

	private function _addMissingElementRows($table)
	{
		// Find all of the elements that don't have a row in this table yet
		$elementIds = craft()->db->createCommand()
			->select('elements.id')
			->from('elements elements')
			->leftJoin($table.' '.$table, $table.'.elementId = elements.id')
			->where($table.'.id IS NULL')
			->queryColumn();

		if ($elementIds)
		{
			$rows = array();
			$locale = craft()->i18n->getPrimarySiteLocaleId();

			foreach ($elementIds as $elementId)
			{
				craft()->config->maxPowerCaptain();

				$this->insert($table, array(
					'elementId' => $elementId,
					'locale'    => $locale
				));
			}
		}
	}

	private function _addMissingLocaleRows($table1, $table2)
	{
		$missingLocales = craft()->db->createCommand()
			->select($table1.'.elementId, '.$table1.'.locale')
			->from($table1.' '.$table1)
			->leftJoin($table2.' '.$table2, array('and',
				$table2.'.elementId = '.$table1.'.elementId',
				$table2.'.locale = '.$table1.'.locale'))
			->where($table2.'.id IS NULL')
			->query();

		if ($missingLocales)
		{
			$rows = array();

			foreach ($missingLocales as $locale)
			{
				craft()->config->maxPowerCaptain();

				$this->insert($table2, array(
					'elementId' => $locale['elementId'],
					'locale'    => $locale['locale']
				));
			}
		}
	}
}
