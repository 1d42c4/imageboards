<?php
/* compiled: post_form.html */
if (!isset($__ctx)) { $__ctx = get_defined_vars(); }
?><?php echo '<form name="post" onsubmit="return doPost(this);" enctype="multipart/form-data" action="'; ?><?php echo view_get($__ctx, 'config.post_url'); ?><?php echo '" method="post">
'; ?><?php if (view_get($__ctx, 'id')): ?><?php echo '<input type="hidden" name="thread" value="'; ?><?php echo view_get($__ctx, 'id'); ?><?php echo '">'; ?><?php endif; ?><?php echo '
<input type="hidden" name="board" value="'; ?><?php echo view_get($__ctx, 'board.uri'); ?><?php echo '">
'; ?><?php if (view_get($__ctx, 'current_page')): ?><?php echo '
	<input type="hidden" name="page" value="'; ?><?php echo view_get($__ctx, 'current_page'); ?><?php echo '">
'; ?><?php endif; ?><?php echo '
'; ?><?php if (view_get($__ctx, 'mod')): ?><?php echo '<input type="hidden" name="mod" value="1">'; ?><?php endif; ?><?php echo '
	<table>
		'; ?><?php if (!((view_get($__ctx, 'config.field_disable_name')) || ((view_get($__ctx, 'mod')) && (view_filter(view_get($__ctx, 'post.mod'), 'hasPermission', [view_get($__ctx, 'config.mod.bypass_field_disable'), view_get($__ctx, 'board.uri')]))))): ?><?php echo '<tr>
			<th>
				'; ?><?php echo _('Name'); ?><?php echo '
			</th>
			<td>
				<input type="text" name="name" size="25" maxlength="35" autocomplete="off"> '; ?><?php if ((view_get($__ctx, 'config.allow_no_country')) && (view_get($__ctx, 'config.country_flags'))): ?><?php echo '<input id="no_country" name="no_country" type="checkbox"> <label for="no_country">'; ?><?php echo _('Don\'t show my flag'); ?><?php echo '</label>'; ?><?php endif; ?><?php echo '
			</td>
		</tr>'; ?><?php endif; ?><?php echo '
		'; ?><?php if (!(((view_get($__ctx, 'config.field_disable_subject')) || ((view_get($__ctx, 'id')) && (view_get($__ctx, 'config.field_disable_reply_subject')))) || ((view_get($__ctx, 'mod')) && (view_filter(view_get($__ctx, 'post.mod'), 'hasPermission', [view_get($__ctx, 'config.mod.bypass_field_disable'), view_get($__ctx, 'board.uri')]))))): ?><?php echo '<tr>
			<th>
				'; ?><?php echo _('Subject'); ?><?php echo '
			</th>
			<td>
				<input style="float:left;" type="text" name="subject" size="25" maxlength="100" autocomplete="off">
				<input accesskey="s" style="margin-left:2px;" type="submit" name="post" value="'; ?><?php if (view_get($__ctx, 'id')): ?><?php echo view_get($__ctx, 'config.button_reply'); ?><?php else: ?><?php echo view_get($__ctx, 'config.button_newtopic'); ?><?php endif; ?><?php echo '" />'; ?><?php if (view_get($__ctx, 'config.spoiler_images')): ?><?php echo ' <input id="spoiler" name="spoiler" type="checkbox"> <label for="spoiler">'; ?><?php echo _('Spoiler Image'); ?><?php echo '</label>'; ?><?php endif; ?><?php echo '
			</td>
		</tr>
		'; ?><?php endif; ?><?php echo '
		<tr>
			<th>
				'; ?><?php echo _('Comment'); ?><?php echo '
			</th>
			<td>
				<textarea name="body" id="body" rows="5" cols="35"></textarea>
				'; ?><?php if ((view_get($__ctx, 'config.field_disable_subject')) || ((view_get($__ctx, 'id')) && (view_get($__ctx, 'config.field_disable_reply_subject')))): ?><?php echo '
				<input accesskey="s" style="margin-left:2px;" type="submit" name="post" value="'; ?><?php if (view_get($__ctx, 'id')): ?><?php echo view_get($__ctx, 'config.button_reply'); ?><?php else: ?><?php echo view_get($__ctx, 'config.button_newtopic'); ?><?php endif; ?><?php echo '" />'; ?><?php if (view_get($__ctx, 'config.spoiler_images')): ?><?php echo ' <input id="spoiler" name="spoiler" type="checkbox"> <label for="spoiler">'; ?><?php echo _('Spoiler Image'); ?><?php echo '</label>'; ?><?php endif; ?><?php echo '
				'; ?><?php endif; ?><?php echo '
			</td>
		</tr>
		'; ?><?php if (view_get($__ctx, 'config.captcha.provider') == 'recaptcha'): ?><?php echo '
		'; ?><?php if (view_get($__ctx, 'config.captcha.dynamic')): ?><?php echo '
		<tr id="captcha" style="display: none;">
		'; ?><?php else: ?><?php echo '
		<tr>
		'; ?><?php endif; ?><?php echo '
			<th>
				'; ?><?php echo _('Verification'); ?><?php echo '
			</th>
			<td>
				<div class="g-recaptcha" data-sitekey="'; ?><?php echo view_get($__ctx, 'config.captcha.recaptcha.sitekey'); ?><?php echo '"></div>
			</td>
		</tr>
		'; ?><?php endif; ?><?php echo '
		'; ?><?php if (view_get($__ctx, 'config.captcha.provider') == 'hcaptcha'): ?><?php echo '
		<tr>
			<th>
				'; ?><?php echo _('Verification'); ?><?php echo '
			</th>
			<td>
				<div class="h-captcha" data-sitekey="'; ?><?php echo view_get($__ctx, 'config.captcha.hcaptcha.sitekey'); ?><?php echo '"></div>
			</td>
		</tr>
		'; ?><?php endif; ?><?php echo '
		'; ?><?php if (view_get($__ctx, 'config.captcha.provider') == 'native'): ?><?php echo '
		<tr class=\'captcha\'>
			<th>
				'; ?><?php echo _('Verification'); ?><?php echo '
			</th>
			<td>
				<script>load_captcha("'; ?><?php echo view_get($__ctx, 'config.captcha.native.provider_get'); ?><?php echo '", "'; ?><?php echo view_get($__ctx, 'config.captcha.native.extra'); ?><?php echo '");</script>
				<noscript>
					<input class=\'captcha_text\' type=\'text\' name=\'captcha_text\' size=\'32\' maxlength=\'6\' autocomplete=\'off\'>
					<div class="captcha_html">
						<img src="/'; ?><?php echo view_get($__ctx, 'config.captcha.native.provider_get'); ?><?php echo '?mode=get&raw=1">
					</div>
				</noscript>
			</td>
		</tr>
		'; ?><?php elseif (view_get($__ctx, 'config.captcha.native.new_thread_capt')): ?><?php echo '
			'; ?><?php if (!(view_get($__ctx, 'id'))): ?><?php echo '
 			<tr class=\'captcha\'>
                        <th>
                                '; ?><?php echo _('Verification'); ?><?php echo '
                        </th>
                        <td>
                                <script>load_captcha("'; ?><?php echo view_get($__ctx, 'config.captcha.native.provider_get'); ?><?php echo '", "'; ?><?php echo view_get($__ctx, 'config.captcha.native.extra'); ?><?php echo '");</script>
				<noscript>
					<input class=\'captcha_text\' type=\'text\' name=\'captcha_text\' size=\'32\' maxlength=\'6\' autocomplete=\'off\'>
					<div class="captcha_html">
						<img src="/'; ?><?php echo view_get($__ctx, 'config.captcha.native.provider_get'); ?><?php echo '?mode=get&raw=1">
					</div>
				</noscript>
                        </td>
                	</tr>
			'; ?><?php endif; ?><?php echo '
		'; ?><?php endif; ?><?php echo '
		<tr id="upload">
			<th>
				'; ?><?php echo _('File'); ?><?php echo '
			</th>
			<td>
				<input type="file" name="file" id="upload_file">
				<script type="text/javascript">if (typeof init_file_selector !== \'undefined\') init_file_selector('; ?><?php echo view_get($__ctx, 'config.max_images'); ?><?php echo ');</script>
			</td>
		</tr>
		'; ?><?php if ((view_get($__ctx, 'mod')) && ((!((view_get($__ctx, 'id')) && (view_filter(view_get($__ctx, 'post.mod'), 'hasPermission', [view_get($__ctx, 'config.mod.sticky'), view_get($__ctx, 'board.uri')])))) || (!((view_get($__ctx, 'id')) && (view_filter(view_get($__ctx, 'post.mod'), 'hasPermission', [view_get($__ctx, 'config.mod.lock'), view_get($__ctx, 'board.uri')])))) || (view_filter(view_get($__ctx, 'post.mod'), 'hasPermission', [view_get($__ctx, 'config.mod.rawhtml'), view_get($__ctx, 'board.uri')])))): ?><?php echo '
		<tr>
			<th>
				'; ?><?php echo _('Flags'); ?><?php echo '
			</th>
			<td>
				'; ?><?php if (!((view_get($__ctx, 'id')) && (view_filter(view_get($__ctx, 'post.mod'), 'hasPermission', [view_get($__ctx, 'config.mod.sticky'), view_get($__ctx, 'board.uri')])))): ?><?php echo '<div class="center">
					<label for="sticky">'; ?><?php echo _('Sticky'); ?><?php echo '</label>
					<input title="'; ?><?php echo _('Sticky'); ?><?php echo '" type="checkbox" name="sticky" id="sticky"><br>
				</div>'; ?><?php endif; ?><?php echo '
				'; ?><?php if (!((view_get($__ctx, 'id')) && (view_filter(view_get($__ctx, 'post.mod'), 'hasPermission', [view_get($__ctx, 'config.mod.lock'), view_get($__ctx, 'board.uri')])))): ?><?php echo '<div class="center">
					<label for="lock">'; ?><?php echo _('Lock'); ?><?php echo '</label><br>
					<input title="'; ?><?php echo _('Lock'); ?><?php echo '" type="checkbox" name="lock" id="lock">
				</div>'; ?><?php endif; ?><?php echo '
				'; ?><?php if (view_filter(view_get($__ctx, 'post.mod'), 'hasPermission', [view_get($__ctx, 'config.mod.rawhtml'), view_get($__ctx, 'board.uri')])): ?><?php echo '<div class="center">
					<label for="raw">'; ?><?php echo _('Raw HTML'); ?><?php echo '</label><br>
					<input title="'; ?><?php echo _('Raw HTML'); ?><?php echo '" type="checkbox" name="raw" id="raw">
				</div>'; ?><?php endif; ?><?php echo '
			</td>
		</tr>
		'; ?><?php endif; ?><?php echo '
		'; ?><?php if ((view_get($__ctx, 'board.post_password')) && (!(view_get($__ctx, 'mod')))): ?><?php echo '
		<tr>
			<th>
				'; ?><?php echo _('Board password'); ?><?php echo '
			</th>
			<td>
				<input type="password" name="board_password" value="" size="18" maxlength="128" autocomplete="current-password" required>
				<span class="unimportant">'; ?><?php echo _('(Required to post on this board.)'); ?><?php echo '</span>
			</td>
		</tr>
		'; ?><?php endif; ?><?php echo '
		'; ?><?php if ((view_get($__ctx, 'board.require_approval')) && (!(view_get($__ctx, 'mod')))): ?><?php echo '
		<tr>
			<td colspan="2" class="unimportant" style="text-align:center;padding:4px 0;">
				'; ?><?php echo _('Posts on this board are reviewed by moderators before they appear.'); ?><?php echo '
			</td>
		</tr>
		'; ?><?php endif; ?><?php echo '
	</table>
</form>

<script type="text/javascript">'; ?><?php echo '
	rememberStuff();
'; ?><?php /* unknown tag: endverbatim */ ?><?php echo '</script>
'; ?>