<form action="options.php" method="post">
    <?php do_settings_sections($slug); ?>
    <p class="submit">
    <table class="form-table">
    	<tbody>
    		<tr>
	    		<th scope="row"><?php _e('Callback URL'); ?></th>
	    		<td><?php echo $callback_url; ?></td>
			</tr>
    	</tbody>
	</table>
    <?php settings_fields($group); ?>
        <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php _e('Save Changes'); ?>">
    </p>
</form>