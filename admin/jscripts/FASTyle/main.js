var FASTyle = {

	switching: false,
	tid: 0,
	sid: 0,
	spinner: {},
	deferred: null,
	dom: {},

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
			top: '50%',
			left: '30px',
			shadow: false,
			hwaccel: false,
			position: 'relative'
		}

		FASTyle.dom.selector = $('select[name="quickjump"]');
		FASTyle.dom.title = $('input[name="title"]');

		switch (type) {

			case 'templates':

				FASTyle.spinner = new Spinner(FASTyle.spinner.opts).spin();

				FASTyle.useEditor = (typeof editor !== 'undefined') ? true : false;

				FASTyle.dom.tid = $('input[name="tid"]');
				FASTyle.dom.textarea = $('textarea[name="template"]');
				FASTyle.dom.editor = (FASTyle.useEditor) ? editor : null;

				FASTyle.dom.textarea.parents('td').wrapInner('<div class="fastyle" />');
				FASTyle.dom.mainContainer = $('.fastyle');
				
				FASTyle.dom.textarea.before('<div id="tabs-wrapper"><ul id="tabs" class="tabs"></ul></div>');
				FASTyle.dom.switcher = $('ul#tabs');
				
				FASTyle.dom.mainContainer.prepend('<div class="sidebar" />');
				FASTyle.dom.sidebar = FASTyle.dom.mainContainer.children('.sidebar');

				// Move the quick jumper and load select2 onto it
				FASTyle.dom.selector.appendTo(FASTyle.dom.sidebar);
				$('select[name="sid"]').appendTo(FASTyle.dom.sidebar);
				FASTyle.dom.sidebar.find('select').select2({
					width: '100%'
				});

				// Hide the title
				if (FASTyle.dom.selector.length) {
					FASTyle.dom.title.appendTo(FASTyle.dom.title.parents('form')).attr('type', 'hidden');
				}
				
				// Remove unnecessary elements
				FASTyle.dom.mainContainer.parents('tr').siblings().find('link').appendTo('head');
				FASTyle.dom.mainContainer.parents('tr').siblings().remove();
				
				// Add the close all button
				FASTyle.dom.sidebar.append('<span class="close_all_button"><input type="button" class="submit_button close_all" value="Close all tabs" /></span>');
				
				// Load the current template into the switcher
				FASTyle.templateEditor.loadButton(FASTyle.dom.title.val(), true);

				FASTyle.templateEditor.saveCurrent();

				// Load the previously opened tabs
				var currentlyOpen = Cookie.get('active-templates-' + FASTyle.sid);
				if (typeof currentlyOpen !== 'undefined') {

					currentlyOpen = currentlyOpen.split('|');
					$.each(currentlyOpen, function(k, v) {
						FASTyle.templateEditor.loadButton(v);
					});

				}
				
				// Close all tabs
				$('body').on('click', '.close_all', function(e) {
					
					e.preventDefault();
					
					FASTyle.dom.switcher.find('a:not(.active) .close').click();
					
				});

				// Close tab
				$('body').on('click', '#tabs span.close', function(e) {

					e.stopImmediatePropagation();

					var _this = $(this);
					var d = true;
					var name = $.trim(_this.parent('a').clone().children().remove().end().text());

					if (_this.parent('a').hasClass('not_saved')) {
						d = confirm('You have unsaved changes in the template "' + name + '". Would you like to close it anyway?');
					}

					if (d) {
						FASTyle.templateEditor.unloadTemplate(name);
					}

				});

				// Mark tabs as not saved when edited
				if (FASTyle.useEditor) {

					FASTyle.dom.editor.on('changes', function(a, b, event) {

						if (!FASTyle.switching) {
							FASTyle.dom.switcher.find('.' + FASTyle.dom.title.val()).addClass('not_saved');
						} else {
							FASTyle.switching = false;
						}

					});

				} else {

					FASTyle.dom.textarea.on('keydown', function(e) {
						if (e.which !== 0 && e.charCode !== 0 && !e.ctrlKey && !e.metaKey && !e.altKey) {
							FASTyle.dom.switcher.find('.' + FASTyle.dom.title.val()).addClass('not_saved');
						}
					});

				}

				// Switch to template
				$('body').on('click', '#tabs a', function(e) {

					e.preventDefault();

					var name = $(this).text();

					FASTyle.templateEditor.saveCurrent();

					if (name != FASTyle.dom.title.val()) {
						FASTyle.templateEditor.loadTemplate(name);
					}

					return false;

				});
				
				// Listen to changes in the quick jumper
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

							FASTyle.sendRequest('GET', 'index.php?action=get_templates', {
									'gid': gid,
									'sid': Number(FASTyle.url.getParameter('sid'))
								},
								(response) => {

									// Stop the spinner
									spinner.stop();

									a.parents('tr').after(response);

									a.data('expanded', true);

									updateUrls(gid);

								}
							);

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
						return FASTyle.save.call(this, e);
					});
				}

				var edit_stylesheet = $('#edit_stylesheet');
				if (edit_stylesheet.length) {
					edit_stylesheet.submit(function(e) {
						return FASTyle.save.call(this, e);
					});
				}

				var edit_stylesheet_simple = $('form[action*="edit_stylesheet"]');
				if (edit_stylesheet_simple.length) {
					$(document).on('submit', edit_stylesheet_simple, function(e) {
						return FASTyle.save.call(this, e);
					});
				}

				var edit_settings = $('#change');
				if (edit_settings.length) {
					edit_settings.submit(function(e) {
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
								var submitButton = $('.submit_button[name="continue"], .submit_button[name="save"], #change .submit_button');
								if (submitButton.length) {
									event.preventDefault();
									submitButton.click();
								}
								break;

								// + F = search template
							case 'f':
								if (FASTyle.dom.selector.length) {
									event.preventDefault();
									FASTyle.dom.selector.select2('open');
									$('.select2-search input').focus();
								}
								break;

						}

					}

					// ALT
					if (event.altKey) {

						switch (String.fromCharCode(event.which).toLowerCase()) {

							// + W = close tab
							case 'w':
								var closeButton = $('#tabs a.active .close');
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

			FASTyle.dom.switcher.find(':not(.' + name + ')').removeClass('active');

			FASTyle.dom.title.val(name);
			FASTyle.dom.tid.val(parseInt(id));

			// Switch template in editor/textarea
			if (FASTyle.useEditor) {

				FASTyle.dom.editor.setValue(template);
				FASTyle.dom.editor.focus();
				FASTyle.dom.editor.clearHistory();
				
				var templateOptions = FASTyle.templateEditor.templates[name];
				
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
				FASTyle.dom.textarea.val(template);
				FASTyle.dom.textarea.focus();
			}

			// Stop the spinner
			FASTyle.spinner.stop();
			
			// Reset the selector's value
			FASTyle.dom.selector.select2('val', '');

			// Update the page URL and title
			var currentTitle = FASTyle.url.getParameter('title');
			if (currentTitle != name) {
				
				history.replaceState(null, '', FASTyle.url.replaceParameter(window.location.href, 'title', name));
				document.title = document.title.replace(currentTitle, name);
				
				var titleElem = $('.border_wrapper .title');
				titleElem.text(titleElem.text().replace(currentTitle, name));
				
			}
			
			return true;

		},

		loadButton: function(name, active) {
			
			if (!name.length) return false;

			// Load the button in the switcher
			var tab = FASTyle.dom.switcher.find('.' + name);

			var className = (active) ? ' active' : '';

			if (!tab.length) {
				
				FASTyle.dom.switcher.append('<li><a class="' + name + className + '">' + name + ' <span class="close"></span></a></li>');
				
				if (active) {
					FASTyle.dom.switcher.scrollLeft(99999);
				}
				
			} else if (className) {
				tab.addClass(className);
			}

		},

		removeButton: function(name) {

			var tab = FASTyle.dom.switcher.find('.' + name);

			if (tab.length)  {

				if (tab.parent('li').is(':only-child')) {
					return false;
				}

				var loadNew = (tab.hasClass('active')) ? true : false;

				tab.closest('li').remove();

				// Switch to the first item if this is the active tab
				if (loadNew) {
					FASTyle.templateEditor.loadTemplate($('#tabs li:first a').text());
				}

				return true;

			}

			return false;

		},

		saveCurrent: function() {

			var current_template = (FASTyle.useEditor) ? FASTyle.dom.editor.getValue() : FASTyle.dom.textarea.val();

			return FASTyle.templateEditor.saveTemplate(FASTyle.dom.title.val(), current_template, FASTyle.dom.tid.val());

		},

		saveTemplate: function(name, template, tid) {

			FASTyle.templateEditor.templates[name] = {
				'tid': parseInt(tid),
				'template': template
			};

			// Save the current editor status
			if (FASTyle.useEditor) {
				FASTyle.templateEditor.templates[name].history = FASTyle.dom.editor.getHistory();
				FASTyle.templateEditor.templates[name].scrollInfo = FASTyle.dom.editor.getScrollInfo();
				FASTyle.templateEditor.templates[name].cursorPosition = FASTyle.dom.editor.getCursor();
				FASTyle.templateEditor.templates[name].selections = FASTyle.dom.editor.listSelections();
			}

			// Add this template to the opened tabs cache
			var currentlyOpen = Cookie.get('active-templates-' + FASTyle.sid);
			var newCookie = (typeof currentlyOpen !== 'undefined' && currentlyOpen.length) ? currentlyOpen.split('|') : [name];

			if (newCookie.indexOf(name) == -1) {
				newCookie.push(name);
			}

			Cookie.set('active-templates-' + FASTyle.sid, newCookie.join('|'));

		},

		unloadTemplate: function(name) {

			name = name.trim();

			if (!FASTyle.templateEditor.removeButton(name)) {
				return false;
			}

			delete FASTyle.templateEditor.templates[name];

			// Delete this template from the opened tabs cache
			var currentlyOpen = Cookie.get('active-templates-' + FASTyle.sid);
			var newCookie = (typeof currentlyOpen !== 'undefined' && currentlyOpen.length) ? currentlyOpen.split('|') : '';

			var index = newCookie.indexOf(name);

			if (index > -1) {
				newCookie.splice(index, 1);
			}

			Cookie.set('active-templates-' + FASTyle.sid, newCookie.join('|'));

		},

		loadTemplate: function(name) {

			name = name.trim();

			var t = FASTyle.templateEditor.templates[name];

			// Launch the spinner
			FASTyle.spinner.spin();
			$('.close_all_button').after(FASTyle.spinner.el);

			if (typeof t !== 'undefined')  {
				return FASTyle.templateEditor.switchToTemplate(name, t.template, t.tid);
			} else {
				
				var url = 'index.php?module=style-templates';
				var data = {
					'action': 'edit_template',
					'sid': FASTyle.sid,
					'get_template_ajax': 1,
					'title': name
				}
				
				return FASTyle.sendRequest('POST', url, data, (response) => {
					return FASTyle.templateEditor.switchToTemplate(name, response.template, response.tid);
				});

			}

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

		var opts = $.extend(true, {}, FASTyle.spinner.opts);

		opts.top = '50%';
		opts.left = '50%';
		opts.position = 'absolute';

		var spinner = new Spinner(opts).spin();
		spinnerContainer.append(spinner.el);

		var url = $this.attr('action') + '&ajax=1';

		FASTyle.sendRequest('POST', url, $this.serialize(), (response) => {
			
			// Stop the spinner
			spinner.stop();

			// Remove the "not saved" marker
			if (FASTyle.dom.switcher.length) {
				FASTyle.dom.switcher.find('.active').removeClass('not_saved');
			}

			// Restore the button
			saveButtonContainer.html(saveButtonHtml);

			// Notify the user
			$.jGrowl(response.message);

			// Eventually handle the updated tid
			if (response.tid && FASTyle.dom.tid.length) {
				FASTyle.dom.tid.val(Number(response.tid));
			}
			
		});

		return false;

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