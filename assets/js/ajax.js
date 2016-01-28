jQuery(document).ready(function (){

  jQuery(document).on( "change", '.tax_tax_tax', function( evt ) {
    var data = jQuery(this).val();

    jQuery.ajax({
        type: 'POST',
        url: ajaxurl,
        data: {"action": "bf_afe_fields_group_create_frontend_form_element_ajax", "data": data},
        beforeSend :function(){

        },
        success: function(data){
          if(data != 'false')
            jQuery('#taxtax_container').html(data);
        },
        error: function (request, status, error) {
            alert(request.responseText);
        }
    });

  });
});
