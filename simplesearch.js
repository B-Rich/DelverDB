var CardResults = new Array();
var SelectedCardID = -1;
var DeckContents = new Array();

var CardResultDisplayLimit = 500;

function variableDefined (name)
{
    return typeof this[name] != 'undefined';
}

$(window).load( function()
{
	$("#textfield").keydown(function(event)
	{ 
		if ( event.which == 13 ) // Return key
		{
			SendSearchRequest();
		}
	});
	
	if ( variableDefined('UseExistingDeck') && variableDefined('OldDeckContents') )
	{
		DeckContents = OldDeckContents;
		DisplayDeckContents();
		ShowNumberOfCardsInDeck();
	};
}
);

var CheckboxNames = new Array
(
	'Creature',
	'Planeswalker',
	'Artifact',
	'Enchantment',
	'Instant',
	'Sorcery',
	'Land',
	"White",
	"Blue",
	"Black",
	"Red",
	"Green",
	'Colourless',
	"Common",
	"Uncommon",
	"Rare",
	"Mythic",
	"MyCards",
	"Name",
	"Types",
	"Rules"
);

////////////////////////////////////////////////////////////////////////////////////////////////////
/// OBJECTS
function DeckCard(_name, _setcode, _id, _count, _numOwn, _multiverseid)
{
	"use strict";
	
	this.name = _name;
	this.setcode = _setcode;
	this.id = _id;
	this.count = _count;
	this.numOwn = _numOwn;
	this.multiverseid = _multiverseid;
}

function Card(_id, _name)
{
	"use strict";
	
	this.name = _name;
	this.totalCount;
	this.sets = new Array();
	
	this.GetFirstSet = GetFirstSet;
	function GetFirstSet()
	{
		for ( var i in this.sets )
		{
			return this.sets[i];
		}
	}
}

function Set(_setcode, _rarity, _count, _multiverseid)
{
	"use strict";
	this.setcode = _setcode;
	this.rarity = _rarity;
	this.count = _count;
	this.multiverseid = _multiverseid;
}

////////////////////////////////////////////////////////////////////////////////////////////////////
/// FUNCTIONS


function DisplayMessage(_msg)
{
	"use strict";
	$("#AddCardMessageDiv").html(_msg);	
}

function FindSetIndex(_card, _setcode)
{
	"use strict";
	for(var index in _card.sets)
	{
		if(_card.sets[index].setcode == _setcode)
			return index;
	}
	return -1;
}

function SendSearchRequest()
{
	"use strict";

	SelectedCardID = -1;
	ResetCardPreview();
	DisplayNoCardSets();
	
	$("#searchResultDiv").html("");
	$("#CardDeckInfoDiv").html("");
	
	var xmlhttp;
	if ( window.XMLHttpRequest )
	{// code for IE7+, Firefox, Chrome, Opera, Safari
		xmlhttp = new XMLHttpRequest();
	}
	else
	{// code for IE6, IE5
		xmlhttp = new ActiveXObject( "Microsoft.XMLHTTP" );
	}

	var url = "cardsearchajax.php";
	var postData = "";
	
	var parameters = [];
	var textField = $("#textfield");
	var textParam = textField.val();
	if ( textParam != "" )
	{
		textParam = encodeURIComponent( textParam );
		parameters["text"] = textParam;
	}
	
	for ( var index in CheckboxNames )
	{
		var cbName = CheckboxNames[index];
		if( $("#"+cbName+"checkbox") === null )
		{
			alert("Could not find checkbox named \""+cbName+"checkbox\"");
			continue;
		}
		
		if ( $("#"+cbName+"checkbox").prop('checked') )
		{
			parameters[cbName] = 1;
		}
	}
	
	var first = true;
	for ( var param in parameters )
	{
		if ( first )
		{
			first = false;
		}
		else
		{
			postData += '&';
		}
		postData += param + '=' + parameters[param];
	}
	
	var selectedExpansions = $("#expansionfield option:selected");
	for ( var i = 0; i < selectedExpansions.length; ++i )
	{
		if ( first )
		{
			first = false;
		}
		else
		{
			postData += '&';
		}
		postData += "set[]="+selectedExpansions[i].value;
	}
	
	$("#numCardsFoundDiv").html( "Searching" );
	
	xmlhttp.onreadystatechange = function()
	{
		if (xmlhttp.readyState == 4 && xmlhttp.status == 200)
		{
			CardResults = new Array();
			
			var xml = xmlhttp.responseXML;
			
			if(xml === null)
			{
				DisplayMessage("Internal error");
				return;
			}
			
			var response = xml.getElementsByTagName("response");
			if(response === null)
			{
				DisplayMessage("Internal error");
				return;
			}
			
			var error = response[0].getElementsByTagName("error");
			if(error != undefined && error.childNodes != undefined)
			{
				var errmsg = error.childNodes[0].data;
				DisplayMessage(errmsg);
				return;
			}
			
			var cards = response[0].getElementsByTagName("card");
			var numCardsFound = cards.length;
			
			if(numCardsFound >= CardResultDisplayLimit)
			{
				$("#numCardsFoundDiv").html("More Than "+CardResultDisplayLimit+" Cards Found: Showing First 500");
			}
			else
			{
				$("#numCardsFoundDiv").html(numCardsFound + " Cards Found");
			}
			
			for ( var cardIndex = 0; cardIndex < numCardsFound; ++cardIndex )
			{
				var card = cards[cardIndex];
				var cardattrs = card.attributes;
				var cardname = cardattrs.getNamedItem('name').value;
				var cardid = cardattrs.getNamedItem('id').value;
				var totalCount = parseInt(cardattrs.getNamedItem('totalcount').value);
				
				var cardObj = new Card(cardid, cardname);
				cardObj.totalCount = totalCount;
				CardResults[cardid] = cardObj;
				
				var sets = card.getElementsByTagName("set");
				for ( var setIndex = 0; setIndex < sets.length; ++setIndex )
				{
					var set = sets[setIndex];
					var setattrs = set.attributes;
					var setcode = setattrs.getNamedItem('setcode').value;
					
					var rarity = setattrs.getNamedItem('rarity').value;
					var count = parseInt(setattrs.getNamedItem('count').value);
					var multiverseid = setattrs.getNamedItem('multiverseid').value;
					totalCount += count;
					
					var setObj = new Set(setcode, rarity, count, multiverseid);
					cardObj.sets[setcode] = setObj;
				}
			}
			DisplaySearchResults();
		}
	}
	url += "?" + postData
	
	xmlhttp.open("GET", url, true);
	xmlhttp.setRequestHeader("Content-type","application/x-www-form-urlencoded");
	xmlhttp.send();
}

function DisplaySearchResults()
{
	"use strict";
	$("#searchResultDiv").html("");
	for(var cardid in CardResults)
	{
		var card = CardResults[cardid];
		var set = card.GetFirstSet();
		
		var html = '<img src="' + GetCardImageURL( set.multiverseid ) + '"\
			alt="'+card.name+'" \
			class="CardImageInWindow" \
			title="'+card.name+'" \
			onclick="SelectSearchCard('+cardid+')" /> ';
		
		$("#searchResultDiv").append( html );
	}
}

function SelectSearchCard( _cardid )
{
	"use strict";
	SelectedCardID = _cardid;
	if ( !(_cardid in CardResults) )
	{
		DisplayMessage("Cannot find card with ID: "+_cardid);
		return;
	}
	
	var card = CardResults[_cardid];
	var cardname = card.name;
	var setcode = card.GetFirstSet().setcode;
	ChangeSetOfCardPreview( setcode );
	if ( PageMode == "Search" )
	{
		DisplayCardSets();
	}
	else if(PageMode == "CreateDeck")
	{
		DisplayCardDeckInfo();
	}
}

function SelectDeckCard(_cardid)
{
	"use strict";
	if(!(_cardid in DeckContents))
	{
		alert(_cardid + " cannot be found");
		return;
	}
	SelectedCardID = _cardid;
	
	var card = DeckContents[_cardid];
	ChangeSetOfCardPreview(card.setcode);
	DisplayCardDeckInfo();
}

function DisplayNoCardSets()
{
	"use strict";
	$("#SelectedCardExpansions").html("");
}

function DisplayCardSets()
{
	"use strict";
	var cardExpDiv = $("#SelectedCardExpansions");
	cardExpDiv.html("");
	
	if(!SelectedCardID in CardResults)
	{
		DisplayMessage("Cannot find "+SelectedCardID + " in results");
		return;
	}
	
	var card = CardResults[SelectedCardID];
	
	if(IsLoggedIn)
	{
		SetCardOwnershipTotal(card.totalCount);
	}
	
	var first = true;
	for ( var setcode in card.sets )
	{
		var set = card.sets[setcode];
		var setimg = GetSetIconURL(set.setcode, set.rarity);
		
		var str = "";
		str += "<label onclick=\"ChangeSetOfCardPreview('"+set.setcode+"')\" title='"+set.setcode+"' >";
		str += "<input type='radio' " +
				"name='exp' " +
				"id='expRadio"+set.setcode+"'"+
				(first?'checked="checked"':'') + 
				"value='"+set.setcode+"' /> ";
		str += "<span id='expSpan"+set.setcode+"'><img src='"+setimg+"' alt='"+set.setcode+"' />";
		
		first = false;
		
		if(IsLoggedIn)
		{
			str += ": " + set.count;
		}
		str += "</span>";
		
		if(IsLoggedIn)
		{
			str += "</label></br>";
		}
		cardExpDiv.append(str);
	}
}

function ResetCardPreview()
{
	"use strict";
	$("#SelectedCardImg").prop("src", "images/cardback.jpg");
}

function ChangeSetOfCardPreview( _setcode )
{
	"use strict";
	var cardname = undefined;
	var multiverseid = undefined;
	if ( SelectedCardID in CardResults )
	{ 
		cardname = CardResults[SelectedCardID].name;
		multiverseid = CardResults[SelectedCardID].sets[_setcode].multiverseid;
	}
	else if(PageMode == "CreateDeck" && SelectedCardID in DeckContents)
	{
		cardname = DeckContents[SelectedCardID].name;
		multiverseid = DeckContents[SelectedCardID].multiverseid;
	}
	else
	{
		$("#SelectedCardImg").prop("src", "images/cardback.jpg");
		DisplayMessage("Card ID "+SelectedCardID+" is not valid");
		return;
	}
	$("#SelectedCardLink").prop('href', 
			'carddetails.php?id=' + SelectedCardID + "&set=" + _setcode );
	
	var imgurl = GetCardImageURL( multiverseid );
	imgurl = $("<div/>").html(imgurl).text();
	$("#SelectedCardImg").prop( "src", imgurl );
}

function SendCardChangeRequest(_cardid, _setcode, _count)
{
	"use strict";
	if(!isNumber(_cardid))
	{
		DisplayMessage("Invalid card id");
		return;
	}
	
	if(!isNumber(_count))
	{
		DisplayMessage("Invalid card count");
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
	
	var url = "usercardsajax.php?count="+_count+"&cardid="+_cardid+"&setcode="+_setcode;
	
	xmlhttp.onreadystatechange = function()
	{
		if (xmlhttp.readyState == 4 && xmlhttp.status == 200)
		{
			var xml = xmlhttp.responseXML;
			
			if ( !xml )
			{
				$("#DeckMessages").html("Internal error");
				return;
			}
		
			var response = xml.childNodes[0];
			var attrs = response.attributes;
			var errno = attrs.getNamedItem('errno').value;
			var cardid = attrs.getNamedItem('cardid').value;
			var setcode = attrs.getNamedItem('setcode').value;
			var newcount = attrs.getNamedItem('newcount').value;
			var message = response.childNodes[0].data;
			
			DisplayMessage(message);
		
			if(!cardid in CardResults)
			{
				DisplayMessage("Warning: Could not find "+cardid+" in CardsFound");
				return;
			}
			
			var setindex = FindSetIndex(CardResults[cardid], setcode);
			if(setindex == -1)
			{
				DisplayMessage("Warning: Could not find set: "+setcode+" for card "+cardid);
				return;
			}
			
			// Update card appearance and storage
			var card = CardResults[cardid];
			card.totalCount += (newcount - card.sets[setindex].count);
			card.sets[setindex].count = newcount;
			var rarity = card.sets[setindex].rarity;
			
			var setimg = GetSetIconURL(setcode, rarity);
			SetCardOwnershipTotal(card.totalCount);
			$("#CardOwnershipTotal").html(card.totalCount);
			$("#expSpan"+setcode).html("<img src='"+setimg+"' alt='"+setcode+"' />" + ": "+newcount+"</span>");
		}
	}
	
	xmlhttp.open("GET", url, true);
	xmlhttp.send();
}

function AddCardButton()
{
	"use strict";
	if(SelectedCardID == -1)
	{
		$("#AddCardMessageDiv").html("No card selected");
		return;
	}
	
	var countField = $("#CardCountField");
	var cardCount = countField.val();
	
	if(!IsValidCardAddCount(cardCount))
	{
		$("#AddCardMessageDiv").html("Invalid count. Must be an integer non-zero value.");
		return;
	}
	
	var setcodeRadio = $("input[type='radio']:checked");
	if(!setcodeRadio)
	{
		DisplayMessage("No set selected");
	}
	
	var setcode = setcodeRadio.val();
		
	SendCardChangeRequest(SelectedCardID, setcode, cardCount);
}

function ResetSetSelector()
{
	"use strict";
	var selectedExpansions = $("#expansionfield option:selected");
	for(var i = 0; i < selectedExpansions.length; ++i)
	{
		selectedExpansions[i].selected = false;
	}
}

function ResetSearchFields()
{
	"use strict";
	for(var index in CheckboxNames)
	{
		var cbName = CheckboxNames[index];
		if(!$("#"+cbName+"checkbox"))
		{
			alert("Could not find checkbox named \""+cbName+"checkbox\"")
		}
		else
		{
			$("#"+cbName+"checkbox").prop("checked", "checked");
		}
	}
	
	ResetSetSelector();
	$("#textfield").val("");
	$("#MyCardscheckbox").prop("checked","");
}

/// Checks all checkboxes in one of the rows to all on if any aren't checked, 
/// otherwise unchecks them all
function ToggleCheckboxes()
{
	"use strict";
	var allChecked = true;
	for(var i = 0; i < arguments.length; ++i)
	{
		if($("#"+arguments[i]+"checkbox").prop("checked") == false)
		{
			allChecked = false;
			break;
		}
	}
	
	for(var i = 0; i < arguments.length; ++i)
	{
		$("#"+arguments[i]+"checkbox").prop("checked", !allChecked);
	}
}

function DisplayCardDeckInfo()
{
	"use strict";
	var html = "";
	if(SelectedCardID == -1)
	{
		$("#CardDeckInfoDiv").html("No Card Selected");
		return;
	}
	
	var cardIsInDeck = (SelectedCardID in DeckContents);
	
	// Disable the remove button if the card isn't currently in the deck
	html += "<input type='button' value='-' ";
	if(!cardIsInDeck)
	{
		html += " disabled='disabled' ";
	}
	html += " onclick=\"javascript:SubtractCardFromDeck()\" />";
	
	var numInDeck = cardIsInDeck ? DeckContents[SelectedCardID].count : 0;
	var numOwn;
	if(IsLoggedIn)
	{
		if(cardIsInDeck)
		{
			numOwn = DeckContents[SelectedCardID].numOwn;
		}
		else if(SelectedCardID in CardResults)
		{
			numOwn = CardResults[SelectedCardID].totalCount;
		}
		else
		{
			numOwn = 0;
		}
	}
	else
	{
		numOwn = "&infin;";
	}
	
	html += "<span class='DeckOverlayText ";
	if(!IsLoggedIn || numInDeck <= numOwn)
	{
		html += ' PositiveOverlayText ';
	}
	else
	{
		html += ' NegativeOverlayText ';
	}
	html += "'>&nbsp;" + numInDeck + "/" + numOwn + "&nbsp;</span>";
	
	html += "<input type='button' value='+' onclick=\"javascript:AddCardToDeck()\" />";
	html += "</br><input type='button' ";
	if(!cardIsInDeck)
	{
		html += " disabled='disabled' ";
	}
	html += "value='Remove' onclick=\"javascript:RemoveCardFromDeck()\" />";
	
	$("#CardDeckInfoDiv").html(html);
}

function DisplayDeckContents()
{
	"use strict";
	var html = "";
	
	for ( var cardid in DeckContents )
	{
		var card = DeckContents[cardid];
		
		var numOwn = IsLoggedIn ? card.numOwn : "&infin;";
		
		html += '<label title="' + card.name + '" onclick="SelectDeckCard(\''+card.id+'\')" >\n';
		html += "<div class='CardImageInWindow DeckCardDiv'>\n";
		html += "<div class='DeckCardOverlay' >\n";
		html += "<p class='DeckOverlayText";
		if(!IsLoggedIn || card.count <= card.numOwn)
		{
			html += " PositiveOverlayText ";
		}
		else
		{
			html += " NegativeOverlayText ";
		}
		html += "'>" + card.count + "/" + numOwn + "</p></div>\n";
		
		html += "<img src=\"" + GetCardImageFilename( card.name, card.setcode ) + "\" \
				class=\"CardImageInWindow\" \
				alt='"+card.setcode+"' \
				title=\"" + card.name + "\" \
				style='z-index: -1;' />\n";
		html += "</div>\n ";
		html += "</label>\n";
	}
	$("#DeckContentsDiv").html(html);
}

function AddCardToDeck()
{
	"use strict";
	if(SelectedCardID in DeckContents)
	{
		DeckContents[SelectedCardID].count++;
	}
	else if(SelectedCardID in CardResults)
	{
		var foundCard = CardResults[SelectedCardID];
		var newCard = new DeckCard();
		newCard.count = 1;
		newCard.name = foundCard.name;
		newCard.setcode = foundCard.GetFirstSet().setcode;
		newCard.id = SelectedCardID;
		newCard.numOwn = foundCard.totalCount;
		DeckContents[SelectedCardID] = newCard;
	}
	else
	{
		alert("Selected card not found in results");
		return;
	}
	DisplayCardDeckInfo();
	DisplayDeckContents();
	ShowNumberOfCardsInDeck();
}

function SubtractCardFromDeck()
{
	"use strict";
	if(SelectedCardID in DeckContents)
	{
		DeckContents[SelectedCardID].count--;
		if(DeckContents[SelectedCardID].count <= 0)
		{
			delete DeckContents[SelectedCardID];
			SelectedCardID = -1;
		}
		DisplayCardDeckInfo();
		DisplayDeckContents();
		ShowNumberOfCardsInDeck();
	}
}

function RemoveCardFromDeck()
{
	"use strict";
	if(SelectedCardID in DeckContents)
	{
		delete DeckContents[SelectedCardID];
		SelectedCardID = -1;
		
		DisplayCardDeckInfo();
		DisplayDeckContents();
		ShowNumberOfCardsInDeck();
	}
}

function SaveDeck(_overwrite)
{
	"use strict";
	
	var xmlhttp;
	if (window.XMLHttpRequest)
	{// code for IE7+, Firefox, Chrome, Opera, Safari
		xmlhttp = new XMLHttpRequest();
	}
	else
	{// code for IE6, IE5
		xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
	}
	
	var url = "createdeckajax.php?";
	
	var count = 0;
	for(var cardIndex in DeckContents)
	{
		count++;
		var cardid = DeckContents[cardIndex].id;
		var cardCount = DeckContents[cardIndex].count;
		url += "count[]="+cardCount+"&id[]="+cardid+"&";
	}
	
	if(count == 0)
	{
		$("#DeckMessages").html("You need to add a card to the deck first!");
		return;
	}
	
	if(_overwrite)
	{
		if(ExistingDeckID == null || ExistingDeckID == -1)
			return;
		
		url +="overwrite=1&deckid="+ExistingDeckID;
	}
	
	var deckName = $("#DeckName").val();
	if(!deckName || deckName == '' || deckName.length > 50)
	{
		$("#DeckMessages").html("Invalid deckname");
		return;
	}
	$("#DeckName").val("");
	url += "&deckname="+deckName;
	
	xmlhttp.onreadystatechange = function()
	{
		if (xmlhttp.readyState == 4 && xmlhttp.status == 200)
		{
			var xml = xmlhttp.responseXML;
			
			if(!xml)
			{
				$("#DeckMessages").html("Error processing deck.");
				return;
			}
			
			var response = xml.childNodes[0];
			var attrs = response.attributes;
			var errno = attrs.getNamedItem('errno').value;
			var message = response.childNodes[0].data;
			$("#DeckMessages").html(message);
			
			if(errno != 0)
			{
				return;
			}
			
			window.location = "mydecks.php";
		}
	}
	xmlhttp.open("GET", url, true);
	xmlhttp.send();
}

function NumberOfCardsInDeck()
{
	"use strict";
	var count = 0;
	for(var cardid in DeckContents)
	{
		var card = DeckContents[cardid];
		count += card.count;
	}
	return count;
}

function ShowNumberOfCardsInDeck()
{
	"use strict";
	$("#CardsInDeckDiv").html(NumberOfCardsInDeck() + " cards in deck");
}

function SetCardOwnershipTotal(_count)
{
	"use strict";
	if(IsLoggedIn)
	{
		$("#CardOwnershipTotal").html(_count);
	}
}


