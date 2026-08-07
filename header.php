<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title><?php wp_title('|', true, 'right'); bloginfo('name'); ?></title>
    <meta content="Talos 2.0 ERP" name="description" />
    <meta content="Once24" name="author" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />

    <!-- Favicon dinámico -->
    <link rel="shortcut icon" href="<?php echo get_template_directory_uri(); ?>/assets/images/favicon.ico">
    
    <!-- El Gancho Maestro de WP para inyectar CSS -->
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
    
    <div class="sidenav shadow">
        <div class="text-center pt-0 pt-lg-3 pb-0 pb-lg-3">
            <div class="d-none d-lg-inline-block">
                <a class="logo text-uppercase" href="<?php echo esc_url( home_url( '/' ) ); ?>">
                    <img src="<?php echo get_template_directory_uri(); ?>/assets/images/logo.png" alt="logo" height="26">
                </a>
            </div>	
        </div>	
        
        <!-- NAVBAR LATERAL -->
        <nav class="navbar navbar-expand-lg navbar-custom text-white sticky">
            <div class="container">
                <a class="mobile-re text-uppercase d-inline-block d-lg-none" href="<?php echo esc_url( home_url( '/' ) ); ?>">
                    <img src="<?php echo get_template_directory_uri(); ?>/assets/images/logo.png" alt="logo" height="20">
                </a>
                <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="mdi mdi-menu"></span>
                </button>

                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav nav flex-column">
                        <li class="nav-item active">
                            <a class="nav-link" href="#Introduction"><i class="mdi mdi-account-circle-outline mr-2"></i>Dashboard</a>
                        </li>
                        <!-- Aquí inyectaremos los demás módulos CPT después -->
                    </ul>
                </div>
            </div>
        </nav>
    </div>

    <!-- CONTENEDOR PRINCIPAL -->
    <div class="main">
        <nav class="navbar navbar-default navbar-fixed-top">
            <div class="justify-content-center mx-auto">
                <div class="navbar-header">
                    <a class="navbar-brand" href="#">
                        <span class="text-dark font-weight-bold">Talos 2.0</span>
                        <small class="text-dark font-weight-semibold">- ERP Admin</small>
                    </a>
                </div>
                <div class="collapse navbar-collapse" id="bs-example-navbar-collapse-1">
                </div>
            </div>
        </nav>