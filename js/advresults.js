
function onShowTagField( _cardID )
{
	"use strict";
	$("#tag_input_" + _cardID ).toggle();
}

function onAddTag( _cardID )
{
	"use strict";
	var tagID = $("#add_tag_"+_cardID ).val();
	
	var xmlhttp = new XMLHttpRequest();
	
	var url = "tagajax.php?mode=add&tagid=" + tagID + "&cardid=" + _cardID;
	
	xmlhttp.onreadystatechange = function()
	{
		"use strict";
		if ( xmlhttp.readyState == 4 && xmlhttp.status == 200 )
		{
			var xml = xmlhttp.responseXML;
			var response = xml.childNodes[0];
			var attrs = response.attributes;
			var errno = attrs.getNamedItem( 'errno' ).value;
			var message = response.childNodes[0].data;
			
			if ( errno > 0 )
			{
				window.location.reload();
			}
		}
	}
	
	xmlhttp.open( "GET", url, true );
	xmlhttp.send();
}

function onCreateTag( _cardID )
{
	"use strict";
	var tagName = $("#tag_name_" + _cardID ).val();
	
	if ( tagName == "" || tagName == undefined )
	{
		return;
	}
	
	var xmlhttp = new XMLHttpRequest();
	
	var url = "tagajax.php?mode=create&tagname=" + tagName;
	
	xmlhttp.onreadystatechange = function()
	{
		"use strict";
		if ( xmlhttp.readyState == 4 && xmlhttp.status == 200 )
		{
			var xml = xmlhttp.responseXML;
			var response = xml.childNodes[0];
			var attrs = response.attributes;
			var errno = attrs.getNamedItem( 'errno' ).value;
			var message = response.childNodes[0].data;
			
			if ( errno > 0 )
			{
				window.location.reload();
			}
		}
	}
	
	xmlhttp.open( "GET", url, true );
	xmlhttp.send();
}

function onRemoveTag( _cardID, _tagID )
{
	"use strict";
	
	var xmlhttp = new XMLHttpRequest();
	
	var url = "tagajax.php?mode=remove&tagid=" + _tagID + "&cardid=" + _cardID;
	
	xmlhttp.onreadystatechange = function()
	{
		"use strict";
		if ( xmlhttp.readyState == 4 && xmlhttp.status == 200 )
		{
			var xml = xmlhttp.responseXML;
			var response = xml.childNodes[0];
			var attrs = response.attributes;
			var errno = attrs.getNamedItem( 'errno' ).value;
			var message = response.childNodes[0].data;
			
			if ( errno > 0 )
			{
				window.location.reload();
			}
		}
	}
	
	xmlhttp.open( "GET", url, true );
	xmlhttp.send();
}