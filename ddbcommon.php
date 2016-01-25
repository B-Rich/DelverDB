<?php
function MTGSymbolReplace($str)
{
    return preg_replace('<\{(.+?)(?:/(.+?))?\}>', "<img src='images/\${1}\${2}.png' alt='\${1}\${2}' class='TextManaSymbol'/>", $str);
}

?>