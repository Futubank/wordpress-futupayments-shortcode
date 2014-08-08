<form action="<?php echo $url; ?>" method="post">
    <?php echo FutubankForm::array_to_hidden_fields($atts); ?>
    <?php foreach ($additional_fields as $name => $params) { ?>
        <?php if ($params['hidden']) { ?>
            <input name="<?php echo $name; ?>" type="hidden" value="">
        <?php } else { ?>
            <p>
                <label><?php echo $params['label']; ?></label><br>
                <input name="<?php echo $name; ?>" type="<?php echo $params['type']; ?>" required>&nbsp;<?php if ($name == 'client_amount') echo $atts['currency']; ?>
            </p>
        <?php } ?>
    <?php } ?>
    <p class="submit">
        <input type="submit" name="submit" class="button button-primary" value="<?php echo esc_attr($atts['button_text']); ?>">
    </p>
</form>
