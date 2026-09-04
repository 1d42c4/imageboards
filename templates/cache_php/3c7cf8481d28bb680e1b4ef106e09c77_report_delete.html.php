<?php
/* compiled: report_delete.html */
if (!isset($__ctx)) { $__ctx = get_defined_vars(); }
?><?php echo '<div id="post-moderation-fields">
	
	<div id="report-fields">
		<label for="reason">'; ?><?php echo _('Reason'); ?><?php echo '</label> 
		<input id="reason" type="text" name="reason" size="20" maxlength="30" />
		<input type="submit" name="report" value="'; ?><?php echo _('Report'); ?><?php echo '" />
	</div>
</div>'; ?>