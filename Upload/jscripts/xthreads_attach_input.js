function xta_load() {
	var s, each, child, parnt;
	if(typeof jQuery != 'undefined') {
		s = jQuery;
		each = function(c, f) {
			c.each(function(k,v){
				f(v);
			});
		};
		child = function(e,s) {
			return jQuery(e).find(s);
		};
		parnt = function(e,s) {
			return jQuery(e).parents(s);
		};
	} else {
		s = $$;
		each = function(c, f) {
			c.each(function(v,k){
				f(v);
			});
		};
		child = function(e,s) {
			if(typeof Prototype.Selector != 'undefined') // MyBB 1.6.x
				return Prototype.Selector.select(s, e);
			else // MyBB 1.4.x
				return Selector.findChildElements(e, [s]);
		};
		parnt = function(e,s) {
			r = $(e).up(s);
			return r ? [r]:r;
		};
	}
	
	// hack to clear the contents of a file input
	clear_file = function(e) {
		n = document.createElement(e.tagName);
		a = e.attributes;
		for(i=0; i<a.length; i++) {
			if(a[i].specified) {
				n.setAttribute(a[i].nodeName, a[i].nodeValue);
			}
		}
		n.className = e.className; // IE fix
		if(n.getAttribute("multiple"))
			n.onchange = changeFunc(parnt(e, '.xta_input_file_container')[0], n, changeFunc);
		e.parentNode.replaceChild(n, e);
		return n;
	};
	
	// always show 'remove' checkboxes
	each(s('.xtarm_label'), function(e){
		e.style.display = "";
	});
	// bind 'remove' checkboxes
	each(s('.xta_file'), function(e){
		chk = child(e, '.xtarm')[0];
		chk.onclick = (function(e,c) {
			return function(){
				v = c.checked;
				if(l = child(e, '.xta_file_link')[0])
					l.style.textDecoration = (v?"line-through":"");
				// add xta_removed class to support external styling
				e.className = e.className.replace(/(\s|^)xta_removed(\s|$)/, ' ');
				if(v) e.className += " xta_removed";
				if(lnk = c.getAttribute('data')) {
					row = document.getElementById(lnk);
					row.style.display = (v?"":"none");
					// also clear data so that it doesn't get submitted
					if(!v) {
						if(l = child(row, '.xta_input_file')[0]) clear_file(l);
						if(l = child(row, '.xta_input_url')[0])  l.value = "http://";
					}
				}
			};
		})(e, chk);
		chk.onclick();
	});
	
	// URL fetching option buttons
	each(s('.xta_input'), function(e){
		opts = child(e, '.xtasel');
		if(!opts || !opts.length) return;
		opts[0].style.display = "";
		each(child(e, '.xtasel_label'), function(c){ // hide other labels
			c.style.display = "none";
		});
		each(child(e, 'input.xtasel_opt'), function(c){
			c.onclick = (function(e,c){
				return function(){
					if(!c.checked) return;
					v = (c.value=="file");
					child(e, '.xta_input_file_row')[0].style.display = (v?"":"none");
					child(e, '.xta_input_url_row')[0].style.display = (!v?"":"none");
					if(!v)
						clear_file(child(e, '.xta_input_file')[0]);
					else if(!child(e, '.xta_input_url')[0].value.match(/^[a-zA-Z0-9\-]+\:/))
						child(e, '.xta_input_url')[0].value = "http://";
				};
			})(e,c);
			c.onclick();
		});
		// also set tabindex for URL box
		if(ti = child(e, '.xta_input_file')[0].tabIndex)
			child(e, '.xta_input_url')[0].tabIndex = ti;
	});
	
	// 'clear' buttons for files
	clrFunc = function(e){
		clr = child(e, 'input.xta_input_file_clr')[0];
		if(!clr) return;
		clr.style.display = "";
		clr.onclick = (function(e){
			return function(){
				clear_file(child(e, 'input.xta_input_file')[0]);
			};
		})(e);
	};
	each(s('.xta_input_file_wrapper'), clrFunc);
	
	changeFunc = function(e,fileinput, changeFunc){
		return function(){
			if(!fileinput.value) return;
			// check last input - if blank, don't append one
			inputs = child(e, 'input.xta_input_file');
			if(!inputs[inputs.length-1].value) return;
			
			// append new input
			input_ws = child(e, '.xta_input_file_wrapper');
			input_w = input_ws[input_ws.length-1];
			new_w = document.createElement(input_w.tagName);
			new_w.setAttribute("class", "xta_input_file_wrapper");
			new_w.innerHTML = input_w.innerHTML;
			new_input = child(new_w, 'input.xta_input_file')[0];
			new_input.onchange = changeFunc(e, new_input, changeFunc);
			e.appendChild(new_w);
			clrFunc(new_w);
		};
	};
	// bind thing for multi file input
	each(s('.xta_input_file_container'), function(e){
		fileinput = child(e, 'input.xta_input_file')[0];
		if(!fileinput.getAttribute('multiple')) return;
		
		fileinput.onchange = changeFunc(e, fileinput, changeFunc);
	});
	
	// re-arrangable multi-attachments
	if(typeof Sortable != 'undefined') each(s('.xta_file_list'), function(e) {
		items = child(e, '.xta_file');
		if(items.length < 2) return;
		
		each(items, function(c) {
			c.style.cursor = "move";
			c.className += " xta_movable"; // for external styling
		});
		opts = {};
		if(o=e.getAttribute("data-sortoptions")) {
			opts = eval('('+o+')');
		}
		opts.tag = items[0].tagName;
		Sortable.create(e, opts);
	});
	else if(jQuery) each(s('.xta_file_list'), function(e) {
		items = child(e, '.xta_file');
		if(items.length < 2) return;
		
		each(items, function(c) {
			c.style.cursor = "move";
			c.className += " xta_movable"; // for external styling
		});
		$(e).sortable({
			axis: 'y',
			//containment: 'parent',
			revert: true
			// cursor: 'move'
		});
	});
}

if(typeof jQuery != 'undefined') jQuery(document).ready(xta_load);
else Event.observe(document, "dom:loaded", xta_load);