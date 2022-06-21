<?php

defined( 'ABSPATH' ) or die(':)');


function alobaidi_video_popup_shortcode($atts){
	if( !empty($atts["url"]) ){
		$url = esc_url($atts["url"]);
	}else{
		$url = null;
	}

	if( !empty($atts["text"]) ){
		$text = $atts["text"];
	}else{
		if( !empty($atts["url"]) ){
			$text = esc_url($atts["url"]);
		}else{
			$text = null;
		}
	}

	if( !empty($atts["auto"]) ){
		if( strtolower($atts["auto"]) == 'no' or $atts["auto"] == "1"){
			$auto = "vp-s";
		}else{
			$auto = "vp-a";
		}
	}else{
		$auto = "vp-a";
	}

	if( !empty($atts["p"]) ){
		$p_before = '<p>';
		$p_after = '</p>';
	}else{
		$p_before = null;
		$p_after = null;
	}

	if( !empty($atts["n"]) ){
		$nofollow = ' rel="nofollow"';
	}else{
		$nofollow = null;
	}

	$filter1 = null;

	$filter2 = apply_filters('wpt_video_popup_shortcode_filter', 0);

	if($filter2 == 1){
		if( !empty($atts["rel"]) ){
			$rel = 1;
		}else{
			$rel = 0;
		}

		$filter1 = apply_filters('wpt_video_popup_attr_filter', $rel);
	}

	if( !empty($atts["url"]) ){
		$parse = parse_url($atts["url"]);
		if( strtolower($parse['host']) == 'soundcloud.com' ){
			if( $auto == 'vp-a' ){
				$sc_auto = '&vp_soundcloud_a=true';
			}else{
				$sc_auto = '&vp_soundcloud_a=false';
			}
			$embed_sc_url = home_url("/?vp_soundcloud=") . $atts["url"] . $sc_auto;
			$data_sc = ' data-soundcloud="1" data-soundcloud-url="'.esc_url($atts["url"]).'" data-embedsc="'.esc_url($embed_sc_url).'"';
			$url = '#';
		}else{
			$data_sc = null;
		}
	}else{
		$data_sc = null;
	}

	if( !empty($atts['wrap']) ){
		if( strtolower($atts['wrap']) == 'no' or $atts['wrap'] == '1' ){
			$dis_wrap = ' data-dwrap="1"';
		}else{
			$dis_wrap = null;
		}
	}else{
		$dis_wrap = null;
	}

	if( !empty($atts['rv']) and $atts['rv'] == '1' ){
		$rv_class = ' vp-dr';
	}else{
		$rv_class = null;
	}

	if( !empty($atts["w"]) ){
		$get_width = $atts["w"];
		$width_attr = ' data-width="'.esc_attr($get_width).'"';
	}else{
		$width_attr = null;
	}

	if( !empty($atts["h"]) ){
		$get_height = $atts["h"];
		$height_attr = ' data-height="'.esc_attr($get_height).'"';
	}else{
		$height_attr = null;
	}

	if( !empty($atts["title"]) ){
		$get_title = $atts["title"];
		$title_attr = ' title="'.esc_attr($get_title).'"';
	}else{
		$title_attr = null;
	}

	if( !empty($atts["co"]) ){
		$get_overlay_color = $atts["co"];
		$overlay_color_attr = ' data-overlay="'.esc_attr($get_overlay_color).'"';
	}else{
		$overlay_color_attr = null;
	}

	if( !empty($atts["dc"]) ){
		$dis_controls_attr = ' data-controls="1"';
	}else{
		$dis_controls_attr = null;
	}

	if( !empty($atts["di"]) ){
		$dis_info_attr = ' data-info="1"';
	}else{
		$dis_info_attr = null;
	}

	if( !empty($atts["iv"]) ){
		$dis_iv_attr = ' data-iv="1"';
	}else{
		$dis_iv_attr = null;
	}

	if( !empty($atts["img"]) ){
		$image_url = $atts["img"];
		$text = null;
		$text = '<img class="vp-img" src="'.$image_url.'">';
	}

	$media = '<a'.$filter1.' class="'.$auto.$rv_class.'" href="'.$url.'"'.$title_attr.$nofollow.$width_attr.$height_attr.$data_sc.$dis_wrap.$overlay_color_attr.$dis_controls_attr.$dis_info_attr.$dis_iv_attr.'>'.$text.'</a>';

	return $p_before.$media.$p_after;
}
add_shortcode('video_popup', 'alobaidi_video_popup_shortcode');