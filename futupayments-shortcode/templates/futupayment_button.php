<form action="<?php echo $url; ?>" method="post">
	<?php echo FutubankForm::array_to_hidden_fields($atts); ?>
    <p class="submit">
    	<input type="submit" name="submit" class="button button-primary" value="<?php echo esc_attr($options['pay_button_text']); ?>">
    </p>
</form>
