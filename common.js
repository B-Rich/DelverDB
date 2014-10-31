function isNumber(n) 
{
	"use strict";
	return !isNaN(parseFloat(n)) && isFinite(n);
}

function IsValidCardAddCount(_count)
{
	"use strict";
	return isNumber(_count) && _count != 0 && _count == Math.floor(_count);
}

function splitstring(str) 
{
	"use strict";
    var pieces = str.match(/"[^"]+"|m\/[^\/]+\/|\S+/g);
    for (var i in pieces) 
    {
        pieces[i] = pieces[i].replace(/"/g, '');
    }
    return pieces;
}

function GetSetIconURL(_setcode, _rarity)
{
	"use strict";
	return "images/exp/" + _setcode + "_" + _rarity + "_small.jpg";
}

function GetCardImageFilename( _cardname, _setcode )
{
	"use strict";
	_cardname = encodeURI( _cardname );
	var str = "images/cardpic/_"+_setcode+'/'+_cardname.replace(':', '').replace('"', '')+".jpg";
	//alert( str );
	return str;
}