function xta_load() {
	var s, child, parnt;
	if(typeof jQuery != 'undefined') {
		s = jQuery;
		child = function(e,s) {
			return jQuery(e).find(s);
		};
		parnt = function(e,s) {
			return jQuery(e).parents(s);
		};
	} else {
		s = $$;
		child = function(e,s) {
			//return Selector.findChildElements(e, s); // -bugged?
			return Prototype.Selector.select(s, e);
		};
		parnt = function(e,s) {
			r = $(e).up(s);
			return r ? [r]:r;
		};
	}
	
	// hack to clear the contents of a file input
	clear_file = function(e) {
		n = document.createElement(e.tagName);
		for(i in e) {
			try {
				n[i] = e[i];
			} catch(x){}
		}
		if(n.getAttribute("multiple"))
			n.onchange = changeFunc(parnt(e, '.xta_input_file_container')[0], n, changeFunc);
		e.parentNode.replaceChild(n, e);
		return n;
	};
	
	// always show 'remove' checkboxes
	s('.xtarm_label').each(function(e){
		e.style.display = "";
	});
	// bind 'remove' checkboxes
	s('.xta_file').each(function(e){
		chk = child(e, '.xtarm')[0];
		chk.onclick = (function(l,c) {
			return function(){
				v = c.checked;
				l.style.textDecoration = (v?"line-through":"");
				if(lnk = c.getAttribute('data'))
					document.getElementById(lnk).style.display = (v?"":"none");
			};
		})(child(e, '.xta_file_link')[0], chk);
		chk.onclick();
	});
	
	// URL fetching option buttons
	s('.xta_input').each(function(e){
		opts = child(e, '.xtasel');
		if(!opts || !opts.length) return;
		opts[0].style.display = "";
		child(e, '.xtasel_label').each(function(c){ // hide other labels
			c.style.display = "none";
		});
		child(e, 'input.xtasel_opt').each(function(c){
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
	s('.xta_input_file_wrapper').each(clrFunc);
	
	// bind thing for multi file input
	s('.xta_input_file_container').each(function(e){
		input = child(e, 'input.xta_input_file')[0];
		if(!input.getAttribute('multiple')) return;
		
		changeFunc = function(e,input, changeFunc){
			return function(){
				if(!input.value) return;
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
		input.onchange = changeFunc(e, input, changeFunc);
	});
	
	// re-arrangable multi-attachments
	// TODO: update this with jQuery version if necessary
	if(typeof Sortable != 'undefined') s('.xta_file_list').each(function(e) {
		items = child(e, '.xta_file');
		if(items.length < 2) return;
		
		items.each(function(c) {
			c.style.cursor = "move";
		});
		Sortable.create(e, {tag: items[0].tagName});
	});
}

if(typeof jQuery != 'undefined') jQuery(document).ready(xta_load);
else Event.observe(document, "dom:loaded", xta_load);