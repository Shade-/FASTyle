// CodeMirror, copyright (c) by Marijn Haverbeke and others
// Distributed under an MIT license: http://codemirror.net/LICENSE
// Define search commands. Depends on dialog.js or another
// implementation of the openDialog method.
// Replace works a little oddly -- it will do the replace on the next
// Ctrl-G (or whatever is bound to findNext) press. You prevent a
// replace by making sure the match is no longer selected when hitting
// Ctrl-G.
(function(mod) {
	if (typeof exports == "object" && typeof module == "object") // CommonJS
		mod(require("../../lib/codemirror"), require("./searchcursor"), require("../dialog/dialog"));
	else if (typeof define == "function" && define.amd) // AMD
		define(["../../lib/codemirror", "./searchcursor", "../dialog/dialog"], mod);
	else // Plain browser env
		mod(CodeMirror);
})(function(CodeMirror) {
	"use strict";

	function searchOverlay(query, caseInsensitive) {
		if (typeof query == "string")
			query = new RegExp(query.replace(/[\-\[\]\/\{\}\(\)\*\+\?\.\\\^\$\|]/g, "\\$&"), caseInsensitive ? "gi" : "g");
		else if (!query.global)
			query = new RegExp(query.source, query.ignoreCase ? "gi" : "g");

		return {
			token: function(stream) {

				query.lastIndex = stream.pos;
				var match = query.exec(stream.string);
				if (match && match.index == stream.pos) {
					stream.pos += match[0].length || 1;
					return "searching";
				} else if (match) {
					stream.pos = match.index;
				} else {
					stream.skipToEnd();
				}

			}
		};
	}

	function SearchState() {
		this.posFrom = this.posTo = this.lastQuery = this.query = null;
		this.overlay = null;
	}

	function getSearchState(cm) {
		return cm.state.search || (cm.state.search = new SearchState());
	}

	function queryCaseInsensitive(query) {
		return typeof query == "string" && query == query.toLowerCase();
	}

	function getSearchCursor(cm, query, pos) {
		// Heuristic: if the query string is all lowercase, do a case insensitive search.
		return cm.getSearchCursor(query, pos, {
			caseFold: queryCaseInsensitive(query),
			multiline: true
		});
	}

	function persistentDialog(cm, text, deflt, onEnter, onKeyUp) {
		cm.openDialog(text, onEnter, {
			value: deflt,
			selectValueOnOpen: true,
			closeOnEnter: false,
			closeOnBlur: false,
			onClose: function() {
				clearSearch(cm);
			},
			onKeyUp: onKeyUp
		});
	}

	function dialog(cm, text, shortText, deflt, f) {
		if (cm.openDialog) cm.openDialog(text, f, {
			value: deflt,
			selectValueOnOpen: true
		});
		else f(prompt(shortText, deflt));
	}

	function confirmDialog(cm, text, shortText, fs) {
		if (cm.openConfirm) cm.openConfirm(text, fs);
		else if (confirm(shortText)) fs[0]();
	}

	function parseString(string) {
		return string.replace(/\\(.)/g, function(_, ch) {
			if (ch == "n") return "\n"
			if (ch == "r") return "\r"
			return ch
		})
	}

	function parseQuery(query) {
		var isRE = query.match(/^\/(.*)\/([a-z]*)$/);
		if (isRE) {
			try {
				query = new RegExp(isRE[1], isRE[2].indexOf("i") == -1 ? "" : "i");
			} catch (e) {} // Not a regular expression after all, do a string search
		} else {
			query = parseString(query)
		}
		if (typeof query == "string" ? query == "" : query.test(""))
			query = /x^/;
		return query;
	}

	var queryDialog =
		'<div><input type="text" style="width: 10em" class="CodeMirror-search-field" placeholder="Search" /> <span class="prev icon-left-open-big button"></span><span class="next icon-right-open-big button"></span></div>' +
		'<div><input type="text" style="width: 10em" class="CodeMirror-search-replace" placeholder="Replacement" /> <span class="button replace">Replace</span><span class="button replace all">All</span></div>';

	function startSearch(cm, state, query) {
		state.queryText = query;
		state.query = parseQuery(query);
		cm.removeOverlay(state.overlay, queryCaseInsensitive(state.query));
		state.overlay = searchOverlay(state.query, queryCaseInsensitive(state.query));
		cm.addOverlay(state.overlay);
		if (cm.showMatchesOnScrollbar) {
			if (state.annotate) {
				state.annotate.clear();
				state.annotate = null;
			}
			state.annotate = cm.showMatchesOnScrollbar(state.query, queryCaseInsensitive(state.query));
		}
	}

	function doSearch(cm, rev, persistent, immediate) {
		var state = getSearchState(cm);
		if (state.query) return findNext(cm, rev);
		var q = cm.getSelection() || state.lastQuery;
		if (q instanceof RegExp && q.source == "x^") q = null
		if (persistent && cm.openDialog) {
			var hiding = null
			var searchNext = function(query, event) {
				CodeMirror.e_stop(event);
				if (!query) return;
				if (query != state.queryText) {
					startSearch(cm, state, query);
					state.posFrom = state.posTo = cm.getCursor();
				}
				if (hiding) hiding.style.opacity = 1
				findNext(cm, event.shiftKey, function(_, to) {
					var dialog
					if (document.querySelector &&
						(dialog = cm.display.wrapper.querySelector(".CodeMirror-dialog")) &&
						dialog.getBoundingClientRect().bottom - 4 > cm.cursorCoords(to, "window").top)
						(hiding = dialog).style.opacity = .4
				})
			};
			cm.setOption('styleSelectedText', 'currentHighlighted');
			persistentDialog(cm, queryDialog, q, searchNext, function(event, query) {
				var keyName = CodeMirror.keyName(event)
				if (keyName != 'Enter' && query != state.queryText) {
					startSearch(cm, state, query);
					cm.execCommand('goLineUp');
					state.posFrom = state.posTo = cm.getCursor();
					searchNext(query, event);
				}
				var extra = cm.getOption('extraKeys'),
					cmd = (extra && extra[keyName]) || CodeMirror.keyMap[cm.getOption("keyMap")][keyName]
				if (cmd == "findNext" || cmd == "findPrev" ||
					cmd == "findPersistentNext" || cmd == "findPersistentPrev") {
					CodeMirror.e_stop(event);
					startSearch(cm, getSearchState(cm), query);
					cm.execCommand(cmd);
				} else if (cmd == "find" || cmd == "findPersistent") {
					CodeMirror.e_stop(event);
					searchNext(query, event);
				}
			});

			// Custom handler to close the dialog when the dialog loses focus (not the input). Requires closeOnBlur == false. 
			$(document).mouseup(function(e) {
				var container = $(".CodeMirror-dialog");

				// if the target of the click isn't the container nor a descendant of the container
				if (!container.is(e.target) && container.has(e.target).length === 0) {
					container.remove();
					clearSearch(cm);
					cm.setOption('styleSelectedText', false);
				}

			});

			// Custom prev/next buttons
			$('.CodeMirror-dialog .prev').on('click', function(e) {
				CodeMirror.e_stop(e);
				doSearch(cm, true, true);
			});
			$('.CodeMirror-dialog .next').on('click', function(e) {
				CodeMirror.e_stop(e);
				doSearch(cm, false, true, true);
			});

			// Custom replace
			$('.CodeMirror-dialog .button.replace').on('click', function(e) {

				var all = ($(this).hasClass('all')) ? true : false;

				var text = parseString($('.CodeMirror-dialog .CodeMirror-search-replace').val());
				var query = $('.CodeMirror-dialog .CodeMirror-search-field').val();
				if (all) {
					replaceAll(cm, query, text);
				} else {
					var cursor = getSearchCursor(cm, query, cm.getCursor("from"));
					var advance = function() {
						var start = cursor.from(),
							match;
						if (!(match = cursor.findNext())) {
							cursor = getSearchCursor(cm, query);
							if (!(match = cursor.findNext()) ||
								(start && cursor.from().line == start.line && cursor.from().ch == start.ch)) return;
						}
						cm.setSelection(cursor.from(), cursor.to());
						cm.scrollIntoView({
							from: cursor.from(),
							to: cursor.to()
						});
						doReplace(match);
					};
					var doReplace = function(match) {
						cursor.replace(typeof query == "string" ? text :
							text.replace(/\$(\d)/g, function(_, i) {
								return match[i];
							}));
					};
					advance();
					searchNext(query, e);
				}

			});

			// Custom handlers to replace things when the enter char is pressed
			$('.CodeMirror-dialog .CodeMirror-search-replace').on('keydown', function(e) {

				if (e.keyCode == 13) {
					return CodeMirror.e_stop(e);
				}

			});

			$('.CodeMirror-dialog .CodeMirror-search-replace').on('keyup', function(e) {

				if (e.keyCode != 13) {
					return false;
				}

				CodeMirror.e_stop(e);

				return $('.CodeMirror-dialog .button.replace:not(.all)').trigger('click');

			});

			if (immediate && q) {
				startSearch(cm, state, q);
				findNext(cm, rev);
			}

		} else {
			dialog(cm, queryDialog, "Search for:", q, function(query) {
				if (query && !state.query) cm.operation(function() {
					startSearch(cm, state, query);
					state.posFrom = state.posTo = cm.getCursor();
					findNext(cm, rev);
				});
			});
		}
	}

	function findNext(cm, rev, callback) {
		cm.operation(function() {
			var state = getSearchState(cm);
			var cursor = getSearchCursor(cm, state.query, rev ? state.posFrom : state.posTo);
			if (!cursor.find(rev)) {
				cursor = getSearchCursor(cm, state.query, rev ? CodeMirror.Pos(cm.lastLine()) : CodeMirror.Pos(cm.firstLine(), 0));
				if (!cursor.find(rev)) return;
			}
			cm.setSelection(cursor.from(), cursor.to());
			cm.scrollIntoView({
				from: cursor.from(),
				to: cursor.to()
			}, 20);
			state.posFrom = cursor.from();
			state.posTo = cursor.to();
			if (callback) callback(cursor.from(), cursor.to())
		});
	}

	function clearSearch(cm) {
		cm.operation(function() {
			var state = getSearchState(cm);
			state.lastQuery = state.query;
			if (!state.query) return;
			state.query = state.queryText = null;
			cm.removeOverlay(state.overlay);
			if (state.annotate) {
				state.annotate.clear();
				state.annotate = null;
			}
		});
	}

	function replaceAll(cm, query, text) {
		cm.operation(function() {
			for (var cursor = getSearchCursor(cm, query); cursor.findNext();) {
				if (typeof query != "string") {
					var match = cm.getRange(cursor.from(), cursor.to()).match(query);
					cursor.replace(text.replace(/\$(\d)/g, function(_, i) {
						return match[i];
					}));
				} else cursor.replace(text);
			}
		});
	}

	CodeMirror.commands.find = function(cm) {
		clearSearch(cm);
		doSearch(cm, false, true);
	};
	CodeMirror.commands.clearSearch = clearSearch;
});