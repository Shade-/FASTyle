! function(t) {
	var i = t(window);
	t.fn.visible = function(t, e, o) {
		if (!(this.length < 1)) {
			var r = this.length > 1 ? this.eq(0) : this,
				n = r.get(0),
				f = i.width(),
				h = i.height(),
				o = o ? o : "both",
				l = e === !0 ? n.offsetWidth * n.offsetHeight : !0;
			if ("function" == typeof n.getBoundingClientRect) {
				var g = n.getBoundingClientRect(),
					u = g.top >= 0 && g.top < h,
					s = g.bottom > 0 && g.bottom <= h,
					c = g.left >= 0 && g.left < f,
					a = g.right > 0 && g.right <= f,
					v = t ? u || s : u && s,
					b = t ? c || a : c && a;
				if ("both" === o) return l && v && b;
				if ("vertical" === o) return l && v;
				if ("horizontal" === o) return l && b
			} else {
				var d = i.scrollTop(),
					p = d + h,
					w = i.scrollLeft(),
					m = w + f,
					y = r.offset(),
					z = y.top,
					B = z + r.height(),
					C = y.left,
					R = C + r.width(),
					j = t === !0 ? B : z,
					q = t === !0 ? z : B,
					H = t === !0 ? R : C,
					L = t === !0 ? C : R;
				if ("both" === o) return !!l && p >= q && j >= d && m >= L && H >= w;
				if ("vertical" === o) return !!l && p >= q && j >= d;
				if ("horizontal" === o) return !!l && m >= L && H >= w
			}
		}
	}
}(jQuery);

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
		orderTimeout: null,
		splitMode: {},
		swiper: null,
		lang: {
			confirm: {
				'delete': 'Are you sure you want to delete {1}?',
				'revert': 'Are you sure you want to revert {1}?',
				'group': 'Are you sure you want to delete this whole template group?',
				'enableQuickMode': 'Quick mode allows you to perform actions without confirmation dialogs. Are you sure you want to enable quick mode?',
				'closeTab': 'This asset has unsaved changes. Would you really like to close it? Changes will be lost.'
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

			if (typeof CodeMirror === 'undefined') {
				this.useEditor = 0;
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

			this.dom.leftview = $('#leftview');
			this.dom.rightview = $('#rightview');
			
			this.dom.instances = {};

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
			
			// Define mixed Twig+HTML
			if (this.useEditor) {
				CodeMirror.defineMode("htmltwig", function(config, parserConfig) {
				  return CodeMirror.overlayMode(CodeMirror.getMode(config, parserConfig.backdrop || "text/html"), CodeMirror.getMode(config, "twig"));
				});
			}

			// Sort stylesheets
			this.dom.sidebar.find('[data-prefix="stylesheets"]').sortable({
				animation: 150,
				onSort: function() {

					var order = this.toArray();

					clearTimeout(FASTyle.orderTimeout);

					FASTyle.orderTimeout = setTimeout(() => {

						var params = {
							module: 'style-fastyle',
							api: 1,
							my_post_key: FASTyle.postKey,
							action: 'saveorder',
							tid: FASTyle.tid,
							disporder: order
						}

						FASTyle.sendRequest('post', 'index.php', params, () => {});

					}, 3000);

				}
			});

			// Close tabs in switcher
			this.dom.switcher.on('click', '.delete', function(e) {

				e.preventDefault();

				if (!FASTyle.quickMode && $(this).closest('[data-title]').hasClass('not-saved')) {

					if (window.confirm(FASTyle.lang.confirm.closeTab) != true) {
						return false;
					}

				}
				
				var name = $(this).closest('[data-title]').data('title');
				
				FASTyle.destroySplitview(name);

				// Save the current resource's status
				FASTyle.saveCurrentResource();

				// Load the new one
				FASTyle.switcher.remove(name);

				return false;

			});

			// Expand/collapse
			this.dom.sidebar.find('.header').on('click', function(e) {
				return $(this).toggleClass('expanded');
			});

			this.resourcesList['ungrouped'] = [];

			// Tooltips
			this.dom.mainContainer.find('[tooltip-n]').tipsy({
				live: true,
				gravity: 'n',
				opacity: 1
			});
			
			this.dom.mainContainer.find('[tooltip-s]').tipsy({
				live: true,
				gravity: 's',
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
				
			// Load the previous editor state
			var currentStorage = this.readLocalStorage();

			// Load the editor
			$.when(this.loadNormalEditor()).then(() => {
	
				try {
					this.loadResource(currentStorage[this.sid].title);
				} catch (e) {
					// No previous state known
				}
			
			});

			// Mark tabs as not saved when edited (just for textareas: the editor handler must be attached every time it's initialized)
			if (!this.useEditor) {

				this.dom.textarea.on('keydown', (e) => {
					
					if (e.which !== 0 && e.charCode !== 0 && !e.ctrlKey && !e.metaKey && !e.altKey) {
						this.dom.mainContainer.find('[data-title].active').addClass('not-saved');
					}
					
				});

			}
			
			// Toggle sidebar
			this.dom.mainContainer.find('.toggler').on('click', (e) => {this.toggleSidebar()});
			
			// Open split view
			this.dom.mainContainer.find('.splitview').on('click', (e) => {this.openSplitview()});
			
			// Close split view
			this.dom.mainContainer.find('.uniview').on('click', (e) => {this.destroySplitview()});

			// Load resource
			this.dom.sidebar.on('click', 'ul [data-title]', function(e) {

				e.preventDefault();

				// Save the current resource's status
				FASTyle.saveCurrentResource();
				
				FASTyle.toggleSidebar();

				// Load the new one
				return FASTyle.loadResource($(this).data('title'));

			});

			// Load resource from the switcher
			this.dom.switcher.on('click', '[data-title]', function(e) {

				e.preventDefault();

				// Save the current resource's status
				FASTyle.saveCurrentResource();
				
				FASTyle.closeSidebar();

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
					FASTyle.dom.currentInstance.refresh();
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

			// Revert/delete/add/diff/twigify
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
				else if (mode == 'twigify') {
					return FASTyle.dom.currentInstance.setValue(FASTyle.twigify(FASTyle.dom.currentInstance.getValue()));
				}
				else if (mode == 'psr2') {
					return FASTyle.dom.currentInstance.setValue(FASTyle.psr2(FASTyle.dom.currentInstance.getValue()));
				}

				if (!FASTyle.quickMode && ['revert', 'delete'].indexOf(mode) > -1) {

					if (window.confirm(FASTyle.lang.confirm[mode].replace('{1}', FASTyle.currentResource.title)) != true) {
						return false;
					}

				}

				var data = {
					module: 'style-fastyle',
					api: 1,
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

						// Reset the not-saved marker
						FASTyle.dom.mainContainer.find('[data-title="' + data.title + '"]').removeClass('not-saved');

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
						return (FASTyle.useEditor) ? FASTyle.dom.currentInstance.setValue(response.content) : FASTyle.dom.textarea.val(response.content);
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
			/*
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
			*/

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

		loadResource: function(name, openingCachedSplitMode) {

			name = name.trim();

			if (name == this.currentResource.title || (this.exists(this.dom.instances.right) && [this.dom.instances.left.title, this.dom.instances.right.title].indexOf(name) > -1)) {
				return false;
			}

			var t = this.resources[name];

			// Launch the spinner
			var overlay = $(this.dom.currentInstance.getWrapperElement()).find('.overlay');
			
			overlay.show();

			if (this.exists(this.dom.instances.right) && !this.exists(this.dom.instances.right.title)) {
				this.addToSplitMode(name);
			}
				
			// Split mode?
			var open = instanceToFocus = '';
			
			if (!openingCachedSplitMode) {
					
				// Is the right instance already occupied by another resource?
				if (this.exists(this.dom.instances.right) && this.exists(this.dom.instances.right.title) && this.dom.instances.right.title != name) {
					this.closeSplitview();
				}
				
				$.each(this.splitMode, (left, right) => {
					
					if ([left, right].indexOf(name) > -1) {
						
						open = (name == left) ? right : left;
						
						instanceToFocus = (name == left) ? 'left' : 'right';
						
						return this.openSplitview();
						
					}
					
				});
				
			}
			
			if (instanceToFocus) {
				this.dom.currentInstance = this.dom.instances[instanceToFocus];
			}
			
			// Split mode load function
			var splitLoad = () => {
					
				if (open) {
					
					// Choose the opposite instance to load this resource in
					var focus = (name == this.dom.instances.left.title) ? 'right' : 'left';
					
					this.dom.currentInstance = this.dom.instances[focus];
					
					return this.loadResource(open, true);
					
				}
				
			};

			if (typeof t !== 'undefined')  {

				// Stop the spinner
				overlay.hide();

				return $.when(this.loadResourceInDOM(name, t.content, t.dateline)).then(splitLoad);

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
					overlay.hide();

					if (response.error) {
						return false;
					}

					this.addToResourceCache(name, response.content, response.dateline);
					return $.when(this.loadResourceInDOM(name, response.content, response.dateline)).then(splitLoad);

				});

			}

		},

		loadResourceInDOM: function(name, content, dateline) {
			
			var deferred = new $.Deferred();

			this.switching = 1;

			// Set this resource internally
			this.setCurrentResource(name);

			// Switch resource in editor/textarea
			if (this.useEditor) {

				// Return to normal view if previously we were in diff mode
				if (this.diffMode) {
					this.loadNormalEditor();
				}

				// Set the current instance title
				this.dom.currentInstance.title = this.currentResource.title;

				// Switch mode if we have to
				var newMode = this.chooseMode(name);

				if (this.dom.currentInstance.getOption('mode') != newMode) {
					this.dom.currentInstance.setOption('mode', newMode);
				}

				this.dom.currentInstance.setValue(content);
				this.dom.currentInstance.clearHistory();

				this.applyEditorStatus();
				
				this.dom.currentInstance.focus();

			} else {

				this.dom.textarea.val(content);
				this.dom.textarea.focus();

			}
			
			this.addToLocalStorage({
				title: name
			});

			// Remember tab
			return deferred.resolve().promise();

		},
		
		addToSplitMode: function(name) {
			return (this.splitMode[this.currentResource.title] = name);
		},
		
		removeFromSplitMode: function(name) {
			return delete this.splitMode[name];
		},

		markAsActive: function(name) {

			if (!name) {
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
		
		toggleSidebar: function() {
			return this.dom.sidebar.toggleClass('open');
		},
		
		closeSidebar: function() {
			return this.dom.sidebar.removeClass('open');
		},

		switcher: {

			add: function(name, active) {

				if (!name) {
					return false;
				}

				// Remove all the active tabs
				FASTyle.dom.switcher.find('.active').removeClass('active');

				// Load button in the switcher
				var tab = FASTyle.dom.switcher.find('[data-title="' + name + '"]');
					
				var split = FASTyle.findInSplitMode(name);

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
					var html = '<div data-title="' + name + '" title="' + name + '" class="swiper-slide' + className + '" tooltip-n><i class="delete icon-cancel"></i>' + name + '</div>';
					
					// Choose where to place this tab
					if (!$.isEmptyObject(split)) {
						
						FASTyle.dom.switcher.find(FASTyle.buildSelector(split.left)).after(html).addClass('active linked left');
						FASTyle.dom.switcher.find(FASTyle.buildSelector(split.right)).addClass('linked');
						
					}
					else {
						FASTyle.dom.switcher.prepend(html);
					}

					return FASTyle.swiper.update();

				} else {
					
					// Split mode, rearrange tabs
					if (!$.isEmptyObject(split)) {
						
						var opposite = FASTyle.dom.switcher.find(FASTyle.buildSelector(split.opposite));
						
						if (!opposite.length) {
							return FASTyle.switcher.add(split.opposite);
						}
						
						var left = FASTyle.dom.switcher.find(FASTyle.buildSelector(split.left));
						var right = FASTyle.dom.switcher.find(FASTyle.buildSelector(split.right));
						
						left.addClass('active linked left');
						right.addClass('active linked');
						
						right.insertAfter(left);
						
						return FASTyle.swiper.update();
						
					}
					else {
						return tab.addClass('active');
					}
					
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

			return (this.currentResource = this.resources[name]);

		},

		saveCurrentResource: function() {
			return (this.currentResource.title) ? this.addToResourceCache(this.currentResource.title, this.getEditorContent()) : false;
		},

		addToResourceCache: function(name, content, dateline) {
			
			if (!this.exists(name)) {
				return false;
			}

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
		
		buildSelector: function(name) {
			return '[data-title="' + name + '"]';
		},

		saveEditorStatus: function() {

			try {

				this.resources[this.currentResource.title].history = this.dom.currentInstance.getHistory();
				this.resources[this.currentResource.title].scrollInfo = this.dom.currentInstance.getScrollInfo();
				this.resources[this.currentResource.title].cursorPosition = this.dom.currentInstance.getCursor();
				this.resources[this.currentResource.title].selections = this.dom.currentInstance.listSelections();

			} catch (e) {
				// currentResource == undefined or resources[currentResource] == undefined
			}

		},

		applyEditorStatus: function() {

			var resourceOptions = this.resources[this.currentResource.title];

			try {

				// Edit history
				if (resourceOptions.history) {
					this.dom.currentInstance.setHistory(resourceOptions.history);
				}

				// Scrolling position and editor dimensions
				if (resourceOptions.scrollInfo) {
					this.dom.currentInstance.scrollTo(resourceOptions.scrollInfo.left, resourceOptions.scrollInfo.top);
					this.dom.currentInstance.setSize(resourceOptions.scrollInfo.clientWidth, resourceOptions.scrollInfo.clientHeight);
				}

				// Cursor position
				if (resourceOptions.cursorPosition) {
					this.dom.currentInstance.setCursor(resourceOptions.cursorPosition);
				}

				// Selections
				if (resourceOptions.selections) {
					this.dom.currentInstance.setSelections(resourceOptions.selections);
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
			this.dom.currentInstance.toTextArea();

			this.dom.textarea.hide();

			var mode = this.chooseMode(this.currentResource.title);

			// Save the original value to the cache
			try {

				this.resources[this.currentResource.title].original = original;
				this.resources[this.currentResource.title].diffMode = 1;

			} catch (e) {
				// currentResource == undefined
			}
			
			// Empty the alternative view panel
			this.dom.rightview.empty();

			// Load the merge view instance
			this.dom.currentInstance = CodeMirror.mergeview(this.dom.rightview[0], {
				orig: original,
				value: this.dom.textarea.val(),
				connect: 'align',
				lineNumbers: true,
				lineWrapping: true,
				collapseIdentical: true,
				foldGutter: true,
				gutters: ["CodeMirror-linenumbers", "CodeMirror-foldgutter"],
// 				indentWithTabs: true,
				indentUnit: 4,
				mode: mode,
				theme: "material",
				keyMap: "sublime"
			}).editor();

			// Reapply the previous editor status
			this.applyEditorStatus();

			// Add the labels
			this.dom.rightview.prepend('<div class="label"><div><span class="button current">' + this.lang.current + '</span></div><div><span class="button original">' + this.lang.original + '</span></div></div>');

			this.dom.textarea.parents('form').on('submit', function() {
				return FASTyle.dom.textarea.val(FASTyle.getEditorContent());
			});

			this.dom.currentInstance.on('changes', function(a, b, event) {

				return (!FASTyle.switching) ?
					FASTyle.dom.mainContainer.find('[data-title="' + FASTyle.currentResource.title + '"]').addClass('not-saved') :
					(FASTyle.switching = 0);

			});

			this.addToLocalStorage({
				diffMode: 1
			});

			return this.dom.bar.find('.diff').addClass('active');

		},

		loadNormalEditor: function() {
			
			var deferred = new $.Deferred();

			if (!this.useEditor) {
				return deferred.resolve().promise();
			}

			this.saveEditorStatus();
			
			this.dom.leftview.empty();
			
			// Open split view by appending a new textarea
			if ($('#editor').length == 0) {
				this.dom.textarea = $('<textarea id="editor" name="editor" value="">').appendTo(this.dom.leftview);
			}

			// Populate textarea with the current editor value
			this.dom.textarea.val(this.getEditorContent());

			var mode = this.chooseMode(this.currentResource.title);

			// Load the standard editor from our textarea
			this.dom.instances.left = CodeMirror.fromTextArea(document.getElementById("editor"), {
				lineNumbers: true,
				lineWrapping: true,
				foldGutter: true,
				gutters: ["CodeMirror-linenumbers", "CodeMirror-foldgutter"],
// 				indentWithTabs: true,
				indentUnit: 4,
				mode: mode,
				theme: "material",
				keyMap: "sublime"
			});
			
			this.dom.currentInstance = this.dom.instances.left;
			this.dom.instances.left.on('focus', function() {
				
				FASTyle.setCurrentResource(FASTyle.dom.instances.left.title);
				FASTyle.markAsActive(FASTyle.dom.instances.left.title);
				
				// Mark the instance we're using
				FASTyle.instanceInUse = 'left';
				
				return (FASTyle.dom.currentInstance = FASTyle.dom.instances.left);
				
			});
			
			this.dom.instances.left.on('changes', function(a, b, event) {

				return (!FASTyle.switching) ?
					FASTyle.dom.mainContainer.find('[data-title="' + FASTyle.dom.instances.left.title + '"]').addClass('not-saved') :
					(FASTyle.switching = 0);

			});

			this.applyEditorStatus();

			this.diffMode = 0;

			// Load overlay
			this.addOverlay();

			this.addToLocalStorage({
				diffMode: 0
			});

			this.dom.bar.find('.diff').removeClass('active');
			
			return deferred.resolve().promise();

		},
		
		openSplitview: function() {
			
			var deferred = new $.Deferred();
			
			if (!this.useEditor || this.exists(this.dom.instances.right)) {
				return deferred.resolve().promise();
			}
			
			this.dom.mainContainer.addClass('split');
			
			// Refresh the left panel to fit to the new sizing
			if (this.exists(this.dom.instances.left)) {
				this.dom.instances.left.refresh();
			}
			
			// Open split view by appending a new textarea
			if ($('#rightInstance').length == 0) {
				this.dom.rightview.append('<textarea id="rightInstance" name="rightInstance" value="">');
			}

			var mode = this.chooseMode(this.currentResource.title);

			// Load the standard editor from our textarea
			this.dom.instances.right = CodeMirror.fromTextArea(document.getElementById("rightInstance"), {
				lineNumbers: true,
				lineWrapping: true,
				foldGutter: true,
				gutters: ["CodeMirror-linenumbers", "CodeMirror-foldgutter"],
// 				indentWithTabs: true,
				indentUnit: 4,
				mode: mode,
				theme: "material",
				keyMap: "sublime"
			});
			
			this.dom.currentInstance = this.dom.instances.right;
			this.dom.instances.right.on('focus', function() {
				
				FASTyle.setCurrentResource(FASTyle.dom.instances.right.title);
				FASTyle.markAsActive(FASTyle.dom.instances.right.title);
				
				// Mark the instance we're using
				FASTyle.instanceInUse = 'right';
				
				return (FASTyle.dom.currentInstance = FASTyle.dom.instances.right);
				
			});

			this.dom.instances.right.on('changes', function(a, b, event) {

				return (!FASTyle.switching) ?
					FASTyle.dom.mainContainer.find('[data-title="' + FASTyle.dom.instances.right.title + '"]').addClass('not-saved') :
					(FASTyle.switching = 0);

			});
			
			this.addOverlay();
			
			return deferred.resolve().promise();
			
		},
		
		closeSplitview: function() {
			
			var deferred = $.Deferred();
			
			if (!this.useEditor || !this.exists(this.dom.instances.right)) {
				return deferred.resolve().promise();
			}
			
			this.dom.mainContainer.removeClass('split');
			
			// 1. Save
			$.each(this.dom.instances, (k, v) => {
				return this.addToResourceCache(v.title, v.getValue());
			});
			
			// Cache the last focused instance locally before it gets wiped
			var name = (this.dom.instances[this.instanceInUse].title) ? this.dom.instances[this.instanceInUse].title : this.currentResource.title;
			var t = this.resources[name];
			
			// Revert the current instance to the left panel
			this.dom.currentInstance = this.dom.instances.left;
			
			// 2. Destroy
			this.dom.instances.right.toTextArea();
			delete this.dom.instances.right;
			
			this.dom.rightview.empty();
			
			this.dom.instances.left.refresh();
			
			return deferred.resolve().promise();
			
		},
		
		destroySplitview: function(name) {
			
			if (!this.exists(this.dom.instances.right) || (name && this.findInSplitMode(name).left)) {
				return false;
			}
				
			this.removeFromSplitMode(this.dom.instances.left.title);
			
			this.dom.switcher.find('[data-title="' + this.dom.instances.left.title + '"], [data-title="' + this.dom.instances.right.title + '"]').removeClass('linked left');
			
			this.setCurrentResource(this.dom.instances.left.title);
			this.markAsActive(this.dom.instances.left.title);
			this.syncBarStatus();
			
			return this.closeSplitview();
			
		},
		
		findInSplitMode: function(name) {
			
			var obj = {};
			
			$.each(this.splitMode, (left, right) => {
				
				if ([left, right].indexOf(name) > -1) {
					
					var current = (name == left) ? left : right;
					var opposite = (current == left) ? right : left;
					
					return (obj = {
						left: left,
						right: right,
						current: current,
						opposite: opposite
					});
					
				}
				
			});
			
			return obj;
			
		},
		
		twigify: function(content) {
			
			content = content.replace(/\{\$([^}]+)\}/g, "{{ $1 }}");
			content = content.replace(/\{\{([^->]*)->([^->]*)\}\}/g, "{{$1.$2}}");
			content = content.replace(/\['([^\'\]]+)'\]/g, ".$1");
			content = content.replace(/\t/g, "    ");
			content = content.replace("&&", "and");
			content = content.replace("||", "or");
			content = content.replace(/^\s*$/g, "");
			
			return content;
			
		},
		
		psr2: function(content) {
			
			content = content.replace(/\}(\n|\t|\s)*else([^{]*)\{/g, "} else$2{");
			content = content.replace(/(if|while|for|foreach|switch|else)\s*(\(([^{]*?)\)|)(\n*)(\t*)\{/g, "$1 $2 {");
			content = content.replace(/^\s*$/g, "");
			content = content.replace(/\t/g, "    ");
			content = content.replace(/else(\s*)if/g, "elseif");
			content = content.replace(/else(\s*)\{/g, "else {");
			
			return content;
			
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
			// Files
			else if (['js', 'php', 'twig'].indexOf(ext) > -1) {

				params.module = 'style-fastyle';
				params.action = 'edit_file';
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
			return (this.useEditor && typeof this.dom.currentInstance !== 'undefined') ? this.dom.currentInstance.getValue() : this.dom.textarea.val();
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
		
		addOverlay: function() {
			
			this.spinner.spin();
			
			this.dom.mainContainer.find('.overlay').remove();
			
			return $('<div class="overlay" />').append(this.spinner.el).hide().prependTo('.CodeMirror');
			
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
		
		chooseMode: function(name) {
			
			if (typeof name === 'undefined') {
				return 'text/html';
			}
			
			var ext = this.getExtension(name);
			return (ext == 'css') ? 'text/css'
				: (ext == 'js') ? 'text/javascript'
				: (ext == 'twig') ? 'htmltwig'
				: (ext == 'php') ? 'application/x-httpd-php'
				: 'text/html';
			
		},

		message: function(message, error) {
			return (!error) ? $.jGrowl(message) : $.jGrowl(message, {
				themeState: 'error'
			});
		}

	}

})(jQuery, window, document);