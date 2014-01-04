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
class InstallService extends BaseApplicationComponent
{
	private $_user;

	/**
	 * Installs Craft!
	 *
	 * @param array $inputs
	 * @throws Exception
	 * @throws \Exception
	 * @return void
	 */
	public function run($inputs)
	{
		craft()->config->maxPowerCaptain();

		if (craft()->isInstalled())
		{
			throw new Exception(Craft::t('Craft is already installed.'));
		}

		// Set the language to the desired locale
		craft()->setLanguage($inputs['locale']);

		$records = $this->findInstallableRecords();

		// Start the transaction
		$transaction = craft()->db->getCurrentTransaction() === null ? craft()->db->beginTransaction() : null;
		try
		{
			Craft::log('Installing Craft.');

			// Create the tables
			$this->_createTablesFromRecords($records);
			$this->_createForeignKeysFromRecords($records);

			$this->_createContentTable();
			$this->_createRelationsTable();
			$this->_createShunnedMessagesTable();
			$this->_createSearchIndexTable();
			$this->_createAndPopulateInfoTable($inputs);

			$this->_createAssetTransformIndexTable();
			$this->_createRackspaceAccessTable();

			$this->_populateMigrationTable();

			Craft::log('Committing the transaction.');
			if ($transaction !== null)
			{
				$transaction->commit();
			}
		}
		catch (\Exception $e)
		{
			if ($transaction !== null)
			{
				$transaction->rollback();
			}

			throw $e;
		}

		// Craft, you are installed now.
		craft()->setIsInstalled();

		$this->_addLocale($inputs['locale']);
		$this->_addUser($inputs);

		if (!craft()->isConsole())
		{
			$this->_logUserIn($inputs);
		}

		$this->_saveDefaultMailSettings($inputs['email'], $inputs['siteName']);
		$this->_createDefaultContent($inputs);

		Craft::log('Finished installing Craft.');
	}

	/**
	 * Finds installable records from the models folder.
	 *
	 * @return array
	 */
	public function findInstallableRecords()
	{
		$records = array();

		$recordsFolder = craft()->path->getAppPath().'records/';
		$recordFiles = IOHelper::getFolderContents($recordsFolder, false, ".*Record\.php$");

		foreach ($recordFiles as $file)
		{
			if (IOHelper::fileExists($file))
			{
				$fileName = IOHelper::getFileName($file, false);

				// Ignore StructuredEntryRecord
				if ($fileName == 'StructuredEntryRecord')
				{
					Craft::log("Skipping record {$file}.", LogLevel::Warning);
					continue;
				}

				$class = __NAMESPACE__.'\\'.$fileName;

				// Ignore abstract classes and interfaces
				$ref = new \ReflectionClass($class);
				if ($ref->isAbstract() || $ref->isInterface())
				{
					Craft::log("Skipping record {$file} because it’s abstract or an interface.", LogLevel::Warning);
					continue;
				}

				$obj = new $class('install');

				if (method_exists($obj, 'createTable'))
				{
					$records[] = $obj;
				}
				else
				{
					Craft::log("Skipping record {$file} because it doesn’t have a createTable() method.", LogLevel::Warning);
				}
			}
			else
			{
				Craft::log("Skipping record {$file} because it doesn’t exist.", LogLevel::Warning);
			}
		}

		return $records;
	}

	/**
	 * Creates the tables as defined in the records.
	 *
	 * @access private
	 * @param $records
	 * @return void
	 */
	private function _createTablesFromRecords($records)
	{
		foreach ($records as $record)
		{
			Craft::log('Creating table for record:'. get_class($record));
			$record->createTable();
		}
	}

	/**
	 * Creates the foreign keys as defined in the records.
	 *
	 * @access private
	 * @param $records
	 */
	private function _createForeignKeysFromRecords($records)
	{
		foreach ($records as $record)
		{
			Craft::log('Adding foreign keys for record:'. get_class($record));
			$record->addForeignKeys();
		}
	}

	/**
	 * Creates the content table.
	 *
	 * @access private
	 */
	private function _createContentTable()
	{
		Craft::log('Creating the content table.');

		craft()->db->createCommand()->createTable('content', array(
			'elementId' => array('column' => ColumnType::Int, 'null' => false),
			'locale'    => array('column' => ColumnType::Locale, 'null' => false),
			'title'     => array('column' => ColumnType::Varchar),
		));
		craft()->db->createCommand()->createIndex('content', 'elementId,locale', true);
		craft()->db->createCommand()->createIndex('content', 'title');
		craft()->db->createCommand()->addForeignKey('content', 'elementId', 'elements', 'id', 'CASCADE', null);
		craft()->db->createCommand()->addForeignKey('content', 'locale', 'locales', 'locale', 'CASCADE', 'CASCADE');

		Craft::log('Finished creating the content table.');
	}

	/**
	 * Creates the relations table.
	 *
	 * @access private
	 */
	private function _createRelationsTable()
	{
		Craft::log('Creating the relations table.');

		craft()->db->createCommand()->createTable('relations', array(
			'fieldId'   => array('column' => ColumnType::Int, 'null' => false),
			'sourceId'  => array('column' => ColumnType::Int, 'null' => false),
			'targetId'  => array('column' => ColumnType::Int, 'null' => false),
			'sortOrder' => array('column' => ColumnType::TinyInt),
		));

		craft()->db->createCommand()->createIndex('relations', 'fieldId,sourceId,targetId', true);
		craft()->db->createCommand()->addForeignKey('relations', 'fieldId', 'fields', 'id', 'CASCADE');
		craft()->db->createCommand()->addForeignKey('relations', 'sourceId', 'elements', 'id', 'CASCADE');
		craft()->db->createCommand()->addForeignKey('relations', 'targetId', 'elements', 'id', 'CASCADE');

		Craft::log('Finished creating the relations table.');
	}

	/**
	 * Creates the shunnedmessages table.
	 *
	 * @access private
	 */
	private function _createShunnedMessagesTable()
	{
		Craft::log('Creating the shunnedmessages table.');

		craft()->db->createCommand()->createTable('shunnedmessages', array(
			'userId'     => array('column' => ColumnType::Int, 'null' => false),
			'message'    => array('column' => ColumnType::Varchar, 'null' => false),
			'expiryDate' => array('column' => ColumnType::DateTime),
		));
		craft()->db->createCommand()->createIndex('shunnedmessages', 'userId,message', true);
		craft()->db->createCommand()->addForeignKey('shunnedmessages', 'userId', 'users', 'id', 'CASCADE');

		Craft::log('Finished creating the shunnedmessages table.');
	}

	/**
	 * Creates the searchindex table.
	 *
	 * @access private
	 */
	private function _createSearchIndexTable()
	{
		Craft::log('Creating the searchindex table.');

		// Taking the scenic route here so we can get to MysqlSchema's $engine argument
		$table = DbHelper::addTablePrefix('searchindex');

		$columns = array(
			'elementId' => DbHelper::generateColumnDefinition(array('column' => ColumnType::Int, 'null' => false)),
			'attribute' => DbHelper::generateColumnDefinition(array('column' => ColumnType::Varchar, 'maxLength' => 25, 'null' => false)),
			'fieldId'   => DbHelper::generateColumnDefinition(array('column' => ColumnType::Int, 'null' => false)),
			'locale'    => DbHelper::generateColumnDefinition(array('column' => ColumnType::Locale, 'null' => false)),
			'keywords'  => DbHelper::generateColumnDefinition(array('column' => ColumnType::Text, 'null' => false)),
		);

		craft()->db->createCommand()->setText(craft()->db->getSchema()->createTable($table, $columns, null, 'MyISAM'))->execute();

		// Give it a composite primary key
		craft()->db->createCommand()->addPrimaryKey('searchindex', 'elementId,attribute,fieldId,locale');

		// Add the FULLTEXT index on `keywords`
		craft()->db->createCommand()->setText('CREATE FULLTEXT INDEX ' .
			craft()->db->quoteTableName(DbHelper::getIndexName('searchindex', 'keywords')).' ON ' .
			craft()->db->quoteTableName($table).' ' .
			'('.craft()->db->quoteColumnName('keywords').')'
		)->execute();

		Craft::log('Finished creating the searchindex table.');
	}

	/**
	 * Populates the info table with install and environment information.
	 *
	 * @access private
	 * @param $inputs
	 * @throws Exception
	 */
	private function _createAndPopulateInfoTable($inputs)
	{
		Craft::log('Creating the info table.');

		craft()->db->createCommand()->createTable('info', array(
			'version'       => array('column' => ColumnType::Varchar,  'length' => 15,    'null' => false),
			'build'         => array('column' => ColumnType::Int,      'length' => 11,    'unsigned' => true, 'null' => false),
			'schemaVersion' => array('column' => ColumnType::Varchar,  'length' => 15,    'null' => false),
			'packages'      => array('column' => ColumnType::Varchar,  'length' => 200),
			'releaseDate'   => array('column' => ColumnType::DateTime, 'null' => false),
			'siteName'      => array('column' => ColumnType::Varchar,  'length' => 100,   'null' => false),
			'siteUrl'       => array('column' => ColumnType::Varchar,  'length' => 255,   'null' => false),
			'timezone'      => array('column' => ColumnType::Varchar,  'length' => 30),
			'on'            => array('column' => ColumnType::TinyInt,  'length' => 1,     'unsigned' => true, 'default' => false, 'null' => false),
			'maintenance'   => array('column' => ColumnType::TinyInt,  'length' => 1,     'unsigned' => true, 'default' => false, 'null' => false),
			'track'         => array('column' => ColumnType::Varchar,  'maxLength' => 40, 'required' => true),
		));

		Craft::log('Finished creating the info table.');

		Craft::log('Populating the info table.');

		$info = new InfoModel(array(
			'version'       => CRAFT_VERSION,
			'build'         => CRAFT_BUILD,
			'schemaVersion' => CRAFT_SCHEMA_VERSION,
			'releaseDate'   => CRAFT_RELEASE_DATE,
			'siteName'      => $inputs['siteName'],
			'siteUrl'       => $inputs['siteUrl'],
			'on'            => 1,
			'maintenance'   => 0,
			'track'         => 'stable',
		));

		if (craft()->saveInfo($info))
		{
			Craft::log('Info table populated successfully.');
		}
		else
		{
			Craft::log('Could not populate the info table.', LogLevel::Error);
			throw new Exception(Craft::t('There was a problem saving to the info table:').$this->_getFlattenedErrors($info->getErrors()));
		}
	}

	/**
	 * Creates the Rackspace access table.
	 */
	private function _createRackspaceAccessTable()
	{
		Craft::log('Creating the Rackspace access table.');

		craft()->db->createCommand()->createTable('rackspaceaccess', array(
			'connectionKey'  => array('column' => ColumnType::Varchar, 'required' => true),
			'token'          => array('column' => ColumnType::Varchar, 'required' => true),
			'storageUrl'     => array('column' => ColumnType::Varchar, 'required' => true),
			'cdnUrl'         => array('column' => ColumnType::Varchar, 'required' => true),
		));

		craft()->db->createCommand()->createIndex('rackspaceaccess', 'connectionKey', true);
		Craft::log('Finished creating the Rackspace access table.');
	}

	/**
	 * Create the Asset Transform Index table.
	 */
	private function _createAssetTransformIndexTable()
	{
		Craft::log('Creating the Asset transform index table.');

		craft()->db->createCommand()->createTable('assettransformindex', array(
			'fileId'       => array('maxLength' => 11, 'column' => ColumnType::Int, 'required' => true),
			'location'     => array('maxLength' => 255, 'column' => ColumnType::Varchar, 'required' => true),
			'sourceId'     => array('maxLength' => 11, 'column' => ColumnType::Int, 'required' => true),
			'fileExists'   => array('column' => ColumnType::Bool),
			'inProgress'   => array('column' => ColumnType::Bool),
			'dateIndexed'  => array('column' => ColumnType::DateTime),
		));

		craft()->db->createCommand()->createIndex('assettransformindex', 'sourceId, fileId, location');
		Craft::log('Finished creating the Asset transform index table.');
	}

	/**
	 * Populates the migrations table with the base migration plus any existing ones from app/migrations.
	 *
	 * @throws Exception
	 */
	private function _populateMigrationTable()
	{
		$migrations = array();

		// Add the base one.
		$migration = new MigrationRecord();
		$migration->version = craft()->migrations->getBaseMigration();
		$migration->applyTime = DateTimeHelper::currentUTCDateTime();
		$migrations[] = $migration;

		$migrationsFolder = craft()->path->getAppPath().'migrations/';
		$migrationFiles = IOHelper::getFolderContents($migrationsFolder, false, "(m(\d{6}_\d{6})_.*?)\.php");

		if ($migrationFiles)
		{
			foreach ($migrationFiles as $file)
			{
				if (IOHelper::fileExists($file))
				{
					$migration = new MigrationRecord();
					$migration->version = IOHelper::getFileName($file, false);
					$migration->applyTime = DateTimeHelper::currentUTCDateTime();

					$migrations[] = $migration;
				}
			}

			foreach ($migrations as $migration)
			{
				if (!$migration->save())
				{
					Craft::log('Could not populate the migration table.', LogLevel::Error);
					throw new Exception(Craft::t('There was a problem saving to the migrations table:').$this->_getFlattenedErrors($migration->getErrors()));
				}
			}
		}

		Craft::log('Migration table populated successfully.');
	}

	/**
	 * Adds the initial locale to the database.
	 *
	 * @access private
	 * @param string $locale
	 */
	private function _addLocale($locale)
	{
		Craft::log('Adding locale.');
		craft()->db->createCommand()->insert('locales', array('locale' => $locale, 'sortOrder' => 1));
		Craft::log('Locale added successfully.');
	}

	/**
	 * Adds the initial user to the database.
	 *
	 * @access private
	 * @param $inputs
	 * @return UserModel
	 * @throws Exception
	 */
	private function _addUser($inputs)
	{
		Craft::log('Creating user.');

		$this->_user = new UserModel();

		$this->_user->username = $inputs['username'];
		$this->_user->newPassword = $inputs['password'];
		$this->_user->email = $inputs['email'];
		$this->_user->admin = true;

		if (craft()->users->saveUser($this->_user))
		{
			Craft::log('User created successfully.');
		}
		else
		{
			Craft::log('Could not create the user.', LogLevel::Error);
			throw new Exception(Craft::t('There was a problem creating the user:').$this->_getFlattenedErrors($this->_user->getErrors()));
		}
	}

	/**
	 * Attempts to log in the given user.
	 *
	 * @access private
	 * @param array $inputs
	 */
	private function _logUserIn($inputs)
	{
		Craft::log('Logging in user.');

		if (craft()->userSession->login($inputs['username'], $inputs['password']))
		{
			Craft::log('User logged in successfully.');
		}
		else
		{
			Craft::log('Could not log the user in.', LogLevel::Warning);
		}
	}

	/**
	 * Saves some default mail settings for the site.
	 *
	 * @access private
	 * @param $email
	 * @param $siteName
	 */
	private function _saveDefaultMailSettings($email, $siteName)
	{
		Craft::log('Saving default mail settings.');

		$settings = array(
			'protocol'     => EmailerType::Php,
			'emailAddress' => $email,
			'senderName'   => $siteName
		);

		if (craft()->systemSettings->saveSettings('email', $settings))
		{
			Craft::log('Default mail settings saved successfully.');
		}
		else
		{
			Craft::log('Could not save default email settings.', LogLevel::Warning);
		}
	}

	/**
	 * Creates initial database content for the install.
	 *
	 * @access private
	 * @param $inputs
	 * @return null
	 */
	private function _createDefaultContent($inputs)
	{
		// Default tag set

		Craft::log('Creating the Default tag set.');

		$tagSet = new TagSetModel();
		$tagSet->name   = Craft::t('Default');
		$tagSet->handle = 'default';

		// Save it
		if (craft()->tags->saveTagSet($tagSet))
		{
			Craft::log('Default tag set created successfully.');
		}
		else
		{
			Craft::log('Could not save the Default tag set.', LogLevel::Warning);
		}

		// Default field group

		Craft::log('Creating the Default field group.');

		$group = new FieldGroupModel();
		$group->name = Craft::t('Default');

		if (craft()->fields->saveGroup($group))
		{
			Craft::log('Default field group created successfully.');
		}
		else
		{
			Craft::log('Could not save the Default field group.', LogLevel::Warning);
		}

		// Heading field

		Craft::log('Creating the Heading field.');

		$headingField = new FieldModel();
		$headingField->groupId      = $group->id;
		$headingField->name         = Craft::t('Heading');
		$headingField->handle       = 'heading';
		$headingField->translatable = true;
		$headingField->type         = 'PlainText';

		if (craft()->fields->saveField($headingField))
		{
			Craft::log('Heading field created successfully.');
		}
		else
		{
			Craft::log('Could not save the Heading field.', LogLevel::Warning);
		}

		// Body field

		Craft::log('Creating the Body field.');

		$bodyField = new FieldModel();
		$bodyField->groupId      = $group->id;
		$bodyField->name         = Craft::t('Body');
		$bodyField->handle       = 'body';
		$bodyField->translatable = true;
		$bodyField->type         = 'RichText';
		$bodyField->settings = array(
			'configFile' => 'Standard.json'
		);

		if (craft()->fields->saveField($bodyField))
		{
			Craft::log('Body field created successfully.');
		}
		else
		{
			Craft::log('Could not save the Body field.', LogLevel::Warning);
		}

		// Tags field

		Craft::log('Creating the Tags field.');

		$tagsField = new FieldModel();
		$tagsField->groupId      = $group->id;
		$tagsField->name         = Craft::t('Tags');
		$tagsField->handle       = 'tags';
		$tagsField->type         = 'Tags';
		$tagsField->settings = array(
			'source' => 'tagset:'.$tagSet->id
		);

		if (craft()->fields->saveField($tagsField))
		{
			Craft::log('Tags field created successfully.');
		}
		else
		{
			Craft::log('Could not save the Tags field.', LogLevel::Warning);
		}

		// Homepage single section

		Craft::log('Creating the Homepage single section.');

		$homepageLayoutFields = array(
			array(
				'fieldId'   => $headingField->id,
				'sortOrder' => 1
			),
			array(
				'fieldId'   => $bodyField->id,
				'sortOrder' => 2
			)
		);

		$homepageLayoutTabs = array(
			array(
				'name'      => Craft::t('Content'),
				'sortOrder' => 1,
				'fields'    => $homepageLayoutFields
			)
		);

		$homepageLayout = new FieldLayoutModel();
		$homepageLayout->type = ElementType::Entry;
		$homepageLayout->setTabs($homepageLayoutTabs);
		$homepageLayout->setFields($homepageLayoutFields);

		$homepageSingleSection = new SectionModel();
		$homepageSingleSection->name       = Craft::t('Homepage');
		$homepageSingleSection->handle     = 'homepage';
		$homepageSingleSection->type       = SectionType::Single;
		$homepageSingleSection->hasUrls  = false;
		$homepageSingleSection->template = 'index';

		$primaryLocaleId = craft()->i18n->getPrimarySiteLocaleId();
		$locales[$primaryLocaleId] = new SectionLocaleModel(array(
			'locale'          => $primaryLocaleId,
			'urlFormat'       => '__home__',
		));

		$homepageSingleSection->setLocales($locales);

		// Save it
		if (craft()->sections->saveSection($homepageSingleSection))
		{
			Craft::log('Homepage single section created successfully.');
		}
		else
		{
			Craft::log('Could not save the Homepage single section.', LogLevel::Warning);
		}

		$homepageEntryTypes = $homepageSingleSection->getEntryTypes();
		$homepageEntryType = $homepageEntryTypes[0];
		$homepageEntryType->setFieldLayout($homepageLayout);

		if (craft()->sections->saveEntryType($homepageEntryType))
		{
			Craft::log('Homepage single section entry type saved successfully.');
		}
		else
		{
			Craft::log('Could not save the Homepage single section entry type.', LogLevel::Warning);
		}

		// Homepage content

		$vars = array(
			'siteName' => ucfirst(craft()->request->getServerName())
		);

		Craft::log('Setting the Homepage content.');

		$criteria = craft()->elements->getCriteria(ElementType::Entry);
		$criteria->sectionId = $homepageSingleSection->id;
		$entryModel = $criteria->first();

		$entryModel->locale = $inputs['locale'];
		$entryModel->getContent()->heading = Craft::t('Welcome to {siteName}!', $vars);
		$entryModel->getContent()->setAttributes(array(
			'body' => '<p>'.Craft::t('It’s true, this site doesn’t have a whole lot of content yet, but don’t worry. Our web developers have just installed the CMS, and they’re setting things up for the content editors this very moment. Soon {siteName} will be an oasis of fresh perspectives, sharp analyses, and astute opinions that will keep you coming back again and again.', $vars).'</p>',
			'heading' => Craft::t('Welcome to {siteName}!', $vars),
		));

		// Save the content
		if (craft()->entries->saveEntry($entryModel))
		{
			Craft::log('Homepage an entry to the Homepage single section.');
		}
		else
		{
			Craft::log('Could not save an entry to the Homepage single section.', LogLevel::Warning);
		}

		// News section

		Craft::log('Creating the News section.');

		$newsSection = new SectionModel();
		$newsSection->type     = SectionType::Channel;
		$newsSection->name     = Craft::t('News');
		$newsSection->handle   = 'news';
		$newsSection->hasUrls  = true;
		$newsSection->template = 'news/_entry';

		$newsSection->setLocales(array(
			$inputs['locale'] => SectionLocaleModel::populateModel(array(
				'locale'    => $inputs['locale'],
				'urlFormat' => 'news/{postDate.year}/{slug}',
			))
		));

		if (craft()->sections->saveSection($newsSection))
		{
			Craft::log('News section created successfully.');
		}
		else
		{
			Craft::log('Could not save the News section.', LogLevel::Warning);
		}

		Craft::log('Saving the News entry type.');

		$newsLayoutFields = array(
			array(
				'fieldId'   => $bodyField->id,
				'required'  => true,
				'sortOrder' => 1
			),
			array(
				'fieldId'   => $tagsField->id,
				'sortOrder' => 2
			),
		);

		$newsLayoutTabs = array(
			array(
				'name'      => Craft::t('Content'),
				'sortOrder' => 1,
				'fields'    => $newsLayoutFields
			)
		);

		$newsLayout = new FieldLayoutModel();
		$newsLayout->type = ElementType::Entry;
		$newsLayout->setTabs($newsLayoutTabs);
		$newsLayout->setFields($newsLayoutFields);

		$newsEntryTypes = $newsSection->getEntryTypes();
		$newsEntryType = $newsEntryTypes[0];
		$newsEntryType->setFieldLayout($newsLayout);

		if (craft()->sections->saveEntryType($newsEntryType))
		{
			Craft::log('News entry type saved successfully.');
		}
		else
		{
			Craft::log('Could not save the News entry type.', LogLevel::Warning);
		}

		// News entry

		Craft::log('Creating a News entry.');

		$newsEntry = new EntryModel();
		$newsEntry->sectionId  = $newsSection->id;
		$newsEntry->typeId     = $newsEntryType->id;
		$newsEntry->locale     = $inputs['locale'];
		$newsEntry->authorId   = $this->_user->id;
		$newsEntry->enabled    = true;
		$newsEntry->getContent()->title = Craft::t('We just installed Craft!');
		$newsEntry->getContent()->setAttributes(array(
			'body' => '<p>'
					. Craft::t('Craft is the CMS that’s powering {siteName}. It’s beautiful, powerful, flexible, and easy-to-use, and it’s made by Pixel &amp; Tonic. We can’t wait to dive in and see what it’s capable of!', $vars)
					. '</p><!--pagebreak--><p>'
					. Craft::t('This is even more captivating content, which you couldn’t see on the News index page because it was entered after a Page Break, and the News index template only likes to show the content on the first page.')
					. '</p><p>'
					. Craft::t('Craft: a nice alternative to Word, if you’re making a website.')
					. '</p>',
		));

		if (craft()->entries->saveEntry($newsEntry))
		{
			Craft::log('News entry created successfully.');
		}
		else
		{
			Craft::log('Could not save the News entry.', LogLevel::Warning);
		}
	}

	/**
	 * Get a flattened list of model errors
	 *
	 * @access private
	 * @param array $errors
	 * @return string
	 */
	private function _getFlattenedErrors($errors)
	{
		$return = '';

		foreach ($errors as $attribute => $attributeErrors)
		{
			$return .= "\n - ".implode("\n - ", $attributeErrors);
		}

		return $return;
	}
}
