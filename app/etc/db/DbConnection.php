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
 *
 */
class DbConnection extends \CDbConnection
{
	private $_isDbConnectionValid = false;

	/**
	 *
	 */
	public function init()
	{
		try
		{
			parent::init();
		}
		// Most likely missing PDO in general or the specific database PDO driver.
		catch(\CDbException $e)
		{
			Craft::log($e->getMessage(), LogLevel::Error);
			$missingPdo = false;

			// TODO: Multi-db driver check.
			if (!extension_loaded('pdo'))
			{
				$missingPdo = true;
				$messages[] = Craft::t('Craft requires the PDO extension to operate.');
			}

			if (!extension_loaded('pdo_mysql'))
			{
				$missingPdo = true;
				$messages[] = Craft::t('Craft requires the PDO_MYSQL driver to operate.');
			}

			if (!$missingPdo)
			{
				Craft::log($e->getMessage(), LogLevel::Error);
				$messages[] = Craft::t('Craft can’t connect to the database with the credentials in craft/config/db.php.');
			}
		}
		catch (\Exception $e)
		{
			Craft::log($e->getMessage(), LogLevel::Error);
			$messages[] = $messages[] = Craft::t('Craft can’t connect to the database with the credentials in craft/config/db.php.');
		}

		if (!empty($messages))
		{
			throw new DbConnectException(Craft::t('{errors}', array('errors' => implode('<br />', $messages))));
		}

		$this->_isDbConnectionValid = true;

		// Now that we've validated the config and connection, set extra db logging if devMode is enabled.
		if (craft()->config->get('devMode'))
		{
			$this->enableProfiling = true;
			$this->enableParamLogging = true;
		}
	}

	/**
	 * @param null $query
	 * @return DbCommand
	 */
	public function createCommand($query = null)
	{
		$this->setActive(true);
		return new DbCommand($this, $query);
	}

	/**
	 * @return bool|string
	 */
	public function backup()
	{
		$backup = new DbBackup();
		if (($backupFile = $backup->run()) !== false)
		{
			return $backupFile;
		}

		return false;
	}

	/**
	 * @param $name
	 * @return string
	 */
	public function quoteDatabaseName($name)
	{
		return $this->getSchema()->quoteTableName($name);
	}

	/**
	 * Returns whether a table exists.
	 *
	 * @param string $table
	 * @param bool $refresh
	 * @return bool
	 */
	public function tableExists($table, $refresh = null)
	{
		// Default to refreshing the tables if Craft isn't installed yet
		if ($refresh || ($refresh === null && !craft()->isInstalled()))
		{
			$this->getSchema()->refresh();
		}

		$table = DbHelper::addTablePrefix($table);
		return in_array($table, $this->getSchema()->getTableNames());
	}

	/**
	 * Checks if a column exists in a table.
	 *
	 * @param string $table
	 * @param string $column
	 * @param bool $refresh
	 * @return bool
	 */
	public function columnExists($table, $column, $refresh = null)
	{
		// Default to refreshing the tables if Craft isn't installed yet
		if ($refresh || ($refresh === null && !craft()->isInstalled()))
		{
			$this->getSchema()->refresh();
		}

		$table = $this->getSchema()->getTable('{{'.$table.'}}');

		if ($table)
		{
			if (($column = $table->getColumn($column)) !== null)
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * @return bool
	 */
	public function isDbConnectionValid()
	{
		return $this->_isDbConnectionValid;
	}
}
