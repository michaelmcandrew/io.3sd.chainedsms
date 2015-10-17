{literal}
<script type="text/javascript" language="javascript">
    
// set the messages to validate    
jQuery('#SMSMailing').change(function(){
	window.location.href = "/civicrm/chainsms/validator?mailingId=" + jQuery(this).children(':selected').attr('id');
});

// select a group to add the contacts to
jQuery('.groupAddSelect').change(function(){
    if(jQuery(this).children("option:selected").attr('group_id') == 0){
        jQuery('#' + jQuery(this).attr('button_id')).css('visibility', 'hidden');
    } else {
        jQuery('#' + jQuery(this).attr('button_id')).css('visibility', 'visible');
    }
});

// add contacts to a given group
jQuery('.groupAdd').click(function (){
    
    var button = jQuery(this);
    var selectId = jQuery(this).attr('select_id');
    var groupId = jQuery('#' + selectId).children("option:selected").attr('group_id');

    var contactsAddedCount = 0;

    jQuery('#' + jQuery(this).attr('section') + ' tbody').children('tr:gt(0)').each(function(){
        var contactId = jQuery(this).attr('contact_id');
 
        CRM.api('GroupContact', 'create', {'sequential': 1, 'group_id': groupId, 'contact_id': contactId},
        {success: function(data) {
            cj.each(data, function(key, value) {
            });
            contactsAddedCount++;
            jQuery('#' + button.attr('notification')).text('Successfully added ' + contactsAddedCount + ' contacts to group!');
          }
        }
        );    
    });

});
	
</script>
{/literal}
