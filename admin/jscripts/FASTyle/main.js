var FASTyle = {

	switching: false,
	input: {
		switcher: '',
		title: '',
		tid: '',
		editor: '',
		textarea: '',
		selector: ''
	},
	tid: 0,
	sid: 0,
	spinner: {},
	deferred: null,

	init: function(type) {

		// Set the spinner default options
		FASTyle.spinner.opts = {
			lines: 9,
			length: 20,
			width: 9,
			radius: 19,
			scale: 0.25,
			corners: 1,
			color: '#000',
			opacity: 0.25,
			rotate: 0,
			direction: 1,
			speed: 1,
			trail: 60,
			fps: 20,
			zIndex: 2e9,
			className: 'spinner',
			top: '-13px',
			left: '-30px',
			shadow: false,
			hwaccel: false,
			position: 'relative'
		}

		FASTyle.input.selector = $('select[name="quickjump"]');
		FASTyle.input.title = $('input[name="title"]');

		switch (type) {

			case 'templates':

				FASTyle.spinner = new Spinner(FASTyle.spinner.opts).spin();

				FASTyle.useEditor = (typeof editor !== 'undefined') ? true : false;

				FASTyle.input.tid = $('input[name="tid"]');
				FASTyle.input.textarea = $('textarea[name="template"]');
				FASTyle.input.editor = (FASTyle.useEditor) ? editor : null;

				// Load select2 onto the quick jumper
				FASTyle.input.selector.select2({
					width: '400px'
				});

				FASTyle.input.textarea.before('<div id="tabs-wrapper"><ul id="fastyle_switcher" class="tabs"></ul></div>');
				FASTyle.input.switcher = $('#fastyle_switcher');

				// Load the current template into the switcher
				FASTyle.templateEditor.loadButton(FASTyle.input.title.val(), true);

				// Hide the title
				if (FASTyle.input.selector.length) {

					FASTyle.input.title.parents('form').prepend(FASTyle.input.title.clone().attr('type', 'hidden'));
					FASTyle.input.title.parents('tr').remove();

					FASTyle.input.title = $('input[name="title"]');

				}

				FASTyle.templateEditor.saveCurrent();

				// Load the previously opened tabs
				var currentlyOpen = Cookie.get('fastyle_tabs_opened');
				if (typeof currentlyOpen !== 'undefined') {

					currentlyOpen = currentlyOpen.split('|');
					$.each(currentlyOpen, function(k, v) {
						FASTyle.templateEditor.loadButton(v);
					});

				}

				// Close tab
				$('body').on('click', '#fastyle_switcher span.close', function(e) {

					e.stopImmediatePropagation();

					var _this = $(this);
					var d = true;

					if (_this.parent('a').hasClass('not_saved')) {
						d = confirm('You have unsaved changes in this tab. Would you like to close it anyway?');
					}

					if (d) {
						FASTyle.templateEditor.unloadTemplate(_this.parent('a').clone().children().remove().end().text());
					}

				});

				// Mark tabs as not saved when edited
				if (FASTyle.useEditor) {

					FASTyle.input.editor.on('changes', function(a, b, event) {

						if (!FASTyle.switching) {
							FASTyle.input.switcher.find('.' + FASTyle.input.title.val()).addClass('not_saved');
						} else {
							FASTyle.switching = false;
						}

					});

				} else {

					FASTyle.input.textarea.on('keydown', function(e) {
						if (e.which !== 0 && e.charCode !== 0 && !e.ctrlKey && !e.metaKey && !e.altKey) {
							FASTyle.input.switcher.find('.' + FASTyle.input.title.val()).addClass('not_saved');
						}
					});

				}

				// Switch to template
				$('body').on('click', '#fastyle_switcher a', function(e) {

					e.preventDefault();

					var name = $(this).text();

					FASTyle.templateEditor.saveCurrent();

					if (name != FASTyle.input.title.val()) {
						FASTyle.templateEditor.loadTemplate(name);
					}

					return false;

				});

				$('body').on('change', 'select[name="quickjump"]', function(e) {

					var name = this.value;

					if (name.length) {

						FASTyle.templateEditor.saveCurrent();

						FASTyle.templateEditor.loadTemplate(name);

					}

				});

				break;

			case 'templatelist':

				var updateUrls = function(gid) {

					var expanded_string = expand_list.join('|');

					// Update the url of every link
					$('.group' + gid + ' a:not([class])').each(function(k, v) {
						return ($(this).attr('href').indexOf('javascript:;') === -1) ? $(this).attr('href', FASTyle.url.replaceParameter($(this).attr('href'), 'expand', expanded_string)) : false;
					});

					// Update the current page url
					var currentExpand = FASTyle.url.getParameter('expand');
					if (currentExpand != expanded_string) {
						history.replaceState(null, '', FASTyle.url.replaceParameter(window.location.href, 'expand', expanded_string));
					}

				}

				var expanded = FASTyle.url.getParameter('expand');
				var expand_list = (typeof expanded !== 'undefined') ? expanded.split('|') : [];

				$('body').on('click', 'tr[id*="group_"] .first a', function(e) {

					e.preventDefault();

					var a = $(this);
					var url = a.attr('href'),
						string = '#group_';

					var gid = Number(url.substring(url.indexOf(string) + string.length));

					if (!gid || typeof gid == 'undefined') {
						return false;
					}

					// Check if there are rows already open
					var visible_rows = a.parents('tr').nextUntil('tr[id*="group_"]');

					if (visible_rows.length > 0 && !visible_rows.hasClass('group' + gid)) {

						visible_rows.addClass('group' + gid);
						a.data('expanded', true);

					}

					// Open
					if (a.data('expanded') != true) {

						var items = $('.group' + gid);

						expand_list.push(gid);

						if (items.length) {

							items.show();

							a.data('expanded', true);
							updateUrls(gid);

						} else {

							FASTyle.spinner.opts.top = '50%';
							FASTyle.spinner.opts.left = '120%';
							FASTyle.spinner.opts.position = 'absolute';

							var spinner = new Spinner(FASTyle.spinner.opts).spin();

							// Launch the spinner
							a.css('position', 'relative').append(spinner.el);

							$.ajax({
								type: 'GET',
								url: 'index.php?action=get_templates',
								data: {
									'gid': gid,
									'sid': Number(FASTyle.url.getParameter('sid'))
								},
								success: function(data) {

									// Delete the spinner
									spinner.stop();

									var html = $.parseJSON(data);

									a.parents('tr').after(html);

									a.data('expanded', true);

									updateUrls(gid);

								}
							});

						}

					}
					// Close
					else {

						a.data('expanded', false).parents('tr').siblings('.group' + gid).hide();

						FASTyle.utils.removeItemFromArray(expand_list, gid);
						updateUrls(gid);

					}

				});

				break;

			default:

				// Save templates/stylesheets/settings with AJAX
				var edit_template = $('#edit_template');
				if (edit_template.length) {
					edit_template.submit(function(e) {
						return FASTyle.ajaxSave.call(this, e);
					});
				}

				var edit_stylesheet = $('#edit_stylesheet');
				if (edit_stylesheet.length) {
					edit_stylesheet.submit(function(e) {
						return FASTyle.ajaxSave.call(this, e);
					});
				}

				var edit_stylesheet_simple = $('form[action*="edit_stylesheet"]');
				if (edit_stylesheet_simple.length) {
					$(document).on('submit', edit_stylesheet_simple, function(e) {
						return FASTyle.ajaxSave.call(this, e);
					});
				}

				var edit_settings = $('#change');
				if (edit_settings.length) {
					edit_settings.submit(function(e) {
						return FASTyle.ajaxSave.call(this, e);
					});
				}

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

								// + F = search template
							case 'f':
								if (FASTyle.input.selector.length) {
									event.preventDefault();
									FASTyle.input.selector.select2('open');
								}
								break;

						}

					}

					// ALT
					if (event.altKey) {

						switch (String.fromCharCode(event.which).toLowerCase()) {

							// + W = close tab
							case 'w':
								var closeButton = $('#fastyle_switcher a.active .close');
								if (closeButton.length) {
									event.preventDefault();
									closeButton.click();
								}
								break;

						}

					}

				});

				break;

		}

	},

	url: {

		getParameter: function(sParam) {
			var sPageURL = decodeURIComponent(window.location.search.substring(1)),
				sURLVariables = sPageURL.split('&'),
				sParameterName,
				i;

			for (i = 0; i < sURLVariables.length; i++) {
				sParameterName = sURLVariables[i].split('=');

				if (sParameterName[0] === sParam) {
					return sParameterName[1] === undefined ? true : sParameterName[1];
				}
			}
		},

		replaceParameter: function(url, paramName, paramValue) {
			if (paramValue == null) {
				paramValue = '';
			}

			var pattern = new RegExp('(' + paramName + '=).*?(&|$)');

			if (url.search(pattern) >= 0) {
				return url.replace(pattern, '$1' + paramValue + '$2');
			}

			return url + (url.indexOf('?') > 0 ? '&' : '?') + paramName + '=' + paramValue;
		}

	},

	templateEditor: {

		templates: {},

		switchToTemplate: function(name, template, id) {

			FASTyle.switching = true;

			FASTyle.templateEditor.loadButton(name, true);

			FASTyle.input.switcher.find(':not(.' + name + ')').removeClass('active');

			FASTyle.input.title.val(name);
			FASTyle.input.tid.val(parseInt(id));

			// Switch template in editor/textarea
			if (FASTyle.useEditor) {

				FASTyle.input.editor.setValue(template);
				FASTyle.input.editor.focus();
				FASTyle.input.editor.clearHistory();
				
				var templateOptions = FASTyle.templateEditor.templates[name];
				
				// Set the previously-saved editor status
				if (typeof templateOptions !== 'undefined') {
					
					// Edit history
					if (templateOptions.history) {
						FASTyle.input.editor.setHistory(templateOptions.history);
					}
					
					// Scrolling position and editor dimensions
					if (templateOptions.scrollInfo) {
						FASTyle.input.editor.scrollTo(templateOptions.scrollInfo.left, templateOptions.scrollInfo.top);
						FASTyle.input.editor.setSize(templateOptions.scrollInfo.clientWidth, templateOptions.scrollInfo.clientHeight);
					}
					
					// Cursor position
					if (templateOptions.cursorPosition) {
						FASTyle.input.editor.setCursor(templateOptions.cursorPosition);
					}
					
				}

			} else {
				FASTyle.input.textarea.val(template);
				FASTyle.input.textarea.focus();
			}

			// Stop the spinner
			FASTyle.spinner.stop();

			// Update the page URL
			var currentTitle = FASTyle.url.getParameter('title');
			if (currentTitle != name) {
				history.replaceState(null, '', FASTyle.url.replaceParameter(window.location.href, 'title', name));
			}
			
			return true;

		},

		loadButton: function(name, active) {

			// Load the button in the switcher
			var tab = FASTyle.input.switcher.find('.' + name);

			var className = (active) ? ' active' : '';

			if (!tab.length) {
				FASTyle.input.switcher.append('<li><a class="' + name + className + '">' + name + ' <span class="close"></span></a></li>');
			} else if (className) {
				tab.addClass(className);
			}

		},

		removeButton: function(name) {

			var tab = FASTyle.input.switcher.find('.' + name);

			if (tab.length)  {

				if (tab.parent('li').is(':only-child')) {
					return false;
				}

				var loadNew = (tab.hasClass('active')) ? true : false;

				tab.closest('li').remove();

				// Switch to the first item if this is the active tab
				if (loadNew) {
					FASTyle.templateEditor.loadTemplate($('#fastyle_switcher li:first a').text());
				}

				return true;

			}

			return false;

		},

		saveCurrent: function() {

			var current_template = (FASTyle.useEditor) ? FASTyle.input.editor.getValue() : FASTyle.input.textarea.val();

			return FASTyle.templateEditor.saveTemplate(FASTyle.input.title.val(), current_template, FASTyle.input.tid.val());

		},

		saveTemplate: function(name, template, tid) {

			FASTyle.templateEditor.templates[name] = {
				'tid': parseInt(tid),
				'template': template
			};

			// Save the current editor status
			if (FASTyle.useEditor) {
				FASTyle.templateEditor.templates[name].history = FASTyle.input.editor.getHistory();
				FASTyle.templateEditor.templates[name].scrollInfo = FASTyle.input.editor.getScrollInfo();
				FASTyle.templateEditor.templates[name].cursorPosition = FASTyle.input.editor.getCursor();
			}

			// Add this template to the opened tabs cache
			var currentlyOpen = Cookie.get('fastyle_tabs_opened');
			var newCookie = (typeof currentlyOpen !== 'undefined' && currentlyOpen.length) ? currentlyOpen.split('|') : [name];

			if (newCookie.indexOf(name) == -1) {
				newCookie.push(name);
			}

			Cookie.set('fastyle_tabs_opened', newCookie.join('|'));

		},

		unloadTemplate: function(name) {

			name = name.trim();

			if (!FASTyle.templateEditor.removeButton(name)) {
				return false;
			}

			delete FASTyle.templateEditor.templates[name];

			// Delete this template from the opened tabs cache
			var currentlyOpen = Cookie.get('fastyle_tabs_opened');
			var newCookie = (typeof currentlyOpen !== 'undefined' && currentlyOpen.length) ? currentlyOpen.split('|') : '';

			var index = newCookie.indexOf(name);

			if (index > -1) {
				newCookie.splice(index, 1);
			}

			Cookie.set('fastyle_tabs_opened', newCookie.join('|'));

		},

		loadTemplate: function(name) {

			name = name.trim();

			var t = FASTyle.templateEditor.templates[name];

			// Launch the spinner
			FASTyle.spinner.spin();
			FASTyle.input.selector.after(FASTyle.spinner.el);

			if (typeof t !== 'undefined')  {
				FASTyle.templateEditor.switchToTemplate(name, t.template, t.tid);
			} else {

				$.get('index.php?module=style-templates&action=edit_template&sid=' + FASTyle.sid + '&get_template_ajax=1&title=' + name, function(data) {

					data = JSON.parse(data);

					FASTyle.templateEditor.switchToTemplate(name, data.template, data.tid);

				});

			}

		}

	},

	ajaxSave: function(e) {

		$this = $(this);

		var pressed = $this.find("input[type=submit]:focus").attr("name");

		if (pressed == "close" || pressed == "save_close") return;

		e.preventDefault();

		var button = $('.submit_button[name="continue"], .submit_button[name="save"], .form_button_wrapper > label:only-child > .submit_button, #change .submit_button');
		var button_container = button.parent();
		var button_container_html = button_container.html();

		// Set up the container to be as much similar to the container 
		var spinnerContainer = $('<div></div>').hide();

		var buttonHeight = button.outerHeight(true);
		var buttonWidth = button.outerWidth(true);

		spinnerContainer.css({
			width: buttonWidth,
			height: buttonHeight,
			position: 'relative',
			'display': 'inline-block',
			'vertical-align': 'top'
		});

		// Replace the button with the spinner container
		button.replaceWith(spinnerContainer);

		var opts = $.extend(true, {}, FASTyle.spinner.opts);

		opts.top = '50%';
		opts.left = '50%';
		opts.position = 'absolute';

		var spinner = new Spinner(opts).spin();
		spinnerContainer.append(spinner.el);

		var url = $this.attr('action') + '&ajax=1';

		if (typeof fastyle_deferred === 'object' && fastyle_deferred.state() == 'pending') {
			fastyle_deferred.abort();
		}

		var data = $this.serialize();
		var oldName = FASTyle.input.title.val();

		FASTyle.deferred = $.ajax({
			type: "POST",
			url: url,
			data: data
		});

		$.when(
			FASTyle.deferred
		).done(function(d, t, response) {

			// Stop the spinner
			spinner.stop();

			// Remove the not_saved marker
			if (FASTyle.input.switcher.length) {
				FASTyle.input.switcher.find('.' + oldName).removeClass('not_saved');
			}

			// Restore the button
			button_container.html(button_container_html);

			var response = JSON.parse(response.responseText);

			// Notify the user
			$.jGrowl(response.message);

			// Eventually handle the updated tid
			if (response.tid && FASTyle.input.tid.length) {
				FASTyle.input.tid.val(Number(response.tid));
			}

		});

		return false;

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
		}

	}

}