<div class="wrap">
	<h2><?php _e( 'WP Plugin Packer Settings' ) ?></h2>

	<form method="post" id="wp_plugin_packer_form">
		<?php settings_fields( $this->wp_plugin_packer ) ?>
		<?php do_settings_sections( $this->wp_plugin_packer ) ?>
		<div class="add-pack"><input type="button" class="button" value="<?php _e( '+ Add Plugin Pack' ) ?>" /></div>
		<input type="hidden" name="plugin_packs" id="plugin_packs" />
		<div class="clear"></div>
		<div class="controls">
			<input class="button import-button" type="button" value="<?php _e( 'Import' ) ?>" />
			<input class="button export-all-button" type="button" value="<?php _e( 'Export All' ) ?>" />
			<input class="button export-button" type="button" value="<?php _e( 'Export Selection' ) ?>" />
			<input class="button disable-button" type="button" value="<?php _e( 'Deactivate Selection' ) ?>" />
			<input class="button enable-button" type="button" value="<?php _e( 'Activate Selection' ) ?>" />
		</div>
		<?php submit_button(); ?>
	</form>
</div>