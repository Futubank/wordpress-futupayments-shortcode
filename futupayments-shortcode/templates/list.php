<h2><?php _e('Orders'); ?></h2>
<table class="wp-list-table widefat fixed">
	<thead>
		<tr>
			<th></th>
			<th><?php _e('Date'); ?></th>
			<th><?php _e('Amount'); ?></th>
			<th><?php _e('Description'); ?></th>
			<th><?php _e('Email'); ?></th>
			<th><?php _e('Name'); ?></th>
			<th><?php _e('Phone'); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php foreach($rows as $row) { ?>
			<tr>
				<td><?php _e('Order #'); ?><?php echo $row['id']; ?></td>
				<td><?php echo $row['creation_datetime']; ?></td>
				<td><?php echo $row['amount']; ?>&nbsp;<?php echo $row['currency']; ?></td>
				<td><?php echo $row['description']; ?></td>
				<td><?php echo $row['client_email']; ?></td>
				<td><?php echo $row['client_name']; ?></td>
				<td><?php echo $row['client_phone']; ?></td>
			</tr>
		<?php } ?>
	</tbody>
</table>
