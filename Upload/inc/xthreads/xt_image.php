<?php

class XTImageTransform {
	var $OWIDTH;
	var $OHEIGHT;
	var $WIDTH;
	var $HEIGHT;
	var $TYPE;
	var $FILENAME;
	var $typeGD;
	
	/*somewhat protected*/ var $_img = null;
	/*private*/ var $_jpeg_quality = 75;
	/*private*/ var $_png_level = 6;
	/*private*/ var $_transColor = array(0,0,0); // transparent colour = black
	
	/*private*/ var $_enableWrite=false; // security mechanism
	
	function load($img) {
		if(!function_exists('imagecreate'))
			return false;
		$dims = getimagesize($img);
		if(empty($dims)) return false;
		$this->OWIDTH = $this->WIDTH = $dims[0];
		$this->OHEIGHT = $this->HEIGHT = $dims[1];
		
		$this->FILENAME = $img;
		
		$types = array(IMAGETYPE_GIF=>'GIF',IMAGETYPE_JPEG=>'JPEG',IMAGETYPE_PNG=>'PNG',
			IMAGETYPE_WBMP=>'WBMP',IMAGETYPE_XBM=>'XBM',
		);
		if(!isset($types[$dims[2]])) return false; // unsupported type? TODO: maybe change this
		$this->TYPE = $types[$dims[2]];
		$this->typeGD = $dims[2];
		
		if($this->_img) imagedestroy($this->_img);
		
		switch($dims[2]) {
			case IMAGETYPE_PNG:
				$this->_img = imagecreatefrompng($img);
				break;
			case IMAGETYPE_JPEG:
				$this->_img = imagecreatefromjpeg($img);
				break;
			case IMAGETYPE_GIF:
				$this->_img = imagecreatefromgif($img);
				break;
			case IMAGETYPE_WBMP:
				$this->_img = imagecreatefromwbmp($img);
				break;
			case IMAGETYPE_XBM:
				$this->_img = imagecreatefromxbm($img);
				break;
		}
		if(!$this->_img) return false;
		return $this;
	}
	
	function blank($w, $h, $bg=0) {
		$this->OWIDTH = $this->WIDTH = $w;
		$this->OHEIGHT = $this->HEIGHT = $h;
		$this->FILENAME = '';
		$this->TYPE = 'PNG';
		$this->typeGD = IMAGETYPE_PNG;
		if($this->_img) imagedestroy($this->_img);
		$this->_img = $this->_surface($w, $h, $this->_color($bg));
		if(!$this->_img) return false;
		return $this;
	}
	
	/*private*/ function _surface($w, $h, $col=null) {
		($im = @imagecreatetruecolor($w, $h)) or ($im = @imagecreate($w, $h));
		// set a transparent background
		imagealphablending($im, false); // don't blend alpha (copy it)
		imagesavealpha($im, true); // save alpha
		if(!isset($col))
			$col = array($this->_transColor[0], $this->_transColor[1], $this->_transColor[2], 255);
		imagefill($im, 0, 0, imagecolorallocatealpha($im, $col[0], $col[1], $col[2], (int)($col[3]/2))); // fill background
		return $im;
	}
	
	function downscale($w, $h) {
		if($this->WIDTH > $w || $this->HEIGHT > $h)
			return $this->scale_max($w, $h);
		return $this;
	}
	function upscale($w, $h) {
		if($this->WIDTH < $w || $this->HEIGHT < $h)
			return $this->scale_min($w, $h);
		return $this;
	}
	
	function scale($w, $h) {
		return $this->scale_max($w, $h);
	}
	function scale_max($w, $h) {
		return $this->_scale($w, $h, true);
	}
	function scale_min($w, $h) {
		return $this->_scale($w, $h, false);
	}
	
	/*private*/ function _scale($w, $h, $max) {
		$r = max($this->WIDTH, 1) / max($this->HEIGHT, 1);
		$w = max($w, 1); $h = max($h, 1);
		if(($r > ($w/$h)) == $max) {
			$nw = $w;
			$nh = max(round($w/$r), 1);
		} else {
			$nw = max(round($h*$r), 1);
			$nh = $h;
		}
		return $this->resize($nw, $nh);
	}
	
	function resize($w, $h) {
		if(!isset($this->_img)) return;
		
		// actual resizing
		$im = $this->_surface($w, $h);
		@imagecopyresampled($im, $this->_img, 0,0,0,0, $w,$h, $this->WIDTH, $this->HEIGHT);
		imagedestroy($this->_img);
		$this->_img = $im;
		
		$this->WIDTH = $w;
		$this->HEIGHT = $h;
		return $this;
	}
	
	function crop($w, $h) {
		return $this->crop_cm($w, $h);
	}
	function crop_lt($w, $h, $x=0, $y=0) {
		if($w < 0) $w += $this->WIDTH;
		if($h < 0) $h += $this->HEIGHT;
		if($x === 'm')
			$x = max(($this->WIDTH - $w)/2, 0);
		elseif($x === 'e')
			$x = max($this->WIDTH - $w, 0);
		elseif($x < 0)
			$x += $this->WIDTH;
		if($y === 'm')
			$y = max(($this->HEIGHT - $h)/2, 0);
		elseif($y === 'e')
			$y = max($this->HEIGHT - $h, 0);
		elseif($y < 0)
			$y += $this->HEIGHT;
		
		// sanity check
		if($w < 0 || $h < 0 || $x < 0 || $y < 0 || $x+$w > $this->WIDTH || $y+$h > $this->HEIGHT)
			return $this;
		
		if(!isset($this->_img)) return;
		
		// actual cropping
		$im = $this->_surface($w, $h);
		@imagecopy($im, $this->_img, 0,0,$x,$y, $w,$h);
		imagedestroy($this->_img);
		$this->_img = $im;
		
		$this->WIDTH = $w;
		$this->HEIGHT = $h;
		return $this;
	}
	function crop_ct($w, $h, $y=0) {
		return $this->crop_lt($w, $h, 'm', $y);
	}
	function crop_rt($w, $h, $x=0, $y=0) {
		return $this->crop_lt($w, $h, ($x?-$x:'e'), $y);
	}
	function crop_lm($w, $h, $x=0) {
		return $this->crop_lt($w, $h, $x, 'm');
	}
	function crop_cm($w, $h) {
		return $this->crop_lt($w, $h, 'm', 'm');
	}
	function crop_rm($w, $h, $x=0) {
		return $this->crop_lt($w, $h, ($x?-$x:'e'), 'm');
	}
	function crop_lb($w, $h, $x=0, $y=0) {
		return $this->crop_lt($w, $h, $x, ($y?-$y:'e'));
	}
	function crop_cb($w, $h, $y=0) {
		return $this->crop_lt($w, $h, 'm', ($y?-$y:'e'));
	}
	function crop_rb($w, $h, $x=0, $y=0) {
		return $this->crop_lt($w, $h, ($x?-$x:'e'), ($y?-$y:'e'));
	}
	
	/** misc filters **/
	/*private*/ function _filter($filt, $a1=null, $a2=null, $a3=null, $a4=null) {
		if(!isset($this->_img)) return;
		imagefilter($this->_img, $filt, $a1, $a2, $a3, $a4);
		return $this;
	}
	function negate() {
		return $this->_filter(IMG_FILTER_NEGATE);
	}
	function grayscale() {
		return $this->_filter(IMG_FILTER_GRAYSCALE);
	}
	function brightness($n) {
		return $this->_filter(IMG_FILTER_BRIGHTNESS, $n);
	}
	function contrast($n) {
		return $this->_filter(IMG_FILTER_CONTRAST, $n);
	}
	function colorize($r,$g,$b,$a) {
		return $this->_filter(IMG_FILTER_COLORIZE, $r,$g,$b,$a);
	}
	function edgedetect() {
		return $this->_filter(IMG_FILTER_EDGEDETECT);
	}
	function emboss() {
		return $this->_filter(IMG_FILTER_EMBOSS);
	}
	function gaussian_blur() {
		return $this->_filter(IMG_FILTER_GAUSSIAN_BLUR);
	}
	function selective_blur() {
		return $this->_filter(IMG_FILTER_SELECTIVE_BLUR);
	}
	function mean_removal() {
		return $this->_filter(IMG_FILTER_MEAN_REMOVAL);
	}
	function smooth($n) {
		return $this->_filter(IMG_FILTER_SMOOTH, $n);
	}
	// requires PHP >= 5.3
	function pixelate($s, $adv=false) {
		return $this->_filter(IMG_FILTER_PIXELATE, $s, $adv);
	}
	
	/*private static*/ function _color($v) {
		if(is_string($v)) {
			if($v[0] == '#') $v=substr($v,1);
			// split into halves to avoid precision/overflow problems
			$colA = (int)base_convert(str_pad(substr($v, 0, 4), 4, '0'), 16, 10);
			$colB = (int)base_convert(str_pad(substr($v, 4, 4), 4, '0'), 16, 10);
			return array(
				($colA >> 8) & 0xFF,
				($colA >> 0) & 0xFF,
				($colB >> 8) & 0xFF,
				($colB >> 0) & 0xFF,
			);
		} elseif(is_int($v)) {
			return array(
				($v >>  0) & 0xFF,
				($v >>  8) & 0xFF,
				($v >> 16) & 0xFF,
				($v >> 24) & 0xFF,
			);
		} elseif(is_array($v)) {
			return array(
				@$v[0] & 0xFF,
				@$v[1] & 0xFF,
				@$v[2] & 0xFF,
				@$v[3] & 0xFF
			);
		}
		return array(0,0,0,0);
	}
	
	function jpeg($transCol=0, $q=null) {
		$this->TYPE = 'JPEG';
		$this->typeGD = IMAGETYPE_JPEG;
		!isset($q) or $this->_jpeg_quality = max(min((int)$q, 100), 0);
		
		$transCol = array_slice($this->_color($transCol), 0,3);
		if($transCol != $this->_transColor) {
			$this->_transColor = $transCol;
			$im = $this->_surface($this->WIDTH, $this->HEIGHT);
			@imagecopy($im, $this->_img, 0,0,0,0, $this->WIDTH,$this->HEIGHT);
			imagedestroy($this->_img);
			$this->_img = $im;
		}
		return $this;
	}
	function png($l=null) {
		$this->TYPE = 'PNG';
		$this->typeGD = IMAGETYPE_PNG;
		!isset($l) or $this->_png_level = max(min((int)$l, 9), 0);
		return $this;
	}
	
	function write($fn) {
		if(!isset($this->_img)) return;
		if(!$this->_enableWrite) return;
		if($this->TYPE == 'JPEG')
			imagejpeg($this->_img, $fn, $this->_jpeg_quality);
		else
			imagepng($this->_img, $fn, $this->_png_level); // PNG_ALL_FILTERS
	}
	
	// PHP 4.x-ers?  heh...
	function __destruct() {
		if(isset($this->_img)) imagedestroy($this->_img);
	}
}

// functions to allow conditionals to create image objects
function newXTImg() {
	return new XTImageTransform;
}
