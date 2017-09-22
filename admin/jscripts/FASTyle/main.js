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

	init: function(sid, tid) {
		
		// Template set ID
		if (sid > 0) {
			FASTyle.sid = sid;
		}
		
		// Theme ID
		if (tid > 0) {
			FASTyle.tid = tid;
		}

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
		
		// Set the postKey
		var postKey = $('[name="my_post_key"]').val();
		
		if (postKey.length) {
			FASTyle.postKey = postKey;
		}

		FASTyle.dom.selector = $('select[name="quickjump"]');
		FASTyle.dom.title = $('input[name="title"]');
		FASTyle.dom.sidebar = $('.sidebar');
		FASTyle.dom.textarea = $('#editor');
		FASTyle.dom.mainContainer = $('.fastyle');
		
		// Expand/collapse
		FASTyle.dom.sidebar.find('.header').on('click', function(e) {
			return $(this).next('ul').toggleClass('expanded');
		});
		
		FASTyle.spinner = new Spinner(FASTyle.spinner.opts).spin();
		FASTyle.useEditor = (typeof editor !== 'undefined') ? true : false;
		FASTyle.dom.editor = (FASTyle.useEditor) ? editor : null;

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
		FASTyle.dom.sidebar.find('ul [data-title]').on('click', function(e) {

			e.preventDefault();

			var name = $(this).attr('data-title');

			FASTyle.storeCurrentResourceInCache();

			if (name != FASTyle.currentResource.title) {
				FASTyle.loadResource(name);
			}

			return false;

		});
		
		// Search resource
		FASTyle.dom.sidebar.find('input[name="search"]').on('keyup', function(e) {
			
			var val = $(this).val();
			
			if (!val) {
				FASTyle.dom.sidebar.find('.header+ul, .header+ul li').removeClass('expanded').removeAttr('style');
				return FASTyle.dom.sidebar.find('.header:not(.search)').show();
			}
			
			// Hide all groups
			FASTyle.dom.sidebar.find('.header:not(.search), .header+ul, .header+ul li').hide();
			
			// Show
			FASTyle.dom.sidebar.find('[data-title*="' + val + '"]').show().closest('ul').show().addClass('expanded').prev('.header').show();
			
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

	loadResourceInDOM: function(name, content, id) {

		FASTyle.switching = true;

		FASTyle.markAsActive(name, true);
		
		var tab = FASTyle.dom.sidebar.find('[data-title="' + name + '"]');

		FASTyle.dom.sidebar.find(':not([data-title="' + name + '"])').removeClass('active');

		// Switch resource in editor/textarea
		if (FASTyle.useEditor) {
			
			// Switch mode if we have to
			if (name.indexOf('.css') > -1) {
				
				if (FASTyle.dom.editor.getOption('mode') != 'text/css') {
					FASTyle.dom.editor.setOption('mode', 'text/css');
				}
				
			}
			else {
				
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

		// Stop the spinner
		FASTyle.spinner.stop();
		
		// Set this resource internally
		FASTyle.currentResource = {
			'title': name,
			'id': id
		};
		
		// Remember tab
		Cookie.set('active-resource-' + FASTyle.sid, name);

		// Update the title
		$('.border_wrapper .title').text(name);
		
		return true;

	},

	markAsActive: function(name, active) {
		
		if (!name.length) return false;
		
		// Find this tab and group
		var tab = FASTyle.dom.sidebar.find('[data-title="' + name + '"]');
		var group = tab.closest('ul');
		
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
					
		tab.addClass('active');

	},

	storeCurrentResourceInCache: function() {
		return (FASTyle.currentResource.title) ? FASTyle.storeResourceInCache(FASTyle.currentResource.title, FASTyle.getEditorContent()) : false;
	},

	storeResourceInCache: function(name, content) {
		
		name = name.trim();

		FASTyle.resources[name] = {
			'content': content
		};

		// Save the current editor status
		if (FASTyle.useEditor) {
			FASTyle.resources[name].history = FASTyle.dom.editor.getHistory();
			FASTyle.resources[name].scrollInfo = FASTyle.dom.editor.getScrollInfo();
			FASTyle.resources[name].cursorPosition = FASTyle.dom.editor.getCursor();
			FASTyle.resources[name].selections = FASTyle.dom.editor.listSelections();
		}

	},

	removeResourceFromCache: function(name) {

		name = name.trim();

		delete FASTyle.resources[name];

	},

	loadResource: function(name, type) {

		name = name.trim();

		var t = FASTyle.resources[name];
		
		if (!type) {
			type = (name.indexOf('.css') > -1) ? 'stylesheet' : 'template';
		}

		// Launch the spinner
		FASTyle.spinner.spin();
		$('.close_all_button').after(FASTyle.spinner.el);

		if (typeof t !== 'undefined')  {
			return FASTyle.loadResourceInDOM(name, t.content);
		} else {
			
			var url = 'index.php?module=style-fastyle';
			var data = {
				'get': type,
				'sid': FASTyle.sid,
				'tid': FASTyle.tid,
				'title': name
			}
			
			return FASTyle.sendRequest('POST', url, data, (response) => {
				return FASTyle.loadResourceInDOM(name, response.content);
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
		
		var data = FASTyle.buildRequestData();

		FASTyle.sendRequest('POST', 'index.php', data, (response) => {
			
			// Stop the spinner
			spinner.stop();

			// Remove the "not saved" marker
			if (FASTyle.dom.sidebar.length) {
				FASTyle.dom.sidebar.find('.active').removeClass('not-saved');
			}

			// Restore the button
			saveButtonContainer.html(saveButtonHtml);

			// Notify the user
			$.jGrowl(response.message);

			// Eventually handle the updated tid (fixes templates not saving through multiple calls when a template hasn't been edited before)
			if (response.tid && data.action == 'edit_template') {
				FASTyle.dom.sidebar.find('[data-title="' + data.title + '"]').attr('data-tid', Number(response.tid));
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