{literal}
<script type="text/javascript" language="javascript">
	jQuery('#SMSMailing').change(function (){
		jQuery('#GroupIds').val(jQuery(this).children(':selected').attr('group_ids'));
		groupIdsToGroupName();
		jQuery('#StartDate').val(jQuery(this).children(':selected').attr('send_date'));
		jQuery('#LimitDate').val(jQuery(this).children(':selected').attr('limit_date'));
		jQuery('#CampaignName').val(jQuery(this).children(':selected').attr('name'));
		setButtonVisibility();
	});
	
	jQuery('#TranslateButton').click(function(){
		jQuery('#TranslateResults').text(' working...');
		
		cj().crmAPI ('SMSSurveyTranslator','Translate',{ 
		  'sequential' :'1', 
		  'aGroups' : jQuery('#GroupIds').val(), 
		  'sStartDate' : jQuery('#StartDate').val(), 
		  'sLimitDate': jQuery('#LimitDate').val(), 
		  'sTranslatorClass' :jQuery('#TranslatorClass').val(), 
		  'sCampaignName' :jQuery('#CampaignName').val(),
		}, { success:function (data){
                        var activitiesCreatedOrUpdated;
                        jQuery.each(data['values'], function(x, activitiesCreatedOrUpdatedParams) {
                            activitiesCreatedOrUpdated = activitiesCreatedOrUpdatedParams['activitiesCreatedOrUpdated'];
                        });
			jQuery('#TranslateResults').text(' finished with ' + activitiesCreatedOrUpdated + ' activites created or updated.');
			}
		});
	});
	
	function fetchStats(){
		cj().crmAPI ('SMSSurveyStats','Get',{ 
		  'sequential' :'1'
		}, { success:function (data){
                  jQuery.each(data['values'], function(campaign, stats) {
                    totalTranslated = parseInt(stats['transfailed']) + parseInt(stats['transpassed']);
                    percentTranslated = (totalTranslated / stats['recipients']).toFixed(2) * 100;
                    percentPassed = (stats['transpassed'] / totalTranslated).toFixed(2) * 100;
                    jQuery('#statsTable').append(
                      "<tr><td>"  + stats['subject'] +
                      "</td><td>" + stats['date'] +
                      "</td><td>" + stats['recipients'] +
                      "</td><td>" + totalTranslated + " (" + percentTranslated + "%)" +
                      "</td><td>" + stats['transfailed'] +
                      "</td><td>" + stats['transpassed'] + " (" + percentPassed + "%)" +
                      "</td></tr>"
                    );
                  });
			}
		});
	}
	
	// handle the tabs
	jQuery('.selector').click(function(){
		if ((jQuery(this).attr('id') == 'StatsButton')){
                  // Is there only one row in the stats table? If so, go ahead and get the data.
                  if (jQuery('#statsTable').children('tbody').children('tr:not(#statsTableTitleRow)').length == 0) {
		    fetchStats();
                  }
		}
		
		jQuery('.Block').not('#'+jQuery(this).attr('selected')).hide();
		jQuery('#'+jQuery(this).attr('selected')).show();
	});
	
	// connect the Group Name selector to the Group Id text field
	jQuery('#GroupName').change(function(){
		jQuery('#GroupIds').val(jQuery(this).children(':selected').attr('group_id'));
	});
	
	function setGroupNameFromGroupId(selector, groupId){
		returnValue = false;
	
		jQuery(selector).children('option').each(function(){
			if (jQuery(this).attr('group_id') == groupId){
				jQuery(this).attr('selected', 'selected');
				returnValue = true;
			}
		});
		return returnValue;
	}
	
	// manifest changes in the Group Ids to the Group Name selector
	function groupIdsToGroupName(){
		if(jQuery('#GroupIds').val() == ""){
			// if it's blank, set the first option to be selected
			jQuery("#GroupName").children('option:eq(0)').attr('selected','selected');
		} else if(jQuery('#GroupIds').val().indexOf(',') >= 0){
			// if it contains a comma, set the multiple option to be selected
			jQuery('#GroupName').children('option').each(function (){
				if (jQuery(this).attr('group_id') == "multiple"){
					jQuery(this).attr('selected','selected');
				}
			});
		} else if (setGroupNameFromGroupId('#GroupName', jQuery('#GroupIds').val())){
			// nothing to do here - set as a side effect in setGroupNameFromGroupId
		} else {
			// if it contains a comma, set the multiple option to be selected
			jQuery('#GroupName').children('option').each(function (){
				if (jQuery(this).attr('group_id') == "unknown"){
					jQuery(this).attr('selected','selected');
				}
			});
		}
	};
	
	// connect changes in the Group Ids to the Group Name selector
	jQuery('#GroupIds').change(groupIdsToGroupName);

        // set the description of the translator class
    	jQuery('#TranslatorClass').change(function(){
                var descriptionText = jQuery('option:selected', this).attr('description');
                jQuery('#TranslatorDescription').text(descriptionText);
        });

	// perform button visible/invisible checks
	function setButtonVisibility(){
		iNumEmptyFields = 0;
		
		jQuery('.inputField').each(function (){
			if(jQuery(this).val() == ""){
				iNumEmptyFields++;
			}
		});
		
		if (iNumEmptyFields == 0){
			jQuery('#TranslateButton').fadeIn();
		} else {
			jQuery('#TranslateButton').fadeOut();
		}
		
	}
	
	jQuery('.inputField').change(setButtonVisibility);
</script>
{/literal}