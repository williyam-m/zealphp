<?php
namespace ZealPHP;

require_once __DIR__ . '/SessionManager.class.php';
/**
 * Session class is a bunch of static methods helpful for easy handling of the session.
 */
$_SERVER['UNIQUE_ID'] = uniqidReal();
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
        Session::$documentRoot = $_SERVER['DOCUMENT_ROOT'];
        if (php_sapi_name() == "cli" and StringUtils::str_ends_with($_SERVER['PHP_SELF'], 'worker.php')) {
            parse_str(implode('&', array_slice($_SERVER['argv'], 1)), $_GET);
            if (isset($_GET['sessid'])) {
                // logit("Trying to reconstruct session ID from $_GET[sessid]", "fatal");
                session_id($_GET['sessid']);
                session_start();
                $session = array();
                foreach ($_SESSION as $k => $v) {
                    $session[$k] = $v;
                }
                session_commit();
                session_write_close();
                $newid = session_create_id('worker-');
                ini_set('session.use_strict_mode', 0);
                session_id($newid);
                session_start();
                foreach ($session as $k => $v) {
                    $_SESSION[$k] = $v;
                }
            } else {
                error_log("No session id", "fatal");
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
        $_SESSION[$key] = $value;
    }

    public static function unset($key)
    {
        unset($_SESSION[$key]);
    }

    /**
     * Checks if the given key is present in $_SESSION, only if session_start() is already called.
     *
     * @param String $key
     * @return Any
     */
    public static function isset($key)
    {
        return isset($_SESSION[$key]);
    }

    /**
     * Session wide property sharing method, which gets the value against a key. If default is supplied, when key is not found, default is returned.
     * @param  String $key
     * @param Bool $default
     * @return Any
     */
    public static function get($key, $default = false)
    {
        if (isset($_SESSION[$key])) {
            return $_SESSION[$key];
        } else {
            return $default;
        }
    }

    /**
     *
     * @param String $key   Key for the value
     * @param Any $value Value can be of any type.
     */
    public static function addMetaTag($tag)
    {
        if (!Session::get('meta-processed')) {
            if (is_array($tag)) {
                array_push(Session::$meta, $tag);
            }
        } else {
            trigger_error("Unable to add meta tags after Session::loadMaster() has been called", E_USER_WARNING);
        }
    }

    public static function bcPush($name, $link = null)
    {
        if (empty($link)) {
            $link = $_SERVER['REQUEST_URI'];
        }
        $bc = Session::get('breadcrumbs', array());
        $breadcrumb = [
            "name" => $name,
            "uri" => $link
        ];
        if (empty($bc)) {
            $bc = [
                $breadcrumb
            ];
        } else {
            if (is_array($bc)) {
                if (!in_array($breadcrumb, $bc)) {
                    array_push($bc, $breadcrumb);
                } else {
                    $key = array_search($breadcrumb, $bc);
                    //Console::log("Found and replaced: ".$key);
                    $bc = array_slice($bc, 0, $key + 1);
                }
            }
        }
        Session::set('breadcrumbs', $bc);
    }

    public static function bcClear()
    {
        Session::set('breadcrumbs', array());
    }

    public static function bcPop($length = 2)
    {
        $bc = Session::get('breadcrumbs', array());
        if (is_array($bc) and count($bc) > $length) {
            array_pop($bc);
        }
        Session::set('breadcrumbs', $bc);
    }

    /**
     * Used internally, this chooses what page to be generated depending on the $authStatus.
     *
     * For example, profile.php exists at htdocs.
     * While loading master from the profile.php, it looks for the template at /bin/templates/profile.php.
     * Here, the file can be named against it's status like profile[default].php or profile[loggedin].php,
     * and this method will automatically generates the appropriate page body.
     * @return [type] [description]
     */
    public static function generatePageBody()
    {
        $self = explode('.', $_SERVER['PHP_SELF']);
        array_pop($self);
        $self = implode('.', $self);
        $self = explode('/', $self);
        $self = array_pop($self);
        if (Session::getAuthStatus() == true) {
            if (file_exists(__DIR__ . '/../template/' . $self . ".php")) {
                include __DIR__ . '/../template/' . $self . ".php";
            } else {
                include __DIR__ . '/../template/' . "_error.php"; //TODO: Enhance error handling
            }
        } else {
            logw($self, "session");
            
        }
    }

    /**
     * This method has to be called from the caller file from the htdocs root.
     * It is essencial for the page to get loaded.
     * @return null
     */
    public static function loadMaster($_data = array())
    {
        extract($_data, EXTR_SKIP);
        include __DIR__ . '/../template/_master.php';
    }

    /**
     * This method has to be called from the caller file from the htdocs root.
     * It is essencial for the page to get loaded.
     * @return null
     */
    public static function loadPortfolioMaster($_data = array())
    {
        extract($_data, EXTR_SKIP);
        include __DIR__ . '/../template/_portfolio_master.php';
    }


    /**
     * Used internally from _master.php, this method is responsible for generating the page footer.
     * @return null
     */
    public static function generateFooter()
    {
        include __DIR__ . '/../template/_footer.php';
    }

    /**
     * Used internally from _master.php, this method is responsible for generating the page footer.
     * @return null
     */
    public static function generateMetaTags()
    {
        include __DIR__ . '/../template/_meta.php';
    }

    /**
     * This template is different from the /bin/template folder, but used to load additional templaces accorss the current file. For example, domain/interets will load /src/template/<currentfile>.php. If you want to load different templates, you can program the /bin/template/<currentfile>.php to load the templaces from <currentfile> folder under the same directory, it will load /src/template/<currentfile>/index.php or any other which you specify.
     *
     * In some cases, we need to have more than one template loaded in same page for different URL parameters. You can add multiple php files in the /src/template/<currentfile> folder, and you can use this method to load them in place. Best example, please check /src/template/interests.php and all the files under /bin/template/interests (folder), and how they are loaded with respect th the parameter using this loadTemplate() method from within the template itself.
     *
     * When $general is set to true, the lookup happen fromn the root of the template, that is the template folder itself.
     * @param  String $_template
     * @return null
     */
    public static function loadTemplate($_template = 'index', $_data = [])
    {
        $_source = Session::getCurrentFile(null);
        extract($_data, EXTR_SKIP);
        //This function returns the current script to build the template path.
        $_general = strpos($_template, '/') === 0;
        if ($_template == '_error') {
            include __DIR__ . '/../template/' . $_template . '.php';
        } elseif ($_general) {
            if (!file_exists(__DIR__ . '/../template/' . $_template . '.php')) {
                $bt = debug_backtrace();
                $caller = array_shift($bt);
                throw new TemplateUnavailableException("The template $_template does not exist on line " . $caller['line'] . " in file " . $caller['file'] . ".");
            }
            include __DIR__ . '/../template/' . $_template . '.php';
        } else {
            if (!file_exists(__DIR__ . '/../template/' . $_source . '/' . $_template . '.php')) {
                $bt = debug_backtrace();
                $caller = array_shift($bt);
                throw new TemplateUnavailableException("The template $_template does not exist on line " . $caller['line'] . " in file " . $caller['file'] . ".");
            }
            include __DIR__ . '/../template/' . $_source . '/' . $_template . '.php';
        }
    }

    public static function templateExists($_template, $general = false, $_source = null)
    {
        $_source = Session::getCurrentFile($_source);
        if ($_template == "_error") {
            return true;
        } elseif ($general) {
            if (!file_exists(__DIR__ . '/../template/' . $_template . '.php')) {
                return false;
            }
            return true;
        } else {
            if (!file_exists(__DIR__ . '/../template/' . $_source . '/' . $_template . '.php')) {
                return false;
            }
            return true;
        }
    }

    public static function isDevUser()
    {
        return in_array(Session::getUser()->getEmail(), get_config('in_development_users'));
    }

    public static function loadErrorPage()
    {
        Session::set('brokenPage', true);
        Session::set('footer', false);
        Session::loadMaster();
    }

    /**
     * To display the errors in the webpages
     */
    public static function devEnv()
    {
        ini_set('display_errors', 1);
        ini_set('display_startup_errors', 1);
        error_reporting(E_ALL);
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
        if ($file == null) {
            $tokens = explode('/', $_SERVER['PHP_SELF']);
            // Console::log($_SERVER);
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
        return $_GET['tab'];
    }

    public static function getCurrentPage()
    {
        return $_GET['page'];
    }

    public static function getCurrentLocation()
    {
        return basename($_SERVER['REDIRECT_URL']);
    }

    public static function getProcessorCount()
    {
        $ncpu = Cache::get('processor_count');
        if ($ncpu) {
            return $ncpu;
        } else {
            $cpus = file_get_contents('/sys/devices/system/cpu/online');
            $ncpu = (int)explode('-', $cpus)[1] + 1;
            Cache::set('processor_count', $ncpu);
            return $ncpu;
        }
    }

    public function getEnvironment()
    {
        return $this->environment;
    }

    public function isBeta()
    {
    }

    public function isProd()
    {
    }

    public function isAlpha()
    {
    }

    /**
     * Get generated at the WebAPI on the page load. It is one of the first things to get processed. If cookie information is present, it automatically generates all the session information about the current user(if logged in).
     *
     * @return  UserSession
     */
    public static function getUserSession()
    {
        return Session::$userSession;
    }

    public static function isAdmin()
    {
        if (isset($_SESSION['profile']) and Session::get('has_lab_access') and in_array(get_config('admin_group'), $_SESSION['profile']->groups)) {
            return true;
        } else {
            return false;
        }
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

    public static function parseCss($file)
    {
        $css = file_get_contents($file);
        preg_match_all('/(?ims)([a-z0-9\s\.\:#_\-@,]+)\{([^\}]*)\}/', $css, $arr);
        $result = array();
        foreach ($arr[0] as $i => $x) {
            $selector = trim($arr[1][$i]);
            $rules = explode(';', trim($arr[2][$i]));
            $rules_arr = array();
            foreach ($rules as $strRule) {
                if (!empty($strRule)) {
                    $rule = explode(":", $strRule);
                    $rules_arr[trim($rule[0])] = trim($rule[1]);
                }
            }

            $selectors = explode(',', trim($selector));
            foreach ($selectors as $strSel) {
                $result[$strSel] = $rules_arr;
            }
        }
        return $result;
    }
}

function uniqidReal($length = 13)
{
    // uniqid gives 13 chars, but you could adjust it to your needs.
    if (function_exists("random_bytes")) {
        $bytes = random_bytes(ceil($length / 2));
    } elseif (function_exists("openssl_random_pseudo_bytes")) {
        $bytes = openssl_random_pseudo_bytes(ceil($length / 2));
    } else {
        throw new \Exception("no cryptographically secure random function available");
    }
    return substr(bin2hex($bytes), 0, $length);
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


function get_config($key)
{
    global $__site_config;
    $array = json_decode($__site_config, true);
    if (isset($array[$key])) {
        return $array[$key];
    } else {
        return null;
    }
}

function logw($log, $tag = "system", $filter = null, $invert_filter = false)
{
    if ($filter != null and !StringUtils::str_contains($_SERVER['REQUEST_URI'], $filter)) {
        return;
    }
    if ($filter != null and $invert_filter) {
        return;
    }

    if(get_class(Session::getUser()) == "User") {
        $user = Session::getUser()->getUsername();
    } else {
        $user = 'worker';
    }

    if (!isset($_SERVER['REQUEST_URI'])) {
        $_SERVER['REQUEST_URI'] = 'cli';
    }

    $bt = debug_backtrace();
    $caller = array_shift($bt);

    $haystack = $_SERVER['PHP_SELF'];
    $needle = 'worker.php';
    $length = strlen($needle);
    if (substr($haystack, -$length) === $needle) {
        $_SERVER['SCRIPT_NAME'] = 'worker:' . basename($_SERVER['PHP_SELF']);
        $_SERVER['REQUEST_URI'] = "";
        $_SERVER['UNIQUE_ID'] = uniqidReal();
    }
    if (class_exists('Session') and (in_array($tag, ["cust_lnch", "dev"]))) {
        $date = date('l jS F Y h:i:s A');
        //$date = date('h:i:s A');
        if (is_object($log)) {
            $log = purify_array($log);
        }
        if (is_array($log)) {
            $log = json_encode($log, JSON_PRETTY_PRINT);
        }
        if (error_log(
            '[*] #' . $tag . ' [' . $date . '] ' . " Request ID: $_SERVER[UNIQUE_ID]\n" .
                '    URL: ' . $_SERVER['SCRIPT_NAME'] . $_SERVER['REQUEST_URI'] . " \n" .
                '    Caller: ' . $caller['file'] . ':' . $caller['line'] . "\n" .
                '    Render time: ' . get_current_render_time() . ' sec' . " \n" .
                "    Message: \n" . indent($log) . "\n\n",
            3,
            get_config('app_log')
        )) {
        }
    }
}

function get_current_render_time(){
    return "NOT IMPLEMENTED";
}


/**
 * Indend the given text with the given number of spaces
 *
 * @param String $string
 * @param Integer $indend	Number of lines to indent
 * @return String
 */
function indent($string, $indend = 4)
{
    $lines = explode(PHP_EOL, $string);
    $newlines = array();
    $s = "";
    $i = 0;
    while ($i < $indend) {
        $s = $s . " ";
        $i++;
    }
    foreach ($lines as $line) {
        array_push($newlines, $s . $line);
    }
    return implode(PHP_EOL, $newlines);
}

/**
 * Takes an iterator or object, and converts it into an Array.
 * @param  Any $obj
 * @return Array
 */
function purify_array($obj)
{
    $h = json_decode(json_encode($obj), true);
    //print_r($h);
    return empty($h) ? [] : $h;
}

