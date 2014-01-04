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
class RoutesService extends BaseApplicationComponent
{
	private $_routes;

	/**
	 * Returns all of the routes.
	 *
	 * @return array
	 */
	public function getAllRoutes()
	{
		if (!isset($this->_routes))
		{
			$this->_routes = array();

			// Where should we look for routes?
			if (craft()->config->get('siteRoutesSource') == 'file')
			{
				$path = craft()->path->getConfigPath().'routes.php';

				if (IOHelper::fileExists($path))
				{
					$this->_routes = require_once $path;
				}
			}
			else
			{
				$records = RouteRecord::model()->ordered()->findAll();

				foreach ($records as $record)
				{
					$this->_routes[$record->urlPattern] = $record->template;
				}
			}
		}

		return $this->_routes;
	}

	/**
	 * Saves a new or existing route.
	 *
	 * @param array  $urlParts The URL as defined by the user.
	 * This is an array where each element is either a string
	 * or an array containing the name of a subpattern and the subpattern.
	 * @param string $template The template to route matching URLs to.
	 * @param int    $routeId The route ID, if editing an existing route.
	 *
	 * @throws Exception
	 * @return RouteRecord
	 */
	public function saveRoute($urlParts, $template, $routeId = null)
	{
		if ($routeId !== null)
		{
			$route = $this->_getRecordRouteById($routeId);

			if (!$route)
			{
				throw new Exception(Craft::t('No route exists with the ID “{id}”', array('id' => $routeId)));
			}
		}
		else
		{
			$route = new RouteRecord();

			// Get the next biggest sort order
			$maxSortOrder = craft()->db->createCommand()
				->select('max(sortOrder)')
				->from('routes')
				->queryScalar();

			$route->sortOrder = $maxSortOrder + 1;
		}

		// Compile the URL parts into a regex pattern
		$urlPattern = '';
		$urlParts = array_filter($urlParts);

		foreach ($urlParts as $part)
		{
			if (is_string($part))
			{
				// Escape any special regex characters
				$urlPattern .= StringHelper::escapeRegexChars($part);
			}
			else if (is_array($part))
			{
				// Is the name a valid handle?
				if (preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $part[0]))
				{
					// Add the var as a named subpattern
					$urlPattern .= '(?P<'.preg_quote($part[0]).'>'.$part[1].')';
				}
				else
				{
					// Just match it
					$urlPattern .= '('.$part[1].')';
				}
			}
		}

		$route->urlParts = JsonHelper::encode($urlParts);
		$route->urlPattern = $urlPattern;
		$route->template = $template;
		$route->save();

		return $route;
	}

	/**
	 * Deletes a route by its ID.
	 *
	 * @param int $routeId
	 * @return bool
	 */
	public function deleteRouteById($routeId)
	{
		craft()->db->createCommand()->delete('routes', array('id' => $routeId));
		return true;
	}

	/**
	 * Updates the route order.
	 *
	 * @param array $routeIds An array of each of the route IDs, in their new order.
	 */
	public function updateRouteOrder($routeIds)
	{
		foreach ($routeIds as $order => $routeId)
		{
			$data = array('sortOrder' => $order + 1);
			$condition = array('id' => $routeId);
			craft()->db->createCommand()->update('routes', $data, $condition);
		}
	}

	/**
	 * Returns a route by its ID.
	 *
	 * @param int $routeId The route ID
	 * @return RouteRecord|null
	 */
	private function _getRecordRouteById($routeId)
	{
		return RouteRecord::model()->findById($routeId);
	}
}
