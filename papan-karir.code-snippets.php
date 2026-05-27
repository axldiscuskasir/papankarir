<?php

/**
 * Register Jobseeker Hook
 */
add_action( 'jet-engine-booking/register-jobseeker', 'register_jobseeker_account' );

function register_jobseeker_account( $data ) {

    // Ambil data form
    $nama     = sanitize_text_field( $data['nama-lengkap'] );
    $email    = sanitize_email( $data['email'] );
    $password = $data['password'];
    $confirm  = $data['konfirmasi-password'];

    // Validasi password
    if ( $password !== $confirm ) {
        wp_die( 'Konfirmasi password tidak cocok' );
    }

    // Cek email
    if ( email_exists( $email ) ) {
        wp_die( 'Email sudah digunakan' );
    }

    // Username otomatis
    $username = sanitize_user( current( explode( '@', $email ) ) );

    if ( username_exists( $username ) ) {
        $username = $username . rand(100,999);
    }

    // Buat user
    $user_id = wp_create_user( $username, $password, $email );

    if ( is_wp_error( $user_id ) ) {
        wp_die( $user_id->get_error_message() );
    }

    // Role Jobseeker
    wp_update_user( array(
        'ID'   => $user_id,
        'role' => 'jobseeker'
    ) );

    // Simpan user meta
    update_user_meta( $user_id, 'nama-lengkap', $nama );

    // Buat profile jobseeker
    $jobseeker_id = wp_insert_post( array(
        'post_type'   => 'profile',
        'post_status' => 'publish',
        'post_title'  => $nama,
        'post_author' => $user_id,
    ) );
	if ( is_wp_error( $jobseeker_id ) || ! $jobseeker_id ) {
    wp_die( 'Gagal membuat profil jobseeker.' );
	}

    // Simpan relasi
    update_user_meta( $user_id, 'jobseeker-id', $jobseeker_id );

    update_user_meta(
        $user_id,
        'jobseeker-link',
        get_permalink( $jobseeker_id )
    );

    // Auto login
    wp_set_current_user( $user_id );
    wp_set_auth_cookie( $user_id );

    // Redirect dashboard
    wp_redirect( home_url( '/dashboard/' ) );
    exit;

}

/**
 * Dashboard Menu Shortcode
 */
function axl_get_vertical_menu_html( $menu_name ) {
    $menu_obj = wp_get_nav_menu_object( $menu_name );

    if ( ! $menu_obj ) {
        return '';
    }

    $menu_items = wp_get_nav_menu_items( $menu_obj->term_id );

    if ( empty( $menu_items ) ) {
        return '';
    }

    global $wp;
    $current_url = untrailingslashit(
        home_url( add_query_arg( array(), $wp->request ) )
    );

    $output = '<ul class="vertical-menu">';

    foreach ( $menu_items as $item ) {
        $item_url     = untrailingslashit( $item->url );
        $active_class = ( $current_url === $item_url ) ? 'active' : '';

        $output .= '<li class="' . esc_attr( $active_class ) . '">';
        $output .= '<a href="' . esc_url( $item->url ) . '">' . wp_kses_post( $item->title ) . '</a>';
        $output .= '</li>';
    }

    $output .= '</ul>';

    return $output;
}

function axl_display_vertical_menu( $atts ) {
    $atts = shortcode_atts(
        array(
            'jobseeker_menu' => 'Dashboard Menu Jobseeker',
            'recruiter_menu' => 'Dashboard Menu Jobrecruiter',
        ),
        $atts,
        'display_menu'
    );

    $current_user = wp_get_current_user();
    $roles        = (array) $current_user->roles;

    if ( in_array( 'administrator', $roles, true ) ) {
        $output  = '<div class="dashboard-admin-menu-preview">';
        $output .= '<p class="dashboard-menu-label">Jobseeker Menu Preview</p>';
        $output .= axl_get_vertical_menu_html( $atts['jobseeker_menu'] );
        $output .= '<p class="dashboard-menu-label">Recruiter Menu Preview</p>';
        $output .= axl_get_vertical_menu_html( $atts['recruiter_menu'] );
        $output .= '</div>';

        return $output;
    }

    if ( in_array( 'jobrecruiter', $roles, true ) ) {
        return axl_get_vertical_menu_html( $atts['recruiter_menu'] );
    }

    return axl_get_vertical_menu_html( $atts['jobseeker_menu'] );
}

add_shortcode( 'display_menu', 'axl_display_vertical_menu' );

function axl_vertical_menu_styles() {
?>
<style>
.vertical-menu{
    list-style:none;
    padding:0;
    margin:0 0 20px 0;
}

.vertical-menu li{
    margin-bottom:8px;
}

.vertical-menu a{
    display:flex;
    align-items:center;
    gap:10px;
    padding:12px 14px;
    border-radius:12px;
    text-decoration:none;
    color:#333;
    font-size:14px;
    font-weight:500;
    transition:all .2s ease;
}

.vertical-menu a:hover,
.vertical-menu li.active a{
    background:#f4c400;
    color:#111;
}

.vertical-menu i{
    width:18px;
    text-align:center;
    font-size:15px;
}

.dashboard-menu-label{
    font-size:12px;
    font-weight:700;
    color:#999;
    text-transform:uppercase;
    margin:18px 0 8px;
}
</style>
<?php
}
add_action( 'wp_head', 'axl_vertical_menu_styles' );

function axl_load_font_awesome_dashboard() {
    wp_enqueue_style(
        'font-awesome-6',
        'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css',
        array(),
        '6.5.1'
    );
}
add_action( 'wp_enqueue_scripts', 'axl_load_font_awesome_dashboard' );

/**
 * Register Recruiter Hook
 */
add_action( 'jet-engine-booking/register-recruiter', 'register_recruiter_user' );

function register_recruiter_user( $form_data ) {

    $nama_perusahaan = isset( $form_data['nama-perusahaan'] ) ? sanitize_text_field( $form_data['nama-perusahaan'] ) : '';
    $email           = isset( $form_data['email'] ) ? sanitize_email( $form_data['email'] ) : '';
    $password        = isset( $form_data['password'] ) ? $form_data['password'] : '';
    $confirm_pass    = isset( $form_data['konfirmasi-password'] ) ? $form_data['konfirmasi-password'] : '';

    if ( empty( $nama_perusahaan ) || empty( $email ) || empty( $password ) || empty( $confirm_pass ) ) {
        return;
    }

    if ( ! is_email( $email ) || email_exists( $email ) ) {
        return;
    }

    if ( $password !== $confirm_pass ) {
        return;
    }

    $username_base = sanitize_user( current( explode( '@', $email ) ), true );
    $username      = $username_base;
    $i             = 1;

    while ( username_exists( $username ) ) {
        $username = $username_base . $i;
        $i++;
    }

    $user_id = wp_create_user( $username, $password, $email );

    if ( is_wp_error( $user_id ) ) {
        return;
    }

    $user = new WP_User( $user_id );
    $user->set_role( 'jobrecruiter' );

    $company_id = wp_insert_post( array(
        'post_title'  => $nama_perusahaan,
        'post_type'   => 'company',
        'post_status' => 'publish',
        'post_author' => $user_id,
    ) );

    if ( is_wp_error( $company_id ) || ! $company_id ) {
        wp_delete_user( $user_id );
        return;
    }

    update_post_meta( $company_id, 'nama-perusahaan', $nama_perusahaan );

    update_user_meta( $user_id, 'company-id', $company_id );
    update_user_meta( $user_id, 'company-link', get_permalink( $company_id ) );

    wp_set_current_user( $user_id );
    wp_set_auth_cookie( $user_id );

    wp_redirect( home_url( '/dashboard/' ) );
    exit;
}

/**
 * Hide Sidebar Menu
 */
function axl_dashboard_sidebar_toggle_script() {
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const toggleBtn = document.querySelector('.dashboard-toggle');
        const sidebar = document.querySelector('.dashboard-sidebar');

        if (!toggleBtn || !sidebar) return;

        toggleBtn.addEventListener('click', function () {
            sidebar.classList.toggle('is-hidden');
        });
    });
    </script>

    <style>
        .dashboard-sidebar {
            transition: all .25s ease;
        }

        .dashboard-sidebar.is-hidden {
            width: 0 !important;
            min-width: 0 !important;
            padding: 0 !important;
            overflow: hidden !important;
            opacity: 0;
        }

        .dashboard-sidebar.is-hidden * {
            display: none !important;
        }
    </style>
    <?php
}
add_action( 'wp_footer', 'axl_dashboard_sidebar_toggle_script' );
