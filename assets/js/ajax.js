jQuery(document).ready(function (){

  jQuery(document).on( "change", '.tax_tax_tax', function( evt ) {

    var id = jQuery(this).attr('data-id');
    var taxonomy = jQuery(this).attr('data-taxonomy');

    var tax_tax_tax = [];
    jQuery('.tax_tax_tax').each(function() {
        tax_tax_tax.push( jQuery(this).val());
    });

    jQuery('input[name="' + id + '"]').val(tax_tax_tax);

    jQuery.ajax({
        type: 'POST',
        url: ajaxurl,
        data: {"action": "bf_afe_fields_group_create_frontend_form_element_ajax", "data": tax_tax_tax, 'id': id, 'taxonomy': taxonomy},
        beforeSend :function(){

        },
        success: function(data){
          if(data != 'false')
            jQuery('#taxtax_container').html(data);
            //jQuery('input[name="' + id + '"]').val(tax_tax_tax);
        },
        error: function (request, status, error) {
            alert(request.responseText);
        }
    });

  });
});
