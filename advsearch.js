var ParameterTypes = ['name', 'rules', 'expansion', 'format', 'colour', 'colourid', 'numcolours', 'type', 'subtype', 'cost', 'cmc', 'power', 'toughness', 'rarity', 'artist', 'count', 'order'];
var DisplayParamTypes = ['Name', 'Rules Text', 'Expansion', 'Format', 'Colour', 'Colour Identity', 'Colour Count', 'Type', 'Subtype', 'Mana Cost', 'Converted Mana Cost', 'Power', 'Toughness', 'Rarity', 'Artist', 'Count', 'Sort Order'];

function Parameter()
{
	this.name;
	this.value;
	this.boolean;
	this.comparison;
}

var Parameters = new Array();

$(window).load(function(){
	DisplayParameters();
});

function AddParam( _paramType )
{
	"use strict";
	
	if( !_paramType in ParameterTypes )
	{
		alert( 'Unrecognised parameter type \"' + _paramType + '"' );
		return;
	}
	
	var paramValue = $('#' + _paramType + 'field').val();
	if ( paramValue == "" )
		return;
	
	var boolVal = $('#' + _paramType + 'bool').val();
	var boolChar = null;
	switch ( boolVal )
	{
	case 'and':
		boolChar  = '&';
		break;
	case 'or':
		boolChar  = '|';
		break;
	case 'not':
		boolChar  = '!';
		break;
	default:
		break;
	};
	
	var compSymbol = null;
	if ( $('#'+_paramType+'comp').is('*') )
	{
		compSymbol = $('#'+_paramType+'comp').val();
	}

	$('#'+_paramType+'field').val("");
	
	var pieces;
	if ( _paramType != "format" )
	{
		pieces = splitstring(paramValue);
	}
	else
	{
		pieces = new Array();
		pieces[0] = paramValue;
	}
	
	
	if ( pieces.length == 0 )
		return;
	
	if ( !(_paramType in Parameters) || Parameters[_paramType] == undefined )
	{
		Parameters[_paramType] = new Array();
	}
	
	for ( var index in pieces )
	{
		var param = new Parameter();
		param.value = pieces[index];
		param.comparison = compSymbol;
		param.boolean = boolChar;
		Parameters[_paramType].push(param);
	}
	
	DisplayParameters();
};

function Submit()
{
	"use strict";
	
	// Remove all hidden fields in the form
	$("[type='hidden']").remove();

	var parameterCount = 0;
	
	for(var paramType in Parameters)
	{
		for(var j in Parameters[paramType])
		{
			if(paramType != 'order')
				++parameterCount;
			
			var param = Parameters[paramType][j];
			
			var paramString = param.value;
			if(param.comparison != undefined)
			{
				paramString = param.comparison + paramString;
			}
			
			if(param.boolean != undefined)
			{
				paramString = param.boolean + paramString;
			}
			
			$("#parametersform").append("<input name='" + paramType+"[]' type='hidden' value='" + paramString + "' />");
		}
	}
	
	var colourBits = 0;
	if ( $('#colour_identity_cb_w').prop('checked') )
	{
		colourBits |= 1;
	}
	if ( $('#colour_identity_cb_u').prop('checked') )
	{
		colourBits |= 2;
	}
	if ( $('#colour_identity_cb_b').prop('checked') )
	{
		colourBits |= 4;
	}
	if ( $('#colour_identity_cb_r').prop('checked') )
	{
		colourBits |= 8;
	}
	if ( $('#colour_identity_cb_g').prop('checked') )
	{
		colourBits |= 16;
	}
	
	// One of the checkboxes is not selected, send the info
	if ( colourBits != (1 | 2 | 4 | 8 | 16 ) )
	{
		$("#parametersform").append("<input name='colouridentity' type='hidden' value='"+colourBits+"' />");
		++parameterCount;
	}
	
	if($('#mycards').prop('checked'))
	{
		$("#parametersform").append("<input name='mycards' type='hidden' value='1' />");
	}
	
	if($('#RequiresMulticoloured').prop('checked'))
	{
		$("#parametersform").append("<input name='multionly' type='hidden' value='1' />");
	}
	
	if ( $('#ExcludeUnselectedColours').prop('checked') )
	{
		$('#parametersform').append( "<input name='excunscolours' type='hidden' value='1' />" );
	}
	
	$("#parametersform").append("<input name='page' type='hidden' value='0' />");
	if ( parameterCount != 0 )
	{
		$("#parametersform").submit();
	}
	
	
};	


function AddTextParam(paramtype)
{
	var p = $("#namefield").val();
	if(p == "")
		return;
	
	nameParams.push(p);
	$("#namefield").val("");
	
	$("#ParametersList").append('<p>'+paramtype+': '+p+'</p>');
};


function DisplayParameters()
{
	var ParDOM = $("#ParameterDiv");
	ParDOM.html("");

	var paramCount = 0;
	for(var paramType in Parameters)
	{	
		var displayIndex = ParameterTypes.indexOf(paramType);
		if(displayIndex == -1)
		{
			alert("Unknown parameter type "+paramType);
			return;
		}
		var displayParam = DisplayParamTypes[displayIndex];
		
		ParDOM.append("<a href=\"javascript:void(0)\" onclick=RemoveParameterType('"+paramType+"')>x</a><b>&nbsp;"+displayParam + "</b></br>");
		for(var paramIndex in Parameters[paramType])
		{
			++paramCount;
			var param = Parameters[paramType][paramIndex];
			
			ParDOM.append("&nbsp;<a href=\"javascript:void(0)\" onclick=\"RemoveParameterValue('"+paramType+"', '"+param.value+"')\">x</a>&nbsp;");
			
			if(param.comparison != undefined)
			{
				switch(param.boolean)
				{
				case undefined:
					break;
				case '&':
					ParDOM.append("AND is");
					break;
				case '|':
					ParDOM.append("OR is");
					break;
				case '!':
					ParDOM.append("is NOT");
					break;
				}
				ParDOM.append(' '+param.comparison+' ');
			}
			else
			{
				switch(param.boolean)
				{
				case undefined:
					break;
				case '&':
					ParDOM.append("DOES contain ");
					break;
				case '|':
					ParDOM.append("OR contains ");
					break;
				case '!':
					ParDOM.append("does NOT contain ");
					break;
				}
			}
			ParDOM.append(param.value + "</br>");
		}
	}
	
	if(paramCount == 0)
	{
		ParDOM.append("None yet");
		return;
	}
}

function RemoveParameterType(_paramType)
{
	"use strict";
	if(!(_paramType in Parameters))
	{
		alert(_paramType+" not found in Parameters");
		return;
	}
	delete Parameters[_paramType];
	DisplayParameters();
}


function RemoveParameterValue(_paramType, _paramValue)
{
	"use strict";
	
	if(!(_paramType in Parameters))
	{
		alert(_paramType+" not found in Parameters");
		return;
	}
	
	var index = -1;
	for(var i in Parameters[_paramType])
	{
		if(Parameters[_paramType][i].value == _paramValue)
		{
			index = i;
			break;
		}
	}
	
	if(index == -1)
	{
		alert( _paramType + " not found in Parameters[" + _paramValue+ "]" );
		return;
	}
	
	Parameters[_paramType].splice(index, 1);
	if(Parameters[_paramType].length == 0)
	{
		delete Parameters[_paramType];
	}
	DisplayParameters();
}
