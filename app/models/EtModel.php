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
class EtModel extends BaseModel
{
	/**
	 * @access protected
	 * @return array
	 */
	protected function defineAttributes()
	{
		// The client license key.
		$attributes['licenseKey'] = AttributeType::String;

		// The license key status.  Set by the server response.
		$attributes['licenseKeyStatus']  = AttributeType::String;

		// The domain that the license is associated with
		$attributes['licensedDomain'] = AttributeType::String;

		// Extra arbitrary data to send to the server.
		$attributes['data'] = AttributeType::Mixed;

		// The url making the request.
		$attributes['requestUrl'] = array(AttributeType::String, 'default' => '');

		// The IP address making the request.
		$attributes['requestIp'] = array(AttributeType::String, 'default' => '1.1.1.1');

		// The time the request was made.
		$attributes['requestTime'] = array(AttributeType::DateTime, 'default' => DateTimeHelper::currentTimeForDb());

		// The port number the request comes from.
		$attributes['requestPort'] = AttributeType::String;

		// Any packages installed on the client.
		$attributes['installedPackages'] = array(AttributeType::Mixed, 'default' => array());

		// All the packages that are actually licensed.
		$attributes['licensedPackages'] = array(AttributeType::Mixed, 'default' => array());

		// Any packages that are in trial mode.
		$attributes['packageTrials'] = array(AttributeType::Mixed, 'default' => array());

		// The local version number.
		$attributes['localVersion'] = array(AttributeType::String, 'required' => true);

		// The local build number.
		$attributes['localBuild'] = array(AttributeType::Number, 'required' => true);

		// The currently logged in user's email address.
		$attributes['userEmail'] = AttributeType::String;

		// The track this install is on.  Not required for backwards compatibility.
		$attributes['track'] = array(AttributeType::String);

		// Any errors to return;
		$attributes['errors'] = AttributeType::Mixed;

		return $attributes;
	}

	/*
	 *
	 */
	public function decode()
	{
		echo JsonHelper::decode($this);
	}
}
