To prevent unpleasant replies to SMS surveys from entering your system in a way 
that could be seen by someone viewing your data, the SMS Survey allows you
to have your own list of words that you want to censor, for instance, swear words.
<br/><br/>
Replies that contain these words listed below will not be inserted into your 
contacts' data fields when the survey is translated.
<br/><br/>
You will be able to view their full reply, including offensive language, in the 
Translation Cleaner tool.
<br/><br/>
<div id='swearWords' style='display: none'>
<div>
  <span>{$form.safe_list.label}</span>
  <span>{$form.safe_list.html}</span>
</div>
<em>This list should contain words that are known to be safe but can trigger 
swear filters due to containing a word that looks like a swear word. Separate by 
commas.</em>
<br/>
<br/>
<div>
  <span>{$form.swear_list.label}</span>
  <span>{$form.swear_list.html}</span>
</div>
<em>This list should contain words that are offensive, separated by a comma.</em>
<br/>
<br/>
</div>

<button type="button" id='showSwears'>Show rude words.</button>
{literal}
<script type="text/javascript" language="javascript">
  jQuery('#showSwears').click(function(){
      jQuery('#swearWords').show();
      jQuery('#showSwears').remove();
  });
</script>
{/literal}
<br/>
<br/>
<div class="crm-submit-buttons">
{include file="CRM/common/formButtons.tpl" location="bottom"}
</div>
