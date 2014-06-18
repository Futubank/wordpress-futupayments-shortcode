<?php
class rksk_Widget extends WP_Widget {

	public function __construct() {
		// widget actual processes
		parent::__construct(
	 		'rksk_widget', // Base ID
			'Robokassa', // Name
			array( 'description' => __( 'Robokassa shortcode widget', 'rksc' ),'id'=>'rksc' ) // Args
		);		
	}

 	public function form( $instance ) {
		if ( isset( $instance[ 'title' ] ) ) {
			$title = $instance[ 'title' ];
		}
		else {
			$title = __( 'New title', 'rksc' );
		}
		$SKU=(isset($instance['SKU']))?$instance['SKU']:'';
		$description=(isset($instance['Description']))?$instance['Description']:'';
		$price=(isset($instance['Price']))?$instance['Price']:'';
		?>
		<p>
		<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:' ); ?></label>

		<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
		</p>
		<p>
		<label for="<?php echo $this->get_field_id( 'SKU' ); ?>"><?php _e( 'SKU:' ); ?></label>
		<input class="widefat" id="<?php echo $this->get_field_id( 'SKU' ); ?>" name="<?php echo $this->get_field_name( 'SKU' ); ?>" type="text" value="<?php echo esc_attr( $SKU ); ?>" />
		</p>
		<p>
		<label for="<?php echo $this->get_field_id( 'Description' ); ?>"><?php _e( 'Description:' ); ?></label>
		<textarea class="widefat" id="<?php echo $this->get_field_id( 'Description' ); ?>" name="<?php echo $this->get_field_name( 'Description' ); ?>"><?php echo esc_attr( $description ); ?></textarea>
		</p>
		<p>
		<label for="<?php echo $this->get_field_id( 'Price' ); ?>"><?php _e( 'Price:' ); ?></label>
		<input class="widefat" id="<?php echo $this->get_field_id( 'Price' ); ?>" name="<?php echo $this->get_field_name( 'Price' ); ?>" type="text" value="<?php echo esc_attr( $price ); ?>" />
		</p>

		<?php
	}

	public function update( $new_instance, $old_instance ) {
		// processes widget options to be saved
			$instance = array();
			$instance['title'] = strip_tags( $new_instance['title'] );
			$instance['SKU'] = strip_tags( $new_instance['SKU'] );
			$instance['Description'] = strip_tags( $new_instance['Description'] );
			$instance['Price'] = strip_tags( $new_instance['Price'] );

			return $instance;	
	}

	public function widget( $args, $instance ) {
 $form="{$args['before_widget']}{$args['before_title']}{$instance['title']}{$args['after_title']}";
 $form.='<form id="rkform" action="/rksc/send.php" method="post">';
 $form.="<p>{$instance['Description']}</p>";
 $form.=__('Name','rksc').':<input name="shpfirstname"><br />';
 $form.=__('email','rksc').':<input name="shpemail"><br />';
 if(!isset($instance['Price']) || $instance['Price']=='')
 {
 	$form.=__('Price','rksc').':<input name="price"><br />';
 }
 $hash='';
 foreach($instance as $key=>$value)
 {
  if($value!='')
  $form.="<input type='hidden' name='a_".strtolower($key)."' value='{$value}'>";
 }

 $hash=md5(implode(':',$instance).":".$options['rksc_sitepass']);
 $form.="<input type='hidden' name='hash' value='{$hash}'>";
 $form.="<input type='submit' value='Pay'>";
 $form.="</form>{$args['after_widget']}";
 echo $form;
	}

}
add_action( 'widgets_init', create_function( '', 'register_widget( "rksk_Widget" );' ) );
//register_widget('rksk_Widget');
?>