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
class m130917_000001_big_names_and_handles extends BaseMigration
{
	/**
	 * Any migration code in here is wrapped inside of a transaction.
	 *
	 * @return bool
	 */
	public function safeUp()
	{
		$baseConfig = array('column' => ColumnType::Varchar, 'required' => true);

		$cols = array(
			'name' => array(
				'assetsources'    => array(),
				'fieldgroups'     => array(),
				'fieldlayouttabs' => array(),
				'fields'          => array(),
				'globalsets'      => array(),
				'sections'        => array(),
				'tagsets'         => array(),
				'usergroups'      => array(),
				'userpermissions' => array(),
			),
			'handle' => array(
				'assettransforms' => array(),
				'fields'          => array('maxLength' => 64),
				'globalsets'      => array(),
				'sections'        => array(),
				'tagsets'         => array(),
				'usergroups'      => array(),
			)
		);

		foreach ($cols as $col => $tables)
		{
			foreach ($tables as $table => $config)
			{
				$config = array_merge($baseConfig, $config);
				$this->alterColumn($table, $col, $config);
			}
		}

		return true;
	}
}
