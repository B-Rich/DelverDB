
var nameSuggestionIndex = -1;

$(document).ready(function()
{
	"use strict";
	$("#CardName, #CardCount, #CardSet. #setSuggestions").keydown( function(event)
	{ 
		"use strict";
		if(event.which == 13)
		{
			SingleCardSubmit();
			return;
		}
		
		if(event.which == 40) // down
		{
			nameSuggestionIndex = nameSuggestionIndex >= 4 ? 4 : nameSuggestionIndex + 1;
		}
		
		if(event.which == 38) // up
		{
			nameSuggestionIndex = nameSuggestionIndex <= 0 ? 0 : nameSuggestionIndex - 1;
		}
		
		
	});
	
	$("#CardName").keyup(function(event)
	{
		"use strict";
		//if(event.which != 40 && event.which != 38)
		DisplayCardNameSuggestions();
	}
	)
	
	$("#CardName").focus(function(event)
	{
		"use strict";
		$("#cardSuggestionBox").show();
		DisplayCardNameSuggestions();
	}
	)
	
	$("#CardName").blur(function(event)
	{
		"use strict";
		//$("#cardSuggestionBox").hide();
	}
	)
});

/// Sends an AJAX request to the server adding the card
function SingleCardSubmit()
{
	"use strict";
	var count = $("#CardCount").val();
	if(count == undefined)
	{
		$("#MessageBox").html("Invalid card count");
		return;
	}
	
	var name = $("#CardName").val();
	if(name == undefined)
	{
		$("#MessageBox").html("Invalid name");
		return;
	}
	var setcode = $("#setSuggestions").val();//$("#CardSet").val();
	if(setcode == undefined)
	{
		$("#MessageBox").html("Invalid setcode");
		return;
	}
	
	if(!IsValidCardAddCount(count))
	{
		$("#MessageBox").html("Invalid card count");
		return;
	}
	
	var xmlhttp;
	if (window.XMLHttpRequest)
	{// code for IE7+, Firefox, Chrome, Opera, Safari
		xmlhttp = new XMLHttpRequest();
	}
	else
	{// code for IE6, IE5
		xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
	}
	
	var url = "usercardsajax.php?count="+count+"&cardname="+name+"&setcode="+setcode;
	
	/// AJAX
	xmlhttp.onreadystatechange = function()
	{
		if (xmlhttp.readyState == 4 && xmlhttp.status == 200)
		{
			
			var xml = xmlhttp.responseXML;
			var response = xml.childNodes[0];
			var attrs = response.attributes;
			var errno = attrs.getNamedItem('errno').value;
			var message = response.childNodes[0].data;
			$("#MessageBox").html(message);
			if(errno != 0)
			{
				return;
			}
			
			/// Reset fields, focus on count field
			$("#CardCount").val("");
			$("#CardName").val("");
			$("#CardCount").focus();
		}
	}
	//alert(url);
	xmlhttp.open("GET", url, true);
	xmlhttp.send();
}

function DisplayCardNameSuggestions()
{
	"use strict";
	
	var cardName = $("#CardName").val();
	if(cardName == undefined || cardName == "")
	{
		$("#cardSuggestionBox").html("");
		return;
	}
	
	var xmlhttp;
	if (window.XMLHttpRequest)
	{// code for IE7+, Firefox, Chrome, Opera, Safari
		xmlhttp = new XMLHttpRequest();
	}
	else
	{// code for IE6, IE5
		xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
	}
	
	var url = "cardnamesuggestion.php?cardname="+cardName;
	
	/// AJAX
	xmlhttp.onreadystatechange = function()
	{
		if (xmlhttp.readyState == 4 && xmlhttp.status == 200)
		{
			
			var xml = xmlhttp.responseXML;
			var response = xml.childNodes[0];
			
			$("#cardSuggestionBox").show();
			
			var cards = response.getElementsByTagName("card");
			var numCardsFound = cards.length;
			
			var html = "";
			for(var cardIndex = 0; cardIndex < numCardsFound; ++cardIndex)
			{
				var card = cards[cardIndex];
				var cardattrs = card.attributes;
				var cardname = cardattrs.getNamedItem('name').value;
				
				if(cardname ==  $("#CardName").val())
				{
					DisplaySetSuggestions();
				}
				else
				{
					$("#setSuggestions").html("");
				}
				
				var selected = nameSuggestionIndex == cardIndex;
				
				html += "<label onclick='SetNameField(\""+cardname+"\")'>";
				if(selected)
				{
					html += "<b>";
				}
				html += cardname;
				if(selected)
				{
					html += "</b>";
				}
				
				html += "</label></br>";
			}
			$("#cardSuggestionBox").html(html);
		}
	}
	$("#cardSuggestionBox").html("Searching...");
	xmlhttp.open("GET", url, true);
	xmlhttp.send();
}

function DisplaySetSuggestions()
{
	"use strict";
	
	var cardName = $("#CardName").val();
	if(cardName == undefined || cardName == "")
	{
		$("#setSuggestions").html("");
		return;
	}
	
	var xmlhttp;
	if (window.XMLHttpRequest)
	{// code for IE7+, Firefox, Chrome, Opera, Safari
		xmlhttp = new XMLHttpRequest();
	}
	else
	{// code for IE6, IE5
		xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
	}
	
	var url = "cardsetsuggestion.php?cardname="+cardName;
	
	/// AJAX
	xmlhttp.onreadystatechange = function()
	{
		if (xmlhttp.readyState == 4 && xmlhttp.status == 200)
		{
			
			var xml = xmlhttp.responseXML;
			var response = xml.childNodes[0];
			
			var sets = response.getElementsByTagName("set");
			var numSetsFound = sets.length;
			
			var html = "";
			for(var setIndex = numSetsFound - 1; setIndex >= 0; --setIndex)
			{
				var set = sets[setIndex];
				var setAttrs = set.attributes;
				var setcode = setAttrs.getNamedItem('code').value;
				var setname = setAttrs.getNamedItem('name').value;
				
				html += "<option value='"+setcode+"' ";
				if(setIndex == numSetsFound - 1)
				{
					html += "selected='selected'";
				}
				html += ">" + setname+"</option>";
			}
			$("#setSuggestions").html(html);
		}
	}
	xmlhttp.open("GET", url, true);
	xmlhttp.send();

}

function SetNameField(_name)
{
	"use strict";
	//alert(_name);
	$("#cardSuggestionBox").html("");
	$("#CardName").val(_name);
	$("#cardSuggestionBox").hide();
	DisplaySetSuggestions();
}