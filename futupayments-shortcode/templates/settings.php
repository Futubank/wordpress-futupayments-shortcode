<form action="options.php" method="post">
    <?php do_settings_sections($slug); ?>
    <?php settings_fields($group); ?>
    <table class="form-table">
        <tbody>
            <tr>
                <th scope="row"><?php _e('Callback URL'); ?></th>
                <td><?php echo $callback_url; ?></td>
            </tr>
        </tbody>
    </table>
    <p class="submit">
        <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php _e('Save Changes'); ?>">
    </p>
</form>