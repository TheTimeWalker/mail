/* global Handlebars */
var Mail = {
    State:{
        currentFolderId:null,
        currentAccountId:null,
        currentMessageId:null
    },
    UI:{
        initializeInterface:function () {
            /* 1. Load folder list,
             * 2. Display it
             * 3. If an account with folders exists
             * 4.   Load message list
             * 5.   Display message list
             */

			$.ajax(OC.generateUrl('apps/mail/accounts'), {
				data:{},
				type:'GET',
				success:function (jsondata) {
					_.each(jsondata, function(account){
						var accountId = account.accountId;
						Mail.UI.loadFoldersForAccount(accountId);
					});
				}
			});
        },

		loadFoldersForAccount : function(accountId) {
			var folders, firstFolder, folderId;

			$.ajax(OC.generateUrl('apps/mail/accounts/{accountId}/folders', {accountId: accountId}), {
				data:{},
				type:'GET',
				success:function (jsondata) {
					var source   = $("#mail-folder-template").html();
					var template = Handlebars.compile(source);
					var html = template(jsondata);

					$('#app-navigation').html(html);

					firstFolder = $('#app-navigation').find('.mail_folders li');

					if (firstFolder.length > 0) {
						$('#app-navigation').fadeIn(800);
						firstFolder = firstFolder.first();
						folderId = firstFolder.data('folder_id');
						accountId = firstFolder.parent().data('account_id');

						Mail.UI.loadMessages(accountId, folderId);

						// Save current folder
						Mail.UI.setFolderActive(accountId, folderId);
						Mail.State.currentAccountId = accountId;
						Mail.State.currentFolderId = folderId;
					} else {
						$('#app-navigation').fadeOut(800);
					}
				}
			});
		},

        clearMessages:function () {
            var table = $('#mail_messages');
            var template = table.find('tr.template').clone();
            var templateLoading = table.find('tr.template_loading').clone();

            table.empty();
            table.append(template);
            table.append(templateLoading);
			$('#messages-loading').fadeIn();
        },

        addMessages:function (data) {
            var table = $('#mail_messages');
            var template = table.find('tr.template').clone();
            var loadingTemplate = table.find('tr.template_loading').clone();
            var messages = data.messages;

            //table.date('');
            for (var i in messages) {
                var message = messages[i];
                var clone = template.clone();
                clone.removeClass('template');

                clone.data('message_id', message.id);
                clone.attr('data-message-id', message.id);
                if (message.flags['unseen']) {
                    clone.addClass('unseen');
                }
                clone.find('.mail_message_summary_from').text(message.from);
                clone.find('.mail_message_summary_subject').text(message.subject);

				var lastModified = new Date(message.dateInt*1000);
				var lastModifiedTime = Math.round(lastModified.getTime() / 1000);

				// date column
				var modifiedColor = Math.round((Math.round((new Date()).getTime() / 1000)-lastModifiedTime)/60/60/24*5);
				if (modifiedColor > 200) {
					modifiedColor = 200;
				}

				var td = $('<td></td>').attr({ "class": "date" });
				td.append($('<span></span>').attr({
					"class": "modified",
					"title": formatDate(lastModified),
					"style": 'color:rgb('+modifiedColor+','+modifiedColor+','+modifiedColor+')'
				}).text( relative_modified_date(lastModifiedTime) ));

				// delete icon
				td.append($('<a></a>').attr({
					"class": "action delete",
					"original-title": t('mail', 'Delete message')
				}).append($('<img class="svg" src="'+OC.imagePath('core', 'actions/delete')+'">')));
				clone.append(td);

                table.append(clone);

                // add loading row
                var loadingClone = loadingTemplate.clone();
                loadingClone.removeClass('template_loading');
                loadingClone.attr('data-message-id', message.id);
                table.append(loadingClone);
            }
        },

        loadMessages:function (accountId, folderId) {
            // Set folder active
            Mail.UI.setFolderInactive(Mail.State.currentAccountId, Mail.State.currentFolderId);
            Mail.UI.setFolderActive(accountId, folderId);
            Mail.UI.clearMessages();

			$('#mail_new_message').fadeIn();

            $.ajax(
				OC.generateUrl('apps/mail/accounts/{accountId}/folders/{folderId}/messages',
					{'accountId':accountId, 'folderId':folderId}), {
					data: {},
					type:'GET',
					success: function (jsondata) {
						$('#messages-loading').fadeOut();

						if (jsondata.status === 'success') {
							// Add messages
							Mail.UI.addMessages(jsondata.data);

							Mail.State.currentAccountId = accountId;
							Mail.State.currentFolderId = folderId;
							Mail.State.currentMessageId = null;
						}
						else {
							// Set the old folder as being active
							Mail.UI.setFolderInactive(accountId, folderId);
							Mail.UI.setFolderActive(Mail.State.currentAccountId, Mail.State.currentFolderId);

							OC.dialogs.alert(jsondata.data.message, t('mail', 'Error'));
						}
					}
				});
        },

        openMessage:function (message_id) {
            var message;

            // close email first
            Mail.UI.closeMessage();
            if (Mail.State.currentMessageId === message_id) {
                return;
            }

            var summary_row = $('#mail_messages tr.mail_message_summary[data-message-id="' + message_id + '"]');
            var load_row = $('#mail_messages').find('tr.mail_message_loading[data-message-id="' + message_id + '"]');
            load_row.show();

            $.getJSON(OC.filePath('mail', 'ajax', 'message.php'), {'account_id':Mail.State.currentAccountId, 'folder_id':Mail.State.currentFolderId, 'message_id':message_id }, function (jsondata) {
                if (jsondata.status == 'success') {

                    summary_row.hide();

                    // hide loading
                    load_row.hide();

                    // Find the correct message
                    load_row.after(jsondata.data);

                    // Set current Message as active
                    Mail.State.currentMessageId = message_id;
                }
                else {
                    OC.dialogs.alert(jsondata.data.message, t('mail', 'Error'));
                }
            });
        },

        closeMessage:function () {
            // Check if message is open
            var message;
            if (Mail.State.currentMessageId !== null) {
                $('#mail_message').remove();
                $('#mail_message_header').remove();

                var summary_row = $('#mail_messages tr.mail_message_summary[data-message-id="' + Mail.State.currentMessageId + '"]');
                summary_row.show();
            }
        },

        setFolderActive:function (account_id, folder_id) {
            $('.mail_folders[data-account_id="' + account_id + '"]>li[data-folder_id="' + folder_id + '"]').addClass('active');
        },

        setFolderInactive:function (accountId, folderId) {
            $('.mail_folders[data-account_id="' + accountId + '"]>li[data-folder_id="' + folderId + '"]').removeClass('active');
        },

        bindEndlessScrolling:function () {
            // Add handler for endless scrolling
            //   (using jquery.endless-scroll.js)
            $('#app-content').endlessScroll({
                fireDelay:10,
                fireOnce:false,
                loader:'',
                callback:function (i) {
                    var from, newLength;

                    // Only do the work if we show a folder
                    if (Mail.State.currentAccountId !== null && Mail.State.currentFolderId !== null) {

                        // do not work if we already hit the end
                        if ($('#mail_messages').data('stop_loading') != 'true') {
                            from = $('#mail_messages .mail_message_summary').length - 1;
                            // minus 1 because of the template

                            // decrease if a message is shown
                            if (Mail.State.currentMessageId !== null) {
                                from = from - 1;
                            }

                            $.ajax(OC.filePath('mail', 'ajax', 'append_messages.php'), {
                                async:true,
                                data:{
									'account_id':Mail.State.currentAccountId,
									'folder_id':Mail.State.currentFolderId,
									'from':from,
									'count':20
								},
                                type:'GET',
                                success:function (jsondata) {
                                    if (jsondata.status === 'success') {
                                        Mail.UI.addMessages(jsondata.data);
                                    }
                                    else {
                                        OC.dialogs.alert(jsondata.data.message, t('mail', 'Error'));
                                    }
									// If we did not get any new messages stop
									newLength = $('#mail_messages .mail_message_summary').length - 1;
									// minus 1 because of the template
									if (from === newLength || ( from === newLength + 1 &&
										Mail.State.currentMessageId !== null )) {
										$('#mail_messages').data('stop_loading', 'true');
									}
								}
                            });
                        }
                    }
                }
            });
        },

        unbindEndlessScrolling:function () {
            $('#app-content').unbind('scroll');
        }
    }
};

$(document).ready(function () {
    Mail.UI.initializeInterface();

    // auto detect button handling
    $('#auto_detect_account').click(function () {
		$('#mail-address').attr('disabled', 'disabled');
		$('#mail-password').attr('disabled', 'disabled');
        $('#auto_detect_account').attr('disabled', "disabled");
        $('#auto_detect_account').val(t('mail', 'Connecting ...'));
		$('#connect-loading').fadeIn();
        var emailAddress, password;
        emailAddress = $('#mail-address').val();
        password = $('#mail-password').val();
        $.ajax(OC.generateUrl('apps/mail/accounts'), {
            data:{
				emailAddress : emailAddress,
				password : password,
				autoDetect : true
			},
            type:'POST',
            success:function (jsondata) {
				// reload on success
				window.location.reload();
            },
			error: function(jqXHR, textStatus, errorThrown){
				var error = errorThrown || textStatus || t('mail', 'Unknown error');
				$('#mail-address').attr('disabled', 'false');
				$('#mail-password').attr('disabled', 'false');
				$('#auto_detect_account').attr('disabled', 'false');
				$('#auto_detect_account').val(t('mail', 'Connect'));
				$('#connect-loading').fadeOut();
				OC.dialogs.alert(error, t('mail', 'Server Error'));
			}
        });
    });

	// new mail message button handling
	$(document).on('click', '#mail_new_message', function () {
		$('#to').val('');
		$('#subject').val('');
		$('#new-message-body').val('');

		$('#mail_new_message').hide();
		$('#new-message-fields').slideDown();
	});

	// Clicking on a folder loads the message list
	$(document).on('click', 'ul.mail_folders li', function () {
		var accountId = $(this).parent().data('account_id');
		var folderId = $(this).data('folder_id');

		Mail.UI.loadMessages(accountId, folderId);
	});

	// Clicking on a message loads the entire message
	$(document).on('click', '#mail_messages .mail_message_summary', function () {
		var messageId = $(this).data('message_id');
		Mail.UI.openMessage(messageId);
	});

    Mail.UI.bindEndlessScrolling();
});
