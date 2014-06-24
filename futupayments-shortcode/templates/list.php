<h2><?php _e('Orders', 'futupayments'); ?></h2>
<table class="wp-list-table widefat fixed">
	<thead>
		<tr>
			<th></th>
			<th><?php _e('Date', 'futupayments'); ?></th>
			<th><?php _e('Amount', 'futupayments'); ?></th>
			<th><?php _e('Description', 'futupayments'); ?></th>
			<th><?php _e('Email', 'futupayments'); ?></th>
			<th><?php _e('Name', 'futupayments'); ?></th>
			<th><?php _e('Phone', 'futupayments'); ?></th>
			<th><?php _e('Status', 'futupayments'); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php foreach($rows as $row) { ?>
			<tr>
				<td><?php _e('Order #', 'futupayments'); ?><?php echo $row['id']; ?></td>
				<td><?php echo $row['creation_datetime']; ?></td>
				<td><?php echo $row['amount']; ?>&nbsp;<?php echo $row['currency']; ?></td>
				<td><?php echo $row['description']; ?></td>
				<td><?php echo $row['client_email']; ?></td>
				<td><?php echo $row['client_name']; ?></td>
				<td><?php echo $row['client_phone']; ?></td>
				<td><?php echo $statuses[$row['status']]; ?></td>
			</tr>
		<?php } ?>
	</tbody>
</table>

<p>
	<a href="<?php echo $_SERVER['REQUEST_URI'] . (count($_GET) > 0 ? '&' : '?') . 'limit=' . ($limit + $step); ?>"><?php _e('Show more', 'futupayments'); ?></a>
</p>
