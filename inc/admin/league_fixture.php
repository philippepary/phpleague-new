<?php

/*
 * This file is part of the PHPLeague package.
 *
 * (c) Maxime Dizerens <mdizerens@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

if ($db->is_league_exists($id_league) === FALSE)
    wp_die(__('We did not find the league in the database.', 'phpleague'));

// Variables
$league_name = $db->return_league_name($id_league);
$setting     = $db->get_league_settings($id_league);
$nb_teams    = (int) $setting->nb_teams;
$nb_legs     = (int) $setting->nb_leg;
$output      = '';
$data        = array();

// Data processing...
if (isset($_POST['fixtures']) && check_admin_referer('phpleague'))
{
    // Secure data
    $post_years  = ( ! empty($_POST['year']) && is_array($_POST['year']))   ? $_POST['year']  : NULL;
    $post_weeks  = ( ! empty($_POST['week']) && is_array($_POST['week']))   ? $_POST['week']  : NULL;
    $post_not_playing = ( ! empty($_POST['notplaying']) && is_array($_POST['notplaying'] )) ? $_POST['notplaying'] : NULL;
    
    $non_playing_error = false;
    if ($post_not_playing != NULL)
    {
        $check = array();
        foreach ($post_not_playing as $not_playing)
        {
            if ( in_array($not_playing, $check) )
            {
                $not_playing_error = true;
                break;
            }
            $check[] = $not_playing;
        }
    }
    if ($post_years === NULL || $post_weeks === NULL)
    {
        $message[] = __('The fixtures format is not good!', 'phpleague');   
    }
    else if ( $not_playing_error )
    {
        $message[] = __('A team cannot be not playing more than once!', 'phpleague');
    }
    else
    {
        // It does not matter which one we count...
        $count = count($post_years);
        for ($i = 1; $i <= $count; $i++)
        {
            // We get each data separately...
            $year  = (int) $post_years[$i];
            $week  = (int) $post_weeks[$i];
            $first_day = DateTime::createFromFormat("Y-m-d", $year."-1-1");
            if(in_array($year,array(2016,2017,2021,2022,2023))) {
                $datetime = $first_day->add(new DateInterval("P".(($week) * 7)."D"));
            }
            else {
                $datetime = $first_day->add(new DateInterval("P".(($week-1) * 7)."D"));
            }
            $month = (int) $datetime->format("n");
            $day   = (int) $datetime->format("j");

            // Bring the data together
            $date  = $year.'-'.$month.'-'.$day;

            if ( $post_not_playing != NULL )
            {
                if ( $i > $count / 2 )
                {
                    $not_playing = $post_not_playing[$i - $count / 2];
                }
                else
                {
                    $not_playing = $post_not_playing[$i];
                }
            }
            else
            {
                $not_playing = NULL;
            }

            // Edit the fixtures in the database...
            $db->edit_league_fixtures($i, $date, $id_league, $not_playing);

            // We update the match datetime using the default time
            foreach ($db->get_fixture_id($i, $id_league) as $row)
            {
                $db->edit_game_datetime($date.' '.$setting->default_time, $row->fixture_id);
            }
        }
        
        $message[] = __('Fixtures updated successfully!', 'phpleague');
    }
}

// Menu
$menu = array(__('Teams', 'phpleague')    => admin_url('admin.php?page=phpleague_overview&option=team&id_league='.$id_league));
$menu[__('Fixtures', 'phpleague')] = '#';
if ( count ( $db->get_fixtures_league($id_league) ) > 0 )
{
    $menu[__('Matches', 'phpleague')] = admin_url('admin.php?page=phpleague_overview&option=match&id_league='.$id_league);
    $menu[__('Results', 'phpleague')] = admin_url('admin.php?page=phpleague_overview&option=result&id_league='.$id_league);
}
$menu[__('Settings', 'phpleague')] = admin_url('admin.php?page=phpleague_overview&option=setting&id_league='.$id_league);

// Odd number of teams is probably not desired...
if (($nb_teams % 2) != 0)
    $message[] = __('Be aware that your league has an odd number of teams.', 'phpleague');

// We need to have at least 2 teams...
if ($nb_teams == 0 || $nb_teams == 1)
{
    $message[] = __('It seems that '.$league_name.' does not have teams registered or only one.', 'phpleague');
}
else
{
    foreach ($db->get_distinct_league_team($id_league) as $array)
    {
        $clubs_list[$array->club_id] = esc_html($array->name);
        $team_ids[] = $array->club_id;
    }
    $output = $fct->form_open(admin_url('admin.php?page=phpleague_overview&option=fixture&id_league='.$id_league));
    
    // Count how many fixtures in the league
    $nb_fixtures = $db->nb_fixtures_league($id_league);
    
    if (($nb_teams % 2) != 0)
        $fixtures_number = ($nb_teams) * 2;
    else
        $fixtures_number = ($nb_teams - 1) * 2;

    // Security check
    if ($nb_fixtures != $fixtures_number)
    {
    
		// tim modified - 1 - prevent deletion of old fixtures! only delete what is necessary
		if($nb_fixtures < $fixtures_number)
		{
			$number = $nb_fixtures+1;
			while ($number <= $fixtures_number)
			{
				$db->add_fixtures_league($number, $id_league);
				$number++;
			}
		}
		else
		{
			$number = $fixtures_number+1;
			while ($number <= $nb_fixtures)
			{
				$db->remove_fixtures_league($id_league, $number);
				$number++;
			}
		}
		/*
        // We removed the "old" data
        $db->remove_fixtures_league($id_league);

        // Add the new fixtures in the database...
        $number = 1;
        while ($number <= $fixtures_number)
        {
            $db->add_fixtures_league($number, $id_league);
            $number++;
        }
		//*/
		// tim modified - 0

    }

    // Years list
    for ($i = 1900; $i <= 2050; $i++)
    {
        $years[$i] = $i;
    }

    // Months list
    for ($i = 1; $i <= 12; $i++)
    {
        $months[$i] = $i;
    }

    // Days list
    for ($i = 1; $i <= 31; $i++)
    {
        $days[$i] = $i;
    }

    // Weeks list
    for ($i = 1; $i <= 53; $i++)
    {
        $weeks[$i] = $i;
    }

    $output .= '<div class="tablenav top"><div class="alignleft actions">'.$fct->input('fixtures', __('Save', 'phpleague'), array('type' => 'submit', 'class' => 'button')).'</div></div>';

    $split = ($nb_teams % 2 != 0) ? ' class="table-splitter"' : '';
    $output .=
    '<table class="widefat text-centered"><thead>
        <tr>
            <th colspan="3" class="table-splitter">Matchs aller</th>
            <th colspan="3"'.$split.'>Matchs retour</th>';
    if ($nb_teams % 2 != 0)
    {
        $output .= '            <th></th>';
    }
    $output .= '
        <tr>
            <th>'.__('Fixture', 'phpleague').'</th>
            <th>'.__('Year', 'phpleague').'</th>
            <th class="table-splitter">'.__('Week', 'phpleague').'</th>
            <th>'.__('Fixture', 'phpleague').'</th>
            <th>'.__('Year', 'phpleague').'</th>
            <th'.$split.'>'.__('Week', 'phpleague').'</th>';
    if ($nb_teams % 2 != 0)
    {
        $output .= '            <th>'.__('Not playing', 'phpleague').'</th>';
    }
    $output .= '
        </tr>
    </thead><tbdody>';
    
    $fixture_list = $db->get_fixtures_league($id_league);
    $half = count($fixture_list) / 2;
    for ($i = 0; $i < $half; $i++)
    {
        $row = $fixture_list[$i];
        // Get years, months and days separately...
        list($year, $month, $day) = explode('-', $row->scheduled);
        if ($year == "0000")
        {
            $year = $setting->year;
        }
        $dateTime = \DateTime::createFromFormat("Y-m-d", $row->scheduled);
        $time = $dateTime->getTimestamp();
        $week = (int) date("W", $time);
        // Used as a key...
        $number = (int) $row->number;

        // Render rows...
        $output .= '<tr>';
        $output .= '<td>'.$number.'</td>';
        $output .= '<td>'.$fct->select('year['.$number.']', $years, (int) $year).'</td>';
        $output .= '<td class="table-splitter">'.$fct->select('week['.$number.']', $weeks, (int) $week).'</td>';

        $row2 = $fixture_list[$i + $half];
        $number2 = (int) $row2->number;
        list($year2, $month2, $day2) = explode('-', $row2->scheduled);
        if ($year2 == "0000")
        {
            $year2 = $setting->year;
        }
        $dateTime2 = \DateTime::createFromFormat("Y-m-d", $row2->scheduled);
        $time2 = $dateTime2->getTimestamp();
        $week2 = (int) date("W", $time2);
        
        $output .= '<td>'.$number2.'</td>';
        $output .= '<td>'.$fct->select('year['.$number2.']', $years, (int) $year2).'</td>';
        $output .= '<td'.$split.'>'.$fct->select('week['.$number2.']', $weeks, (int) $week2).'</td>';
        if ($nb_teams % 2 != 0)
        {
            $non_playing_team_id = $row->non_playing_team_id;
            if ($non_playing_team_id === null) 
            {
                $non_playing_team_id = $team_ids[$i];
            }
            $output .= '<td>'.$fct->select('notplaying['.$number.']', $clubs_list, $non_playing_team_id).'</td>';
        }
        $output .= '</tr>';
    }

    $output .= '</tbody></table>'.$fct->form_close();
}

$data[] = array(
    'menu'  => __('Fixtures', 'phpleague'),
    'title' => __('Fixtures of ', 'phpleague').$league_name,
    'text'  => $output,
    'class' => 'full'
);

// Render the page
echo $ctl->admin_container($menu, $data, $message);
