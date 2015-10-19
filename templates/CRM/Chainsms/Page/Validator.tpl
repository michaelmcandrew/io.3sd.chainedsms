
Select group:<br/>
<select id="SMSMailing">
    <option id="">- select -</option>
    {foreach from=$aSMSMailings item=eachMailing}
        <option id="{$eachMailing.id}" {if $eachMailing.id eq $iMailingId}selected="selected"{/if}>{$eachMailing.name}</option>
    {/foreach}
</select>
<br/>

{if $iMailingId != 0}
    {include file="CRM/Chainsms/Page/ValidatorInvalidContacts.tpl" 
        id = "noMassSMS" 
        data = $aNoMassSMS 
        title = "Missing Mass SMS"
        title_stats = $aNoMassSMS_title
        error = "No missing Mass SMS issues found."}
        
    {include file="CRM/Chainsms/Page/ValidatorInvalidContacts.tpl" 
        id = "noFirstSMSDelivered" 
        data = $aNoFirstSMSDelivered 
        title = "Missing first SMS Delivery"
        title_stats = $aNoFirstSMSDelivered_title
        error = "No first SMS delivered issues found."}
        
    {include file="CRM/Chainsms/Page/ValidatorInvalidContacts.tpl"
        id = "noSecondSMS" 
        data = $aNoSecondSMS 
        title = "Missing second Outbound SMS" 
        title_stats = $aNoSecondSMS_title
        error = "No second Outbound SMS issues found."}
        
    {include file="CRM/Chainsms/Page/ValidatorInvalidContacts.tpl" 
        id = "missingDeliveredSMS" 
        data = $aMissingDeliveredSMS 
        title = "Missing Delivered SMS Activities" 
        title_stats = $aMissingDeliveredSMS_title
        error = "No missing Delivered SMS found."}
    
    {if !$aNoMassSMS}
    	<strong>No missing Mass SMS issues found.</strong><br/>
    {/if}
    {if !$aNoFirstSMSDelivered}
    	<strong>No first SMS delivered issues found.</strong><br/>
    {/if}
    {if !$aNoSecondSMS}
    	<strong>No second Outbound SMS issues found.</strong><br/>
    {/if}
    {if !$aMissingDeliveredSMS}
    	<strong>No missing Delivered SMS found.</strong><br/>
    {/if}
        
 {/if}



{include file="CRM/Chainsms/Page/ValidatorJquery.tpl"}
