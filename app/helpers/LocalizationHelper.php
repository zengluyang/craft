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
class LocalizationHelper
{
	/**
	 * Will take a decimal value, will remove the locale specific grouping separator and change the locale specific
	 * decimal so a dot.
	 *
	 * @static
	 * @param $number
	 * @return mixed
	 */
	public static function normalizeNumber($number)
	{
		$language = craft()->language;
		$languageData = craft()->i18n->getLocaleData($language);
		$decimalSymbol = $languageData->getNumberSymbol('decimal');
		$groupSymbol = $languageData->getNumberSymbol('group');

		$number = str_replace($groupSymbol, '', $number);
		$number = str_replace($decimalSymbol, '.', $number);

		return $number;
	}
}
