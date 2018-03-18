<?php
/**
 * ownCloud - user_cas
 *
 * @author Felix Rupp <kontakt@felixrupp.com>
 * @copyright Felix Rupp <kontakt@felixrupp.com>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Affero General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\UserCAS\Controller;


use \OCP\IRequest;
use \OCP\AppFramework\Http\RedirectResponse;
use \OCP\AppFramework\Controller;
use \OCP\IConfig;
use \OCP\IUserSession;

use OCA\UserCAS\Service\AppService;
use OCA\UserCAS\Service\UserService;
use OCA\UserCAS\Service\LoggingService;
use OCA\UserCAS\Exception\PhpCas\PhpUserCasLibraryNotFoundException;


/**
 * Class AuthenticationController
 *
 * @package OCA\UserCAS\Controller
 *
 * @author Felix Rupp <kontakt@felixrupp.com>
 * @copyright Felix Rupp <kontakt@felixrupp.com>
 *
 * @since 1.4.0
 */
class AuthenticationController extends Controller
{

    /**
     * @var string $appName
     */
    protected $appName;

    /**
     * @var \OCP\IConfig $config
     */
    private $config;

    /**
     * @var \OCA\UserCAS\Service\UserService $userService
     */
    private $userService;

    /**
     * @var \OCA\UserCAS\Service\AppService $appService
     */
    private $appService;

    /**
     * @var IUserSession $userSession
     */
    private $userSession;

    /**
     * @var \OCA\UserCAS\Service\LoggingService $loggingService
     */
    private $loggingService;

    /**
     * AuthenticationController constructor.
     * @param $appName
     * @param IRequest $request
     * @param IConfig $config
     * @param UserService $userService
     * @param AppService $appService
     * @param IUserSession $userSession
     * @param LoggingService $loggingService
     */
    public function __construct($appName, IRequest $request, IConfig $config, UserService $userService, AppService $appService, IUserSession $userSession, LoggingService $loggingService)
    {
        $this->appName = $appName;
        $this->config = $config;
        $this->userService = $userService;
        $this->appService = $appService;
        $this->userSession = $userSession;
        $this->loggingService = $loggingService;
        parent::__construct($appName, $request);
    }

    /**
     * Login method.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     * @PublicPage
     *
     * @return \OCP\AppFramework\Http\RedirectResponse
     */
    public function casLogin()
    {

        if (!$this->appService->isCasInitialized()) {

            try {

                $this->appService->init();
            } catch (PhpUserCasLibraryNotFoundException $e) {

                $this->loggingService->write(\OCP\Util::FATAL, 'Fatal error with code: ' . $e->getCode() . ' and message: ' . $e->getMessage());

                header("Location: " . $this->appService->getAbsoluteURL('/'));
                die();
            }
        }

        # Handle redirect based on cookie value
        if (isset($_COOKIE['user_cas_redirect_url'])) {

            $location = $this->appService->getAbsoluteURL(urldecode($_COOKIE['user_cas_redirect_url']));
        } else {

            $location = $this->appService->getAbsoluteURL("/");
        }

        $this->loggingService->write(\OCP\Util::DEBUG, 'The Redirect URL Parameter in Login Action was: ' . $location);

        if (!$this->userService->isLoggedIn()) {

            try {

                if (\phpCAS::isAuthenticated()) {

                    $userName = \phpCAS::getUser();

                    $this->loggingService->write(\OCP\Util::INFO, "phpCAS user " . $userName . " has been authenticated.");

                    $isLoggedIn = $this->userService->login($this->request, $userName, '');

                    if ($isLoggedIn) {

                        $this->loggingService->write(\OCP\Util::DEBUG, "phpCAS user has been authenticated against owncloud.");

                        return new RedirectResponse($location);
                    } else { # Not authenticated against owncloud

                        $this->loggingService->write(\OCP\Util::ERROR, "phpCAS user has not been authenticated against owncloud.");

                        $redirectResponse = new RedirectResponse($this->appService->linkToRoute('core.login.showLoginForm'));
                        $redirectResponse->setStatus(\OCP\AppFramework\Http::STATUS_FORBIDDEN);

                        return $redirectResponse;
                    }
                } else { # Not authenticated against CAS

                    $this->loggingService->write(\OCP\Util::INFO, "phpCAS user is not authenticated, redirect to CAS server.");

                    \phpCAS::forceAuthentication();
                }
            } catch (\CAS_Exception $e) {

                $this->loggingService->write(\OCP\Util::ERROR, "phpCAS has thrown an exception with code: " . $e->getCode() . " and message: " . $e->getMessage() . ".");

                $redirectResponse = new RedirectResponse($this->appService->linkToRoute('core.login.showLoginForm'));
                $redirectResponse->setStatus(\OCP\AppFramework\Http::STATUS_INTERNAL_SERVER_ERROR);

                return $redirectResponse;
            }
        } else {

            $this->loggingService->write(\OCP\Util::INFO, "phpCAS user is already authenticated against owncloud.");

            return new RedirectResponse($location);
        }
    }
}