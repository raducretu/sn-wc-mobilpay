jQuery(document).ready(function($){	
	$('#point_type').change(function(e) { console.log('change');
		var point_type = $(this).val();
		getBalanceUser(point_type);
	});
    //Theme MJ ko cho nhung user thuong dung ajax /wp-content/themes/MJ/framework/class/class_admin.php, vo day them 
    //if( !user_can($userdata->ID, 'administrator') &&  !user_can($userdata->ID, 'contributor') &&   !user_can($userdata->ID, 'editor')  ){
    //   wp_die(__('Oops! You do not have sufficient permissions to access this page.')); 
    // }
});

function getBalanceUser(point_type) {
    jQuery('#your-points').html("Loading..."); 
	//console.log('folder', folder);
    jQuery.ajax({
        type: 'POST',
        url: sn_payza_sendmoney_ajax.ajaxurl,
        data: {
            action: 'sn_get_balance_user',
            point_type: point_type
        },
        success: function(data, textStatus, XMLHttpRequest) {
        	jQuery('#your-points').html(data); 
            return false;
        },
        error: function(MLHttpRequest, textStatus, errorThrown) {
            jQuery('#your-points').html(0);
            alert(errorThrown);
        }
    });
}