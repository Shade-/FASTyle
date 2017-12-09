!function(t){var i=t(window);t.fn.visible=function(t,e,o){if(!(this.length<1)){var r=this.length>1?this.eq(0):this,n=r.get(0),f=i.width(),h=i.height(),o=o?o:"both",l=e===!0?n.offsetWidth*n.offsetHeight:!0;if("function"==typeof n.getBoundingClientRect){var g=n.getBoundingClientRect(),u=g.top>=0&&g.top<h,s=g.bottom>0&&g.bottom<=h,c=g.left>=0&&g.left<f,a=g.right>0&&g.right<=f,v=t?u||s:u&&s,b=t?c||a:c&&a;if("both"===o)return l&&v&&b;if("vertical"===o)return l&&v;if("horizontal"===o)return l&&b}else{var d=i.scrollTop(),p=d+h,w=i.scrollLeft(),m=w+f,y=r.offset(),z=y.top,B=z+r.height(),C=y.left,R=C+r.width(),j=t===!0?B:z,q=t===!0?z:B,H=t===!0?R:C,L=t===!0?C:R;if("both"===o)return!!l&&p>=q&&j>=d&&m>=L&&H>=w;if("vertical"===o)return!!l&&p>=q&&j>=d;if("horizontal"===o)return!!l&&m>=L&&H>=w}}}}(jQuery);

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
		swiper: null,
		lang: {
			confirm: {
				'delete': 'Are you sure you want to delete {1}?',
				'revert': 'Are you sure you want to revert {1}?',
				'group': 'Are you sure you want to delete this whole template group?',
				'enableQuickMode': 'Quick mode allows you to perform actions without confirmation dialogs. Are you sure you want to enable quick mode?'
			},
			identical: 'This resource is identical to its original counterpart.',
			lastEditedPrefix: 'Last edited: ',
			current: 'Current',
			original: 'Original',
			date: {
				secondSingle: '1 second ago',
				secondsPlural: ' seconds ago',
				minuteSingle: '1 minute ago',
				minutesPlural: ' minutes ago',
				hourSingle: '1 hour ago',
				hoursPlural: ' hours ago',
				today: 'Today, ',
				yesterday: 'Yesterday, '
			}
		},

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
			this.dom.bar = this.dom.mainContainer.find('.bar:not(.switcher)');
			this.dom.switcher = this.dom.mainContainer.find('.switcher .content .swiper-wrapper');

			this.dom.mergeView = $('#mergeview');
			
			// SimpleBar for sidebar
			this.dom.simpleBar = new SimpleBar(this.dom.sidebar[0]);

			// Switcher slider
			this.swiper = new Swiper('.fastyle .switcher .content', {
				navigation: {
					nextEl: '.swiper-button-next',
					prevEl: '.swiper-button-prev',
				},
				slidesPerView: 5,
				slidesPerGroup: 5,
				keyboard: true,
				spaceBetween: 10
			});

			// Switcher close tabs handler
			this.dom.switcher.on('click', '.delete', function(e) {

				e.preventDefault();

				// Save the current resource's status
				FASTyle.saveCurrentResource();

				// Load the new one
				FASTyle.switcher.remove($(this).closest('[data-title]').data('title'));

				return false;

			});

			// Expand/collapse
			this.dom.sidebar.find('.header').on('click', function(e) {
				return $(this).toggleClass('expanded');
			});

			this.resourcesList['ungrouped'] = [];

			this.dom.sidebar.find('li i.icon-attention').tipsy({
				gravity: 's',
				opacity: 1
			});

			this.dom.switcher.find('.swiper-slide').tipsy({
				live: true,
				gravity: 'n',
				opacity: 1
			});

			// Build a virtual array of resources
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

					var ext = FASTyle.getExtension(title);

					if (ext == 'css') {
						prefix = 'stylesheets';
					} else if (ext == 'js') {
						prefix = 'javascripts';
					}

					if (typeof FASTyle.resourcesList[prefix] === 'undefined') {
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
						FASTyle.dom.mainContainer.find('[data-title].active').addClass('not-saved');
					}
				});

			}

			// Load resource
			this.dom.sidebar.on('click', 'ul [data-title]', function(e) {

				e.preventDefault();

				// Save the current resource's status
				FASTyle.saveCurrentResource();

				// Load the new one
				return FASTyle.loadResource($(this).data('title'));

			});

			// Load resource from the switcher
			this.dom.switcher.on('click', '[data-title]', function(e) {

				e.preventDefault();

				// Save the current resource's status
				FASTyle.saveCurrentResource();

				// Load the new one
				return FASTyle.loadResource($(this).data('title'));

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
					found.show().parents('ul').show().prev('.header').addClass('expanded').show();

				} else {
					notFoundElement.show();
				}

			});

			// Quick mode
			this.dom.bar.find('.actions .quickmode').on('click', function(e) {

				e.stopImmediatePropagation();

				var currentStorage = FASTyle.readLocalStorage();

				var confirm = (!FASTyle.quickMode && FASTyle.exists(currentStorage[FASTyle.sid]) && !currentStorage[FASTyle.sid].quickMode) ? window.confirm(FASTyle.lang.confirm.enableQuickMode) : true;
				if (confirm != true) {
					return false;
				}

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

				FASTyle.swiper.update();

				return (active) ? $(this).removeClass('icon-resize-full').addClass('icon-resize-small') : $(this).removeClass('icon-resize-small').addClass('icon-resize-full');

			});

			// Add shortcut
			this.dom.bar.find('.actions input[type="textbox"][name="title"]').on('keydown', function(e) {

				if (e.keyCode != 13) {
					return true;
				}

				e.preventDefault();

				return $(this).siblings('.add').trigger('click');

			})

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

					var confirm = window.confirm(FASTyle.lang.confirm[mode].replace('{1}', FASTyle.currentResource.title));
					if (confirm != true) {
						return false;
					}

				}

				var data = {
					module: 'style-fastyle',
					api: 1,
					ajax: 1,
					my_post_key: FASTyle.postKey,
					title: FASTyle.currentResource.title
				};

				if (mode == 'add') {

					data.content = '';
					data.title = FASTyle.dom.bar.find('input[name="title"]').val().trim();

					if (!data.title) {
						return false;
					}

				}

				// Determine type
				var type = (FASTyle.getExtension(data.title) == 'css') ? 'stylesheet' : false;
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

						// "/" chars are special chars used to create subfolders
						var text = (FASTyle.getExtension(data.title) == 'js') ? data.title.slice(data.title.lastIndexOf('/') + 1) : data.title;

						newElem.data(attributes).text(text);
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
						FASTyle.switcher.remove(data.title);
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

						// Identical
						if (response.content == FASTyle.currentResource.content) {

							// We don't need the status indicator anymore
							tab.removeData('status').removeAttr('data-status').removeAttr('status');
							FASTyle.syncBarStatus();

							// Save the original value to the cache
							try {
								FASTyle.resources[FASTyle.currentResource.title].original = original;
							} catch (e) {
								// currentResource == undefined
							}

							return FASTyle.message(FASTyle.lang.identical, true);

						}

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

			// Load currently open tabs
			try {
				var currentOpenedTabs = currentStorage[this.sid].openedTabs;
			} catch (e) {}

			if (FASTyle.exists(currentOpenedTabs)) {

				$.each(currentOpenedTabs, function(k, name) {
					FASTyle.switcher.add(name);
				});

			}

			// Apply the previous editor status
			try {

				if (currentStorage[this.sid].fullPage) {
					this.dom.bar.find('.fullpage').trigger('click');
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

					var confirm = window.confirm(FASTyle.lang.confirm.group);
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

			// Add shortcuts
			$(window).bind('keydown', function(event) {

				// CTRL/CMD
				if (event.ctrlKey || event.metaKey) {

					switch (String.fromCharCode(event.which).toLowerCase()) {

						// + S = save
						case 's':
							var submitButton = $('input[type="submit"][name="continue"], input[type="submit"][name="save"], #change input[type="submit"]');
							if (submitButton.length) {
								event.preventDefault();
								submitButton.click();
							}
							break;

					}

				}

			});

		},

		loadResource: function(name) {

			name = name.trim();

			if (name == this.currentResource.title) {
				return false;
			}

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

		loadResourceInDOM: function(name, content, dateline) {

			this.switching = 1;

			// Set this resource internally
			this.setCurrentResource(name, dateline);

			this.markAsActive(name);

			// Switch resource in editor/textarea
			if (this.useEditor) {

				// Return to normal view
				if (this.diffMode) {
					this.loadNormalEditor();
				}

				// Switch mode if we have to
				var ext = this.getExtension(name);
				var newMode = (ext == 'css') ? 'text/css' : (ext == 'js') ? 'text/javascript' : 'text/html';

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

			this.switcher.add(name, true);

			// Remove any other active tab
			this.dom.sidebar.find('.active').removeClass('active');

			var group = tab.parents('ul').prev('.header');

			// Is this group not already expanded?
			group.each(function() {
				var $this = $(this);
				if (!$this.hasClass('expanded')) {
					$this.addClass('expanded');
				}
			});

			// Is this group even visible?
			var scrollElem = $(this.dom.simpleBar.getScrollElement());

			if (!tab.visible()) {
				scrollElem.scrollTop(scrollElem.scrollTop() + tab.position().top - (scrollElem.outerHeight() / 2) + (tab.outerHeight() / 2));
			}

			this.syncBarStatus();

			return tab.addClass('active');

		},

		switcher: {

			add: function(name, active) {

				if (!name.length) return false;

				// Remove any other active tabs
				FASTyle.dom.switcher.find('.active').removeClass('active');

				// Load the button in the switcher
				var tab = FASTyle.dom.switcher.find('[data-title="' + name + '"]');

				if (!tab.length) {

					// Add this tab to the currently active tabs
					var storage = FASTyle.readLocalStorage();

					try {
						var currentOpenedTabs = storage[FASTyle.sid].openedTabs;
					} catch (e) {}

					if (!FASTyle.exists(currentOpenedTabs)) {
						currentOpenedTabs = [];
					}

					if (currentOpenedTabs.indexOf(name) == -1) {
						currentOpenedTabs.push(name);
					}

					FASTyle.addToLocalStorage({
						openedTabs: currentOpenedTabs
					});

					var className = (active) ? ' active' : '';

					// Add the tab to the DOM
					FASTyle.dom.switcher.prepend('<div data-title="' + name + '" title="' + name + '" class="swiper-slide' + className + '"><i class="delete icon-cancel"></i>' + name + '</div>');
					FASTyle.swiper.update();

					return true;

				} else {
					return tab.addClass('active');
				}

			},

			remove: function(name) {

				var tab = FASTyle.dom.switcher.find('[data-title="' + name + '"]');

				if (tab.length) {

					if (tab.is(':only-child')) {
						return false;
					}

					// Remove this tab from the currently active tabs
					var storage = FASTyle.readLocalStorage();

					try {
						var currentOpenedTabs = storage[FASTyle.sid].openedTabs;
					} catch (e) {}

					if (FASTyle.exists(currentOpenedTabs)) {

						var index = currentOpenedTabs.indexOf(name);

						if (index > -1) {
							currentOpenedTabs.splice(index, 1);
						}

						FASTyle.addToLocalStorage({
							openedTabs: currentOpenedTabs
						});

					}
					
					// Remove this resource from cache (forcing a reload upon next loading)
					FASTyle.removeResourceFromCache(name);

					var loadNew = (tab.hasClass('active')) ? true : false;

					tab.remove();
					
					// Remove any tooltip, which should disappear on blur (but the event doesn't fire if we close the tab)
					$('.tipsy').remove();
					
					FASTyle.swiper.update();
					
					// Remove the not saved marker
					FASTyle.dom.mainContainer.find('[data-title="' + name + '"]').removeClass('not-saved');
					
					// Switch to the first item if this is the active tab
					if (loadNew) {
						FASTyle.loadResource(FASTyle.dom.switcher.find('[data-title]:first-child').data('title'));
					}

					return true;

				}

				return false;

			},

		},

		syncBarStatus: function() {

			// Clear existing attributes
			$.map(['dateline', 'status', 'attachedto', 'dateline'], function(item) {
				return FASTyle.dom.bar.removeAttr(item);
			});

			var currentTab = this.dom.sidebar.find('[data-title="' + this.currentResource.title + '"]');
			var attributes = currentTab.data();

			attributes.dateline = (this.exists(currentTab.data('status'))) ? this.lang.lastEditedPrefix + this.processDateline(this.currentResource.dateline) : '';

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

			var ext = this.getExtension(this.currentResource.title);
			var mode = (ext == 'css') ? 'text/css' : (ext == 'js') ? 'text/javascript' : 'text/html';

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
				collapseIdentical: true,
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
			this.dom.mergeView.prepend('<div class="label"><div><span class="button current">' + this.lang.current + '</span></div><div><span class="button original">' + this.lang.original + '</span></div></div>');

			this.dom.textarea.parents('form').on('submit', function() {
				return FASTyle.dom.textarea.val(FASTyle.getEditorContent());
			});

			this.dom.editor.on('changes', function(a, b, event) {

				return (!FASTyle.switching) ?
					FASTyle.dom.mainContainer.find('[data-title].active').addClass('not-saved') :
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

			var mode = 'text/html';
			if (typeof this.currentResource.title !== 'undefined') {

				var ext = this.getExtension(this.currentResource.title);
				mode = (ext == 'css') ? 'text/css' : (ext == 'js') ? 'text/javascript' : 'text/html';

			}

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
					FASTyle.dom.mainContainer.find('[data-title].active').addClass('not-saved') :
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
				if (FASTyle.sid != -1 && !FASTyle.exists(currentTab.data('status'))) {
					currentTab.data('status', 'modified').attr('status', 'modified');
				}

				// Remove the "not saved" marker
				FASTyle.dom.mainContainer.find('[data-title="' + data.title + '"]').removeClass('not-saved');

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
			var ext = this.getExtension(params.title);

			// Stylesheet
			if (ext == 'css') {

				params.module = 'style-themes';
				params.action = 'edit_stylesheet';
				params.mode = 'advanced';
				params.tid = this.tid;
				params.file = this.currentResource.title;
				params.stylesheet = content;

			}
			// Scripts
			else if (ext == 'js') {

				params.module = 'style-fastyle';
				params.action = 'edit_javascript';
				params.content = content;
				params.api = 1;

			}
			// Templates
			else {

				params.module = 'style-templates';
				params.action = 'edit_template';
				params.sid = this.sid;
				params.template = content;

				// This is NOT the theme ID, but the template ID!
				params.tid = parseInt(this.dom.sidebar.find('[data-title="' + this.currentResource.title + '"]').data('tid'));

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
					return FASTyle.message(response.message, true);
				}

				// Show success message
				return (response.message) ? FASTyle.message(response.message) : false;

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
			var ext = this.getExtension(title);
			if (ext == 'css') {
				group = 'stylesheets';
			} else if (ext == 'js') {
				group = 'javascripts';
			}
			// Ungrouped template
			else if (typeof this.resourcesList[group] === 'undefined') {
				group = 'ungrouped';
			}

			return group;

		},

		addToResourceList: function(title) {

			var group = this.findResourceGroup(title);
			var ext = this.getExtension(title);

			this.resourcesList[group].push(title);

			// Sort alphabetically if it's a template or script
			if (!ext || ext == 'js') {
				this.resourcesList[group].sort();
			}

			return this.resourcesList[group].indexOf(title);

		},

		removeFromResourceList: function(title) {

			var ext = this.getExtension(title);

			var group = this.findResourceGroup(title);
			var index = this.resourcesList[group].indexOf(title);

			if (index < 0) {
				return false;
			}

			delete this.resourcesList[group][index];

			// Sort alphabetically if it's a template or script
			if (!ext || ext == 'js') {
				this.resourcesList[group].sort();
			}

			return (index > 0) ? index - 1 : index;

		},

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

		exists: function(variable) {
			return (typeof variable !== 'undefined' && variable != null && variable) ? true : false;
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
				return this.lang.date.yesterday + hourTime;
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
						return this.lang.date.secondSingle;
					}

					return diff + this.lang.date.secondsPlural;

				}
				// Minutes ago
				else if (diff < hour) {

					if (diff < minute * 2) {
						return this.lang.date.minuteSingle;
					}

					return Math.floor(diff / minute) + this.lang.date.minutesPlural;

				}
				// Hours ago
				else if (diff < day) {

					if (diff < hour * 2) {
						return this.lang.date.hourSingle;
					}

					return Math.floor(diff / hour) + this.lang.date.hoursPlural;

				}

				return this.lang.date.today + hourTime;

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

		},

		getExtension: function(name) {

			var ext = name.substr(name.lastIndexOf('.') + 1);

			return (ext == name) ? '' : ext;

		},

		message: function(message, error) {
			return (!error) ? $.jGrowl(message) : $.jGrowl(message, {
				themeState: 'error'
			});
		}

	}

})(jQuery, window, document);