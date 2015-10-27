<?php

/*
 * This file is part of the PHPLeague package.
 *
 * (c) Maxime Dizerens <mdizerens@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

// Get ID fixture
$fixture_number = ( ! empty($_GET['id_fixture']) && $db->is_fixture_exists($_GET['id_fixture'], $id_league) === TRUE)
    ? (int) $_GET['id_fixture'] : 1;

// Security
if ($db->is_league_exists($id_league) === FALSE)
    wp_die(__('We did not find the league in the database.', 'phpleague'));

// If fixture does not exist, warn user (perhaps database manually deleted)
if ($db->is_fixture_exists($fixture_number, $id_league) !== TRUE)

    $message[] = __('The fixture does not exist. Please resave fixtures.', 'phpleague');    

// Variables
$league_name = $db->return_league_name($id_league);
$setting     = $db->get_league_settings($id_league);
$nb_teams    = (int) $setting->nb_teams;
$nb_legs     = (int) $setting->nb_leg;
$page_url    = 'admin.php?page=phpleague_overview&option=match&id_league='.$id_league.'&id_fixture='.$fixture_number;
$output      = '';
$data        = array();
$menu        = array(
    __('Teams', 'phpleague')    => admin_url('admin.php?page=phpleague_overview&option=team&id_league='.$id_league),
    __('Fixtures', 'phpleague') => admin_url('admin.php?page=phpleague_overview&option=fixture&id_league='.$id_league),
    __('Matches', 'phpleague')  => '#',
    __('Results', 'phpleague')  => admin_url('admin.php?page=phpleague_overview&option=result&id_league='.$id_league),
    __('Settings', 'phpleague') => admin_url('admin.php?page=phpleague_overview&option=setting&id_league='.$id_league)
);

// Check what kind of fixtures we are dealing with (odd/even)
if ($nb_teams == 2) {
    $nb_fixtures = 2;
    $nb_matches = 1;
} else if ($nb_teams == 3) {
    $nb_fixtures = 6;
    $nb_matches = 1;
} else if (($nb_teams % 2) != 0)
{
    $nb_fixtures = ($nb_teams) * 2;
    $nb_matches  = ($nb_teams - 1) / 2;
}
else
{
    $nb_fixtures = ($nb_teams - 1) * 2;
    $nb_matches  = ($nb_teams / 2);
}
$fixture_number2 = $fixture_number + ($nb_fixtures / 2);

// Data processing...
if (isset($_POST['matches']) && check_admin_referer('phpleague'))
{
    // Secure data
    $id_fixture = ( ! empty($_POST['id_fixture'])) ? (int) $_POST['id_fixture'] : 0;
    $id_fixture2 = ( ! empty($_POST['id_fixture2'])) ? (int) $_POST['id_fixture2'] : 0;
    $id_home    = ( ! empty($_POST['id_home']) && is_array($_POST['id_home'])) ? $_POST['id_home'] : NULL;
    $id_away    = ( ! empty($_POST['id_away']) && is_array($_POST['id_away'])) ? $_POST['id_away'] : NULL;

    if ($id_fixture === 0)
    {
        $message[] = __('An error occurred with the fixture ID.', 'phpleague');
    }
    elseif ($id_home === NULL || $id_away === NULL)
    {
        $message[] = __('An error occurred because of the datatype given.', 'phpleague');
    }
    else
    {
        // Remove all previous data
        $db->remove_matches_from_fixture($id_fixture);
        $db->remove_matches_from_fixture($id_fixture2);

        // Array containing the teams to avoid duplicate
        $array = array();
        
        // Insert new data
        for ($counter = 0; $counter < $nb_matches; $counter++)
        {
            // We cannot have the same team twice
            if ($id_home[$counter] == $id_away[$counter])
            {
                $message[] = __('You cannot have the same team at home and away.', 'phpleague');
                break;
            }
            elseif (in_array($id_home[$counter], $array) || in_array($id_away[$counter], $array))
            {
                $message[] = __('You cannot have the same team twice in a fixture.', 'phpleague');
                break;
            }

            $db->add_matches_to_fixture($id_fixture, (int) $id_home[$counter], (int) $id_away[$counter]);
            $db->add_matches_to_fixture($id_fixture2, (int) $id_away[$counter], (int) $id_home[$counter]);

            // Add the teams into the array to check them later...
            $array[] = $id_home[$counter];
            $array[] = $id_away[$counter];
        }

        $message[] = __('Match(es) updated successfully.', 'phpleague');
    }
}

$pagination = $fct->pagination($nb_fixtures, 2, $fixture_number, 'id_fixture');
$output .= $fct->form_open(admin_url($page_url));
$output .= '<div class="tablenav"><div class="alignleft actions">'.$fct->input('matches', __('Save', 'phpleague'),
        array('type' => 'submit', 'class' => 'button')).'</div>';

if ($pagination)
    $output .= '<div class="tablenav-pages">'.$pagination.'</div>';

// Check if the fixture exists in matches table
$id_fixture = $db->get_fixture_id($fixture_number, $id_league, FALSE);
$id_fixture2 = $db->get_fixture_id($fixture_number2, $id_league, FALSE);
$i = $team_home = $team_away = 0;
$output .= '</div><table class="widefat"><thead><tr><th colspan="3" class="table-splitter">'.$league_name.
        __(' - Fixture: ', 'phpleague').$fixture_number.'</th><th colspan="3">'.$league_name.
        __(' - Fixture: ', 'phpleague').$fixture_number2.'</th></tr>'
        .'<tr><th class="text-centered">'.__('Home', 'phpleague').'</th><th class="text-centered">'
        .__('Away', 'phpleague').'</th><th class="table-splitter"><th></th><th class="text-centered">'.__('Home', 'phpleague').'</th><th class="text-centered">'
        .__('Away', 'phpleague').'</th></tr></thead>';

// Get teams
$team_ids = array();
foreach ($db->get_distinct_league_team($id_league) as $array)
{
    $clubs_list[$array->club_id] = esc_html($array->name);
    $team_ids[] = $array->club_id;
}
if ($nb_teams % 2 != 0)
{
    $old_team_ids[] = array_merge(array(), $team_ids);
    // Reorder team ids according to non playing teams for generator
    $fixtures_list = $db->get_fixtures_league($id_league);
    for ($i = 0; $i < $nb_teams; $i++) {
        $j = (2 * $i) % $nb_teams;
        if ($j == 0) {
            $j = $nb_teams;
        }
        $team_ids[$i] = $fixtures_list[$j - 1]->non_playing_team_id;
    }
}

$matches = array();
for ($i = 0; $i < $nb_matches; $i++) {
    // Get teams ID
    foreach ($db->get_matches_by_fixture($id_fixture, $i ) as $row)
    {
        $team_home = (int) $row->id_team_home;
        $team_away = (int) $row->id_team_away;
        $matches[] = array($team_home, $team_away);
    }
}
if (count($matches) < $nb_matches) {
    // Remove all previous data
    $fixtures = $db->get_fixtures_league($id_league);
    for ($i = 1; $i < count($fixtures); $i++) {
        $db->remove_matches_from_fixture($fixtures[$i]->id);
	}
    // Matches not assigned, set them automatically
    if ($nb_teams % 2 == 0) {
        $x_home = false;
    } else {
        $x_home = true;
    }
    $fix_nums_x = array();
    for ($x = 0; $x < $nb_teams - 1; $x++) {
        if ($nb_teams % 2 == 0) {
            // Need to assign 2 times because last y is skipped
            // Not necessary with unpar number of teams
            $x_home = !$x_home;
        }
        $team_id_X = $team_ids[$x];
        $fix_nums_x[$x] = array();
        for ($y = 0; $y < $nb_teams - 1; $y++) {
            if ($x == $y) {
   	            continue;
            }
            $team_id_Y = $team_ids[$y];
            $fixture_num = ($x + $y - 1) % ($nb_fixtures / 2) + 1;
            $fixture_back_num = $fixture_num + $nb_fixtures / 2;
            if ($x_home) {
                $fixture_id = $db->get_fixture_id($fixture_num, $id_league, false);
                $db->add_matches_to_fixture($fixture_id, $team_id_X, $team_id_Y);
            } else {
                $fixture_id = $db->get_fixture_id($fixture_back_num, $id_league, false);
                $db->add_matches_to_fixture($fixture_id, $team_id_Y, $team_id_X);
            }
            $fix_nums_x[$x][$y] = $fixture_num;
            $x_home = !$x_home;
        }
    }
    // Fill last line and column
    $home = ($nb_teams % 2) == 0;
    $last_team = $team_ids[count($team_ids) - 1];
    for ($i = 0; $i < $nb_teams - 1; $i++) {
        $fixture_num = 0;
        if ($nb_teams % 2 == 0) {
            // Par, get first available fixture for the team
            for ($fix_num = 1; $fix_num < $nb_teams + 1; $fix_num++) {
                $found = true;
                for ($j = 0; $j < $nb_teams; $j++) {
                    if ($fix_nums_x[$i][$j] == $fix_num) {
                        $found = false;
                        break;
                    }
                }
                if ($found) {
                    $fixture_num = $fix_num;
                    break;
                }
            }
            if ($fixture_num == 0) {
                echo("Error");
                continue;
            }
        } else {
            // Unpar number of teams
            $fixture_not_played = (2 * $i) % $nb_teams;
            if ($fixture_not_played == 0) {
                $fixture_not_played = $nb_teams;
            }
            // Check for last fixture available
            for ($fix_num = 1; $fix_num < $nb_teams + 1; $fix_num++) {
                $found = true;
                if ($fix_num == $fixture_not_played) {
                    continue;
                }
                for ($j = 0; $j < $nb_teams; $j++) {
                    if ($fix_nums_x[$i][$j] == $fix_num) {
                        $found = false;
                        break;
                    }
                }
                if ($found) {
                    $fixture_num = $fix_num;
                    break;
                }
            }
            if ($fixture_num == 0) {
                echo("Error");
                continue;
            }
        }
        $fixture_back_num = $fixture_num + $nb_fixtures / 2;
        $fixture_id = $db->get_fixture_id($fixture_num, $id_league, false);
        $fixture_back_id = $db->get_fixture_id($fixture_back_num, $id_league, false);
        if ($home) {
            $db->add_matches_to_fixture($fixture_id, $team_ids[$i], $last_team);
            $db->add_matches_to_fixture($fixture_back_id, $last_team, $team_ids[$i]);
        } else {
            $db->add_matches_to_fixture($fixture_back_id, $team_ids[$i],$last_team);
            $db->add_matches_to_fixture($fixture_id, $last_team, $team_ids[$i]);
        }
        $home = !$home;
    }
}

// Matches
for ($counter = $nb_matches; $counter > 0; $counter = $counter - 1)
{
    // Get teams ID
    foreach ($db->get_matches_by_fixture($id_fixture, $nb_matches - $counter ) as $row)
    {
        $team_home = (int) $row->id_team_home;
        $team_away = (int) $row->id_team_away;
    }

    // Home matches
    $output .= '<tr '.$fct->alternate('', 'class="alternate"').'><td class="club-disp">';
    $output .= $fct->input('id_home[]', $team_home, array('type' => 'hidden', 'id' => "home$counter"));
    $output .= '<span id="home' . $counter . 'disp">' . $clubs_list[$team_home] . '</span>';
    $output .= '</td>';

    // Away matches
    $output .= '<td class="club-disp">';
    $output .= $fct->input('id_away[]', $team_away, array('type' => 'hidden', 'id' => "away$counter"));
    $output .= '<span id="away' . $counter . 'disp">' . $clubs_list[$team_away] . '</span>';
    $output .= '</td>';

    // Invert
    $output .= '<td colspan="2" style="text-align:center">';
    $output .= '<input type="button" onclick="javascript:invert(' . $counter . ');" value="' . __('Invert', 'phpleague') . '" />';
    $output .= '</td>';

    // Inverted match
    $output .= '<td class="club-disp">';
    $output .= $fct->input("id_home2[]", $team_away, array('type' => 'hidden', 'id' => "homeback$counter"));
    $output .= '<span id="homeback' . $counter . 'disp">' . $clubs_list[$team_away] . '</span>';
    $output .= '</td>';
    $output .= '<td class="club-disp">';
    $output .= $fct->input("id_away2[]", $team_home, array('type' => 'hidden', 'id' => "awayback$counter"));
    $output .= '<span id="awayback' . $counter . 'disp">' . $clubs_list[$team_home] . '</span>';
    $output .= '</td></tr>';

    $i++;
}

$output .= '</table>'.$fct->input('id_fixture', $id_fixture, array('type' => 'hidden')).$fct->input('id_fixture2', $id_fixture2, array('type' => 'hidden')).$fct->form_close();

$output .= '<script type="text/javascript">';
$output .= 'invert = function(fixture_id) {';
$output .= '	var newhome = jQuery("#homeback" + fixture_id).val();';
$output .= '	var newhomedisp = jQuery("#homeback" + fixture_id + "disp").html();';
$output .= '	var newaway = jQuery("#awayback" + fixture_id).val();';
$output .= '	var newawaydisp = jQuery("#awayback" + fixture_id + "disp").html();	';
$output .= '	jQuery("#home" + fixture_id).val(newhome);';
$output .= '	jQuery("#home" + fixture_id + "disp").html(newhomedisp);';
$output .= '	jQuery("#away" + fixture_id).val(newaway);';
$output .= '	jQuery("#away" + fixture_id + "disp").html(newawaydisp);';
$output .= '	jQuery("#homeback" + fixture_id).val(newaway);';
$output .= '	jQuery("#homeback" + fixture_id + "disp").html(newawaydisp);';
$output .= '	jQuery("#awayback" + fixture_id).val(newhome);';
$output .= '	jQuery("#awayback" + fixture_id + "disp").html(newhomedisp);';
$output .= '}';
$output .= '</script>';
$data[]  = array(
    'menu'  => __('Matches', 'phpleague'),
    'title' => __('Matches of ', 'phpleague').$league_name,
    'text'  => $output,
    'class' => 'full'
);

// Render the page
echo $ctl->admin_container($menu, $data, $message);
