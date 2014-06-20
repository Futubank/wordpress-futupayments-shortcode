<form action="options.php" method="post">
    <?php settings_fields($group); ?>
    <?php do_settings_sections($slug); ?>
    <p class="submit">
        <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php _e('Save Changes'); ?>">
    </p>
</form>
