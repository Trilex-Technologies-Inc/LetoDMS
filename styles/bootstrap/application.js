
$(document).ready( function() {
	var sidebarStorageKey = 'letodms-sidebar-collapsed';

	/*
	 * Bootstrap 2 listens to both touchstart and click for dropdown toggles.
	 * Mobile browsers synthesize a click after a tap, which can open and then
	 * immediately close the menu. Use the click path alone for reliable taps.
	 */
	$(document).off('touchstart.dropdown.data-api');

	if (window.matchMedia && window.matchMedia('(min-width: 701px)').matches) {
		try {
			if (window.localStorage.getItem(sidebarStorageKey) === 'true')
				$('body').addClass('sb-sidebar-collapsed');
		} catch (e) {}
	}

	function updateSidebarToggle() {
		var isMobile = window.matchMedia && window.matchMedia('(max-width: 700px)').matches;
		var isExpanded = isMobile
			? $('body').hasClass('sb-sidebar-open')
			: !$('body').hasClass('sb-sidebar-collapsed');
		$('.sb-sidebar-toggle')
			.attr('aria-expanded', isExpanded ? 'true' : 'false')
			.attr('aria-label', isExpanded ? 'Collapse navigation' : 'Expand navigation');
	}

	$('.sb-sidebar-toggle').on('click', function() {
		var isMobile = window.matchMedia && window.matchMedia('(max-width: 700px)').matches;
		if (isMobile) {
			$('body').toggleClass('sb-sidebar-open');
		} else {
			var isCollapsed = $('body').toggleClass('sb-sidebar-collapsed').hasClass('sb-sidebar-collapsed');
			try { window.localStorage.setItem(sidebarStorageKey, isCollapsed ? 'true' : 'false'); } catch (e) {}
		}
		updateSidebarToggle();
	});
	updateSidebarToggle();
	$(window).on('resize', updateSidebarToggle);
	$('.sb-sidebar-nav a').each(function() {
		if (!$(this).attr('data-label'))
			$(this).attr('data-label', $.trim($(this).find('span:last').text()));
		if (window.location.pathname.indexOf($(this).attr('href').split('/').pop().split('?')[0]) !== -1)
			$(this).addClass('active');
	});
	$('body').on('hidden', '.modal', function () {
		$(this).removeData('modal');
	});

	$('body').on('touchstart.dropdown', '.dropdown-menu', function (e) { e.stopPropagation(); });

	$('#expirationdate, #fromdate, #todate, #createstartdate, #createenddate, #expirationstartdate, #expirationenddate')
		.datepicker()
		.on('changeDate', function(ev){
			$('#expirationdate, #fromdate, #todate, #createstartdate, #createenddate, #expirationstartdate, #expirationenddate').datepicker('hide');
		});

	$(".chzn-select").chosen();
	$(".chzn-select-deselect").chosen({allow_single_deselect:true});

	$(".pwd").passStrength({
		url: "../op/op.Ajax.php",
		onChange: function(data, target) {
			pwsp = 100*data.score;
			$('#'+target+' div.bar').width(pwsp+'%');
			if(data.ok) {
				$('#'+target+' div.bar').removeClass('bar-danger');
				$('#'+target+' div.bar').addClass('bar-success');
			} else {
				$('#'+target+' div.bar').removeClass('bar-success');
				$('#'+target+' div.bar').addClass('bar-danger');
			}
		}
	});

	/* The typeahead functionality useѕ the rest api */
	$("#searchfield").typeahead({
		minLength: 3,
		source: function(query, process) {
			$.get('../restapi/index.php/search', { query: query, limit: 8, mode: 'typeahead' }, function(data) {
					process(data);
			});
		},
		/* updater is called when the item in the list is clicked. It is
		 * actually provided to update the input field, but here we use
		 * it to set the document location. */
		updater: function (item) {
			document.location = "../op/op.Search.php?query=" + encodeURIComponent(item.substring(1));
			return item;
		},
		/* Set a matcher that allows any returned value */
		matcher : function (item) {
			return true;
		},
		highlighter : function (item) {
			if(item.charAt(0) == 'D')
				return '<i class="icon-file"></i> ' + item.substring(1);
			else if(item.charAt(0) == 'F')
				return '<i class="icon-folder-close"></i> ' + item.substring(1);
			else
				return '<i class="icon-search"></i> ' + item.substring(1);
		}
	});

	/* Document chooser */
	$("#choosedocsearch").typeahead({
		minLength: 3,
		formname: 'form1',
		source: function(query, process) {
//		console.log(this.options);
			$.get('../op/op.Ajax.php', { command: 'searchdocument', query: query, limit: 8 }, function(data) {
					process(data);
			});
		},
		/* updater is called when the item in the list is clicked. It is
		 * actually provided to update the input field, but here we use
		 * it to set the document location. */
		updater: function (item) {
			strarr = item.split("#");
			//console.log(this.options.formname);
			$('#docid' + this.options.formname).attr('value', strarr[0]);
			return strarr[1];
		},
		/* Set a matcher that allows any returned value */
		matcher : function (item) {
			return true;
		},
		highlighter : function (item) {
			strarr = item.split("#");
			return '<i class="icon-file"></i> ' + strarr[1];
		}
	});

	/* Folder chooser */
	$("#choosefoldersearch").typeahead({
		minLength: 3,
		formname: 'form1',
		source: function(query, process) {
//		console.log(this.options);
			$.get('../op/op.Ajax.php', { command: 'searchfolder', query: query, limit: 8 }, function(data) {
					process(data);
			});
		},
		/* updater is called when the item in the list is clicked. It is
		 * actually provided to update the input field, but here we use
		 * it to set the document location. */
		updater: function (item) {
			strarr = item.split("#");
			//console.log(this.options.formname);
			$('#targetid' + this.options.formname).attr('value', strarr[0]);
			return strarr[1];
		},
		/* Set a matcher that allows any returned value */
		matcher : function (item) {
			return true;
		},
		highlighter : function (item) {
			strarr = item.split("#");
			return '<i class="icon-folder-close"></i> ' + strarr[1];
		}
	});
});

function allowDrop(ev) {
	ev.preventDefault();
//	console.log(ev);
}

/* Native inline handlers pass the raw DOM event, whose dataTransfer is on the
   event itself. If a jQuery-wrapped event ever arrives, unwrap it. */
function _dndEvent(ev) {
	return (ev && ev.originalEvent && ev.originalEvent.dataTransfer) ? ev.originalEvent : ev;
}

function onDragStartDocument(ev) {
	ev = _dndEvent(ev);
	/* currentTarget is the draggable <a rel="document_ID">; target may be the
	   inner <img>, which carries no rel attribute. */
	var attr_rel = $(ev.currentTarget).attr('rel');
	console.log('onDragStartDocument rel=' + attr_rel);
	if (!attr_rel) return;
	ev.dataTransfer.setData("id", attr_rel.split("_")[1]);
	ev.dataTransfer.setData("type","document");
	/* Some browsers require effectAllowed for a drop to be accepted. */
	ev.dataTransfer.effectAllowed = "move";
}

function onDragStartFolder(ev) {
	ev = _dndEvent(ev);
	var attr_rel = $(ev.currentTarget).attr('rel');
	console.log('onDragStartFolder rel=' + attr_rel);
	if (!attr_rel) return;
	ev.dataTransfer.setData("id", attr_rel.split("_")[1]);
	ev.dataTransfer.setData("type","folder");
	ev.dataTransfer.effectAllowed = "move";
}

function onDrop(ev) {
	ev = _dndEvent(ev);
	ev.preventDefault();
	/* Folder rows inside the clipboard would otherwise bubble up to the
	   clipboard's onAddClipboard handler and trigger both actions. */
	ev.stopPropagation();
	var attr_rel = $(ev.currentTarget).attr('rel');
	if (!attr_rel) return;
	var target_type = attr_rel.split("_")[0];
	var target_id = attr_rel.split("_")[1];
	var source_type = ev.dataTransfer.getData("type");
	var source_id = ev.dataTransfer.getData("id");
	if(source_type == 'document') {
		url = "../out/out.MoveDocument.php?documentid="+source_id+"&targetid="+target_id;
		document.location = url;
	} else if(source_type == 'folder') {
		url = "../out/out.MoveFolder.php?folderid="+source_id+"&targetid="+target_id;
		document.location = url;
	}
//	console.log(attr_rel);
//	console.log(ev.dataTransfer.getData("type") + ev.dataTransfer.getData("id"));
}

function onAddClipboard(ev) {
	ev = _dndEvent(ev);
	ev.preventDefault();
	var source_type = ev.dataTransfer.getData("type");
	var source_id = ev.dataTransfer.getData("id");
	console.log('onAddClipboard type=' + source_type + ' id=' + source_id);
	if(source_type == 'document' || source_type == 'folder') {
		url = "../op/op.AddToClipboard.php?id="+source_id+"&type="+source_type;
		document.location = url;
	}
}
