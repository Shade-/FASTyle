var FASTyle = {

	switching: false,
	tid: 0,
	sid: 0,
	spinner: {},
	deferred: null,
	dom: {},
	currentResource: {},
	resources: {},
	postKey: '',
	quickMode: false,
	resourcesList: {},

	init: function(sid, tid) {

		// Template set ID
		if (sid >= -1) {
			FASTyle.sid = sid;
		}

		// Theme ID
		if (tid > 0) {
			FASTyle.tid = tid;
		}

		// Notification defaults
		$.jGrowl.defaults.appendTo = ".fastyle";
		$.jGrowl.defaults.position = "bottom-right";

		// Set the spinner default options
		FASTyle.spinner.opts = {
			lines: 9,
			length: 20,
			width: 9,
			radius: 19,
			scale: 0.25,
			corners: 1,
			color: '#fff',
			opacity: 0.25,
			rotate: 0,
			direction: 1,
			speed: 1,
			trail: 60,
			fps: 20,
			zIndex: 2e9,
			className: 'spinner',
			top: '50%',
			left: '50%',
			shadow: false,
			hwaccel: false,
			position: 'relative'
		}

		// Set the postKey
		var postKey = $('[name="my_post_key"]').val();

		if (postKey.length) {
			FASTyle.postKey = postKey;
		}

		FASTyle.dom.sidebar = $('#sidebar');
		FASTyle.dom.textarea = $('#editor');
		FASTyle.dom.mainContainer = $('.fastyle');
		FASTyle.dom.bar = FASTyle.dom.mainContainer.find('.bar');

		// Expand/collapse
		FASTyle.dom.sidebar.find('.header').on('click', function(e) {
			return $(this).toggleClass('expanded');
		});

		// Build a virtual array of stylesheets and templates
		$.each(FASTyle.dom.sidebar.find('[data-prefix], [data-title]'), function(k, item) {

			var prefix = item.getAttribute('data-prefix');
			var title = item.getAttribute('data-title');

			if (prefix) {

				if (prefix == -1) {
					prefix = 'ungrouped';
				}

				FASTyle.resourcesList[prefix] = [];

			}

			if (title) {

				prefix = title.split('_');
				prefix = prefix[0];

				if (title.indexOf('.css') > -1) {
					prefix = 'stylesheets';
				} else if (typeof FASTyle.resourcesList[prefix] === 'undefined') {
					prefix = 'ungrouped';
				}

				FASTyle.resourcesList[prefix].push(title);

			}

		});

		FASTyle.spinner = new Spinner(FASTyle.spinner.opts).spin();
		FASTyle.useEditor = (typeof editor !== 'undefined') ? true : false;
		FASTyle.dom.editor = (FASTyle.useEditor) ? editor : null;

		// Load overlay
		if (FASTyle.useEditor)  {
			$('<div class="overlay" />').hide().prependTo('.CodeMirror');
			FASTyle.spinner.spin();
			$('.CodeMirror .overlay').append(FASTyle.spinner.el);
		}

		// Load the previously opened resource
		var currentlyOpen = Cookie.get('active-resource-' + FASTyle.sid);
		if (typeof currentlyOpen !== 'undefined') {
			FASTyle.loadResource(currentlyOpen);
		}

		// Close tab
		/*$('body').on('click', '#tabs span.close', function(e) {

			e.stopImmediatePropagation();

			var _this = $(this);
			var d = true;
			var name = $.trim(_this.parent('a').clone().children().remove().end().text());

			if (_this.parent('a').hasClass('not_saved')) {
				d = confirm('You have unsaved changes in the template "' + name + '". Would you like to close it anyway?');
			}

			if (d) {
				FASTyle.removeResourceFromCache(name);
			}

		});*/

		// Mark tabs as not saved when edited
		if (FASTyle.useEditor) {

			FASTyle.dom.editor.on('changes', function(a, b, event) {

				if (!FASTyle.switching) {
					FASTyle.dom.sidebar.find('[data-title="' + FASTyle.currentResource.title + '"]').addClass('not-saved');
				} else {
					FASTyle.switching = false;
				}

			});

		} else {

			FASTyle.dom.textarea.on('keydown', function(e) {
				if (e.which !== 0 && e.charCode !== 0 && !e.ctrlKey && !e.metaKey && !e.altKey) {
					FASTyle.dom.sidebar.find('[data-title="' + FASTyle.currentResource.title + '"]').addClass('not_saved');
				}
			});

		}

		// Load resource
		FASTyle.dom.sidebar.on('click', 'ul [data-title]', function(e) {

			e.preventDefault();

			var name = $(this).attr('data-title');

			// Save the current resource's status
			FASTyle.saveCurrentResource();

			// Load the new one
			if (name != FASTyle.currentResource.title) {
				FASTyle.loadResource(name);
			}

			return false;

		});

		var notFoundElement = FASTyle.dom.sidebar.find('.nothing-found');

		// Search resource
		$('.sidebar input[name="search"]').on('keyup', function(e) {

			var val = $(this).val();

			if (!val) {
				FASTyle.dom.sidebar.find('.header+ul, .header+ul li').removeClass('expanded').removeAttr('style');
				return FASTyle.dom.sidebar.find('.header').show();
			}

			// Hide all groups
			FASTyle.dom.sidebar.find('.header, .header+ul, .header+ul li').hide();

			// Show
			var found = FASTyle.dom.sidebar.find('[data-title*="' + val + '"]');

			if (found.length) {
				notFoundElement.hide();
				found.show().closest('ul').show().prev('.header').addClass('expanded').show();
			} else {
				notFoundElement.show();
			}

		});

		// Quick mode
		FASTyle.dom.bar.find('.actions span.quickmode').on('click', function(e) {

			e.stopImmediatePropagation();

			FASTyle.quickMode = (FASTyle.quickMode == true) ? false : true;

			return $(this).toggleClass('enabled');

		});

		// Full page
		FASTyle.dom.bar.find('.actions .fullpage').on('click', function(e) {

			FASTyle.dom.mainContainer.toggleClass('full');

			if (FASTyle.useEditor) FASTyle.dom.editor.refresh();

			return ($(this).hasClass('icon-resize-full')) ? $(this).removeClass('icon-resize-full').addClass('icon-resize-small') : $(this).removeClass('icon-resize-small').addClass('icon-resize-full');

		});

		// Revert/delete/add
		FASTyle.dom.bar.find('.actions span').on('click', function(e) {

			e.preventDefault();

			var tab = FASTyle.dom.sidebar.find('[data-title="' + FASTyle.currentResource.title + '"]');
			var mode = $(this).attr('data-mode');

			if ((tab.attr('data-status') == 'modified' && mode == 'delete') || (tab.attr('data-status') == 'original' && mode == 'revert')) {
				return false;
			}

			if (!FASTyle.quickMode && ['revert', 'delete'].indexOf(mode) > -1) {

				var confirm = window.confirm('Are you sure you want to ' + mode + ' this template?');
				if (confirm != true) {
					return false;
				}

			}

			var data = {
				'module': 'style-fastyle',
				'api': 1,
				'my_post_key': FASTyle.postKey,
				'action': mode,
				'title': tab.attr('data-title'),
				'sid': FASTyle.sid
			};

			if (mode == 'add') {

				data.template = '';
				data.title = FASTyle.dom.bar.find('input[name="title"]').val().trim();

				if (!data.title) {
					return false;
				}

			}

			return FASTyle.sendRequest('post', 'index.php', data, (response) => {

				// Error?
				if (response.error) {
					return $.jGrowl(response.message, {
						themeState: 'error'
					});
				}

				$.jGrowl(response.message);

				// Template added
				if (mode == 'add') {

					var index = FASTyle.findResourceGroup(data.title);
					var position = FASTyle.addToResourceList(data.title);
					var prevTitle = (position > 0) ? FASTyle.resourcesList[index][position - 1] : FASTyle.resourcesList[index][position + 1];

					// Finally show the new template!
					var prevElem = FASTyle.dom.sidebar.find('[data-title="' + prevTitle + '"]');
					var newElem = prevElem.clone();

					newElem.attr('data-status', 'original').attr('data-title', data.title).text(data.title);

					tab = (position > 0) ? newElem.insertAfter(prevElem) : newElem.insertBefore(prevElem);

					FASTyle.addResourceToCache(data.title, '', Math.round(new Date().getTime() / 1000));
					FASTyle.loadResource(data.title);

				}
				// Template reverted
				else {
					tab.removeAttr('data-status');
				}

				// Template deleted
				if (mode == 'delete') {

					tab.remove();
					FASTyle.removeResourceFromCache(data.title);

					var group = FASTyle.findResourceGroup(data.title);
					var newIndex = FASTyle.removeFromResourceList(data.title);
					return FASTyle.loadResource(FASTyle.resourcesList[group][newIndex]);

				}

				if (response.tid) {
					tab.attr('data-tid', Number(response.tid));
				}

				if (!response.template) {
					response.template = '';
				}

				FASTyle.syncBarStatus();

				// Prevents the tab to be marked as "not saved"
				FASTyle.switching = true;

				if (tab.hasClass('active')) {
					return (FASTyle.useEditor) ? FASTyle.dom.editor.setValue(response.template) : FASTyle.dom.textarea.val(response.template);
				}

			});

		});

		// Delete template group
		FASTyle.dom.sidebar.on('click', '.deletegroup', function(e) {

			e.preventDefault();

			if (!FASTyle.quickMode) {

				var confirm = window.confirm('Are you sure you want to delete this whole template group?');
				if (confirm != true) {
					return false;
				}

			}

			var tab = FASTyle.dom.sidebar.find('[data-title="' + FASTyle.currentResource.title + '"]');

			var data = {
				'module': 'style-fastyle',
				'api': 1,
				'my_post_key': FASTyle.postKey,
				'action': 'deletegroup',
				'gid': parseInt($(this).parent('[data-gid]').attr('data-gid'))
			};

			return FASTyle.sendRequest('post', 'index.php', data, (response) => {
				return $.jGrowl(response.message);
			});

		});

		// Save templates/stylesheets with AJAX
		var form = $('#fastyle_editor');
		if (form.length) {
			form.submit(function(e) {
				return FASTyle.save.call(this, e);
			});
		}

		/*var edit_settings = $('#change');
		if (edit_settings.length) {
			edit_settings.submit(function(e) {
				return FASTyle.save.call(this, e);
			});
		}*/

		// Add shortcuts
		$(window).bind('keydown', function(event) {

			// CTRL/CMD
			if (event.ctrlKey || event.metaKey) {

				switch (String.fromCharCode(event.which).toLowerCase()) {

					// + S = save
					case 's':
						var submitButton = $('.submit_button[name="continue"], .submit_button[name="save"], #change .submit_button');
						if (submitButton.length) {
							event.preventDefault();
							submitButton.click();
						}
						break;

				}

			}

		});

	},

	loadResourceInDOM: function(name, content, dateline) {

		FASTyle.switching = true;

		// Set this resource internally
		FASTyle.setCurrentResource(name, dateline);

		FASTyle.markAsActive(name);

		var tab = FASTyle.dom.sidebar.find('[data-title="' + name + '"]');

		FASTyle.dom.sidebar.find(':not([data-title="' + name + '"])').removeClass('active');

		// Switch resource in editor/textarea
		if (FASTyle.useEditor) {

			// Switch mode if we have to
			if (name.indexOf('.css') > -1) {

				if (FASTyle.dom.editor.getOption('mode') != 'text/css') {
					FASTyle.dom.editor.setOption('mode', 'text/css');
				}

			} else {

				if (FASTyle.dom.editor.getOption('mode') != 'text/html') {
					FASTyle.dom.editor.setOption('mode', 'text/html');
				}

			}

			FASTyle.dom.editor.setValue(content);
			FASTyle.dom.editor.focus();
			FASTyle.dom.editor.clearHistory();

			var templateOptions = FASTyle.resources[name];

			// Set the previously-saved editor status
			if (typeof templateOptions !== 'undefined') {

				// Edit history
				if (templateOptions.history) {
					FASTyle.dom.editor.setHistory(templateOptions.history);
				}

				// Scrolling position and editor dimensions
				if (templateOptions.scrollInfo) {
					FASTyle.dom.editor.scrollTo(templateOptions.scrollInfo.left, templateOptions.scrollInfo.top);
					FASTyle.dom.editor.setSize(templateOptions.scrollInfo.clientWidth, templateOptions.scrollInfo.clientHeight);
				}

				// Cursor position
				if (templateOptions.cursorPosition) {
					FASTyle.dom.editor.setCursor(templateOptions.cursorPosition);
				}

				// Selections
				if (templateOptions.selections) {
					FASTyle.dom.editor.setSelections(templateOptions.selections);
				}

			}

		} else {
			FASTyle.dom.textarea.val(content);
			FASTyle.dom.textarea.focus();
		}

		// Remember tab
		Cookie.set('active-resource-' + FASTyle.sid, name);

		return true;

	},

	markAsActive: function(name) {

		if (!name.length) return false;

		// Find this tab and group
		var tab = FASTyle.dom.sidebar.find('[data-title="' + name + '"]');
		var group = tab.closest('ul').prev('.header');

		// Is this group not already expanded?
		if (!group.hasClass('expanded')) {
			group.addClass('expanded');
		}

		// Is this group even visible?
		var scrollingPosition = FASTyle.dom.sidebar.scrollTop();
		var tabPosition = tab.position().top;
		var scrollingEnd = scrollingPosition + FASTyle.dom.sidebar.outerHeight();

		if (tabPosition < scrollingPosition || tabPosition > scrollingEnd) {
			FASTyle.dom.sidebar.scrollTop(tabPosition);
		}

		FASTyle.syncBarStatus();

		return tab.addClass('active');

	},

	syncBarStatus: function() {

		var title = FASTyle.currentResource.title;
		var currentTab = FASTyle.dom.sidebar.find('[data-title="' + title + '"]');

		if (FASTyle.utils.exists(currentTab.attr('data-status'))) {

			FASTyle.dom.bar.attr('data-status', currentTab.attr('data-status')).find('.label .name').text(title);
			FASTyle.updateDatelineLabel(FASTyle.currentResource.dateline);

		} else {

			FASTyle.dom.bar.removeAttr('data-status').find('.label .name').text(title);
			FASTyle.removeDatelineLabel();

		}

	},

	setCurrentResource: function(name) {

		if (!name) {
			return false;
		}

		FASTyle.currentResource = FASTyle.resources[name];

	},

	saveCurrentResource: function() {
		return (FASTyle.currentResource.title) ? FASTyle.addResourceToCache(FASTyle.currentResource.title, FASTyle.getEditorContent()) : false;
	},

	addResourceToCache: function(name, content, dateline) {

		name = name.trim();

		if (typeof FASTyle.resources[name] == 'undefined') {
			FASTyle.resources[name] = {};
		}

		FASTyle.resources[name].title = name;
		FASTyle.resources[name].content = content;

		// Update the last edited dateline, if provided
		if (dateline) {
			FASTyle.resources[name].dateline = dateline;
		}

		// Save the current editor status
		if (FASTyle.useEditor) {

			FASTyle.resources[name].history = FASTyle.dom.editor.getHistory();
			FASTyle.resources[name].scrollInfo = FASTyle.dom.editor.getScrollInfo();
			FASTyle.resources[name].cursorPosition = FASTyle.dom.editor.getCursor();
			FASTyle.resources[name].selections = FASTyle.dom.editor.listSelections();

		}

		return FASTyle.resources[name];

	},

	removeResourceFromCache: function(name) {

		name = name.trim();

		return delete FASTyle.resources[name];

	},

	loadResource: function(name, type) {

		name = name.trim();

		var t = FASTyle.resources[name];

		if (!type) {
			type = (name.indexOf('.css') > -1) ? 'stylesheet' : 'template';
		}

		// Launch the spinner
		$('.CodeMirror .overlay').show();

		if (typeof t !== 'undefined')  {
				
			// Stop the spinner
			$('.CodeMirror .overlay').hide();
			
			return FASTyle.loadResourceInDOM(name, t.content, t.dateline);
			
		} else {

			var data = {
				'api': 1,
				'module': 'style-fastyle',
				'get': type,
				'sid': FASTyle.sid,
				'tid': FASTyle.tid,
				'title': name
			}

			return FASTyle.sendRequest('POST', 'index.php', data, (response) => {
				
				// Stop the spinner
				$('.CodeMirror .overlay').hide();
				
				// Error?
				if (response.error) {
					return $.jGrowl(response.message, {
						themeState: 'error'
					});
				}

				FASTyle.addResourceToCache(name, response.content, response.dateline);
				FASTyle.loadResourceInDOM(name, response.content, response.dateline);

			});

		}

	},

	save: function(e) {

		$this = $(this);

		var pressed = $this.find("input[type=submit]:focus").attr("name");

		if (pressed == "close" || pressed == "save_close") return;

		e.preventDefault();

		var saveButton = $('.submit_button[name="continue"], .submit_button[name="save"], .form_button_wrapper > label:only-child > .submit_button, #change .submit_button');
		var saveButtonContainer = saveButton.parent();
		var saveButtonHtml = saveButtonContainer.html();

		if (!FASTyle.dom.mainContainer.hasClass('full')) {

			// Set up the container to be as much similar to the container 
			var spinnerContainer = $('<div />').css({
				width: saveButton.outerWidth(true),
				height: saveButton.outerHeight(true),
				position: 'relative',
				'display': 'inline-block',
				'vertical-align': 'top'
			});

			// Replace the button with the spinner container
			saveButton.replaceWith(spinnerContainer);

		} else {
			var spinnerContainer = $('<div />');
		}

		var opts = $.extend(true, {}, FASTyle.spinner.opts);

		opts.top = '50%';
		opts.left = '50%';
		opts.position = 'absolute';

		var spinner = new Spinner(opts).spin();
		spinnerContainer.append(spinner.el);

		var data = FASTyle.buildRequestData();

		FASTyle.sendRequest('POST', 'index.php', data, (response) => {

			var currentTab = FASTyle.dom.sidebar.find('.active');

			// Stop the spinner
			spinner.stop();

			// Restore the button
			saveButtonContainer.html(saveButtonHtml);

			// Error?
			if (response.error) {
				return $.jGrowl(response.message, {
					themeState: 'error'
				});
			}

			// Modify this resource's status
			if (FASTyle.sid != -1 && !FASTyle.utils.exists(currentTab.attr('data-status'))) {
				currentTab.attr('data-status', 'modified');
				FASTyle.dom.bar.attr('data-status', 'modified');
			}

			// Remove the "not saved" marker
			if (FASTyle.dom.sidebar.length) {
				currentTab.removeClass('not-saved');
			}

			// Notify the user
			$.jGrowl(response.message);

			// Update last edited
			FASTyle.updateDatelineLabel(Math.round(new Date().getTime() / 1000));

			// Eventually handle the updated tid (fixes templates not saving through multiple calls when a template hasn't been edited before)
			if (response.tid && data.action == 'edit_template') {
				currentTab.attr('data-tid', Number(response.tid));
			}

		});

		return false;

	},

	buildRequestData: function() {

		var params = {};

		params.ajax = 1;
		params.my_post_key = FASTyle.postKey;

		var content = FASTyle.getEditorContent();

		// Stylesheet
		if (FASTyle.currentResource.title.indexOf('.css') > -1) {

			params.module = 'style-themes';
			params.action = 'edit_stylesheet';
			params.mode = 'advanced';
			params.tid = FASTyle.tid;
			params.file = FASTyle.currentResource.title;
			params.stylesheet = content;

		}
		// Templates
		else {

			params.module = 'style-templates';
			params.action = 'edit_template';
			params.title = FASTyle.currentResource.title;
			params.sid = FASTyle.sid;
			params.template = content;

			// This is NOT the theme ID, but the template ID!
			params.tid = parseInt($('[data-title="' + params.title + '"]').attr('data-tid'));

		}

		return params;

	},

	sendRequest: function(type, url, data, callback) {

		FASTyle.request = $.ajax({
			type: type,
			url: url,
			data: data
		});

		$.when(FASTyle.request).done(function(output, t) {
			return (typeof callback === 'function' && t == 'success') ? callback.apply(this, [JSON.parse(output)]) : false;
		});

	},

	isRequestPending: function() {
		return (typeof FASTyle.request === 'object' && FASTyle.request.state() == 'pending');
	},

	getEditorContent: function() {
		return (FASTyle.useEditor) ? FASTyle.dom.editor.getValue() : FASTyle.dom.textarea.val();
	},

	updateDatelineLabel: function(dateline) {
		return FASTyle.dom.bar.find('.label .date').html('Last edited: ' + FASTyle.utils.processDateline(dateline));
	},

	removeDatelineLabel: function() {
		return FASTyle.dom.bar.find('.label .date').empty();
	},

	findResourceGroup: function(title) {

		var split = title.split('_');
		var group = split[0];

		// Stylesheet
		if (title.indexOf('.css') > -1) {
			group = 'stylesheets';
		}
		// Ungrouped template
		else if (typeof FASTyle.resourcesList[group] === 'undefined') {
			group = 'ungrouped';
		}

		return group;

	},

	addToResourceList: function(title) {

		var group = FASTyle.findResourceGroup(title);

		FASTyle.resourcesList[group].push(title);

		// Sort alphabetically
		FASTyle.resourcesList[group].sort();

		return FASTyle.resourcesList[group].indexOf(title);

	},

	removeFromResourceList: function(title) {

		var group = FASTyle.findResourceGroup(title);
		var index = FASTyle.resourcesList[group].indexOf(title);

		if (index < 0) {
			return false;
		}

		delete FASTyle.resourcesList[group][index];

		// Sort alphabetically
		FASTyle.resourcesList[group].sort();

		return (index > 0) ? index - 1 : index;

	},

	utils: {

		removeItemFromArray: function(array, value) {
			if (Array.isArray(value)) { // For multi remove
				for (var i = array.length - 1; i >= 0; i--) {
					for (var j = value.length - 1; j >= 0; j--) {
						if (array[i] == value[j]) {
							array.splice(i, 1);
						};
					}
				}
			} else { // For single remove
				for (var i = array.length - 1; i >= 0; i--) {
					if (array[i] == value) {
						array.splice(i, 1);
					}
				}
			}
		},

		exists: function(data) {
			return (typeof data === 'undefined' || data == false) ? false : true;
		},

		processDateline: function(dateline, type) {

			var date;

			if (!dateline || !FASTyle.utils.exists(dateline)) {
				date = new Date();
			} else {
				date = new Date(dateline * 1000);
			}

			var monthNames = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
			var todayMidnight = new Date().setHours(0, 0, 0, 0) / 1000;
			var yesterdayMidnight = todayMidnight - (24 * 60 * 60);
			var hour = ('0' + date.getHours()).slice(-2) + ":" + ('0' + date.getMinutes()).slice(-2);

			if (dateline > yesterdayMidnight && dateline < todayMidnight) {
				return 'Yesterday, ' + hour;
			} else if (dateline > todayMidnight) {
				return 'Today, ' + hour;
			}

			var string = (date.getDate() + " " + monthNames[date.getMonth()]);
			var year = date.getFullYear();

			if (year != new Date().getFullYear()) {
				string += ' ' + year;
			}

			if (type == 'day') {
				return string;
			}

			return string + ', ' + hour;

		}

	}

}