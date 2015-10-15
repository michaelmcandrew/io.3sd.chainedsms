{literal}
<script type="text/javascript" language="javascript">

jQuery('.save_activity').click(function(){
	var sNewDetails = jQuery(this).parent().parent().find('input').val();
	
	var sActivityId = jQuery(this).parent().parent().attr('id');
  var iActivityId = parseInt(sActivityId.replace('activity-', ''));
	
	cj().crmAPI ('Activity','create',{ 'sequential' :'1', 'details' : sNewDetails, 'id' : iActivityId }
  		,{ success:function (data){    
		}
	});
	return false;
});
	
jQuery('.delete_activity').click(function(){
	var sActivityId = jQuery(this).parent().parent().attr('id');
	cj().crmAPI ('Activity','Delete',{ 'sequential' :'1', 'id' : sActivityId.replace("activity-", "") }
  		,{ success:function (data){    
  			
  			
		}
	});
	
	// TODO - make the following a condition of it being a success? why doesn't that work?'
	jQuery(this).parent().parent().find("td").fadeOut(1000, function(){
  		 jQuery(this).remove();
  	});
	return false;
});
	
jQuery('#selectCampaign').change(function(){
	window.location.href = "/civicrm/chainsms/translationcleaning?campaign=" + jQuery(this).val() + "&offset=0";
});	
	
jQuery('#selectFilter').change(function(){
	window.location.href = "/civicrm/chainsms/translationcleaning?campaign=" + jQuery('#selectCampaign').val() + "&offset=0&filter=" + jQuery(this).val();
});		
	
jQuery('#addContactToGroup').change(function(){
	var iGroupId = jQuery(this).children(":selected").attr("id");
	cj().crmAPI('GroupContact','create',{ 'sequential' :'1', 'group_id' : iGroupId, 'contact_id' : '{/literal}{$aSMSConversationActivity.source_contact_id}{literal}'}
		,{ success:function (data){
			jQuery('#groupAddResult').text('added successfully!');
    	}
	})
});	
	
jQuery('.complete_activity').click(function(){
	var sActivityId = jQuery(this).parent().parent().attr('id');
	cj().crmAPI ('Activity','update',{ 'sequential' :'1', 'id' : sActivityId.replace("activity-", ""), 'status_id' :'2'}
		,{ success:function (data){   
		}
	});
	
	// TODO why doesn't this work in the context of "success" above?
	jQuery(this).fadeOut();
	
	return false;
});	
	
</script>
{/literal}
