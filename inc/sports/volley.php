<?php

/*
 * This file is part of the PHPLeague package.
 *
 * (c) Maxime Dizerens <mdizerens@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

if ( ! class_exists('PHPLeague_Sports_Volley')) {
    PHPLeague_Sports::$sports["volley"] = "PHPLeague_Sports_Volley";
    /**
     * PHPLeague Sports (Volley) library.
     */
    class PHPLeague_Sports_Volley extends PHPLeague_Sports {

        // Player positions
        public static $positions = array();
  
        /**
         * Constructor
         *
         * @param  none
         * @return void
         */
        public function __construct()
        {
            parent::__construct();
        }

		public function get_points($goals, $goals_taken) {
			if ($goals_taken == $this->get_forfeit_code()) {
				// Won by forfeat
				return 3;
			} else if ($goals == $this->get_forfeit_code()) {
                // Declared forfeit
			    return -1;
			}
			switch ($goals) {
			case 3:
				if ($goals_taken == 0 || $goals_taken == 1) {
					return 3;
				} else if ($goals_taken == 2) {
					return 2;
				}
				break;
			case 2:
				return 1;
			default:
				return 0;
			}
		}

        private function add_result($result, $row, $home) {
            if ($row->goal_home == null) {
                return;
            }
            if ($home) {
                $goals = (int) $row->goal_home;
                $goals_taken = (int) $row->goal_away;
            } else {
                $goals = (int) $row->goal_away;
                $goals_taken = (int) $row->goal_home;
            }
            if ($goals == $this->get_forfeit_code()) {
                // Declared forfeit
                $result->forfeits++;
            } else if ($goals_taken == $this->get_forfeit_code()) {
                // Won by forfeit
                $result->won3_0++;
            } else {
                // Normal cases
                switch ($goals) {
                case 3:
                    switch ($goals_taken) {
                    case 0: $result->won3_0++; break;
                    case 1: $result->won3_1++; break;
                    case 2: $result->won3_2++; break;
                    }
                    break;
                case 2: $result->lost3_2++; break;
                case 1: $result->lost3_1++; break;
                case 0: $result->lost3_0++; break;
                }
            }
            $result->points += $this->get_points($goals, $goals_taken);
            $result->played++;
        }
        private function sort_results($results) {
            $ordering = array();
            foreach ($results as $result) {
                $ordering[] = $result;
            }
            usort($ordering, 'PHPLeague_Volley_Result::compare');
            return $ordering;
        }

		public function results_table($id_league) {
			global $wpdb;
			$db = new PHPLeague_Database;
            // Prepare results
            $results = array();
			foreach ($db->get_fixtures_by_league($id_league) as $row)
            {
                if ( ! isset($results[$row->id_team_home]) ) {
                    $results[$row->id_team_home] = new PHPLeague_Volley_Result($row->id_team_home, $row->home_name);
                }
                if ( ! isset($results[$row->id_team_away]) ) {
                    $results[$row->id_team_away] = new PHPLeague_Volley_Result($row->id_team_away, $row->away_name);
                }
                $home = $results[$row->id_team_home];
                $this->add_result($home, $row, true);
                $away = $results[$row->id_team_away];
                $this->add_result($away, $row, false);
            }
            $ranking = $this->sort_results($results);
            // Display
			$output = '<table id="phpleague" class="volley-results"><thead><tr>'
					.'<th class="centered" rowspan="2">'.__('Pos', 'phpleague').'</th>'
					.'<th rowspan="2">'.__('Team', 'phpleague').'</th>'
					.'<th class="centered" rowspan="2">'.__('Pts', 'phpleague').'</th>'
					.'<th class="centered" rowspan="2">'.__('P', 'phpleague').'</th>'
					.'<th class="centered" colspan="3">'.__('W', 'phpleague').'</th>'
					.'<th class="centered" colspan="3">'.__('L', 'phpleague').'</th>'
					.'<th class="centered" rowspan="2">'.__('Forfeits', 'phpleague').'</th>'
					.'<th class="centered" rowspan="2">'.__('Won sets', 'phpleague').'</th>'
					.'<th class="centered" rowspan="2">'.__('Lost sets', 'phpleague').'</th>'
					.'<th class="centered" rowspan="2">'.__('+/-', 'phpleague').'</th>'
					.'</tr><tr>'
					.'<th>3-0</th><th>3-1</th><th>3-2</th><th>3-0</th><th>3-1</th><th>3-2</th>'
					.'</tr></thead><tbody>';
			for ($i = 0; $i < count($ranking); $i++) {
			    $result = $ranking[$i];
			    $output .= '<tr>';
			    $output .= '<td class="header">' . ($i + 1) . '</td>';
			    $output .= '<td class="header">' . $result->team_name . '</td>';
			    $output .= '<td class="strong">' . $result->points . '</td>';
			    $output .= '<td>' . $result->played . '</td>';
			    $output .= '<td>' . $result->won3_0 . '</td>';
			    $output .= '<td>' . $result->won3_1 . '</td>';
			    $output .= '<td>' . $result->won3_2 . '</td>';
			    $output .= '<td>' . $result->lost3_0 . '</td>';
			    $output .= '<td>' . $result->lost3_1 . '</td>';
			    $output .= '<td>' . $result->lost3_2 . '</td>';
			    $output .= '<td>' . $result->forfeits . '</td>';
			    $output .= '<td>' . $result->get_won_sets() . '</td>';
			    $output .= '<td>' . $result->get_lost_sets() . '</td>';
			    $output .= '<td class="strong">' . $result->get_sets_advantage() . '</td>';
			    $output .= '</tr>';
			}
            $output .= '</tbody></table>';
            return $output;
		}
    }
    class PHPLeague_Volley_Result {
    	public $team_id;
    	public $team_name;
    	public $played;
    	public $points;
    	public $won3_0;
    	public $won3_1;
    	public $won3_2;
    	public $lost3_0;
    	public $lost3_1;
    	public $lost3_2;
    	public $forfeits;
        public function __construct($team_id, $team_name) {
            $this->team_id = $team_id;
            $this->team_name = $team_name;
            $this->played = 0;
            $this->points = 0;
            $this->won3_0 = 0;
            $this->won3_1 = 0;
            $this->won3_2 = 0;
            $this->lost3_0 = 0;
            $this->lost3_1 = 0;
            $this->lost3_2 = 0;
            $this->forfeits = 0;
        }
        public function get_victories() {
            return $this->won3_0 + $this->won3_1 + $this->won3_2;
        }
        public function get_won_sets() {
            return ($this->won3_0 + $this->won3_1 + $this->won3_2)* 3
                    + $this->lost3_1 + $this->lost3_2 * 2;
        }
        public function get_lost_sets() {
            return ($this->lost3_0 + $this->lost3_1 + $this->lost3_2 + $this->forfeits)* 3
                    + $this->won3_1 + $this->won3_2 * 2;
        }
        public function get_sets_advantage() {
            return $this->get_won_sets() - $this->get_lost_sets();
        }
        public static function compare($result, $result2) {
            // Check points
            if ($result->points > $result2->points) {
                return -1;
            } else if ($result->points < $result2->points) {
                return 1;
            } else {
                // Equality, check victories
                if ($result->get_victories() > $result2->get_victories()) {
                    return -1;
                } else if ($result->get_victories() < $result2->get_victories()) {
                    return 1;
                } else {
                    // Equality, check sets advantage
                    if ($result->get_sets_advantage() > $result2->get_sets_advantage()) {
                        return -1;
                    } else if ($result->get_sets_advantage() < $result2->get_sets_advantage()) {
                        return 1;
                    } else {
                        // Equality, check won sets
                        if ($result->get_won_sets() > $result2->get_won_sets()) {
                            return -1;
                        } else if ($result->get_won_sets() < $result2->get_won_sets()) {
                            return 1;
                        } else {
                            return 0;
                        }
                    }
                }
            }
        }
    }
}
