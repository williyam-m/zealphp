<?php
namespace ZealPHP;
use ZealPHP\G;

/**
 * Session class is a bunch of static methods helpful for easy handling of the session.
 */
class Session
{
    /**
     * Default page title. Modified at the entry point @ /*.php.
     * @var string
     */
    public static $pageTitle = 'Welcome to ZealPHP';

    /**
     * Sublogo is rendered as the text aside of the logo brand text. Modified at the entry point @ /*.php.
     * @var string
     */
    public static $subLogo = '';

    /**
     * CSRF token for the session, generated by the PageViewInsights. Initialized at WebAPI.
     * @var string
     */
    public static $csrfToken = '';

    /**
     * Version information generated by the QuickGit class. Initialized at WebAPI.
     * @var string
     */
    public static $version = '';
    public static $fullVersion = '';
    public static $versionDescription = '';

    /**
     * Environment decided by the WebAPI. It can be  local, beta or prod. Used for several features inside the webapp.
     * @var string
     */
    public static $environment = '';

    /**
     * LocalPack is an array, which can have array("title"=>"", "message"=>"") as it's resource. It get's popped up if set during the session at the client side only on an API request limited only to the local $environment.
     * @var null
     */
    public static $localPack = null;

    /**
     * Modified at the entry point @ /*.php, the array can be specified with the list of CSS files to be loaded at for the page.
     * @var array
     */
    public static $customCss = array();

    /**
     * Modified at the entry point @ /*.php, the array can be specified with the list of JS files to be loaded at for the page.
     * @var array
     */
    public static $customJs = array();

    /**
     * Generated at the WebAPI on the page load. It is one of the first things to get processed. If cookie information is present, it automatically generates all the session information about the current user(if logged in).
     * @var UserSession
     */
    public static $userSession = null;

    /**
     * This vairable is used to check if the user is logged in or not.
     * @var String Constants::STATUS_DEFAULT || Constants::STATUS_LOGGEDIN.
     */
    public static $authStatus = null;

    /**
     * If the current user is a moderator, this flag is set true.
     * @var boolean
     */
    public static $isModerator = false;

    /**
     * All the previledges of the current user is loaded as an array. [NOT IMPLEMENTED YET //TODO]
     * @var Array
     */
    public static $privileges = null;
    public static $privilegesGroup = null;

    /**
     * Any key/value pair needs to be shared accross the session can be stored here. Used by the Session::get()/Session::set() methods.
     * @var array
     */
    public static $property = array();

    /**
     * Used by Console::log to print to javascript logs
     *
     * @var array
     */
    public static $consolelogs = array();

    /**
     * Meta tags need to be set for the page load. This is getting processed in _meta.php - but it will not have an effect after Session::loadMaster() has been called.
     * @var array
     */
    public static $meta = array();

    /**
     * CacheCDN domain, decided by the WebAPI for the current environment. Utilized by the Session::cacheCDN/cdn method.
     * @var String
     */
    public static $cacheCDN = null;

    /**
     * The flag is set true, if the user is a superuser.
     * @var boolean
     */
    public static $isSuperUser = false;

    public static $documentRoot = null;
    /**
     * Initiailizes session by calling session_start() and prepares console.
     */
    public static function init()
    {
        $g = G::instance();
        Session::$documentRoot = $g->server['DOCUMENT_ROOT'];
        if (php_sapi_name() == "cli" and StringUtils::str_ends_with($g->server['PHP_SELF'], 'worker.php')) {
            parse_str(implode('&', array_slice((array)$g->server['argv'], 1)), $_GET);
            if (isset($_GET['sessid'])) {
                // logit("Trying to reconstruct session ID from $_GET[sessid]", "fatal");
                session_id($_GET['sessid']);
                session_start();
                $session = array();
                foreach ($g->session as $k => $v) {
                    $session[$k] = $v;
                }
                session_commit();
                session_write_close();
                $newid = session_create_id('worker-');
                ini_set('session.use_strict_mode', 0);
                session_id($newid);
                session_start();
                foreach ($session as $k => $v) {
                    $g->session[$k] = $v;
                }
            } else {
                elog("No session id", "fatal");
            }
        } else {
            session_start();
            Session::$consolelogs = array();
        }
    }

    // /**
    //  * Masked by the cdn() method defined at Init.php, this method generates the CacheCDN url for the provided URI.
    //  * @param  String $url
    //  * @return String
    //  */
    // public static function cacheCDN($url) {
    // 	$result = new Url($url);
    // 	$result->append('_', Session::$version);
    // 	$result = $result->getAbsoluteUrl(Session::$cacheCDN);
    // 	return $result;
    // }

    public static function getUser()
    {
        // if(is_array(Session::$userSession)) {
        //     throw new Exception('getUser method not found in Session::$userSession');
        // }
        // if (!method_exists(Session::$userSession, 'getUser')) {
        //     throw new Exception('getUser method not found in Session::$userSession');
        // }
        $us = Session::getUserSession();
        if (is_array($us)) {
            if ($us['env'] == "worker") {
                return null;
            }
        }
        if (empty($us)) {
            return null;
        }

        if (get_class($us) === "UserSession") {
            if (empty($us->getUser())) {
                return null;
            }
            return $us->getUser();
        }
        return null;
    }

    public static function getUserSession()
    {
        return Session::$userSession;
    }

    public static function getUserID()
    {
        return empty(Session::getUserSession()) ? null : Session::getUserSession()->getUser()->getID(false);
    }

    public static function getAuthStatus()
    {
        return Session::$authStatus;
    }

    /**
     * Session wide property sharing method, which sets a value against a key.
     * @param String $key   Key for the value
     * @param Any $value Value can be of any type.
     */
    public static function set($key, $value)
    {
        $g = G::instance();
        $g->session[$key] = $value;
    }

    public static function unset($key)
    {
        $g = G::instance();
        unset($g->session[$key]);
    }

    /**
     * Checks if the given key is present in $g->session, only if session_start() is already called.
     *
     * @param String $key
     * @return Any
     */
    public static function isset($key)
    {
        $g = G::instance();
        return isset($g->session[$key]);
    }

    /**
     * Session wide property sharing method, which gets the value against a key. If default is supplied, when key is not found, default is returned.
     * @param  String $key
     * @param Bool $default
     * @return Any
     */
    public static function get($key, $default = false)
    {
        $g = G::instance();
        if (isset($g->session[$key])) {
            return $g->session[$key];
        } else {
            return $default;
        }
    }


    /**
     * To display the errors in the webpages
     */
    public static function devEnv($enable = true)
    {
        ini_set('display_errors', $enable);
        ini_set('display_startup_errors', $enable);
        if ($enable) {
            error_reporting(E_ALL);
        } else {
            error_reporting(0);
        }
    }

    /**
     * Generate pesudo random hash encoded with Base64
     * @param  integer $int length in bytes
     * @return String       Base64 representation of the pseudorandom bytes.
     */
    public static function generatePesudoRandomHash($int = 10)
    {
        return base64_encode(openssl_random_pseudo_bytes($int));
    }

    /**
     * Returns the current executing script name without extenstion
     * @return String
     */
    public static function getCurrentFile($file = null)
    {
        $g = G::instance();
        if ($file == null) {
            $tokens = explode('/', $g->server['PHP_SELF']);
            // Console::log($g->server);
            $currentFile = array_pop($tokens);
            $currentFile = explode('.', $currentFile);
            array_pop($currentFile);
            $currentFile = implode('.', $currentFile);
            return $currentFile;
        } else {
            return basename($file, '.php');
        }
    }

    public static function getCurrentTab()
    {
        $g = G::instance();
        return $g->get['tab'];
    }

    public static function getCurrentPage()
    {
        $g = G::instance();
        return $g->get['page'];
    }

    public static function getCurrentLocation()
    {
        $g = G::instance();
        return basename($g->server['REDIRECT_URL']);
    }

    public function getEnvironment()
    {
        return $this->environment;
    }

    /**
     * Set generated at the WebAPI on the page load. It is one of the first things to get processed. If cookie information is present, it automatically generates all the session information about the current user(if logged in).
     *
     * @param  UserSession  $userSession  Generated at the WebAPI on the page load. It is one of the first things to get processed. If cookie information is present, it automatically generates all the session information about the current user(if logged in).
     *
     * @return  self
     */
    public static function setUserSession($userSession)
    {
        Session::$userSession = $userSession;
        return Session::$userSession;
    }

    public static function isAuthenticated()
    {
        return Session::$authStatus == 'success';
    }

}

class TemplateUnavailableException extends \Exception {

	protected $message = "The template you are trying to include does not seem to exist. Please check the file name.
	Invalid error message. ";
	protected $code = 1002;

	public function __construct($message) {
		$this->message = $message;
		parent::__construct($this->message, $this->code);
	}

	public function __toString() {
		return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
	}

}

class StringUtils
{
    public static function str_starts_with($haystack, $needle)
    {
        $length = strlen($needle);
        return (substr($haystack, 0, $length) === $needle);
    }

    public static function str_ends_with($haystack, $needle)
    {
        $length = strlen($needle);
        if ($length == 0) {
            return true;
        }
        return (substr($haystack, -$length) === $needle);
    }

    /**
    * A general method used to ger the string between two index locations.
    * @param  String $string
    * @param  Integer $start
    * @param  Integer $end
    * @return String         The sliced string.
    */
    public static function get_string_between($string, $start, $end)
    {
        $string = ' ' . $string;
        $ini = strpos($string, (int)$start);
        if ($ini == 0) {
            return '';
        }

        $ini += strlen((int)$start);
        $len = strpos($string, (int)$end, $ini) - $ini;
        return substr($string, $ini, $len);
    }


   public static function str_contains($haystack, $needle)
   {
       return strpos($haystack, $needle) !== false;
   }
}

