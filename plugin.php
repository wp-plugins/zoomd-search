<?PHP
/*
Plugin name: Zoomd Search
Plugin URI: http://zoomd.com/
Description: A beautiful search experience, that engages your visitors, improves conversion and adds monetization to your site
Author: Zoomd
Author URI: http://zoomd.com/
License: GPLv2 or later
Version: 1.1
*/

session_start();

add_action('wp_footer', 'add_zoomd_plugin');
function add_zoomd_plugin() {
    $clientId  = get_option('zoomd_clientId');
    if( $clientId ) {
        wp_register_script( 'zoomd_search', plugins_url( 'js/sphereup.widget.min.js', __FILE__ ), array('jquery'), 1, true);
        wp_enqueue_script('zoomd_search');
        ?>
        <!-- START SPHEREUP -->
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                if(typeof SphereUp != 'undefined' && typeof SphereUp.SphereUpWidget != 'undefined') {
                    SphereUp.SphereUpWidget.loadWidget({clientId: '<?php echo $clientId ?>'});
                }
            });
        </script>
        <!-- END SPHEREUP -->
    <?php
    }
 }

function zoomd_plugin_admin_scripts() {
    if ( is_admin() ){
        if ( isset($_GET['page']) && $_GET['page'] == 'zoomd_settings' ) {
            wp_enqueue_script('jquery');
        }
    }
}
add_action( 'admin_init', 'zoomd_plugin_admin_scripts' );

function zoomd_plugin_deactivation() {
    delete_option('zoomd_siteurl');
    delete_option('zoomd_clientId');
    delete_option('zoomd_email');
    delete_option('zoomd_password');
    delete_option('zoomd_sphereup_admin_url');
    delete_option('zoomd_deferred_admin_notices');
}

register_deactivation_hook(__FILE__, 'zoomd_plugin_deactivation');

function zoomd_plugin_settings() {

    add_options_page( __( "ZoomD Plugin settings", BSEARCH_LOCAL_NAME ), __( "Zoomd Search", BSEARCH_LOCAL_NAME ), 'manage_options', 'zoomd_settings', 'zoomd_display_settings');
}
add_action( 'admin_menu', 'zoomd_plugin_settings' );

function zoomd_action_links( $links ) {
    $newField = array();
    $newField[] = '<a href="'. get_admin_url(null, 'options-general.php?page=zoomd_settings') .'">Settings</a>';
    $newField = array_merge($newField, $links);
    return $newField;
}
add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), 'zoomd_action_links' );

add_action('admin_notices', 'zoomd_admin_notices');
function zoomd_admin_notices() {
    if ($notices= get_option('zoomd_deferred_admin_notices')) {
        echo "<script>
            var $ = jQuery;
            $(document).ready(function() {
                $('#setting-error-settings_updated').hide();
            });

        </script>";
        foreach ($notices as $notice) {
            echo "<div class='error'><p>$notice</p></div>";
        }
        delete_option('zoomd_deferred_admin_notices');
    }
}

function zoomd_display_settings() {
    $siteurl = (get_option('zoomd_siteurl') != '') ? get_option('zoomd_siteurl') :  get_option('siteurl');
    $clientId  = get_option('zoomd_clientId');
    $email  = (filter_var(get_option('zoomd_email'), FILTER_VALIDATE_EMAIL)) ? get_option('zoomd_email') : get_option('admin_email');
    $password  = (get_option('zoomd_password') != '') ? get_option('zoomd_password') : '';
    $first_name  = (get_option('blogname') != '') ? get_option('blogname') : 'ZoomDPlugin';
    $second_name  = (get_option('siteurl') != '') ? get_option('siteurl') : 'ZoomDPlugin';
    $adminUrl  = (get_option('zoomd_sphereup_admin_url') != '') ? get_option('zoomd_sphereup_admin_url') : false;

    $updateAdminLink = false;

    if ( get_option('zoomd_clientId_email') != $email || !$clientId || get_option('zoomd_clientId_siteUrl') != $siteurl ) {
        $updateAdminLink = true;
    }

    if($updateAdminLink && $password) {
        $jsonurl = "https://suadmin-ss.sphereup.com/SelfService/Register?firstName=" . urlencode($first_name) . "&lastName=" . urlencode($second_name) . "&email=" . urlencode($email) . "&password=" . urlencode($password) . "&url=" . urlencode($siteurl);

        $json = file_get_contents($jsonurl);
        $result = json_decode($json);

        if(isset($result->error)) {
            $error = "Something went wrong, please try saving again.";
            if(strcasecmp('Bad data', $result->error) == 0) {
                $error = "Please review and correct the submitted information and try saving again.";
            } else if (strcasecmp('Account with another id', substr($result->error, 0, 23)) == 0) {
                $error = "This email is already registered in the system, please use a different one.";
            } else if (strcasecmp('Unsupported', $result->error) == 0) {
                $error = "Unfortunately, this site is currently unsupported by Zoomd Search. Please feel free to try us again in the future.";
            }

            update_option('zoomd_deferred_admin_notices', array($error) );
            zoomd_admin_notices();
        }

        if(isset($result->clientId)) {
            $clientId = $result->clientId;
            update_option('zoomd_clientId', $result->clientId);
            update_option('zoomd_clientId_email', $email);
            update_option('zoomd_sphereup_admin_url', 'http://www.zoomd.com/#login');
            $adminUrl = 'http://www.zoomd.com/#login';
        }
    }

    $additionalData = 'style="width: 250px;"';
    if($adminUrl) {
        $additionalData .= ' disabled="disabled"';
    }

    $html = '<div class="wrap">

            <form method="post" name="options" action="options.php">

            <h2>Zoomd Account</h2>' . wp_nonce_field('update-options') . '
            <table width="100%" cellpadding="10" class="form-table">';

    if(!$clientId || !$password) {
        $html .= '<tr>
                    <td align="left" scope="row" style="color: red;" colspan="2">
                        Your plugin is currently not set up.<br/><br/>Please enter the details below and click Save. Don\'t forget to choose a password too!
                    </td>
                </tr>';
    }

    $html .= '<tr>
                    <td align="left" style="max-width: 200px;" width="200">
                        <label>Your Website URL: </label>
                    </td>
                    <td align="left">
                        <input type="text" name="zoomd_siteurl" value="' . $siteurl . '" '.$additionalData.' required/>
                    </td>
                </tr>
                <tr>
                    <td align="left">
                        <label>Your Email Address: </label>
                    </td>
                    <td align="left" scope="row">
                        <input type="email" name="zoomd_email" value="' . $email . '" '.$additionalData.' required/>
                    </td>
                </tr>
                <tr>
                    <td align="left">
                        <label>Create a Password: </label>
                    </td>
                    <td align="left" scope="row">
                        <input type="password" name="zoomd_password" value="' . $password . '" '.$additionalData.' required title="3 characters minimum" pattern=".{3,}"/>
                    </td>
                </tr>';

    if( $adminUrl ) {
        $html .= '<tr><td align="left" scope="row" colspan="2">Thanks you for creating a Zoomd Search account!<br/>Visit the Admin page <a href="'.$adminUrl.'" target="_blank">HERE</a></td></tr>';
    }
    $html .= '</table>';

    if ( !$adminUrl ) {
        $html .= '<p class="submit">
                <input type="hidden" name="action" value="update" />
                <input type="hidden" name="page_options" value="zoomd_siteurl,zoomd_email,zoomd_password" />
                <input type="submit" name="Submit" value="Save" />
            </p>';

    }
    $html .= '</form></div>';
    echo $html;
}