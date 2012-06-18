function xta_load() {
	var s, child;
	if(typeof jQuery != 'undefined') {
		s = jQuery;
		child = function(e,s) {
			return jQuery(e).find(s);
		};
	} else {
		s = $$;
		child = function(e,s) {
			//return Selector.findChildElements(e, s); // -bugged?
			return Prototype.Selector.select(s, e);
		};
	}
	
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
					if(!v) child(e, '.xta_input_file')[0].value="";
				};
			})(e,c);
			c.onclick();
		});
		// also set tabindex for URL box
		if(ti = child(e, '.xta_input_file')[0].tabIndex)
			child(e, '.xta_input_url')[0].tabIndex = ti;
	});
	
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
				new_w.onchange = changeFunc(e, child(new_w, 'input.xta_input_file')[0], changeFunc);
				e.appendChild(new_w);
			};
		};
		input.onchange = changeFunc(e, input, changeFunc);
	});
}

if(typeof jQuery != 'undefined') jQuery(document).ready(xta_load);
else Event.observe(document, "dom:loaded", xta_load);