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
class AppController extends BaseController
{
	/**
	 * Returns update info.
	 */
	public function actionCheckForUpdates()
	{
		craft()->userSession->requirePermission('performUpdates');

		$forceRefresh = (bool) craft()->request->getPost('forceRefresh');
		craft()->updates->getUpdates($forceRefresh);

		$this->returnJson(array(
			'total'    => craft()->updates->getTotalAvailableUpdates(),
			'critical' => craft()->updates->isCriticalUpdateAvailable()
		));
	}

	/**
	 * Loads any CP alerts.
	 */
	public function actionGetCpAlerts()
	{
		$this->requireAjaxRequest();
		craft()->userSession->requirePermission('accessCp');

		$path = craft()->request->getRequiredPost('path');

		// Fetch 'em and send 'em
		$alerts = CpHelper::getAlerts($path, true);
		$this->returnJson($alerts);
	}

	/**
	 * Shuns a CP alert for 24 hours.
	 */
	public function actionShunCpAlert()
	{
		$this->requireAjaxRequest();
		craft()->userSession->requirePermission('accessCp');

		$message = craft()->request->getRequiredPost('message');
		$user = craft()->userSession->getUser();

		$currentTime = DateTimeHelper::currentUTCDateTime();
		$tomorrow = $currentTime->add(new DateInterval('P1D'));

		if (craft()->users->shunMessageForUser($user->id, $message, $tomorrow))
		{
			$this->returnJson(array(
				'success' => true
			));
		}
		else
		{
			$this->returnErrorJson(Craft::t('An unknown error occurred.'));
		}
	}

	/**
	 * Transfers the Craft license to the current domain.
	 */
	public function actionTransferLicenseToCurrentDomain()
	{
		$this->requireAjaxRequest();
		$this->requirePostRequest();
		craft()->userSession->requireAdmin();

		$response = craft()->et->transferLicenseToCurrentDomain();

		if ($response === true)
		{
			$this->returnJson(array(
				'success' => true
			));
		}
		else
		{
			$this->returnErrorJson($response);
		}
	}

	/**
	 * Fetches the installed package info from Elliott.
	 */
	public function actionFetchPackageInfo()
	{
		$this->requireAjaxRequest();
		craft()->userSession->requireAdmin();

		$etResponse = craft()->et->fetchPackageInfo();

		if ($etResponse)
		{
			// Make sure we've got a valid license key (mismatched domain is OK for these purposes)
			if ($etResponse->licenseKeyStatus != LicenseKeyStatus::Invalid)
			{
				$packages = $etResponse->data;

				// Include which packages are actually licensed
				foreach ($etResponse->licensedPackages as $packageName)
				{
					$packages[$packageName]['licensed'] = true;
				}

				// Include which packages are in trial
				foreach ($etResponse->packageTrials as $packageName => $expiryDate)
				{
					$currentTime = DateTimeHelper::currentUTCDateTime();
					$diff = $expiryDate - $currentTime->getTimestamp();
					$daysLeft = round($diff / 86400); // 60 * 60 * 24

					$packages[$packageName]['trial'] = true;
					$packages[$packageName]['daysLeftInTrial'] = $daysLeft;
				}

				$this->returnJson(array(
					'success'  => true,
					'packages' => $packages
				));
			}
			else
			{
				$this->returnErrorJson(Craft::t('Your license key is invalid.'));
			}
		}
		else
		{
			$this->returnErrorJson(Craft::t('Craft is unable to fetch package info at this time.'));
		}
	}

	/**
	 * Passes along a given CC token to Elliott to purchase a package.
	 */
	public function actionPurchasePackage()
	{
		$this->requirePostRequest();
		$this->requireAjaxRequest();
		craft()->userSession->requireAdmin();

		$model = new PackagePurchaseOrderModel(array(
			'ccTokenId'     => craft()->request->getRequiredPost('ccTokenId'),
			'package'       => craft()->request->getRequiredPost('package'),
			'expectedPrice' => craft()->request->getRequiredPost('expectedPrice'),
		));

		if (craft()->et->purchasePackage($model))
		{
			$this->returnJson(array(
				'success' => true,
				'package' => $model->package
			));
		}
		else
		{
			$this->returnJson(array(
				'errors' => $model->getErrors()
			));
		}
	}

	/**
	 * Installs a package.
	 */
	public function actionInstallPackage()
	{
		$this->requirePostRequest();
		$this->requireAjaxRequest();
		craft()->userSession->requireAdmin();

		$package = craft()->request->getRequiredPost('package');
		$success = craft()->installPackage($package);

		$this->returnJson(array(
			'success' => $success
		));
	}

	/**
	 * Uninstalls a package.
	 */
	public function actionUninstallPackage()
	{
		$this->requirePostRequest();
		$this->requireAjaxRequest();
		craft()->userSession->requireAdmin();

		$package = craft()->request->getRequiredPost('package');
		$success = craft()->uninstallPackage($package);

		$this->returnJson(array(
			'success' => $success
		));
	}

	/**
	 * Begins a package trial.
	 */
	public function actionBeginPackageTrial()
	{
		$this->requirePostRequest();
		$this->requireAjaxRequest();
		craft()->userSession->requireAdmin();

		$model = new TryPackageModel(array(
			'packageHandle' => craft()->request->getRequiredPost('package'),
		));

		if (craft()->et->tryPackage($model))
		{
			$this->returnJson(array(
				'success' => true,
				'package' => $model->packageHandle
			));
		}
		else
		{
			$this->returnJson(array(
				'errors' => $model->getErrors()
			));
		}
	}
}
