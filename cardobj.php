<?php

class Card
{
    public $id;
    public $name;
    public $cost;
    public $colours;
    public $type;
    public $subtype;
    public $power;
    public $numpower;
    public $toughness;
    public $numtoughness;
    public $cmc;
    public $loyalty;
    public $rules;

    public $imageurl;
    public $total;

    public $sets = array();
    public $tags = array();

    public function ConstructFromResults( $cardRow )
    {
        $this->id = $cardRow['id'];
        $this->name = $cardRow['name'];
        $this->name = str_replace( '"', '', $this->name );
        $this->cost = $cardRow['cost'];
        $this->cost = MTGSymbolReplace( $this->cost );

        $this->type = $cardRow['type'];
        $this->subtype = $cardRow['subtype'];

        $this->power = $cardRow['power'];
        $this->toughness = $cardRow['toughness'];

        $this->loyalty = $cardRow['loyalty'];

        $this->numpower = $cardRow['numpower'];
        $this->numtoughness = $cardRow['numtoughness'];
        $this->cmc = $cardRow['cmc'];

        $this->rules = $cardRow['rules'];
        $this->rules = str_replace('~', '</br>', $this->rules);
        $this->rules = str_replace( '(', '<i>(', $this->rules );
        $this->rules = str_replace( ')', ')</i>', $this->rules );
        $this->rules = MTGSymbolReplace( $this->rules );
    }

    public function GetlatestSet()
    {
        $latestSet = null;
        $highestIndex = -1;
        foreach ( $this->sets as $set )
        {
            $setcode = $set->code;
            $newIndex = array_search( $setcode, Defines::$SetcodeOrder );
            if ( $newIndex !== false and $newIndex > $highestIndex )
            {
                $highestIndex = $newIndex;
                $latestSet = $set;
            }
        }
        
        if ( $highestIndex == -1 )
        {
            $SearchLog->log( "Could not find set for $this->name" );
            return null;
        }
        return $latestSet;
    }
    
    public function GetFirstImageURL()
    {
        $set = $this->sets[0];
        return $this->GetImageURLInSet( $set->code );
    }

    public function GetImageURLInSet( $_setcode )
    {
        $key = array_search($_setcode, array_column($this->sets, 'code'));
        $set = $this->sets[$key];
        $imgurl = "images/cards/".$set->multiverseid.".png";
        return $imgurl;
    }
    
    public function AddSet( $_setcode, $_rarity, $cnum, $artist, $_count, $_multiverseid )
    {
        $this->total += $_count;
        $set = new Set();
        $set->code = $_setcode;
        
        $set->rarity = $_rarity;
        $set->cnum = $cnum;
        $set->artist = $artist;
        $set->multiverseid = $_multiverseid;
        
        $set->count = $_count;
        $set->symbolurl = "images/exp/".$_setcode.'_'.$_rarity.'_small.jpg';
        $this->sets[] = $set;
    }
}

class Set
{
    public $code;
    public $multiverseid;
    public $rarity;
    public $cnum;
    public $artist;
    public $count;
};

?>