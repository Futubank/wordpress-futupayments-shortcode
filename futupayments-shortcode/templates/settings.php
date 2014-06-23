<form action="options.php" method="post">
    <?php settings_fields($group); ?>
    <?php do_settings_sections($slug); ?>
    <p>
    	<strong><?php _e('Callback URL'); ?>:</strong> <?php echo $callback_url; ?>
    </p>
    <p class="submit">
        <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php _e('Save Changes'); ?>">
    </p>
</form>
