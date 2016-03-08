After users have texted back their responses to SMS Surveys, these need to be "translated" from their text messages into fields in our database.
<br><br>
Conversations which have "scheduled" status, where a new Inbound SMS message has been received, or where no translations have been made previously will be re-translated.
<br><br>
<span class="selected selector" selected="TranslateBlock"><a>Translate SMS Surveys</a></span>
 | <span class="selector" selected="StatsBlock" id="StatsButton"><a>View Translation Statistics</a></span>
<br><br>

<div class="Block" id="TranslateBlock">
To translate a batch of messages, select an SMS Mailing from the following list<br><br>
<select style="margin-left: 50px" id="SMSMailing">
	<option id="">- select -</option>
	{foreach from=$aSMSMailings item=eachMailing}
		<option id="{$eachMailing.id}" title="{$eachMailing.title}" send_date="{$eachMailing.send_date}" limit_date="{$eachMailing.limit_date}" group_ids="{$eachMailing.group_ids}" name="{$eachMailing.name}">{$eachMailing.name}</option>
	{/foreach}
</select>
<br><br>and/or enter your own details below:

<br/><br/>
<table style="padding-left: 50px; padding-right: 50px;">
<tr>
    <td>Group Name</td><td>
        <select id="GroupName" >  
            <option group_id="">- select -</option>
                    {foreach from=$aGroups item=eachGroup}
                            <option group_id="{$eachGroup.id}" >{$eachGroup.title}</option>
                    {/foreach}
            <option group_id="multiple">- multiple groups -</option>
            <option group_id="unknown">- unknown group -</option>
	</select>
    </td>
    <td style="width: 50%"><em>This is only used to set the Group Ids in the field below.</em></td>
</tr>
<tr><td>Group Ids</td><td><input id="GroupIds" type="text" size="50" class="inputField"></input></td><td><em>List of group ids separated by a comma e.g. 123,456.</em></td></tr>
<tr><td>Translating Start Date</td><td><input id="StartDate" type="text" size="50" class="inputField"></input></td><td><em>No processing before this day. e.g. 2012-11-25.</em></td></tr>
<tr><td>Translating Limit Date</td><td><input id="LimitDate" type="text" size="50" class="inputField"></input></td><td><em>No processing after this day - keep this as tight as possible! e.g. 2012-11-27.</em></td></tr>

<tr>
    <td>TranslationTypes</td>
    <td>
        <select id="TranslatorClass" class="inputField">  
            <option group_id="">- select -</option>
            {foreach from=$aTranslationOptions item=eachTranslationOption}
                    <option value="{$eachTranslationOption.value}" description='{$eachTranslationOption.description}'>{$eachTranslationOption.name}</option>
            {/foreach}
        </select>
    </td>
    <td id='TranslatorDescription'></td>
</tr>
<!--<tr><td>Translator Class</td><td><input id="TranslatorClass" type="text" size="50" class="inputField" value="CRM_Chainsms_Translator_FFNov12"></input></td><td><em>If in doubt leave CRM_Chainsms_Translator_FFNov12 as the default.</em></td></tr>-->
<tr><td>Campaign Name</td><td><input id="CampaignName" type="text" size="50" class="inputField"></input></td><td><em>This appears as the finished SMS Conversation's Subject e.g. 2013 TS1b Y11.<br/>Campaigns are how statistics are determined and cleaning is performed.</em></td></tr>
</table>

<input id="TranslateButton" type="button" style="display: none;" value="Translate!"></button><span id="TranslateResults"></span>
</div>

<div class="Block" id="StatsBlock" style="display:none">
<table id="statsTable">
<tr id='statsTableTitleRow'><th>Campaign</th><th>Date</th><th>Number Sent</th><th>Total translated</th><th>Couldn't be<br/>fully translated.</th><th>Fully translated</th></tr>

</table>
</div>

{include file="CRM/Chainsms/Page/ChainSMSTranslateJquery.tpl"}