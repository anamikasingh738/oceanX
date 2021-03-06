<?php
/**
 * UAEL VideoGallery Module.
 *
 * @package UAEL
 */

namespace UltimateElementor\Modules\VideoGallery;

use UltimateElementor\Base\Module_Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Module.
 */
class Module extends Module_Base {

	/**
	 * Module should load or not.
	 *
	 * @since 1.6.0
	 * @access public
	 *
	 * @return bool true|false.
	 */
	public static function is_enable() {
		return true;
	}

	/**
	 * Get Module Name.
	 *
	 * @since 1.6.0
	 * @access public
	 *
	 * @return string Module name.
	 */
	public function get_name() {
		return 'uael-video-gallery';
	}

	/**
	 * Get Widgets.
	 *
	 * @since 1.6.0
	 * @access public
	 *
	 * @return array Widgets.
	 */
	public function get_widgets() {
		return array(
			'Video_Gallery',
		);
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();
	}
}

