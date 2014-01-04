<?php

/**
 * Craft by Pixel & Tonic
 *
 * @package   Craft
 * @author    Pixel & Tonic, Inc.
 * @copyright Copyright (c) 2013, Pixel & Tonic, Inc.
 * @license   http://buildwithcraft.com/license Craft License Agreement
 * @link      http://buildwithcraft.com
 */

namespace Craft;


class Requirements
{
	public static function getRequirements()
	{
		$requiredPhpVersion = '5.3.0';
		$requiredMysqlVersion = '5.1.0';

		return array(
			new Requirement(
				Craft::t('PHP Version'),
				version_compare(PHP_VERSION, $requiredPhpVersion, ">="),
				true,
				'<a href="http://buildwithcraft.com">Craft</a>',
				Craft::t('PHP {version} or higher is required.', array('version' => $requiredPhpVersion))
			),
			new Requirement(
				Craft::t('$_SERVER Variable'),
				($message = static::_checkServerVar()) === '',
				true,
				'<a href="http://buildwithcraft.com">Craft</a>',
				$message
			),
			new Requirement(
				Craft::t('Reflection extension'),
				class_exists('Reflection', false),
				true,
				'<a href="http://buildwithcraft.com">Craft</a>',
				'The <a href="http://php.net/manual/en/class.reflectionextension.php">ReflectionExtension</a> is required.'
			),
			new Requirement(
				Craft::t('PCRE extension'),
				extension_loaded("pcre"),
				true,
				'<a href="http://buildwithcraft.com">Craft</a>',
				'<a href="http://php.net/manual/en/book.pcre.php">PCRE</a> is required.'
			),
			new Requirement(
				'SPL extension',
				extension_loaded("SPL"),
				true,
				'<a href="http://buildwithcraft.com">Craft</a>',
				'<a href="http://php.net/manual/en/book.spl.php">SPL</a> is required.'
			),
			new Requirement(
				Craft::t('PDO extension'),
				extension_loaded('pdo'),
				true,
				Craft::t('All <a href="http://www.yiiframework.com/doc/api/#system.db">DB-related classes</a>'),
				'<a href="http://php.net/manual/en/book.pdo.php">PDO</a> is required.'
			),
			new Requirement(
				Craft::t('PDO MySQL extension'),
				extension_loaded('pdo_mysql'),
				true,
				Craft::t('All <a href="http://www.yiiframework.com/doc/api/#system.db">DB-related classes</a>'),
				Craft::t('The <http://php.net/manual/en/ref.pdo-mysql.php>PDO MySQL</a> driver is required if you are using a MySQL database.')
			),
			new Requirement(
				Craft::t('Mcrypt extension'),
				extension_loaded('mcrypt'),
				true,
				'<a href="http://www.yiiframework.com/doc/api/CSecurityManager">CSecurityManager</a>',
				Craft::t('<a href="http://php.net/manual/en/book.mcrypt.php">Mcrypt</a> is required.')
			),
			new Requirement(
				Craft::t('GD extension with FreeType support or Imagick extension'),
				extension_loaded('gd') || extension_loaded('imagick'),
				true,
				'<a href="http://buildwithcraft.com">Craft</a>',
				'<a href="http://php.net/manual/en/book.image.php">GD</a> or <a href="http://php.net/manual/en/class.imagick.php">Imagick</a> is required.'
			),
			new Requirement(
				Craft::t('MySQL version'),
				version_compare(craft()->db->getServerVersion(), $requiredMysqlVersion, ">="),
				true,
				'<a href="http://buildwithcraft.com">Craft</a>',
				Craft::t('MySQL {version} or higher is required to run Craft.', array('version' => $requiredMysqlVersion))
			),
			new Requirement(
				Craft::t('MySQL InnoDB support'),
				static::_isInnoDbEnabled(),
				true,
				'<a href="http://buildwithcraft.com">Craft</a>',
				Craft::t('Craft requires the MySQL InnoDB storage engine to run.')
			),
			new Requirement(
				Craft::t('SSL support'),
				extension_loaded('openssl'),
				true,
				'<a href="http://buildwithcraft.com">Craft</a>',
				Craft::t('Craft requires <a href="http://php.net/manual/en/book.openssl.php">OpenSSL</a> in order to run.')
			),
			new Requirement(
				Craft::t('cURL support'),
				extension_loaded('curl'),
				true,
				'<a href="http://buildwithcraft.com">Craft</a>',
				Craft::t('Craft requires <a href="http://php.net/manual/en/book.curl.php">cURL</a> in order to run.')
			),
			new Requirement(
				Craft::t('crypt() with CRYPT_BLOWFISH enabled'),
				true,
				function_exists('crypt') && defined('CRYPT_BLOWFISH') && CRYPT_BLOWFISH,
				'<a href="http://www.yiiframework.com/doc/api/1.1/CPasswordHelper">CPasswordHelper</a>',
				Craft::t('Craft requires the <a href="http://php.net/manual/en/function.crypt.php">crypt()</a> function with CRYPT_BLOWFISH enabled for secure password storage.')
			),
			new Requirement(
				Craft::t('PCRE UTF-8 support'),
				preg_match('/./u', 'Ü') === 1,
				true,
				'<a href="http://buildwithcraft.com">Craft</a>',
				Craft::t('<a href="http://php.net/manual/en/book.pcre.php">PCRE</a> must be compiled to support UTF-8.')
			),
			new Requirement(
				Craft::t('Multibyte String support'),
				(extension_loaded('mbstring') && ini_get('mbstring.func_overload') != 1),
				true,
				'<a href="http://buildwithcraft.com">Craft</a>',
				Craft::t('Craft requires the <a href="http://www.php.net/manual/en/book.mbstring.php">Multibyte String extension</a> with <a href="http://php.net/manual/en/mbstring.overload.php">Function Overloading</a> disabled in order to run.')
			),
			new Requirement(
				Craft::t('iconv support'),
				function_exists('iconv'),
				false,
				'<a href="http://buildwithcraft.com">Craft</a>',
				Craft::t('Craft requires <a href="http://php.net/manual/en/book.iconv.php">iconv</a> in order to run.')
			),
		);
	}

	/**
	 * @access private
	 * @return string
	 */
	private static function _checkServerVar()
	{
		$vars = array('HTTP_HOST', 'SERVER_NAME', 'SERVER_PORT', 'SCRIPT_NAME', 'SCRIPT_FILENAME', 'PHP_SELF', 'HTTP_ACCEPT', 'HTTP_USER_AGENT');
		$missing = array();

		foreach($vars as $var)
		{
			if (!isset($_SERVER[$var]))
			{
				$missing[] = $var;
			}
		}

		if (!empty($missing))
		{
			return Craft::t('$_SERVER does not have {messages}.', array('messages' => implode(', ', $missing)));
		}

		if (!isset($_SERVER["REQUEST_URI"]) && isset($_SERVER["QUERY_STRING"]))
		{
			return Craft::t('Either $_SERVER["REQUEST_URI"] or $_SERVER["QUERY_STRING"] must exist.');
		}

		if (!isset($_SERVER["PATH_INFO"]) && strpos($_SERVER["PHP_SELF"], $_SERVER["SCRIPT_NAME"]) !== 0)
		{
			return Craft::t('Unable to determine URL path info. Please make sure $_SERVER["PATH_INFO"] (or $_SERVER["PHP_SELF"] and $_SERVER["SCRIPT_NAME"]) contains proper value.');
		}

		return '';
	}

	/**
	 * Checks to see if the MySQL InnoDB storage engine is installed and enabled.
	 *
	 * @access private
	 * @return bool
	 */
	private function _isInnoDbEnabled()
	{
		$results = craft()->db->createCommand()->setText('SHOW ENGINES')->queryAll();

		foreach ($results as $result)
		{
			if (mb_strtolower($result['Engine']) == 'innodb' && mb_strtolower($result['Support']) != 'no')
			{
				return true;
			}
		}

		return false;
	}
}
