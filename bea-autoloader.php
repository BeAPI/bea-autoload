<?php
/*
 Plugin Name: Autoloader
 Version: 1.0.3
 Plugin URI: http://www.beapi.fr
 Description: The autoloader class in order to load all plugins files
 Author: BE API Technical team
 Author URI: http://www.beapi.fr
 ----

 Copyright 2016 BE API Technical team (human@beapi.fr)

 This program is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with this program; if not, write to the Free Software
 Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */
namespace BEA;

// don't load directly
if ( ! defined( 'ABSPATH' ) ) {
	die( '-1' );
}

/**
 * An example of a general-purpose implementation that includes the optional
 * functionality of allowing multiple base directories for a single namespace
 * prefix.
 *
 * Given a foo-bar package of classes in the file system at the following
 * paths ...
 *
 *     /path/to/packages/foo-bar/
 *         src/
 *             baz.php             # Foo\Bar\Baz
 *             Qux/
 *                 quux.php        # Foo\Bar\Qux\Quux
 *         tests/
 *             baz-test.php         # Foo\Bar\Baz_test
 *             Qux/
 *                 quux-test.php    # Foo\Bar\Qux\Quux_test
 *
 * ... add the path to the class files for the \Foo\Bar\ namespace prefix
 * as follows:
 *
 *      <?php
 *      // instantiate the loader
 *      $loader = \BEA\Autoloader::get_instance();
 *
 *      // register the base directories for the namespace prefix
 *      $loader->addNamespace('Foo\Bar', '/path/to/packages/foo-bar/src');
 *      $loader->addNamespace('Foo\Bar', '/path/to/packages/foo-bar/tests');
 *
 * The following line would cause the autoloader to attempt to load the
 * \Foo\Bar\Qux\Quux class from /path/to/packages/foo-bar/src/Qux/Quux.php:
 *
 *      <?php
 *      new \Foo\Bar\Qux\Quux;
 *
 * The following line would cause the autoloader to attempt to load the
 * \Foo\Bar\Qux\QuuxTest class from /path/to/packages/foo-bar/tests/Qux/QuuxTest.php:
 *
 *      <?php
 *      new \Foo\Bar\Qux\QuuxTest;
 */
class Autoloader {
	/**
	 * An associative array where the key is a namespace prefix and the value
	 * is an array of base directories for classes in that namespace.
	 *
	 * @var array
	 */
	protected $prefixes = array();

	/**
	 * @var self
	 */
	protected static $instance;

	/**
	 * @return self
	 */
	final public static function get_instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new static;
		}

		return self::$instance;
	}


	/**
	 * Constructor protected from the outside
	 */
	private function __construct() {

	}

	/**
	 * prevent the instance from being cloned
	 *
	 * @throws Exception
	 */
	private function __clone() {
		throw new \Exception( 'Cannot clone singleton' );
	}

	/**
	 * prevent from being unserialized
	 *
	 * @throws Exception
	 */
	final public function __wakeup() {
		throw new \Exception( 'Cannot serialize singleton' );
	}

	/**
	 * Register loader with SPL autoloader stack.
	 *
	 * @return void
	 */
	public function register() {
		spl_autoload_register( array( $this, 'loadClass' ) );
	}

	/**
	 * Adds a base directory for a namespace prefix.
	 *
	 * @param string $prefix The namespace prefix.
	 * @param string $base_dir A base directory for class files in the
	 * namespace.
	 * @param bool $prepend If true, prepend the base directory to the stack
	 * instead of appending it; this causes it to be searched first rather
	 * than last.
	 *
	 * @return void
	 */
	public function addNamespace( $prefix, $base_dir, $prepend = false ) {
		// normalize namespace prefix
		$prefix = trim( $prefix, '\\' ) . '\\';

		// normalize the base directory with a trailing separator
		$base_dir = rtrim( $base_dir, DIRECTORY_SEPARATOR ) . '/';

		// initialize the namespace prefix array
		if ( isset( $this->prefixes[ $prefix ] ) === false ) {
			$this->prefixes[ $prefix ] = array();
		}

		// retain the base directory for the namespace prefix
		if ( $prepend ) {
			array_unshift( $this->prefixes[ $prefix ], $base_dir );
		} else {
			array_push( $this->prefixes[ $prefix ], $base_dir );
		}
	}

	/**
	 * Loads the class file for a given class name.
	 *
	 * @param string $class The fully-qualified class name.
	 *
	 * @return mixed The mapped file name on success, or boolean false on
	 * failure.
	 */
	public function loadClass( $class ) {
		// the current namespace prefix
		$prefix = $class;

		// work backwards through the namespace names of the fully-qualified
		// class name to find a mapped file name
		while ( false !== $pos = strrpos( $prefix, '\\' ) ) {

			// retain the trailing namespace separator in the prefix
			$prefix = substr( $class, 0, $pos + 1 );

			// the rest is the relative class name
			$relative_class = substr( $class, $pos + 1 );

			// try to load a mapped file for the prefix and relative class
			$mapped_file = $this->loadMappedFile( $prefix, $relative_class );
			if ( $mapped_file ) {
				return $mapped_file;
			}

			// remove the trailing namespace separator for the next iteration
			// of strrpos()
			$prefix = rtrim( $prefix, '\\' );
		}

		// never found a mapped file
		return false;
	}

	/**
	 * Load the mapped file for a namespace prefix and relative class.
	 *
	 * @param string $prefix The namespace prefix.
	 * @param string $relative_class The relative class name.
	 *
	 * @return mixed Boolean false if no mapped file can be loaded, or the
	 * name of the mapped file that was loaded.
	 */
	protected function loadMappedFile( $prefix, $relative_class ) {
		// are there any base directories for this namespace prefix?
		if ( isset( $this->prefixes[ $prefix ] ) === false ) {
			return false;
		}

		// look through base directories for this namespace prefix
		foreach ( $this->prefixes[ $prefix ] as $base_dir ) {

			// replace the namespace prefix with the base directory,
			// replace namespace separators with directory separators
			// in the relative class name, append with .php
			$file = $base_dir
				. strtolower( str_replace( array( '\\', '_' ), array( '/', '-' ), $relative_class ) )
				. '.php';

			// if the mapped file exists, require it
			if ( $this->requireFile( $file ) ) {
				// yes, we're done
				return $file;
			}
		}

		// never found it
		return false;
	}

	/**
	 * If a file exists, require it from the file system.
	 *
	 * @param string $file The file to require.
	 *
	 * @return bool True if the file exists, false if not.
	 */
	protected function requireFile( $file ) {
		if ( file_exists( $file ) ) {
			require $file;

			return true;
		}

		return false;
	}
}

// instantiate the loader
$loader = Autoloader::get_instance();
// register the autoloader
$loader->register();
