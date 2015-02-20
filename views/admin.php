<div class="wrap">
    <h2>Horizons Redirector</h2>

    <form action="<?php echo admin_url( 'options.php' ); ?>" method="post">
        <?php settings_fields( 'horizons_switcher_admin_page' ); ?>
            <?php do_settings_sections( 'horizons_switcher_admin_page' ); ?>
        <?php submit_button(); ?>
    </form>
</div> <!-- .wrap -->
