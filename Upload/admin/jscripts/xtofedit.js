
var appendNewChild = function(e,t) {
	var o=e.ownerDocument.createElement(t);
	e.appendChild(o);
	return o;
};
var xtOFEditor = Class.create();

var xtOFEditorLang = {};

xtOFEditor.prototype = {
	src: null,
	loadFunc: null,
	saveFunc: null,
	fields: [],
	copyStyles: false,
	
	// internal vars
	winOpen: false,
	unsaved: false,
	oldFormSubmit: null,
	
	initialize: function(){},
	
	init: function() {
		this.src.readOnly = true;
		this.src.onclick = this.open.bind(this);
		//this.src.onkeypress = this.open.bind(this);
		
		if(this.src.form && !window.opera) {
			if(this.src.form.onsubmit)
				this.oldFormSubmit = this.src.form.onsubmit;
			this.src.form.onsubmit = this.formSubmit.bind(this);
			//Event.observe(this.src.form, "submit", this.formSubmit.bind(this));
		}
		Event.observe(window, "focus", this.focusWin.bind(this));
		Event.observe(window, "beforeunload", this.parentLeave.bind(this));
	},
	
	isOpen: function() {
		return this.winOpen && this.window && this.window.document;
	},
	closeWindow: function() {
		if(this.isOpen()) {
			//this.window.onbeforeunload = null;
			this.winOpen = false;
			this.window.close();
			this.window = null;
		}
	},
	
	parentLeave: function() {
		this.closeWindow();
	},
	
	formSubmit: function() {
		if(this.isOpen()) {
			if(!confirm(xtOFEditorLang.confirmFormSubmit))
				return false;
			this.closeWindow();
		}
		if(this.oldFormSubmit)
			return this.oldFormSubmit();
		else
			return true;
	},
	
	focusWin: function() {
		if(this.isOpen()) {
			setTimeout(function() {
				try {
					this.window.focus();
				} catch(e) {}
			}.bind(this), 10);
		}
	},
	
	open: function() {
		if(window.opera) { // cannot detect window opened/closed in Opera
			// so just close any old window
			this.closeWindow();
		}
		if(this.isOpen()) {
			try {
				this.window.focus();
			} catch(e) {}
			return;
		}
		this.winOpen = true; // prevent our "race" condition :P
		var data = this.loadFunc(this.src.value);
		var i;
		
		this.window = window.open("", "", "status=0,toolbar=0,location=0,menubar=0,directories=0,resizable=1,scrollbars=1,width=400,height=400");
		if(this.copyStyles) {
			var headTag;
			if(headTag = this.window.document.getElementsByTagName("head")) {
				headTag = headTag[0];
				for(i=0; i<document.styleSheets.length; i++)
					if(document.styleSheets[i].href)
						this.addStylesheet(headTag, document.styleSheets[i].href);
			}
		}
		if(xtOFEditorLang.windowTitle)
			this.window.document.title = xtOFEditorLang.windowTitle;
		if(!this.window.document.body)
			this.window.document.body = this.window.document.createElement("body");
		var frm = this.window.document.createElement("form");
		frm.id = "frm";
		this.window.document.body.appendChild(frm);
		var editTbl = this.window.document.createElement("table");
		editTbl.id = "editctlstbl";
		editTbl.style.width = '100%';
		frm.appendChild(editTbl);
		
		// create header
		var header = appendNewChild(editTbl, "thead");
		header = appendNewChild(header, "tr");
		for(i=0; i<this.fields.length; i++) {
			var cell = appendNewChild(header, "th");
			cell.innerHTML = this.fields[i].title;
			if(this.fields[i].width)
				cell.style.width = this.fields[i].width;
		}
		var footRow = appendNewChild(editTbl, "tfoot");
		footRow = appendNewChild(footRow, "tr");
		footRow = appendNewChild(footRow, "td");
		footRow.colSpan = this.fields.length;
		footRow.style.textAlign = "center";
		footRow.style.verticalAlign = "bottom";
		//var submit = appendNewChild(footRow, "input");
		var submit = this.window.document.createElement("input");
		submit.type="submit";
		footRow.appendChild(submit);
		if(xtOFEditorLang.saveButton)
			submit.value = xtOFEditorLang.saveButton;
		var tbody = this.window.document.createElement("tbody");
		tbody.id = "editctls";
		editTbl.appendChild(tbody);
		//appendNewChild(editTbl, "tbody").id = "editctls";
		
		frm.onsubmit = this.save.bind(this);
		//this.window.onbeforeunload = this.beforeClose.bind(this);
		Event.observe(this.window, "beforeunload", this.beforeClose.bind(this));
		for(i=0; i<data.length; i++)
			this.addEditLine(data[i]);
		
		// add blank line
		this.addEditLine([]);
		
		this.unsaved = false;
	},
	
	addStylesheet: function(headTag, href) {
		var stylesheet = headTag.ownerDocument.createElement("link");
		stylesheet.setAttribute("rel", "stylesheet");
		stylesheet.setAttribute("type", "text/css");
		stylesheet.setAttribute("href", href);
		headTag.appendChild(stylesheet);
	},
	
	addEditLine: function(data) {
		var row = appendNewChild(this.window.document.getElementById("editctls"), "tr");
		var i;
		for(i=0; i<this.fields.length; i++) {
			var cell = appendNewChild(row, "td");
			cell.style.verticalAlign = "top";
			var input = this.fields[i].elemFunc(cell);
			if(data[i])
				input.value = data[i];
			var updatFunc = function() {
				this.inputOnChange(input);
			}.bind(this);
			Event.observe(input, "change", updatFunc);
		}
	},
	
	rowIsBlank: function(row) {
		var i, j;
		for(i=0; i<row.childNodes.length; i++) {
			cell = row.childNodes[i];
			if(cell.nodeName.toUpperCase() != "TD") continue;
			for(j=0; j<cell.childNodes.length; j++)
				if(cell.childNodes[j].value) {
					return false;
				}
		}
		return true;
	},
	inputOnChange: function(input) {
		var row = input.parentNode.parentNode;
		// check if all boxes are empty
		
		if(this.rowIsBlank(row)) {
			// if 2nd last, remove last row
			if(row.nextSibling && !row.nextSibling.nextSibling) {
				do {
					var prevRow = row.previousSibling;
					row.parentNode.removeChild(row.nextSibling);
					// remove any preceeding blank rows too
					row = prevRow;
				} while(row && this.rowIsBlank(row));
			}
			// TODO: remove blank line in the middle of things?
		} else {
			if(!row.nextSibling)
				this.addEditLine([]);
		}
		this.unsaved = true;
	},
	
	save: function() {
		var data = [];
		// loop through and grab data
		var editctls = this.window.document.getElementById("editctls");
		for(var iRow=0; iRow<editctls.childNodes.length; iRow++) {
			var row = editctls.childNodes[iRow];
			if(!row || !row.nodeName || row.nodeName.toUpperCase() != "TR") continue;
			//if(this.rowIsBlank(row)) continue;
			var datum = [];
			var isBlank = true;
			for(var iCell=0; iCell<row.childNodes.length; iCell++) {
				var cell = row.childNodes[iCell];
				if(!cell || !cell.nodeName || cell.nodeName.toUpperCase() != "TD") continue;
				datum.push(cell.childNodes[0].value);
				if(cell.childNodes[0].value) isBlank = false;
			}
			if(!isBlank)
				data.push(datum);
		}
		
		this.src.value = this.saveFunc(data);
		this.closeWindow();
		
		return false;
	},
	
	beforeClose: function() {
		if(!this.isOpen()) return;
		// check modification status and ask to save
		this.window.document.activeElement.blur(); // run update routine
		if(this.unsaved && confirm(xtOFEditorLang.closeSaveChanges))
			this.save();
		else {
			this.winOpen = false;
		}
	},
	
	// create a text area in a cell
	textAreaFunc: function(c) {
		var o = appendNewChild(c, "textarea");
		o.style.width = '100%';
		return o;
	}
};
