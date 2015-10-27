<?php

/*
 * This file is part of the PHPLeague package.
 *
 * (c) Maxime Dizerens <mdizerens@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

define ("FORFEIT_INPUT", "/");

// Get leagues for user
$user_ID = get_current_user_id();
$my_clubs = array();
$my_club_ids = array();
if ($user_ID != 0)
{
    $db = new PHPLeague_Database();
    $clubs = $db->get_user_clubs_information($user_ID);
    foreach ($clubs as $club)
    {
        $my_clubs[] = $club;
    }
}
$leagues = $db->get_every_league(0, $db->count_leagues());
$my_leagues = array();
foreach($leagues as $league)
{
    foreach ($my_clubs as $club)
    {
        if (!$db->is_club_already_in_league($league->id, $club->id))
        { // is_club_already_in_league returns false when in league
            $my_leagues[] = $league;
            break;
        }
    }
}

$id_league = ( ! empty($_GET['id_league']) && $db->is_league_exists($_GET['id_league']) === TRUE )
    ? (int) $_GET['id_league'] : $my_leagues[0]->id;
foreach ($my_clubs as $club)
{
    $my_club_ids[] = $db->get_team_id($club->id, $id_league);
}


if ($db->is_league_exists($id_league) === FALSE)
    wp_die(__('We did not find the league in the database.', 'phpleague'));

// Variables
$league_name = $db->return_league_name($id_league);
$setting     = $db->get_league_settings($id_league);
$nb_teams    = (int) $setting->nb_teams;
$nb_legs     = (int) $setting->nb_leg;
$nb_players  = (int) $setting->nb_starter + (int) $setting->nb_bench;
$sport       = new PHPLeague_Sports::$sports[$setting->sport];
$page_url    = 'admin.php?page=phpleague_my_results&id_league='.$id_league;
$output      = '';
$data        = array();

// Menu: select league
$menu = array();
foreach($my_leagues as $league)
{
    if ($id_league == $league->id)
        $menu[$league->name] = "#";
    else
        $menu[$league->name] = admin_url('admin.php?page=phpleague_my_results&id_league='.$league->id);
}

// Data processing...
if (isset($_POST['results']) && check_admin_referer('phpleague'))
{
    // Secure data
    $array = ( ! empty($_POST['array'])) ? $_POST['array'] : NULL;

    if (is_array($array))
    {
        $success = TRUE;
        foreach ($array as $item)
        {
            if ( ! (($item['goal_home'] == '') || ($item['goal_away'] == '')))
            {
                if ($item['goal_home'] == FORFEIT_INPUT) {
                    $item['goal_home'] = $sport->get_forfeit_code();
                }
                if ($item['goal_away'] == FORFEIT_INPUT) {
                    $item['goal_away'] = $sport->get_forfeit_code();
                }
                $db->update_results($item['goal_home'], $item['goal_away'], $item['date'], $item['id_match']);
            }
            elseif (($item['goal_home'] == '') || ($item['goal_away'] == ''))
            {
                $db->update_results(NULL, NULL, $item['date'], $item['id_match']);
            }
            else
            {
                $success = FALSE;
            }
        }
        if ($success)
            $message[] = __('Match(es) updated successfully.', 'phpleague');
        else
            $message[] = __('An error occurred during the results generation.', 'phpleague');
    }
    
    // Player mod is enabled, so check if we have anything to add in the database
    if ($setting->player_mod === 'yes')
    {
        // Secure data
        $players = ( ! empty($_POST['players'])) ? $_POST['players'] : 0;

        if (is_array($players))
        {
            foreach ($players as $key => $item)
            {
                // Remove old match data
                $db->remove_players_data_match($key);
                foreach ($item as $row)
                {
                    // TODO - Show a message that we intercept a duplicata try
                    // $key = id_match, $row = id_player_team
                    // Check that the player is real and not already once in the match
                    if ($row > 0 && $db->player_data_already_match($row, $key) === FALSE)
                        $db->add_player_data(255, $row, $key, 1);
                }
            }
        }
    }
}

$output    .= $fct->form_open(admin_url($page_url));
$output    .= '<div class="tablenav"><div class="alignleft actions">'
           .$fct->input('results', __('Save', 'phpleague'), array('type' => 'submit', 'class' => 'button')).'</div></div>';

$nb_fixtures = $db->nb_fixtures_league($id_league);
$count = 0;
for ($fixture_number = 1; $fixture_number <= $nb_fixtures; $fixture_number++)
{
    $show = false;
    $table = '<table class="widefat"><thead><tr><th colspan="6">'.
            __(' - Fixture: ', 'phpleague') . $fixture_number . '</th></tr></thead><tbody>';
    foreach ($db->get_results_by_fixture($fixture_number, $id_league) as $key => $row)
    {
        if (in_array($row->home_id, $my_club_ids) || in_array($row->away_id, $my_club_ids))
        {
            $show = true;
            $table .= '<tr>'
                    .'<td style="text-align:right;">'.esc_html($row->name_home).'</td>';

            $table .= '<td class="check-column">'.$fct->input('array['.$count.'][goal_home]',
                    ($row->goal_home == $sport->get_forfeit_code()) ? FORFEIT_INPUT : $row->goal_home, array('size' => 2)).'</td>';

            $table .= '<td class="check-column">'.$fct->input('array['.$count.'][goal_away]',
                    ($row->goal_away == $sport->get_forfeit_code()) ? FORFEIT_INPUT : $row->goal_away, array('size' => 2)).'</td>';

            $table .= '<td>'.esc_html($row->name_away).'</td>';
            $table .= '<td class="check-column">'.$fct->input('array['.$count.'][date]',
                    esc_html($row->played), array('size' => 18, 'class' => 'masked-full', 'type' => 'hidden')).$fct->input('array['.$count.'][id_match]',
                    (int) $row->match_id, array('type' => 'hidden')).'</td>';

            $table .= '<td class="check-column">'.$button_players.'</td></tr>';
        }
    }
    $table .= "</tbody></table>";
    if ($show)
    {
        $output .= $table;
        $count++;
    }
}

$output    .= '<div class="tablenav"><div class="alignleft actions">'
           .$fct->input('results', __('Save', 'phpleague'), array('type' => 'submit', 'class' => 'button')).'</div></div>';

$output .= $fct->form_close();
$output .= '<p>'.__('Enter / for forfeit', 'phpleague').'</p>';
$data[]  = array(
    'menu'  => $setting->name,
    'title' => __('Results of ', 'phpleague').$league_name,
    'text'  => $output,
    'class' => 'full'
);

// Render the page
echo $ctl->admin_container($menu, $data, $message);
