<?php

/*
 * This file is part of the PHPLeague package.
 *
 * (c) Maxime Dizerens <mdizerens@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

// Variables
$id_club  = ( ! empty($_GET['id_club'])) ? (int) $_GET['id_club'] : 0;
$message  = array();
$data     = array();
$menu     = array(__('Club Information', 'phpleague') => '#');

// Security check
if ($db->is_club_unique($id_club, 'id') === TRUE)
    wp_die(__('We did not find the club in the database.', 'phpleague'));

// Data processing...
if (isset($_POST['edit_club']) && check_admin_referer('phpleague'))
{
    // Secure data
    $name     = (string) trim($_POST['name']);
    $venue    = (string) trim($_POST['venue']);
    $coach    = (string) trim($_POST['coach']);
    $creation = (string) trim($_POST['creation']);
    $website  = (string) trim($_POST['website']);
    $logo_b   = (string) trim($_POST['big_logo']);
    $logo_m   = (string) trim($_POST['mini_logo']);
    $country  = (int) $_POST['country'];
    $wp_user_id = (int) $_POST['wp_user_id'];
    $address  = (string) trim($_POST['address']);
    $phone    = (string) trim($_POST['phone']);
    $training = (string) trim($_POST['training_time']);
    
    // Date must follow a particular pattern
    if ( ! preg_match('/^([0-9]{4})$/', $creation))
    {
        $creation = '0000';
        $message[] = __('The creation date must be 4 digits (optional).', 'phpleague');
    }
    
    // We need to pass those tests to insert the data
    if ($id_club === 0)
    {
       $message[] = __('Busted! We got 2 different IDs which is not possible!', 'phpleague');
    }
    else
    {
        $db->update_club_information($id_club, $name, $country, $coach, $venue, $creation, $website, $logo_b, $logo_m, $wp_user_id, $address, $phone, $training);
        $message[] = __('Club information edited with success!', 'phpleague');
    }
}

// Get countries list... 
foreach ($db->get_every_country(0, 250, 'ASC') as $array)
{
    $countries_list[$array->id] = esc_html($array->name);
}
$wp_users[null] = __('None');
foreach (get_users() as $user)
{
    $wp_users[$user->ID] = esc_html($user->display_name);
}
$logo_m_list = $fct->return_dir_files(WP_PHPLEAGUE_UPLOADS_PATH.'logo_mini/');
$logo_b_list = $fct->return_dir_files(WP_PHPLEAGUE_UPLOADS_PATH.'logo_big/');
$club_info   = $db->get_club_information($id_club);
$output      = $fct->form_open(admin_url('admin.php?page=phpleague_club&id_club='.$id_club));
$table       =
    '<table class="form-table">
        <tr>
            <td class="required">'.__('Club Name:', 'phpleague').'</td>
            <td>'.$fct->input('name', esc_html($club_info->name)).'</td>
            <td>'.__('WordPress user:', 'phpleague').'</td>
            <td>'.$fct->select('wp_user_id', $wp_users, $club_info->wp_user_id).$fct->hidden('country', (int) $club_info->id_country).'</td>
        </tr>
        <tr>
            <td>'.__('Coach Name:', 'phpleague').'</td> 
            <td>'.$fct->input('coach', esc_html($club_info->coach)).'</td>
            <td>'.__('Club Venue:', 'phpleague').'</td>
            <td>'.$fct->input('venue', esc_html($club_info->venue)).'</td>
        </tr>
        <tr>
            <td>'.__('Address:', 'phpleague').'</td>
            <td>'.$fct->input('address', esc_html($club_info->address)).'</td>
            <td>'.__('Phone:', 'phpleague').'</td>
            <td>'.$fct->input('phone', esc_html($club_info->phone)).'</td>
        </tr>
        <tr>
            <td>'.__('Club Website:', 'phpleague').'</td>
            <td>'.$fct->input('website', esc_html($club_info->website)).'</td>
            <td>'.__('Creation Year:', 'phpleague').'</td>
            <td>'.$fct->input('creation', (int) $club_info->creation).'</td>
        </tr>
        <tr>
            <td>'.__('Big Logo:', 'phpleague').'</td>
            <td>'.$fct->select('big_logo', $logo_b_list, esc_html($club_info->logo_big)).'</td>
            <td>'.__('Mini Logo:', 'phpleague').'</td>
            <td>'.$fct->select('mini_logo', $logo_m_list, esc_html($club_info->logo_mini)).'</td>
        </tr>
        <tr>
            <td>'.__('Training time:', 'phpleague').'</td>
            <td colspan="3">'.$fct->textarea('training_time', $club_info->training_time).'</td>
        </tr>
    </table>
    <div class="submit">
        '.$fct->input('id_club', $id_club, array('type' => 'hidden')).'
        '.$fct->input('edit_club', __('Save', 'phpleague'), array('type' => 'submit')).'
    </div>';
    
$output .= $table.$fct->form_close();

$data[] = array(
    'menu'  => __('Club Information', 'phpleague'),
    'title' => __('Club Information', 'phpleague'),
    'text'  => $output,
    'class' => 'full'
);

// Render the page
echo $ctl->admin_container($menu, $data, $message);
