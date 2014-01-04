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

craft()->requirePackage(CraftPackage::Users);

/**
 *
 */
class UserPermissionsService extends BaseApplicationComponent
{
	private $_permissionsByGroupId;
	private $_permissionsByUserId;

	/**
	 * Returns all of the known permissions, sorted by category.
	 *
	 * @return array
	 */
	public function getAllPermissions()
	{
		// General

		$general = array(
			'accessSiteWhenSystemIsOff' => array(
				'label' => Craft::t('Access the site when the system is off')
			),
			'accessCp' => array(
				'label' => Craft::t('Access the CP'),
				'nested' => array(
					'accessCpWhenSystemIsOff' => array(
						'label' => Craft::t('Access the CP when the system is off')
					),
					'performUpdates' => array(
						'label' => Craft::t('Perform Craft and plugin updates')
					),
				)
			),
		);

		foreach (craft()->plugins->getPlugins() as $plugin)
		{
			if ($plugin->hasCpSection())
			{
				$general['accessCp']['nested']['accessPlugin-'.$plugin->getClassHandle()] = array(
					'label' => Craft::t('Access {plugin}', array('plugin' => $plugin->getName()))
				);
			}
		}

		$permissions[Craft::t('General')] = $general;

		// Users

		$permissions[Craft::t('Users')] = array(
			'editUsers' => array(
				'label' => Craft::t('Edit users'),
				'nested' => array(
					'registerUsers' => array(
						'label' => Craft::t('Register users')
					),
					'administrateUsers' => array(
						'label' => Craft::t('Administrate users')
					)
				),
			),
			'deleteUsers' => array(
				'label' => Craft::t('Delete users')
			),
		);

		// Locales

		if (craft()->hasPackage(CraftPackage::Localize))
		{
			$label = Craft::t('Locales');
			$locales = craft()->i18n->getSiteLocales();

			foreach ($locales as $locale)
			{
				$permissions[$label]['editLocale:'.$locale->getId()] = array(
					'label' => $locale->getName()
				);
			}
		}

		// Entries

		$sections = craft()->sections->getAllSections();

		foreach ($sections as $section)
		{
			$label = Craft::t('Section - {section}', array('section' => Craft::t($section->name)));
			$permissions[$label] = $this->_getEntryPermissions($section->id);
		}

		// Global sets

		$globalSets = craft()->globals->getAllSets();

		if ($globalSets)
		{
			$permissions[Craft::t('Global Sets')] = $this->_getGlobalSetPermissions($globalSets);
		}

		// Asset sources

		$assetSources = craft()->assetSources->getAllSources();

		if ($assetSources)
		{
			$permissions[Craft::t('Asset Sources')] = $this->_getAssetSourcePermissions($assetSources);
		}

		// Plugins

		foreach (craft()->plugins->call('registerUserPermissions') as $pluginHandle => $pluginPermissions)
		{
			$plugin = craft()->plugins->getPlugin($pluginHandle);
			$permissions[$plugin->getName()] = $pluginPermissions;
		}

		return $permissions;
	}

	/**
	 * Returns all of a given user group's permissions.
	 *
	 * @param int $groupId
	 * @return array
	 */
	public function getPermissionsByGroupId($groupId)
	{
		if (!isset($this->_permissionsByUserId[$groupId]))
		{
			$groupPermissions = craft()->db->createCommand()
				->select('p.name')
				->from('userpermissions p')
				->join('userpermissions_usergroups p_g', 'p_g.permissionId = p.id')
				->where(array('p_g.groupId' => $groupId))
				->queryColumn();

			$this->_permissionsByGroupId[$groupId] = $groupPermissions;
		}

		return $this->_permissionsByGroupId[$groupId];
	}

	/**
	 * Returns all of the group permissions a given user has.
	 *
	 * @param int $userId
	 * @return array
	 */
	public function getGroupPermissionsByUserId($userId)
	{
		return craft()->db->createCommand()
			->select('p.name')
			->from('userpermissions p')
			->join('userpermissions_usergroups p_g', 'p_g.permissionId = p.id')
			->join('usergroups_users g_u', 'g_u.groupId = p_g.groupId')
			->where(array('g_u.userId' => $userId))
			->queryColumn();
	}

	/**
	 * Returns whether a given user group has a given permission.
	 *
	 * @param int $groupId
	 * @param string $checkPermission
	 * @return bool
	 */
	public function doesGroupHavePermission($groupId, $checkPermission)
	{
		$allPermissions = $this->getPermissionsByGroupId($groupId);
		$checkPermission = mb_strtolower($checkPermission);

		return in_array($checkPermission, $allPermissions);
	}

	/**
	 * Saves new permissions for a user group.
	 *
	 * @param int $groupId
	 * @param array $permissions
	 * @return bool
	 */
	public function saveGroupPermissions($groupId, $permissions)
	{
		// Delete any existing group permissions
		craft()->db->createCommand()
			->delete('userpermissions_usergroups', array('groupId' => $groupId));

		$permissions = $this->_filterOrphanedPermissions($permissions);

		if ($permissions)
		{
			$groupPermissionVals = array();

			foreach ($permissions as $permissionName)
			{
				$permissionRecord = $this->_getPermissionRecordByName($permissionName);
				$groupPermissionVals[] = array($permissionRecord->id, $groupId);
			}

			// Add the new group permissions
			craft()->db->createCommand()
				->insertAll('userpermissions_usergroups', array('permissionId', 'groupId'), $groupPermissionVals);
		}

		return true;
	}

	/**
	 * Returns all of a given user's permissions.
	 *
	 * @param int $userId
	 * @return array
	 */
	public function getPermissionsByUserId($userId)
	{
		if (!isset($this->_permissionsByUserId[$userId]))
		{
			$groupPermissions = $this->getGroupPermissionsByUserId($userId);

			$userPermissions = craft()->db->createCommand()
				->select('p.name')
				->from('userpermissions p')
				->join('userpermissions_users p_u', 'p_u.permissionId = p.id')
				->where(array('p_u.userId' => $userId))
				->queryColumn();

			$this->_permissionsByUserId[$userId] = array_unique(array_merge($groupPermissions, $userPermissions));
		}

		return $this->_permissionsByUserId[$userId];
	}

	/**
	 * Returns whether a given user has a given permission.
	 *
	 * @param int $userId
	 * @param string $checkPermission
	 * @return bool
	 */
	public function doesUserHavePermission($userId, $checkPermission)
	{
		$allPermissions = $this->getPermissionsByUserId($userId);
		$checkPermission = mb_strtolower($checkPermission);

		return in_array($checkPermission, $allPermissions);
	}

	/**
	 * Saves new permissions for a user.
	 *
	 * @param int $userId
	 * @param array $permissions
	 * @return bool
	 */
	public function saveUserPermissions($userId, $permissions)
	{
		// Delete any existing user permissions
		craft()->db->createCommand()
			->delete('userpermissions_users', array('userId' => $userId));

		// Filter out any orphaned permissions
		$permissions = $this->_filterOrphanedPermissions($permissions);

		if ($permissions)
		{
			$userPermissionVals = array();

			foreach ($permissions as $permissionName)
			{
				$permissionRecord = $this->_getPermissionRecordByName($permissionName);
				$userPermissionVals[] = array($permissionRecord->id, $userId);
			}

			// Add the new user permissions
			craft()->db->createCommand()
				->insertAll('userpermissions_users', array('permissionId', 'userId'), $userPermissionVals);
		}

		return true;
	}

	/**
	 * Returns the entry permissions for a given section.
	 *
	 * @access private
	 * @param int|null $sectionId
	 * @return array
	 */
	private function _getEntryPermissions($sectionId)
	{
		$suffix = ':'.$sectionId;

		$permissions = array(
			"editEntries{$suffix}" => array(
				'label' => Craft::t('Edit entries'),
				'nested' => array(
					"createEntries{$suffix}" => array(
						'label' => Craft::t('Create entries'),
					),
					"publishEntries{$suffix}" => array(
						'label' => Craft::t('Publish entries live')
					),
				)
			),
			"deleteEntries{$suffix}" => array(
				'label' => Craft::t('Delete entries')
			),


		);

		if (craft()->hasPackage(CraftPackage::Users))
		{
			$permissions["editEntries{$suffix}"]['nested']["editPeerEntries{$suffix}"] = array(
				'label' => Craft::t('Edit other authors’ entries'),
				'nested' => array(
					"deletePeerEntries{$suffix}" => array(
						'label' => Craft::t('Delete other authors’ entries')
					),
				)
			);

			if (craft()->hasPackage(CraftPackage::PublishPro))
			{
				$permissions["editEntries{$suffix}"]['nested']["editPeerEntries{$suffix}"]['nested']["editPeerEntryDrafts{$suffix}"] = array(
					'label' => Craft::t('Edit other authors’ drafts'),
					'nested' => array(
						"publishPeerEntryDrafts{$suffix}" => array(
							'label' => Craft::t('Publish other authors’ drafts')
						),
						"deletePeerEntryDrafts{$suffix}" => array(
							'label' => Craft::t('Delete other authors’ drafts')
						),
					)
				);
			}
		}

		return $permissions;
	}

	/**
	 * Returns the global set permissions.
	 *
	 * @access private
	 * @param array $globalSets
	 * @return array
	 */
	private function _getGlobalSetPermissions($globalSets)
	{
		$permissions = array();

		foreach ($globalSets as $globalSet)
		{
			$permissions['editGlobalSet:'.$globalSet->id] = array(
				'label' => Craft::t('Edit “{title}”', array('title' => $globalSet->name))
			);
		}

		return $permissions;
	}

	/**
	 * Returns the array source permissions.
	 *
	 * @access private
	 * @param array $assetSources
	 * @return array
	 */
	private function _getAssetSourcePermissions($assetSources)
	{
		$permissions = array();

		foreach ($assetSources as $source)
		{
			$permissions['viewAssetSource:'.$source->id] = array(
				'label' => Craft::t('View source “{title}”', array('title' => $source->name))
			);
		}

		return $permissions;
	}

	/**
	 * Filters out any orphaned permissions.
	 *
	 * @access private
	 * @param array $postedPermissions
	 * @return array $filteredPermissions
	 */
	private function _filterOrphanedPermissions($postedPermissions)
	{
		$filteredPermissions = array();

		if ($postedPermissions)
		{
			foreach ($this->getAllPermissions() as $categoryPermissions)
			{
				$this->_findSelectedPermissions($categoryPermissions, $postedPermissions, $filteredPermissions);
			}
		}

		return $filteredPermissions;
	}

	/**
	 * Iterates through a group of permissions, returning the ones that were selected.
	 *
	 * @access private
	 * @param array $permissionsGroup
	 * @param array $postedPermissions
	 * @param array &$filteredPermissions
	 */
	private function _findSelectedPermissions($permissionsGroup, $postedPermissions, &$filteredPermissions)
	{
		foreach ($permissionsGroup as $name => $data)
		{
			if (in_array($name, $postedPermissions))
			{
				$filteredPermissions[] = $name;

				if (!empty($data['nested']))
				{
					$this->_findSelectedPermissions($data['nested'], $postedPermissions, $filteredPermissions);
				}
			}
		}
	}

	/**
	 * Returns a permission record based on its name. If a record doesn't exist, it will be created.
	 *
	 * @access private
	 * @param string $permissionName
	 * @return UserPermissionRecord
	 */
	private function _getPermissionRecordByName($permissionName)
	{
		// Permission names are always stored in lowercase
		$permissionName = mb_strtolower($permissionName);

		$permissionRecord = UserPermissionRecord::model()->findByAttributes(array(
			'name' => $permissionName
		));

		if (!$permissionRecord)
		{
			$permissionRecord = new UserPermissionRecord();
			$permissionRecord->name = $permissionName;
			$permissionRecord->save();
		}

		return $permissionRecord;
	}
}
