
{if $data != 0}
<br/>
<h3>{$title} ( {$title_stats.failedCount} / {$title_stats.totalCount} ):</h3>
<table id="{$id}_contactsData">
    <tr contact_id="0"><th>Id</th><th>Name</th><th>Actions</th></tr>
    {foreach from=$data item=eachRecord}
        {ts}<tr contact_id="{$eachRecord}">
            <td>{$eachRecord}</td>
            <td>
                {crmAPI var='result' entity='Contact' action='getvalue' sequential=1 id=$eachRecord return='display_name'}
                {$result}
            </td>
            <td><a href="/civicrm/contact/view?reset=1&cid={$eachRecord}">View contact</a></td>
        </tr>{/ts}
    {/foreach}
</table>

Add contacts to group: <select id="{$id}_selectGroup" button_id="{$id}_groupAddButton" class="groupAddSelect">
    <option group_id="0">- select -</option>
{crmAPI var='result' entity='Group' action='get' sequential=1 rowCount=0}
{foreach from=$result.values item=Group}
  <option group_id={$Group.id}>{$Group.title}</option>
{/foreach}
</select>

<button type="button" id="{$id}_groupAddButton" section="{$id}_contactsData" class="groupAdd" 
            select_id="{$id}_selectGroup" notification="{$id}_notification" style="visibility:hidden">Add contacts</button>
            
<p id="{$id}_notification"></p>
{/if}
<br/>
