/**
 *  This is our main javascript file
 *  Written by: JEM Products 
 */


jQuery(document).ready(function($) {

	
    
	//also if we have switched page the slider refreshes, so we need to make it match the URL params...
	params = jempfliteGetURLParams();
	
	if(typeof(params['min-price']) !== 'undefined'){
		min = params['min-price'] ;
	} else {
		min = parseInt(jempflite_params.min_price)
	}
	
	if(typeof(params['max-price']) !== 'undefined'){
		max =  params['max-price'] ;
	} else {
		max =parseInt(jempflite_params.max_price); 
	}
	
	
    $( "#jempflite-price-slider" ).slider({
      range: true,
      min: parseInt(jempflite_params.min_price),
      max: parseInt(jempflite_params.max_price),
      values: [ min, max],
      slide: function( event, ui ) {
    	  if(jempflite_params.ccy_side == 'left'){
    	        $( "#jempflite-amount" ).text( jempflite_params.ccy_symbol + jempflite_params.ccy_space + ui.values[ 0 ] + 
    	        								" - " + jempflite_params.ccy_symbol + jempflite_params.ccy_space +ui.values[ 1 ] );

    	  } else {
  	        $( "#jempflite-amount" ).text( ui.values[ 0 ] + jempflite_params.ccy_space +  jempflite_params.ccy_symbol + 
					" - " + ui.values[ 1 ] + jempflite_params.ccy_space + jempflite_params.ccy_symbol);
    		  
    	  }
    	  
      },
      change: function ( event, ui ){
    	  //lets add the new values to the URL
    	  //we don't add them id they are the min or the max, in fact we remove them!!!
    	  
    	  //First the min
    	  var min = $( "#jempflite-price-slider" ).slider( "option", "min" );
    	  if( ui.values[ 0 ] == min ){
    		  //remove it
    		  url = jempfliteUpdateQueryString('min-price');
    	  } else {
    		  url = jempfliteUpdateQueryString('min-price', ui.values[ 0 ] );
    	  }
    	  
    	  //now the max
    	  var max = $( "#jempflite-price-slider" ).slider( "option", "max" );
    	  if( ui.values[ 1 ] == max ){
    		  //remove it
    		  url = jempfliteUpdateQueryString('max-price', null, url);
    	  } else {
    		  url = jempfliteUpdateQueryString('max-price', ui.values[ 1 ], url );
    	  }

    	  
    	  //always reset to page 1
    	  url = url.replace(/page\/([0-9]+)/, 'page/1');
    	  history.pushState({}, '', url);

    	  //now lets refresh
    	  refreshProducts();
    	  
      }
    });  // END SLIDER

	

	// Initial setup
	if (jempflite_params.ccy_side == 'left') {
		$("#jempflite-amount").text(
				jempflite_params.ccy_symbol
						+ jempflite_params.ccy_space
						+ $("#jempflite-price-slider").slider("values",
								0)
						+ " - "
						+ jempflite_params.ccy_symbol
						+ jempflite_params.ccy_space
						+ $("#jempflite-price-slider").slider("values",
								1));

	} else {
		$("#jempflite-amount").text(
				$("#jempflite-price-slider").slider("values", 0)
						+ jempflite_params.ccy_space
						+ jempflite_params.ccy_symbol
						+ " - "
						+ $("#jempflite-price-slider").slider("values",
									1) + jempflite_params.ccy_space
							+ jempflite_params.ccy_symbol);

	}

	//setup selec2 
	$('#jempflite-attribute-select').select2();
	
	//handle changes to the item
	$('#jempflite-attribute-select').on("select2-selecting", function (e) {
		//alert('selected' + e.val + " " + e.choice['text']);
		
		//lets add it into our URL query
		vars = jempfliteGetURLParams();
		
		//what attribute are we working on? It's in the class of the select (I know I need a better way of passing it in)
		cls = $(this).attr('class');
		//strip off jempflite-
		cls = cls.replace('jempflite-', '');
		
		//are we quereying on attribute?
		if( typeof(vars['filter-value']) == 'undefined' ){
			//don't have one so lets push it on!
			url = jempfliteUpdateQueryString('attr-query', cls); 
			url = jempfliteUpdateQueryString('filter-value', e.val, url); 
			
		} else {
			//one already exists
			
			//get the val
			val = vars['filter-value'];
			
			//add this one on
			val = val + "," + e.val;
			url = jempfliteUpdateQueryString('attr-query', cls); 
			url = jempfliteUpdateQueryString('filter-value', val, url); 
			
				
		}
		

		//always reset to page1
		url = url.replace(/page\/([0-9]+)/, 'page/1');

		//push onto the history
		history.pushState({}, '', url);
		
		//now lets refresh
		refreshProducts();
	});
	
	$('#jempflite-attribute-select').on("select2-removed", function (e) {
		//alert('UN-selected'  + e.val + " " + e.choice['text']);
		//remove this one from the list
		vars = jempfliteGetURLParams();
		val = vars['filter-value'];
		
		pieces = val.split(',');
		existingLocn = pieces.indexOf(e.val)
		if(existingLocn > -1){
			//it is aready there! so just remove it from aray
			pieces.splice(existingLocn, 1);
			
			if(pieces.length > 0){
				//join together
				val = pieces.join();
	
			} else {
				val = null;
			}
			
			url = jempfliteUpdateQueryString('filter-value', val, url);
			//always reset to page1
			url = url.replace(/page\/([0-9]+)/, 'page/1');

			//push onto the history
			history.pushState({}, '', url);
			
			//now lets refresh
			refreshProducts();
			
		} 
		
		
	});
	
	//handle the clicks on the category filters
	$("#jempflite-categories ul li a").live('click', function(e) {
		e.preventDefault();
		
		//key and val
		key = $(this).attr('jempflite-key');
		val = $(this).attr('jempflite-value');
		
		//get existing parms
		vars = jempfliteGetURLParams();
		
		//do we have an existing one?
		if(typeof(vars[key]) !== 'undefined'){
			
			//split it up by ,
			vals = vars[key].split(',');
			existingLocn = vals.indexOf(val)
			if(existingLocn > -1){
				//it is aready there! so just remove it from aray
				vals.splice(existingLocn, 1);
				
				if(vals.length > 0){
					//join together
					val = vals.join();
		
				} else {
					val = null;
				}
				
				
			} else {
				val  = vars[key] + "," + val; 
				
			}
		}
		
		
		//add this onto our url!
		url = jempfliteUpdateQueryString(key, val);
		//always reset to page1
		url = url.replace(/page\/([0-9]+)/, 'page/1');

		history.pushState({}, '', url);

		//now lets refresh
		refreshProducts();

	});
	
    //We will take control of the sorting dropdown....
	
	//don't let it fire!
    $(document).on('submit', 'form.woocommerce-ordering', function (event) {
        event.preventDefault();
    });
    //handle the change
    $(document).on('change', 'select.orderby', function (event) {
        event.preventDefault();

        //get the value 
        val = $('.woocommerce-ordering select.orderby').val()
        
        //add this onto our url!
		url = jempfliteUpdateQueryString('orderby', val);
        
		//always reset to page1
		url = url.replace(/page\/([0-9]+)/, 'page/1');

		history.pushState({}, '', url);

		//now lets refresh
		refreshProducts();
    });
});

//***********************************
//Handles success on the ajax call
//***********************************
function ajaxSuccess(data){

	//results returned?
	
	if(jQuery(data).find(jempflite_params.no_products_found).length > 0){
		//no products
		products = jQuery(data).find(jempflite_params.no_products_found); 
	} else {
		products = jQuery(data).find(jempflite_params.custom_loop_container); 
		
	}
	
	//ok lets load the new shop results onto the page
	//jQuery(jempflite_params.custom_loop_container).html( products );
	jQuery(jempflite_params.custom_loop_container).html( products );

	//now load the new widgets ove the old - we only have one so pretty easy
	new_widget = jQuery(data).find('#jempflite-categories');
	jQuery('#jempflite-categories').replaceWith(new_widget);
}

//***********************************
//Handles ERROR on the ajax call
//***********************************
function ajaxError(data){

	//something bad happened! Go back to previous page
	window.history.back();
}



//This gets the URL params
//inspired by http://jquery-howto.blogspot.com/2009/09/get-url-parameters-values-with-jquery.html
function jempfliteGetURLParams()
{
    var vars = [], hash;
    var hashes = window.location.href.slice(window.location.href.indexOf('?') + 1).split('&');
    for(var i = 0; i < hashes.length; i++)
    {
        hash = hashes[i].split('=');
        vars.push(hash[0]);
        vars[hash[0]] = hash[1];
    }
    return vars;
}


/**
 * This updates a paramter on the URL
 * Insipred by http://stackoverflow.com/questions/5999118/add-or-update-query-string-parameter
 * If no url - gets it from window.location.href
 * If you don't specify a value, it deletes the paramter
 * @param key
 * @param value
 * @param url
 */
function jempfliteUpdateQueryString(key, value, url) {
    if (!url) url = window.location.href;
    var re = new RegExp("([?&])" + key + "=.*?(&|#|$)(.*)", "gi"),
        hash;

    if (re.test(url)) {
        if (typeof value !== 'undefined' && value !== null)
            return url.replace(re, '$1' + key + "=" + value + '$2$3');
        else {
            hash = url.split('#');
            url = hash[0].replace(re, '$1$3').replace(/(&|\?)$/, '');
            if (typeof hash[1] !== 'undefined' && hash[1] !== null) 
                url += '#' + hash[1];
            return url;
        }
    }
    else {
        if (typeof value !== 'undefined' && value !== null) {
            var separator = url.indexOf('?') !== -1 ? '&' : '?';
            hash = url.split('#');
            url = hash[0] + separator + key + '=' + value;
            if (typeof hash[1] !== 'undefined' && hash[1] !== null) 
                url += '#' + hash[1];
            return url;
        }
        else
            return url;
    }
}
//***********************************
// This is where the magic happens
//***********************************
function refreshProducts(){
	
	//loading image
	var loader = '<div class="jempflite-loading-image" style="background-color: #ffffff;"><img src="' + jempflite_params.plugin_url + 'images/loader2.gif"></div>';
	
	jQuery(jempflite_params.shop_loop_container).html(loader);
	
	//do the ajax call
	jQuery.ajax({
		url: url,
		type: 'GET',
		success: ajaxSuccess,
		error: ajaxError
	});

}
