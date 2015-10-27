<?php

/*
 * This file is part of the PHPLeague package.
 *
 * (c) Maxime Dizerens <mdizerens@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

if ( ! class_exists('PHPLeague_Sports')) {
    
    /**
     * PHPLeague Sports abstraction library.
     *
     * @category   Sports
     * @package    PHPLeague
     * @author     Maxime Dizerens
     * @copyright  (c) 2011 Mikaweb Design, Ltd
     */
  abstract class PHPLeague_Sports {
        // Available sports, registered in subclasses with key as sport code
        // and value as class name
        public static $sports = array();
        // Player positions
        public static $positions = array();

        /**
         * Constructor
         *
         * @param  none
         * @return void
         */
        public function __construct() {}

		public abstract function get_points($goals, $goals_taken);
		public abstract function results_table($league_id);
		/** Get goals number representing a forfeit */
		public function get_forfeit_code() {
		    return -1;
		}
    }
}
