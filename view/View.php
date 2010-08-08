<?php
/*
 * Copyright (c) 2009 - 2010, Frosted Design
 * All rights reserved.
 */

namespace hydrogen\view;

use hydrogen\config\Config;
use hydrogen\view\ContextStack;
use hydrogen\view\exceptions\NoSuchVariableException;
use hydrogen\view\exceptions\NoSuchViewException;

/**
 * The View class is a basic view implementation, allowing a separate view
 * layer with its own request-level variable scope and page-level variable scope.
 *
 * At any point during the execution of the web application (but preferably in the
 * Controller layer), variables can be injected into the View layer -- either before
 * the view is loaded using {@link #setVar}, or at the same time the view is loaded
 * in {@link #load}.  Inside the view, these request-scope variables can be accessed
 * with $this->variableName.  Page-scope variables are local PHP variables such as
 * $varName.
 *
 * Views are stored in a directory defined by the config value "folder" in group
 * "view".  View names are the names of the PHP files in that folder (without
 * the trailing ".php").  So for example, if the view folder were "views" and inside
 * of that folder was the file main.php, that view could be loaded by executing:
 *
 * <pre>
 * use hydrogen\view\View;
 * View::load("main");
 * </pre>
 *
 * Similarly, if the php file were in a subfolder called "common", it could be loaded
 * with:
 *
 * <pre>
 * use hydrogen\view\View;
 * View::load("common/main");
 * </pre>
 *
 * Or, if you wish to load common/header from inside a view php file:
 *
 * <pre>
 * $this->loadView("common/header");
 * </pre>
 *
 * View can be easily extended to include more functions or completely change the
 * loading format for the library.
 */
class View {
	
	protected static $view = false;
	protected $appURL;
	protected $viewURL;
	protected $requestContext;
	
	protected static function getView() {
		if (static::$view === false)
			static::$view = new View();
		return static::$view;
	}
	
	/**
	 * Translates a view name into an absolute path at which the view can
	 * be found.
	 *
	 * @param string viewName The name of the view to be found.
	 * @return string The absolute path to the requested view.
	 */
	public static function getViewPath($viewName) {
		$path = Config::getRequiredVal("view", "folder") .
			DIRECTORY_SEPARATOR . $viewName .
			Config::getRequiredVal("view", "file_extension");
		return Config::getAbsolutePath($path);
	}
	
	/**
	 * Sets a variable or array of variables to the specified value(s)
	 * in the View layer.  Any variables declared with this function
	 * will be available to any view loaded using the {@link #load}
	 * function.
	 *
	 * @param keyOrArray string|array A variable name to set, or an
	 * 		associative array of varName => value pairs.
	 * @param value mixed The value for the specified key.  If keyOrArray
	 * 		is an associative array, this value is not used.
	 */
	public static function setVar($keyOrArray, $value=false) {
		$view = static::getView();
		if (is_array($keyOrArray))
			$view->requestContext->setArray($keyOrArray);
		else
			$view->requestContext->set($keyOrArray, $value);
	}
	
	/**
	 * Loads and displays the specified view, making available all of the
	 * variables set with {@link #setVar} as well as any passed in the
	 * $varArray argument.
	 *
	 * The view name to be loaded is the name of the .php file in the
	 * views folder, without the .php on the end.  So if your views folder contains
	 * a file named "homepage.php", it can be loaded by submitting "homepage" as
	 * the $viewName argument.  If the views folder contains a folder named blog, and
	 * inside of that a file named "summary.php", that view can be loaded using the
	 * name "blog/summary".
	 *
	 * The views folder is specified in the configuration file like this:
	 *
	 * <pre>
	 * [view]
	 * folder = /path/to/views/folder
	 * </pre>
	 *
	 * Although, this setting and the other view settings may be better placed in
	 * Hydrogen's autoconfig file as direct calls to the Config library, since these
	 * are not typically things that end users should set themselves.
	 *
	 * @param viewName string The name of the view to load, as described above.
	 * @param varArray array|boolean An optional array of variables to pass into
	 * 		the loaded view.
	 */
	public static function load($viewName, $varArray=false) {
		$view = static::getView();
		if (is_array($varArray))
			static::setVar($varArray);
		$view->loadView($viewName);
	}
	
	/**
	 * Displays the specified view folder by passing it through the Hydrogen
	 * template engine, either reading a pre-compiled PHP file directly or
	 * parsing the template file into natively runnable PHP code and then
	 * reading that.
	 *
	 * @param path string The full, absolute path to the template file to
	 * 		include.
	 */
	protected function displayTemplate($viewName) {
		$template = new Template($viewName);
		$template->render($this->requestContext);
	}
	
	/**
	 * Includes the specified php file in the page, making available to it each
	 * variable that has been set in the static View.
	 *
	 * @param path string The full, absolute path to the PHP file to include.
	 */
	protected function displayPlain($viewName) {
		$path = static::getViewPath($viewName);
		$success = include(Config::getAbsolutePath($path));
		if (!$success)
			throw new NoSuchViewException("File $path could not be loaded.");
	}
	
	/**
	 * Loads the specified view within the currently executing view.  For
	 * example, in any given view, you could execute:
	 *
	 * <pre>
	 * <?php $this->loadView("pagecomponents/header"); ?>
	 * </pre>
	 *
	 * That line would inject a common header (found
	 * at VIEW_FOLDER/pagecomponents/header.php) into the view wherever that
	 * line is located.  It would have access to all request-scope variables,
	 * but none of the page-scope variables.  This is an advantage to using a
	 * built-in PHP include function like this:
	 *
	 * <pre>
	 * <?php include(__DIR__ . "/pagecomponents/header.php"); ?>
	 * </pre>
	 *
	 * because then the pages would share page-scope variables, possibly
	 * overwriting important data.
	 *
	 * @param viewName string The name of the view to display.
	 */
	public function loadView($viewName) {
		if (Config::getVal("view", "use_templates") === "1")
			$this->displayTemplate($viewName);
		else
			$this->displayPlain($viewName);
	}
	
	/**
	 * Generates a URL relative to the base URL of this web application.
	 * Calling this function with no arguments returns the base URL set in
	 * the config file, with the trailing slash (if there is one) removed.
	 *
	 * Optionally, this function may be called with a path, which will be
	 * appended to the base URL before it is returned.
	 *
	 * @param path string|boolean The path to append to the base app URL, or
	 * 		false to return the base URL with no additional path.
	 * @return string The base URL for this web app with the given path appended,
	 * 		if provided.
	 */
	public function appURL($path=false) {
		if ($path !== false) {
			if ($path[0] == '/')
				return $this->appURL . $path;
			return $this->appURL . '/' . $path;
		}
		return $this->appURL;
	}
	
	/**
	 * Generates a URL relative to the root view URL of this web application.
	 * Calling this function with no arguments returns the root view URL for
	 * the currently used view, with no trailing slash.  Calling this function
	 * with a path returns the root view URL with the given path appended to it.
	 *
	 * If the Config value [view]->root_url is set, this will be used as the root
	 * view URL.  Otherwise, the Config value [view]->url_path will be appended to
	 * the URL stored in [general]->app_url.
	 *
	 * @param path string|boolean The path to append to the root view URL, or
	 * 		false to return the view URL with no additional path.
	 * @return string The root URL for this view with the given path appended,
	 * 		if provided.
	 */
	public function viewURL($path=false) {
		if ($path !== false) {
			if ($path[0] == '/')
				return $this->viewURL . $path;
			return $this->viewURL . '/' . $path;
		}
		return $this->viewURL;
	}
	
	/**
	 * Returns request-scope variables as they are called upon.
	 *
	 * This is a PHP Magic Method and should not be called directly.
	 *
	 * @param varName string The name of the variable being asked for.
	 * @return mixed The value of the variable if it exists, or false if
	 * 		does not.
	 */
	public function __get($varName) {
		try {
			$val = $this->requestContext->get($varName);
		}
		catch (NoSuchVariableException $e) {
			return false;
		}
		return $val;
	}
	
	/**
	 * Sets or creates a new request-scope variable.
	 *
	 * This is a PHP Magic Method and should not be called directly.
	 *
	 * @param varName string The name of the variable to set or create
	 * 		with the specified value.
	 * @param value mixed The value to which the variable should be set.
	 */
	public function __set($varName, $value) {
		$this->requestContext->set($varName, $value);
	}
	
	/**
	 * Checks to see whether a specified request-scope variable exists.
	 *
	 * This is a PHP Magic Method and should not be called directly.
	 *
	 * @param varName string The name of the variable to check.
	 * @return boolean true if set, false if not set.
	 */
	public function __isset($varName) {
		return $this->requestContext->keyExists($varName);
	}
	
	/**
	 * Unsets (deletes) the specified request-scope variable.
	 *
	 * This is a PHP Magic Method and should not be called directly.
	 *
	 * @param varName string The name of the variable to unset.
	 */
	public function __unset($varName) {
		$this->requestContext->delete($varName);
	}
	
	/**
	 * This class should not be instantiated outside of View.
	 */
	protected function __construct() {
		$this->requestContext = new ContextStack();
		$this->appURL = Config::getRequiredVal("general", "app_url");
		if ($this->appURL[strlen($this->appURL) - 1] == '/')
			$this->appURL = substr($this->appURL, 0, -1);
		$this->viewURL = Config::getVal("view", "root_url");
		if ($this->viewURL === false) {
			$this->viewURL = $this->appURL(Config::getRequiredVal("view", 
				"url_path"));
		}
		if ($this->viewURL[strlen($this->viewURL) - 1] == '/')
			$this->viewURL = substr($this->viewURL, 0, -1);
	}
	
}

?>