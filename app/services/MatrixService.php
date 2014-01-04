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
class MatrixService extends BaseApplicationComponent
{
	private $_blockTypesById;
	private $_blockTypeRecordsById;
	private $_blockRecordsById;
	private $_uniqueBlockTypeAndFieldHandles;
	private $_parentMatrixFields;

	/**
	 * Returns the block types for a given Matrix field.
	 *
	 * @param int $fieldId
	 * @param string|null $indexBy
	 * @return array
	 */
	public function getBlockTypesByFieldId($fieldId, $indexBy = null)
	{
		$blockTypeRecords = MatrixBlockTypeRecord::model()->ordered()->findAllByAttributes(array(
			'fieldId' => $fieldId
		));

		$blockTypes = MatrixBlockTypeModel::populateModels($blockTypeRecords);

		if (!$indexBy)
		{
			return $blockTypes;
		}
		else
		{
			$indexedBlockTypes = array();

			foreach ($blockTypes as $blockType)
			{
				$indexedBlockTypes[$blockType->$indexBy] = $blockType;
			}

			return $indexedBlockTypes;
		}
	}

	/**
	 * Returns a block type by its ID.
	 *
	 * @param int $blockTypeId
	 * @return MatrixBlockTypeModel|null
	 */
	public function getBlockTypeById($blockTypeId)
	{
		if (!isset($this->_blockTypesById) || !array_key_exists($blockTypeId, $this->_blockTypesById))
		{
			$blockTypeRecord = MatrixBlockTypeRecord::model()->findById($blockTypeId);

			if ($blockTypeRecord)
			{
				$this->_blockTypesById[$blockTypeId] = MatrixBlockTypeModel::populateModel($blockTypeRecord);
			}
			else
			{
				$this->_blockTypesById[$blockTypeId] = null;
			}
		}

		return $this->_blockTypesById[$blockTypeId];
	}

	/**
	 * Validates a block type.
	 *
	 * @param MatrixBlockTypeModel $blockType
	 * @return bool
	 */
	public function validateBlockType(MatrixBlockTypeModel $blockType)
	{
		$validates = true;

		$blockTypeRecord = $this->_getBlockTypeRecord($blockType);

		$blockTypeRecord->fieldId = $blockType->fieldId;
		$blockTypeRecord->name    = $blockType->name;
		$blockTypeRecord->handle  = $blockType->handle;

		if (!$blockTypeRecord->validate())
		{
			$validates = false;
			$blockType->addErrors($blockTypeRecord->getErrors());
		}

		// Can't validate multiple new rows at once so we'll need to give these a temporary context
		// to avioid false unique handle validation errors, and just validate those manually.
		$originalFieldContext = craft()->content->fieldContext;
		craft()->content->fieldContext = StringHelper::randomString(10);

		foreach ($blockType->getFields() as $field)
		{
			craft()->fields->validateField($field);

			// Make sure the block type handle + field handle combo is unique for the whole field.
			// This prevents us from worying about content column conflicts like "a" + "b_c" == "a_b" + "c".
			if ($blockType->handle && $field->handle)
			{
				$blockTypeAndFieldHandle = $blockType->handle.'_'.$field->handle;

				if (in_array($blockTypeAndFieldHandle, $this->_uniqueBlockTypeAndFieldHandles))
				{
					// This error *might* not be entirely accurate, but it's such an edge case
					// that it's probably better for the error to be worded for the common problem
					// (two duplicate handles within the same block type).
					$error = Craft::t('{attribute} "{value}" has already been taken.', array(
						'attribute' => Craft::t('Handle'),
						'value' => $field->handle
					));

					$field->addError('handle', $error);
				}
				else
				{
					$this->_uniqueBlockTypeAndFieldHandles[] = $blockTypeAndFieldHandle;
				}
			}

			if ($field->hasErrors())
			{
				$blockType->hasFieldErrors = true;
				$validates = false;
			}
		}

		craft()->content->fieldContext = $originalFieldContext;

		return $validates;
	}

	/**
	 * Saves a block type.
	 *
	 * @param MatrixBlockTypeModel $blockType
	 * @param bool $validate
	 * @return bool
	 */
	public function saveBlockType(MatrixBlockTypeModel $blockType, $validate = true)
	{
		if (!$validate || $this->validateBlockType($blockType))
		{
			$transaction = craft()->db->getCurrentTransaction() === null ? craft()->db->beginTransaction() : null;
			try
			{
				$contentService = craft()->content;
				$fieldsService  = craft()->fields;

				$originalFieldContext         = $contentService->fieldContext;
				$originalFieldColumnPrefix    = $contentService->fieldColumnPrefix;
				$originalOldFieldColumnPrefix = $fieldsService->oldFieldColumnPrefix;

				// Get the block type record
				$blockTypeRecord = $this->_getBlockTypeRecord($blockType);
				$isNewBlockType = $blockType->isNew();

				if (!$isNewBlockType)
				{
					// Get the old block type fields
					$oldBlockTypeRecord = MatrixBlockTypeRecord::model()->findById($blockType->id);
					$oldBlockType = MatrixBlockTypeModel::populateModel($oldBlockTypeRecord);

					$contentService->fieldContext        = 'matrixBlockType:'.$blockType->id;
					$contentService->fieldColumnPrefix   = 'field_'.$oldBlockType->handle.'_';
					$fieldsService->oldFieldColumnPrefix = 'field_'.$oldBlockType->handle.'_';

					$oldFieldsById = array();

					foreach ($oldBlockType->getFields() as $field)
					{
						$oldFieldsById[$field->id] = $field;
					}

					// Figure out which ones are still around
					foreach ($blockType->getFields() as $field)
					{
						if (!$field->isNew())
						{
							unset($oldFieldsById[$field->id]);
						}
					}

					// Drop the old fields that aren't around anymore
					foreach ($oldFieldsById as $field)
					{
						$fieldsService->deleteField($field);
					}

					// Refresh the schema cache
					craft()->db->getSchema()->refresh();
				}

				// Set the basic info on the new block type record
				$blockTypeRecord->fieldId   = $blockType->fieldId;
				$blockTypeRecord->name      = $blockType->name;
				$blockTypeRecord->handle    = $blockType->handle;
				$blockTypeRecord->sortOrder = $blockType->sortOrder;

				// Save it, minus the field layout for now
				$blockTypeRecord->save(false);

				if ($isNewBlockType)
				{
					// Set the new ID on the model
					$blockType->id = $blockTypeRecord->id;
				}

				// Save the fields and field layout
				$fieldLayoutFields = array();
				$sortOrder = 0;

				// Resetting the fieldContext here might be redundant if this isn't a new blocktype but whatever
				$contentService->fieldContext      = 'matrixBlockType:'.$blockType->id;
				$contentService->fieldColumnPrefix = 'field_'.$blockType->handle.'_';

				foreach ($blockType->getFields() as $field)
				{
					if (!$fieldsService->saveField($field))
					{
						throw new Exception(Craft::t('An error occurred while saving this Matrix block type.'));
					}

					$sortOrder++;
					$fieldLayoutFields[] = array(
						'fieldId'   => $field->id,
						'required'  => $field->required,
						'sortOrder' => $sortOrder
					);
				}

				$contentService->fieldContext        = $originalFieldContext;
				$contentService->fieldColumnPrefix   = $originalFieldColumnPrefix;
				$fieldsService->oldFieldColumnPrefix = $originalOldFieldColumnPrefix;

				$fieldLayout = new FieldLayoutModel();
				$fieldLayout->type = ElementType::MatrixBlock;
				$fieldLayout->setFields($fieldLayoutFields);
				$fieldsService->saveLayout($fieldLayout, false);

				// Update the block type model & record with our new field layout ID
				$blockType->setFieldLayout($fieldLayout);
				$blockType->fieldLayoutId = $fieldLayout->id;
				$blockTypeRecord->fieldLayoutId = $fieldLayout->id;

				// Update the block type with the field layout ID
				$blockTypeRecord->save(false);

				if (!$isNewBlockType)
				{
					// Delete the old field layout
					$fieldsService->deleteLayoutById($oldBlockType->fieldLayoutId);
				}

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

			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	 * Deletes a block type.
	 *
	 * @param MatrixBlockTypeModel $blockType
	 * @return bool
	 */
	public function deleteBlockType(MatrixBlockTypeModel $blockType)
	{
		$transaction = craft()->db->getCurrentTransaction() === null ? craft()->db->beginTransaction() : null;
		try
		{
			// First delete the blocks of this type
			$blockIds = craft()->db->createCommand()
				->select('id')
				->from('matrixblocks')
				->where(array('typeId' => $blockType->id))
				->queryColumn();

			craft()->elements->deleteElementById($blockIds);

			// Now delete the block type fields
			$originalFieldColumnPrefix = craft()->content->fieldColumnPrefix;
			craft()->content->fieldColumnPrefix = 'field_'.$blockType->handle.'_';

			foreach ($blockType->getFields() as $field)
			{
				craft()->fields->deleteField($field);
			}

			craft()->content->fieldColumnPrefix = $originalFieldColumnPrefix;

			// Delete the field layout
			craft()->fields->deleteLayoutById($blockType->fieldLayoutId);

			// Finally delete the actual block type
			$affectedRows = craft()->db->createCommand()->delete('matrixblocktypes', array('id' => $blockType->id));

			if ($transaction !== null)
			{
				$transaction->commit();
			}

			return (bool) $affectedRows;
		}
		catch (\Exception $e)
		{
			if ($transaction !== null)
			{
				$transaction->rollback();
			}

			throw $e;
		}
	}

	/**
	 * Validates a Matrix field's settings.
	 *
	 * @param MatrixSettingsModel $settings
	 * @return bool
	 */
	public function validateFieldSettings(MatrixSettingsModel $settings)
	{
		$validates = true;

		$this->_uniqueBlockTypeAndFieldHandles = array();

		foreach ($settings->getBlockTypes() as $blockType)
		{
			if (!$this->validateBlockType($blockType))
			{
				// Don't break out of the loop because we still want to get validation errors
				// for the remaining block types.
				$validates = false;
			}
		}

		return $validates;
	}

	/**
	 * Saves a Matrix field's settings.
	 *
	 * @param MatrixSettingsModel $settings
	 * @param bool $validate
	 * @return bool
	 */
	public function saveSettings(MatrixSettingsModel $settings, $validate = true)
	{
		if (!$validate || $this->validateFieldSettings($settings))
		{
			$transaction = craft()->db->getCurrentTransaction() === null ? craft()->db->beginTransaction() : null;
			try
			{
				$matrixField = $settings->getField();

				// Create the content table first since the block type fields will need it
				$oldContentTable = $this->getContentTableName($matrixField, true);
				$newContentTable = $this->getContentTableName($matrixField);

				// Do we need to create/rename the content table?
				if (!craft()->db->tableExists($newContentTable))
				{
					if ($oldContentTable && craft()->db->tableExists($oldContentTable))
					{
						MigrationHelper::renameTable($oldContentTable, $newContentTable);
					}
					else
					{
						$this->_createContentTable($newContentTable);
					}
				}

				// Delete the old block types first,
				// in case there's a handle conflict with one of the new ones
				$oldBlockTypes = $this->getBlockTypesByFieldId($matrixField->id);
				$oldBlockTypesById = array();

				foreach ($oldBlockTypes as $blockType)
				{
					$oldBlockTypesById[$blockType->id] = $blockType;
				}

				foreach ($settings->getBlockTypes() as $blockType)
				{
					if (!$blockType->isNew())
					{
						unset($oldBlockTypesById[$blockType->id]);
					}
				}

				foreach ($oldBlockTypesById as $blockType)
				{
					$this->deleteBlockType($blockType);
				}

				// Save the new ones
				$sortOrder = 0;

				$originalContentTable = craft()->content->contentTable;
				craft()->content->contentTable = $newContentTable;

				foreach ($settings->getBlockTypes() as $blockType)
				{
					$sortOrder++;
					$blockType->fieldId = $matrixField->id;
					$blockType->sortOrder = $sortOrder;
					$this->saveBlockType($blockType, false);
				}

				craft()->content->contentTable = $originalContentTable;

				if ($transaction !== null)
				{
					$transaction->commit();
				}

				return true;
			}
			catch (\Exception $e)
			{
				if ($transaction !== null)
				{
					$transaction->rollback();
				}

				throw $e;
			}
		}
		else
		{
			return false;
		}
	}

	/**
	 * Deletes a Matrix field.
	 *
	 * @param FieldModel $matrixField
	 * @return bool
	 */
	public function deleteMatrixField(FieldModel $matrixField)
	{
		$transaction = craft()->db->getCurrentTransaction() === null ? craft()->db->beginTransaction() : null;
		try
		{
			$originalContentTable = craft()->content->contentTable;
			$contentTable = $this->getContentTableName($matrixField);
			craft()->content->contentTable = $contentTable;

			// Delete the block types
			$blockTypes = $this->getBlockTypesByFieldId($matrixField->id);

			foreach ($blockTypes as $blockType)
			{
				$this->deleteBlockType($blockType);
			}

			// Drop the content table
			craft()->db->createCommand()->dropTable($contentTable);

			craft()->content->contentTable = $originalContentTable;

			if ($transaction !== null)
			{
				$transaction->commit();
			}

			return true;
		}
		catch (\Exception $e)
		{
			if ($transaction !== null)
			{
				$transaction->rollback();
			}

			throw $e;
		}
	}

	/**
	 * Returns the content table name for a given Matrix field.
	 *
	 * @param FieldModel $matrixField
	 * @param bool $useOldHandle
	 * @return string|false
	 */
	public function getContentTableName(FieldModel $matrixField, $useOldHandle = false)
	{
		$name = '';

		do
		{
			if ($useOldHandle)
			{
				if (!$matrixField->oldHandle)
				{
					return false;
				}

				$handle = $matrixField->oldHandle;
			}
			else
			{
				$handle = $matrixField->handle;
			}

			$name = '_'.strtolower($handle).$name;
		}
		while ($matrixField = $this->getParentMatrixField($matrixField));

		return 'matrixcontent'.$name;
	}

	/**
	 * Validates a block.
	 *
	 * @param MatrixBlockModel $block
	 * @return bool
	 */
	public function validateBlock(MatrixBlockModel $block)
	{
		$block->clearErrors();

		$blockRecord = $this->_getBlockRecord($block);

		$blockRecord->fieldId   = $block->fieldId;
		$blockRecord->ownerId   = $block->ownerId;
		$blockRecord->typeId    = $block->typeId;
		$blockRecord->sortOrder = $block->sortOrder;

		$blockRecord->validate();
		$block->addErrors($blockRecord->getErrors());

		$originalFieldContext = craft()->content->fieldContext;
		craft()->content->fieldContext = 'matrixBlockType:'.$block->typeId;

		$fieldLayout = $block->getType()->getFieldLayout();

		craft()->content->prepElementContentForSave($block, $fieldLayout);

		if (!craft()->content->validateElementContent($block, $fieldLayout))
		{
			$block->addErrors($block->getContent()->getErrors());
		}

		craft()->content->fieldContext = $originalFieldContext;

		return !$block->hasErrors();
	}

	/**
	 * Saves a block.
	 *
	 * @param MatrixBlockModel $block
	 * @param bool             $validate
	 * @throws \Exception
	 * @return bool
	 */
	public function saveBlock(MatrixBlockModel $block, $validate = true)
	{
		if (!$validate || $this->validateBlock($block))
		{
			$transaction = craft()->db->getCurrentTransaction() === null ? craft()->db->beginTransaction() : null;
			try
			{
				$blockRecord = $this->_getBlockRecord($block);
				$isNewBlock = $blockRecord->isNewRecord();

				$blockRecord->fieldId   = $block->fieldId;
				$blockRecord->ownerId   = $block->ownerId;
				$blockRecord->typeId    = $block->typeId;
				$blockRecord->sortOrder = $block->sortOrder;

				if (!$isNewBlock)
				{
					$elementRecord = $blockRecord->element;

					$elementLocaleRecord = ElementLocaleRecord::model()->findByAttributes(array(
						'elementId' => $block->id,
						'locale'    => $block->locale
					));
				}
				else
				{
					$elementRecord = new ElementRecord();
					$elementRecord->type = ElementType::MatrixBlock;
				}

				$elementRecord->enabled = $block->enabled;

				if (empty($elementLocaleRecord))
				{
					$elementLocaleRecord = new ElementLocaleRecord();
					$elementLocaleRecord->locale = $block->locale;
				}

				// Save the element block first
				$elementRecord->save(false);

				// Now that we have an element ID, save it on the other stuff
				$block->id = $elementRecord->id;
				$blockRecord->id = $block->id;
				$elementLocaleRecord->elementId = $block->id;
				$block->getContent()->elementId = $block->id;

				$blockRecord->save(false);
				$elementLocaleRecord->save(false);

				$originalFieldContext = craft()->content->fieldContext;
				craft()->content->fieldContext = 'matrixBlockType:'.$block->typeId;

				$originalFieldColumnPrefix = craft()->content->fieldColumnPrefix;
				craft()->content->fieldColumnPrefix = 'field_'.$block->getType()->handle.'_';

				craft()->content->saveContent($block->getContent());
				craft()->content->postSaveOperations($block, $block->getContent());

				craft()->content->fieldContext = $originalFieldContext;
				craft()->content->fieldColumnPrefix = $originalFieldColumnPrefix;

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

			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	 * Saves a Matrix field.
	 *
	 * @param FieldModel $matrixField
	 * @param int        $ownerId
	 * @param array      $blocks
	 * @throws \Exception
	 * @return bool
	 */
	public function saveField(FieldModel $matrixField, $ownerId, $blocks)
	{
		$transaction = craft()->db->getCurrentTransaction() === null ? craft()->db->beginTransaction() : null;
		try
		{
			$originalContentTable = craft()->content->contentTable;
			craft()->content->contentTable = $this->getContentTableName($matrixField);

			$blockIds = array();

			foreach ($blocks as $block)
			{
				// The owner ID might not have been set yet
				$block->ownerId = $ownerId;

				$this->saveBlock($block, false);

				$blockIds[] = $block->id;
			}

			// Get the IDs of blocks that are row deleted
			$deletedBlockIds = craft()->db->createCommand()
				->select('id')
				->from('matrixblocks')
				->where(array('and',
					'ownerId = :ownerId',
					'fieldId = :fieldId',
					array('not in', 'id', $blockIds)
				), array(
					':ownerId' => $ownerId,
					':fieldId' => $matrixField->id
				))
				->queryColumn();

			craft()->elements->deleteElementById($deletedBlockIds);

			craft()->content->contentTable = $originalContentTable;

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

		return true;
	}

	/**
	 * Returns the parent Matrix field, if any.
	 *
	 * @param FieldModel       $matrixField
	 * @return FieldModel|null
	 */
	public function getParentMatrixField(FieldModel $matrixField)
	{
		if (!isset($this->_parentMatrixFields) || !array_key_exists($matrixField->id, $this->_parentMatrixFields))
		{
			// Does this Matrix field belong to another one?
			$parentMatrixFieldId = craft()->db->createCommand()
				->select('fields.id')
				->from('fields fields')
				->join('matrixblocktypes blocktypes', 'blocktypes.fieldId = fields.id')
				->join('fieldlayoutfields fieldlayoutfields', 'fieldlayoutfields.layoutId = blocktypes.fieldLayoutId')
				->where('fieldlayoutfields.fieldId = :matrixFieldId', array(':matrixFieldId' => $matrixField->id))
				->queryScalar();

			if ($parentMatrixFieldId)
			{
				$this->_parentMatrixFields[$matrixField->id] = craft()->fields->getFieldById($parentMatrixFieldId);
			}
			else
			{
				$this->_parentMatrixFields[$matrixField->id] = null;
			}
		}

		return $this->_parentMatrixFields[$matrixField->id];
	}

	/**
	 * Returns a block type record by its ID or creates a new one.
	 *
	 * @access private
	 * @param  MatrixBlockTypeModel $blockType
	 * @throws Exception
	 * @return MatrixBlockTypeRecord
	 */
	private function _getBlockTypeRecord(MatrixBlockTypeModel $blockType)
	{
		if (!$blockType->isNew())
		{
			$blockTypeId = $blockType->id;

			if (!isset($this->_blockTypeRecordsById) || !array_key_exists($blockTypeId, $this->_blockTypeRecordsById))
			{
				$this->_blockTypeRecordsById[$blockTypeId] = MatrixBlockTypeRecord::model()->findById($blockTypeId);

				if (!$this->_blockTypeRecordsById[$blockTypeId])
				{
					throw new Exception(Craft::t('No block type exists with the ID “{id}”', array('id' => $blockTypeId)));
				}
			}

			return $this->_blockTypeRecordsById[$blockTypeId];
		}
		else
		{
			return new MatrixBlockTypeRecord();
		}
	}

	/**
	 * Returns a block record by its ID or creates a new one.
	 *
	 * @access private
	 * @param  MatrixBlockModel $block
	 * @throws Exception
	 * @return MatrixBlockRecord
	 */
	private function _getBlockRecord(MatrixBlockModel $block)
	{
		$blockId = $block->id;

		if ($blockId)
		{
			if (!isset($this->_blockRecordsById) || !array_key_exists($blockId, $this->_blockRecordsById))
			{
				$this->_blockRecordsById[$blockId] = MatrixBlockRecord::model()->with('element')->findById($blockId);

				if (!$this->_blockRecordsById[$blockId])
				{
					throw new Exception(Craft::t('No block exists with the ID “{id}”', array('id' => $blockId)));
				}
			}

			return $this->_blockRecordsById[$blockId];
		}
		else
		{
			return new MatrixBlockRecord();
		}
	}

	/**
	 * Creates the content table for a Matrix field.
	 *
	 * @access private
	 * @param string $name
	 */
	private function _createContentTable($name)
	{
		craft()->db->createCommand()->createTable($name, array(
			'elementId' => array('column' => ColumnType::Int, 'null' => false),
			'locale'    => array('column' => ColumnType::Locale, 'null' => false)
		));

		craft()->db->createCommand()->createIndex($name, 'elementId,locale', true);
		craft()->db->createCommand()->addForeignKey($name, 'elementId', 'elements', 'id', 'CASCADE', null);
		craft()->db->createCommand()->addForeignKey($name, 'locale', 'locales', 'locale', 'CASCADE', 'CASCADE');
	}
}
