var FASTyle = {};
(function($, window, document) {

	FASTyle = {

		switching: 0,
		tid: 0,
		sid: 0,
		spinner: {},
		deferred: null,
		dom: {},
		currentResource: {},
		resources: {},
		postKey: '',
		quickMode: 0,
		resourcesList: {},
		useEditor: 1,
		useLocalStorage: 1,
		currentEditorStatus: {},

		init: function(sid, tid) {

			// Template set ID
			if (sid >= -1) {
				this.sid = sid;
			}

			// Theme ID
			if (tid > 0) {
				this.tid = tid;
			}

			// Notification defaults
			$.jGrowl.defaults.appendTo = ".fastyle";
			$.jGrowl.defaults.position = "bottom-right";
			$.jGrowl.defaults.closer = false;
			$.jGrowl.openDuration = 300;
			$.jGrowl.animateOpen = {
				opacity: 1
			};
			$.jGrowl.closeDuration = 300;
			$.jGrowl.animateClose = {
				opacity: 0
			};

			// Set the spinner default options
			this.spinner.opts = {
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
				this.postKey = postKey;
			}

			this.dom.sidebar = $('#sidebar');
			this.dom.textarea = $('#editor');
			this.dom.mainContainer = $('.fastyle');
			this.dom.bar = this.dom.mainContainer.find('.bar');

			this.dom.mergeView = $('<div id="mergeview" />').insertAfter(this.dom.textarea.get(0));

			// Expand/collapse
			this.dom.sidebar.find('.header').on('click', function(e) {
				return $(this).toggleClass('expanded');
			});

			this.resourcesList['ungrouped'] = [];

			// Build a virtual array of stylesheets and templates
			$.each(this.dom.sidebar.find('[data-prefix], [data-title]'), function(k, item) {

				var prefix = item.getAttribute('data-prefix');
				var title = item.getAttribute('data-title');

				if (prefix) {

					prefix = prefix.toLowerCase();

					if (!prefix) {
						prefix = 'ungrouped';
					}

					FASTyle.resourcesList[prefix] = [];

				}

				if (title) {

					prefix = title.split('_');
					prefix = prefix[0].toLowerCase();

					if (title.indexOf('.css') > -1) {
						prefix = 'stylesheets';
					} else if (typeof FASTyle.resourcesList[prefix] === 'undefined') {
						prefix = 'ungrouped';
					}

					FASTyle.resourcesList[prefix].push(title);

				}

			});

			this.spinner = new Spinner(this.spinner.opts).spin();

			// Disable spaces in search and title inputs
			this.dom.bar.find('input[type="textbox"]').on({

				keydown: function(e) {
					if (e.which === 32) return false;
				},

				change: function() {
					this.value = this.value.replace(/\s/g, "_");
				}

			});

			// Determine localStorage support
			if (typeof Storage === 'undefined') {
				this.useLocalStorage = 0;
			}

			// Load the editor
			this.loadNormalEditor();

			// Load the previous editor state
			var currentStorage = this.readLocalStorage();

			try {
				this.loadResource(currentStorage[this.sid].title);
			} catch (e) {
				// No previous state known
			}

			// Mark tabs as not saved when edited (just for textareas: the editor handler must be attached every time it's initialized)
			if (!this.useEditor) {

				this.dom.textarea.on('keydown', function(e) {
					if (e.which !== 0 && e.charCode !== 0 && !e.ctrlKey && !e.metaKey && !e.altKey) {
						FASTyle.dom.sidebar.find('[data-title="' + FASTyle.currentResource.title + '"]').addClass('not-saved');
					}
				});

			}

			// Load resource
			this.dom.sidebar.on('click', 'ul [data-title]', function(e) {

				e.preventDefault();

				var name = $(this).data('title');

				// Save the current resource's status
				FASTyle.saveCurrentResource();

				// Load the new one
				return (name != FASTyle.currentResource.title) ? FASTyle.loadResource(name) : false;

			});

			var notFoundElement = this.dom.sidebar.find('.nothing-found');

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
			this.dom.bar.find('.actions .quickmode').on('click', function(e) {

				e.stopImmediatePropagation();

				FASTyle.quickMode = (FASTyle.quickMode == true) ? false : true;

				FASTyle.addToLocalStorage({
					quickMode: FASTyle.quickMode
				});

				return $(this).toggleClass('enabled');

			});

			// Full page
			this.dom.bar.find('.actions .fullpage').on('click', function(e) {

				FASTyle.dom.mainContainer.toggleClass('full');

				if (FASTyle.useEditor) {
					FASTyle.dom.editor.refresh();
				}

				// Remember this editor state across page loads
				var active = ($(this).hasClass('icon-resize-full')) ? 1 : 0;

				FASTyle.addToLocalStorage({
					fullPage: active
				});

				return (active) ? $(this).removeClass('icon-resize-full').addClass('icon-resize-small') : $(this).removeClass('icon-resize-small').addClass('icon-resize-full');

			});

			// Revert/delete/add/diff
			this.dom.bar.find('.actions span').on('click', function(e) {

				e.preventDefault();

				var $this = $(this);
				var tab = FASTyle.dom.sidebar.find('[data-title="' + FASTyle.currentResource.title + '"]');
				var mode = $this.data('mode');

				if ((tab.data('status') == 'modified' && mode == 'delete') || (tab.data('status') == 'original' && mode == 'revert')) {
					return false;
				}

				if (mode == 'diff') {

					// Switch back to normal mode
					if ($this.hasClass('active')) {

						// Remove the current resource's diffMode flag from the internal cache
						try {
							FASTyle.resources[FASTyle.currentResource.title].diffMode = 0;
						} catch (e) {
							// currentResource == undefined
						}

						return FASTyle.loadNormalEditor();

					}
					// Switch to diff mode using the cached value
					else if (FASTyle.currentResource.original) {
						return FASTyle.loadDiffMode(FASTyle.currentResource.original);
					}

				}

				if (!FASTyle.quickMode && ['revert', 'delete'].indexOf(mode) > -1) {

					var confirm = window.confirm('Are you sure you want to ' + mode + ' this template?');
					if (confirm != true) {
						return false;
					}

				}

				var data = {
					module: 'style-fastyle',
					api: 1,
					ajax: 1,
					my_post_key: FASTyle.postKey,
					title: tab.data('title')
				};

				if (mode == 'add') {

					data.content = '';
					data.title = FASTyle.dom.bar.find('input[name="title"]').val().trim();

					if (!data.title) {
						return false;
					}

				}

				var type = (data.title.indexOf('.css') > -1) ? 'stylesheet' : 'template';

				if (type == 'stylesheet') {

					data.tid = FASTyle.tid;

					if (mode == 'revert') {
						mode = 'delete';
					}

				} else {
					data.sid = FASTyle.sid;
				}

				data.action = mode;

				return FASTyle.sendRequest('post', 'index.php', data, (response) => {

					if (response.error) {
						return false;
					}

					// Resource added
					if (data.action == 'add') {

						var index = FASTyle.findResourceGroup(data.title);
						var position = FASTyle.addToResourceList(data.title);
						var prevTitle = (position > 0) ? FASTyle.resourcesList[index][position - 1] : FASTyle.resourcesList[index][position + 1];

						// Finally show the new resource
						var prevElem = FASTyle.dom.sidebar.find('[data-title="' + prevTitle + '"]');
						var newElem = prevElem.clone();

						var status = (type == 'stylesheet') ? 'modified' : 'original';
						var attributes = {
							'status': status,
							'title': data.title
						};

						newElem.data(attributes).text(data.title);
						$.each(newElem.data(), function(k, v) {
							return newElem.attr('data-' + k, v);
						});

						tab = (position > 0) ? newElem.insertAfter(prevElem) : newElem.insertBefore(prevElem);

						FASTyle.addToResourceCache(data.title, '', Math.round(new Date().getTime() / 1000));
						FASTyle.loadResource(data.title);

					}

					// Resource reverted
					if (data.action == 'revert') {

						tab.removeData('status').removeAttr('data-status').removeAttr('status');

						// Since we reverted this resource, the diff mode is useless
						if (FASTyle.diffMode) {
							FASTyle.loadNormalEditor();
						}

					}

					// Resource deleted
					if (data.action == 'delete') {

						tab.remove();
						FASTyle.removeResourceFromCache(data.title);

						var group = FASTyle.findResourceGroup(data.title);
						var newIndex = FASTyle.removeFromResourceList(data.title);
						var newResource = FASTyle.resourcesList[group][newIndex];

						if (typeof newResource === 'undefined') {
							newResource = 'postbit';
						}

						return FASTyle.loadResource(newResource);

					}

					// Diff
					if (data.action == 'diff' && FASTyle.useEditor) {
						return FASTyle.loadDiffMode(response.content);
					}

					// Resource added or reverted
					if (response.tid) {
						tab.data('tid', Number(response.tid)).attr('tid', Number(response.tid));
					}

					if (!response.content) {
						response.content = '';
					}

					FASTyle.syncBarStatus();

					// Prevents the tab to be marked as "not saved"
					FASTyle.switching = true;

					if (tab.hasClass('active')) {
						return (FASTyle.useEditor) ? FASTyle.dom.editor.setValue(response.content) : FASTyle.dom.textarea.val(response.content);
					}

				});

			});

			// Apply the previous editor status
			try {

				if (currentStorage[this.sid].fullPage) {

					this.dom.mainContainer.toggleClass('full');
					this.dom.bar.find('.actions .fullpage').removeClass('icon-resize-full').addClass('icon-resize-small');

				}

				if (currentStorage[this.sid].quickMode) {
					this.dom.bar.find('.quickmode').trigger('click');
				}

			} catch (e) {
				// This set does not have any previous state known
			}

			// Delete template group
			this.dom.sidebar.on('click', '.deletegroup', function(e) {

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
					'gid': parseInt($(this).parent('[data-gid]').data('gid'))
				};

				return FASTyle.sendRequest('post', 'index.php', data);

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

			this.switching = 1;

			// Set this resource internally
			this.setCurrentResource(name, dateline);

			this.markAsActive(name);

			var tab = this.dom.sidebar.find('[data-title="' + name + '"]');

			// Switch resource in editor/textarea
			if (this.useEditor) {

				// Return to normal view
				if (this.diffMode) {
					this.loadNormalEditor();
				}

				// Switch mode if we have to
				var newMode = (name.indexOf('.css') > -1) ? 'text/css' : 'text/html';

				if (this.dom.editor.getOption('mode') != newMode) {
					this.dom.editor.setOption('mode', newMode);
				}

				this.dom.editor.setValue(content);
				this.dom.editor.focus();
				this.dom.editor.clearHistory();

				this.applyEditorStatus();

			} else {

				this.dom.textarea.val(content);
				this.dom.textarea.focus();

			}

			// Remember tab
			return this.addToLocalStorage({
				title: name
			});

		},

		markAsActive: function(name) {

			if (!name.length) {
				return false;
			}

			// Find this tab
			var tab = this.dom.sidebar.find('[data-title="' + name + '"]');

			if (!tab.length) {
				return false;
			}

			// Remove any other active tab
			this.dom.sidebar.find('.active').removeClass('active');

			var group = tab.closest('ul').prev('.header');

			// Is this group not already expanded?
			if (!group.hasClass('expanded')) {
				group.addClass('expanded');
			}

			// Is this group even visible?
			var scrollingPosition = this.dom.sidebar.scrollTop();
			var tabPosition = tab.position().top;
			var scrollingEnd = scrollingPosition + this.dom.sidebar.outerHeight();

			if (tabPosition < scrollingPosition || tabPosition > scrollingEnd) {
				this.dom.sidebar.scrollTop(tabPosition);
			}

			this.syncBarStatus();

			return tab.addClass('active');

		},

		syncBarStatus: function() {

			// Clear existing attributes
			$.map(['dateline', 'status', 'attachedto', 'dateline'], function(item) {
				return FASTyle.dom.bar.removeAttr(item);
			});

			var currentTab = this.dom.sidebar.find('[data-title="' + this.currentResource.title + '"]');
			var attributes = currentTab.data();

			attributes.dateline = (this.utils.exists(currentTab.data('status'))) ? 'Last edited: ' + this.utils.processDateline(this.currentResource.dateline) : '';

			this.dom.bar.find('.label > *').empty();

			$.each(attributes, function(key, value) {
				return FASTyle.dom.bar.find('.' + key).text(value);
			});

			return this.dom.bar.data(attributes).attr(attributes);

		},

		setCurrentResource: function(name) {

			if (!name) {
				return false;
			}

			this.currentResource = this.resources[name];

		},

		saveCurrentResource: function() {
			return (this.currentResource.title) ? this.addToResourceCache(this.currentResource.title, this.getEditorContent()) : false;
		},

		addToResourceCache: function(name, content, dateline) {

			name = name.trim();

			if (typeof this.resources[name] == 'undefined') {
				this.resources[name] = {};
			}

			this.resources[name].title = name;
			this.resources[name].content = content;

			// Update the last edited dateline, if provided
			if (dateline) {
				this.resources[name].dateline = dateline;
			}

			// Save the current editor status
			if (this.useEditor) {
				this.saveEditorStatus();
			}

			return this.resources[name];

		},

		removeResourceFromCache: function(name) {

			name = name.trim();

			return delete this.resources[name];

		},

		saveEditorStatus: function() {

			try {

				this.resources[this.currentResource.title].history = this.dom.editor.getHistory();
				this.resources[this.currentResource.title].scrollInfo = this.dom.editor.getScrollInfo();
				this.resources[this.currentResource.title].cursorPosition = this.dom.editor.getCursor();
				this.resources[this.currentResource.title].selections = this.dom.editor.listSelections();

			} catch (e) {
				// currentResource == undefined or resources[currentResource] == undefined
			}

		},

		applyEditorStatus: function() {

			var resourceOptions = this.resources[this.currentResource.title];

			try {

				// Edit history
				if (resourceOptions.history) {
					this.dom.editor.setHistory(resourceOptions.history);
				}

				// Scrolling position and editor dimensions
				if (resourceOptions.scrollInfo) {
					this.dom.editor.scrollTo(resourceOptions.scrollInfo.left, resourceOptions.scrollInfo.top);
					this.dom.editor.setSize(resourceOptions.scrollInfo.clientWidth, resourceOptions.scrollInfo.clientHeight);
				}

				// Cursor position
				if (resourceOptions.cursorPosition) {
					this.dom.editor.setCursor(resourceOptions.cursorPosition);
				}

				// Selections
				if (resourceOptions.selections) {
					this.dom.editor.setSelections(resourceOptions.selections);
				}

				// Diff mode
				if (resourceOptions.diffMode && !this.diffMode && resourceOptions.original) {
					this.loadDiffMode(resourceOptions.original);
				}

			} catch (e) {
				// resourceOptions == undefined
			}

		},

		loadDiffMode: function(original) {

			if (!this.useEditor) {
				return false;
			}

			if (!original) {
				original = '';
			}

			this.diffMode = 1;

			// Save before destroying
			this.saveEditorStatus();

			// Destroy the current CodeMirror instance
			this.dom.editor.toTextArea();

			this.dom.textarea.hide();

			var mode = (this.currentResource.title.indexOf('.css') > -1) ? 'text/css' : 'text/html';

			// Save the original value to the cache
			try {

				this.resources[this.currentResource.title].original = original;
				this.resources[this.currentResource.title].diffMode = 1;

			} catch (e) {
				// currentResource == undefined
			}

			// Load the merge view instance
			this.dom.editor = CodeMirror.MergeView(this.dom.mergeView[0], {
				orig: original,
				value: this.dom.textarea.val(),
				connect: 'align',
				lineNumbers: true,
				lineWrapping: true,
				foldGutter: true,
				gutters: ["CodeMirror-linenumbers", "CodeMirror-foldgutter"],
				indentWithTabs: true,
				indentUnit: 4,
				mode: mode,
				theme: "material",
				keyMap: "sublime"
			}).editor();

			// Reapply the previous editor status
			this.applyEditorStatus();

			// Add the labels
			this.dom.mergeView.prepend('<div class="label"><div><span class="button current">Current</span></div><div><span class="button original">Original</span></div></div>');

			this.dom.textarea.parents('form').on('submit', function() {
				return FASTyle.dom.textarea.val(FASTyle.getEditorContent());
			});

			this.dom.editor.on('changes', function(a, b, event) {

				return (!FASTyle.switching) ?
					FASTyle.dom.sidebar.find('[data-title="' + FASTyle.currentResource.title + '"]').addClass('not-saved') :
					(FASTyle.switching = 0);

			});

			this.addToLocalStorage({
				diffMode: 1
			});

			return this.dom.bar.find('.diff').addClass('active');

		},

		loadNormalEditor: function() {

			if (!this.useEditor) {
				return false;
			}

			// Populate textarea with the current editor value
			this.dom.textarea.val(this.getEditorContent());

			this.saveEditorStatus();

			var mode = (typeof this.currentResource.title !== 'undefined' && this.currentResource.title.indexOf('.css') > -1) ? 'text/css' : 'text/html';

			// Destroy the diff view
			this.dom.mergeView.empty();

			// Load the standard editor from our textarea
			this.dom.editor = CodeMirror.fromTextArea(document.getElementById("editor"), {
				lineNumbers: true,
				lineWrapping: true,
				foldGutter: true,
				gutters: ["CodeMirror-linenumbers", "CodeMirror-foldgutter"],
				indentWithTabs: true,
				indentUnit: 4,
				mode: mode,
				theme: "material",
				keyMap: "sublime"
			});

			this.dom.editor.on('changes', function(a, b, event) {

				return (!FASTyle.switching) ?
					FASTyle.dom.sidebar.find('[data-title="' + FASTyle.currentResource.title + '"]').addClass('not-saved') :
					(FASTyle.switching = 0);

			});

			this.applyEditorStatus();

			this.diffMode = 0;

			// Load overlay
			this.spinner.spin();
			$('<div class="overlay" />').append(this.spinner.el).hide().prependTo('.CodeMirror');

			this.addToLocalStorage({
				diffMode: 0
			});

			return this.dom.bar.find('.diff').removeClass('active');

		},

		loadResource: function(name) {

			name = name.trim();

			var t = this.resources[name];

			// Launch the spinner
			$('.CodeMirror .overlay').show();

			if (typeof t !== 'undefined')  {

				// Stop the spinner
				$('.CodeMirror .overlay').hide();

				return this.loadResourceInDOM(name, t.content, t.dateline);

			} else {

				var data = {
					api: 1,
					module: 'style-fastyle',
					action: 'get',
					sid: this.sid,
					tid: this.tid,
					title: name
				}

				return this.sendRequest('post', 'index.php', data, (response) => {

					// Stop the spinner
					$('.CodeMirror .overlay').hide();

					if (response.error) {
						return false;
					}

					FASTyle.addToResourceCache(name, response.content, response.dateline);
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

			FASTyle.sendRequest('post', 'index.php', data, (response) => {

				// Stop the spinner
				spinner.stop();

				// Restore the button
				saveButtonContainer.html(saveButtonHtml);

				if (response.error) {
					return false;
				}

				var currentTab = FASTyle.dom.sidebar.find('.active');

				// Modify this resource's status
				if (FASTyle.sid != -1 && !FASTyle.utils.exists(currentTab.data('status'))) {
					currentTab.data('status', 'modified').attr('status', 'modified');
				}

				// Remove the "not saved" marker
				if (FASTyle.dom.sidebar.length) {
					currentTab.removeClass('not-saved');
				}

				// Update internal cache
				FASTyle.addToResourceCache(data.title, FASTyle.getEditorContent(), Math.round(new Date().getTime() / 1000));
				FASTyle.syncBarStatus();

				// Eventually handle the updated tid (fixes templates not saving through multiple calls when a template hasn't been edited before)
				if (response.tid && data.action == 'edit_template') {
					currentTab.data('tid', Number(response.tid)).attr('tid', Number(response.tid));
				}

			});

			return false;

		},

		buildRequestData: function() {

			var params = {};

			params.ajax = 1;
			params.my_post_key = this.postKey;
			params.title = this.currentResource.title;

			var content = this.getEditorContent();

			// Stylesheet
			if (this.currentResource.title.indexOf('.css') > -1) {

				params.module = 'style-themes';
				params.action = 'edit_stylesheet';
				params.mode = 'advanced';
				params.tid = this.tid;
				params.file = this.currentResource.title;
				params.stylesheet = content;

			}
			// Templates
			else {

				params.module = 'style-templates';
				params.action = 'edit_template';
				params.sid = this.sid;
				params.template = content;

				// This is NOT the theme ID, but the template ID!
				params.tid = parseInt($('[data-title="' + params.title + '"]').data('tid'));

			}

			return params;

		},

		sendRequest: function(type, url, data, callback) {

			this.request = $.ajax({
				type: type,
				url: url,
				data: data
			});

			$.when(this.request).done(function(output, t) {

				var response = JSON.parse(output);

				// Need to login again
				if (response.errors == 'login') {
					return window.location.reload(false);
				}

				// Apply callback
				if (typeof callback === 'function' && t == 'success') {
					callback.apply(this, [response]);
				}

				// Handle response errors
				if (response.error) {

					return $.jGrowl(response.message, {
						themeState: 'error'
					});

				}

				// Show success message
				return (response.message) ? $.jGrowl(response.message) : false;

			});

		},

		isRequestPending: function() {
			return (typeof this.request === 'object' && this.request.state() == 'pending');
		},

		getEditorContent: function() {
			return (this.useEditor && typeof this.dom.editor !== 'undefined') ? this.dom.editor.getValue() : this.dom.textarea.val();
		},

		findResourceGroup: function(title) {

			var split = title.split('_');
			var group = split[0].toLowerCase();

			// Stylesheet
			if (title.indexOf('.css') > -1) {
				group = 'stylesheets';
			}
			// Ungrouped template
			else if (typeof this.resourcesList[group] === 'undefined') {
				group = 'ungrouped';
			}

			return group;

		},

		addToResourceList: function(title) {

			var group = this.findResourceGroup(title);

			this.resourcesList[group].push(title);

			// Sort alphabetically if it's a template
			if (title.indexOf('.css') == -1) {
				this.resourcesList[group].sort();
			}

			return this.resourcesList[group].indexOf(title);

		},

		removeFromResourceList: function(title) {

			var group = this.findResourceGroup(title);
			var index = this.resourcesList[group].indexOf(title);

			if (index < 0) {
				return false;
			}

			delete this.resourcesList[group][index];

			// Sort alphabetically if it's a template
			if (title.indexOf('.css') == -1) {
				this.resourcesList[group].sort();
			}

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

				if (!dateline || !this.exists(dateline)) {
					date = new Date();
				} else {
					date = new Date(dateline * 1000);
				}

				var now = Math.round(new Date().getTime() / 1000);

				var monthNames = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
				var todayMidnight = new Date().setHours(0, 0, 0, 0) / 1000;
				var yesterdayMidnight = todayMidnight - (24 * 60 * 60);
				var hourTime = ('0' + date.getHours()).slice(-2) + ":" + ('0' + date.getMinutes()).slice(-2);

				if (dateline > yesterdayMidnight && dateline < todayMidnight) {
					return 'Yesterday, ' + hourTime;
				}
				// Relative date
				else if (dateline > todayMidnight) {

					var diff = now - dateline;
					var minute = 60;
					var hour = minute * 60;
					var day = hour * 24;

					// Just now
					if (diff < 60) {

						if (diff < 2) {
							return '1 second ago';
						}

						return diff + ' seconds ago';

					}
					// Minutes ago
					else if (diff < hour) {

						if (diff < minute * 2) {
							return '1 minute ago';
						}

						return Math.floor(diff / minute) + ' minutes ago';

					}
					// Hours ago
					else if (diff < day) {

						if (diff < hour * 2) {
							return '1 hour ago';
						}

						return Math.floor(diff / hour) + ' hours ago';

					}

					return 'Today, ' + hourTime;

				}

				var string = (date.getDate() + " " + monthNames[date.getMonth()]);
				var year = date.getFullYear();

				if (year != new Date().getFullYear()) {
					string += ' ' + year;
				}

				if (type == 'day') {
					return string;
				}

				return string + ', ' + hourTime;

			}

		},

		deleteFromLocalStorage: function(key) {

			if (!this.useLocalStorage) {
				return false;
			}

			obj = this.readLocalStorage();

			if (obj[key]) {
				delete obj[key];
			}

			return this.addToLocalStorage(obj);

		},

		readLocalStorage: function() {

			if (!this.useLocalStorage) {
				return false;
			}

			obj = localStorage.FASTyle;

			// Wrapped in a try/catch block to prevent obj to be undefined
			try {
				obj = JSON.parse(obj);
			} catch (e) {
				obj = {};
			}

			return obj;

		},

		addToLocalStorage: function(obj) {

			if (!this.useLocalStorage) {
				return false;
			}

			var current = this.readLocalStorage();

			if (typeof current[this.sid] === 'undefined') {
				current[this.sid] = {};
			}

			for (var key in obj) {
				current[this.sid][key] = obj[key];
			}

			localStorage.FASTyle = JSON.stringify(current);

		}

	}

})(jQuery, window, document);