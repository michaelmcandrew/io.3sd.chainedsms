{include file="CRM/common/crmeditable.tpl"}
<h3>Translation Cleaner</h3>

SMS Survey Responses need to be "Translated" into fields in CiviCRM.

After editing the input messages in the conversations here, they need to be translated for a second time.<br/><br/>
You can either translate an existing group or <a href="/civicrm/group/add?reset=1">create a new group</a> for translation. 
To translate a group, please click <a href="/civicrm/chainsms/translate">here</a>.<br/><br/>

Select your 'campaign' from the list:

<select id="selectCampaign">
    <option value="">- select -</option>
	{foreach from=$aDistinctCampaigns item=eachCampaign}
		<option {if $sCampaignName eq $eachCampaign}selected="selected"{/if} value="{$eachCampaign}">{$eachCampaign}</option>
	{/foreach}
</select>
<br/><br/>
You can optionally select a filter from the list: <select id="selectFilter">
	<option value='unset'>- select -</option>
	<!-- TODO REFACTOR for now we have hardcoded the error types here. This should be drawn from the data eventually. -->
	<option {if $sFilter eq 'Invalid reply to initial multiple choice question'}selected="selected"{/if} value='Invalid reply to initial multiple choice question'>Invalid reply to multiple choice</option>
	<option {if $sFilter eq 'Cannot find a contact in CiviCRM for this university'}selected="selected"{/if} value='Cannot find a contact in CiviCRM for this university'>Invalid university</option>
	<option {if $sFilter eq 'Could not split the uni reply into exactly one university and subject'}selected="selected"{/if} value='Could not split the uni reply into exactly one university and subject'>Invalid university reply</option>
	<option {if $sFilter eq 'Could not split the job reply into exactly one employer and job title'}selected="selected"{/if} value='Could not split the job reply into exactly one employer and job title'>Invalid work reply</option>
	<option {if $sFilter eq 'Could not determine the course'}selected="selected"{/if} value='Could not determine the course'>Invalid course</option>
	<option {if $sFilter eq 'Cannot find a contact in CiviCRM for this institution'}selected="selected"{/if} value='Cannot find a contact in CiviCRM for this institution'>Invalid institution</option>
	<option {if $sFilter eq 'Could not split the education reply into exactly one institution and course'}selected="selected"{/if} value='Could not split the education reply into exactly one institution and course'>Invalid education reply</option>
	<option {if $sFilter eq 'Could not determine the year group'}selected="selected"{/if} value='Could not determine the year group'>Invalid year group</option>
	<option {if $sFilter eq 'Rude word alert!'}selected="selected"{/if} value='Rude word alert!'>Rude words!</option>
</select>
<br/><br/>

{if isset($sCampaignName)}
<div>
{if isset($sErrorMsg)}
{$sErrorMsg}
{else}
{if $offset gt 0}<a href="/civicrm/chainsms/translationcleaning?campaign={$sCampaignName}&offset={$offset-1}&filter={$sFilter}">Previous contact</a>{/if}
{if $offset gt 0 && $offset lt ($iCountInvalidSMSConversations -1)} | {/if}
{if $offset lt ($iCountInvalidSMSConversations-1)}<a href="/civicrm/chainsms/translationcleaning?campaign={$sCampaignName}&offset={$offset+1}&filter={$sFilter}">Next contact</a> {/if} 
({$sPageNum} of {$iCountInvalidSMSConversations}).
<br/><br/>
<table>
<tr><th>Msg Type</th><th>Date & Time</th><th>Details</th><th>Options</th></tr>
<!-- first row is SMS Conversation -->
<tr id="activity-{$aSMSConversationActivity.id}" class="crm-entity">
<td><strong>SMS Conversation</strong></td>
<td>{$aSMSConversationActivity.activity_date_time}</td>
<td>{$aSMSConversationActivity.details}</td>
<td><a href="/civicrm/contact/view?reset=1&cid={$aSMSConversationActivity.source_contact_id}">Edit contact</a> | 
 Retranslate (coming soon) | <a href="#" class="delete_activity">Delete activity</a> | 
 <br/><a href="#" class="complete_activity">Mark activity as completed (only after editing contact)</a></td>
</tr>

<!-- next rows are the inbetween messages -->
{foreach from=$aSMSMessages item=eachMessage}
{ts}
<tr id="activity-{$eachMessage.id}" class="crm-entity">
	<td><strong>{$eachMessage.direction}</strong></td>
	<td>{$eachMessage.activity_date_time}</td>
	{if $eachMessage.direction eq "Inbound"}<td><span class="crmf-details crm-editable">{$eachMessage.details}</span></td>{/if}
	<!--{if $eachMessage.direction eq "Inbound"}<td><input type="text" size="120" value="{$eachMessage.details}"></input></td>{/if}-->
	{if $eachMessage.direction eq "Outbound"}<td>{$eachMessage.details}</td>{/if}
	<td><!--<a href="#" class="save_activity">Save activity</a> | --><a href="#" class="delete_activity">Delete activity</a></td>
</tr>
{/ts}
{/foreach}

<!-- last row is the Mass SMS that was sent -->
<tr>
<td><strong>Mass SMS</strong></td>
<td>{$aMassSMSActivity.activity_date_time}</td>
<td>{$aMassSMSActivity.details}</td>
<td></td>
</tr>
</table>
</div>

<div>
<strong>Group Membership </strong>(non-smart groups only):<br/>
{$sGroupMembership}
<br/>
<br/>
Add contact to group: 
<select id="addContactToGroup">
<option value ="">- select -</option>
{crmAPI var="GroupS" entity="Group" action="get" sequential="1" rowCount="0"}
{foreach from=$GroupS.values item=Group}
<option id="{$Group.id}">{$Group.title}</option>
{/foreach}
</select>
<span id="groupAddResult"></span>
</div>
{/if}
{/if}<!--if isset(sErrorMsg)-->
{include file="CRM/Chainsms/Page/ChainSMSTranslationCleaningJquery.tpl"}
