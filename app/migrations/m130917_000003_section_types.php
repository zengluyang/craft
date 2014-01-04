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
class m130917_000003_section_types extends BaseMigration
{
	/**
	 * Any migration code in here is wrapped inside of a transaction.
	 *
	 * @return bool
	 */
	public function safeUp()
	{
		$sectionsTable = $this->dbConnection->schema->getTable('{{sections}}');

		if ($sectionsTable->getColumn('type') === null)
		{
			// Add the type and maxDepth columns to the sections table
			$this->addColumnAfter('sections', 'type', array('column' => ColumnType::Enum, 'values' => array(SectionType::Single, SectionType::Channel, SectionType::Structure), 'default' => SectionType::Channel, 'null' => false), 'handle');
			$this->addColumnAfter('sections', 'maxDepth', array('column' => ColumnType::Int, 'maxLength' => 11, 'decimals' => 0, 'unsigned' => true, 'length' => 10), 'template');

			// Add the 'nestedUrlFormat' column to the sections_i18n table
			$this->addColumnAfter('sections_i18n', 'nestedUrlFormat', array('column' => ColumnType::Varchar), 'urlFormat');

			// entries.authorId is no longer required, since Singles don't have an author
			$this->alterColumn('entries', 'authorId', array('column' => ColumnType::Int));

			// Add the hierarchy columns to the entries table
			$cols = array(
				'root'  => array('column' => ColumnType::Int, 'unsigned' => true),
				'lft'   => array('column' => ColumnType::Int, 'unsigned' => true),
				'rgt'   => array('column' => ColumnType::Int, 'unsigned' => true),
				'depth' => array('column' => ColumnType::SmallInt, 'unsigned' => true),
			);
			$lastCol = 'typeId';

			foreach ($cols as $name => $type)
			{
				$this->addColumnAfter('entries', $name, $type, $lastCol);
				$this->createIndex('entries', $name, false);
				$lastCol = $name;
			}
		}

		return true;
	}
}
