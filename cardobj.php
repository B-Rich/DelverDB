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
		$this->id = $cardRow['cardid'];
		$this->name = $cardRow['name'];
		$this->name = str_replace('"', '', $this->name);
		$this->cost = $cardRow['cost'];
		$this->cost = MTGSymbolReplace($this->cost);

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
		$this->rules = MTGSymbolReplace($this->rules);
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
		$replacements = array('"', '|', '<', '>', '?', '\\', '/', '*', ':');
		$cardname = str_replace($replacements, "", $this->name);

		$imgurl = "images/cardpic/_".$_setcode."/$cardname.jpg";
		$imgurl = str_replace(' ', '%20', $imgurl);
		return $imgurl;
	}

	public function AddSet( $_setcode, $_rarity, $cnum, $artist, $_count )
	{
		$this->total += $_count;
		$set = new Set();
		$set->code = $_setcode;
		$set->name = Defines::$SetCodeToNameMap[$_setcode];
		$set->rarity = $_rarity;
		$set->cnum = $cnum;
		$set->artist = $artist;
		$set->imageurl = $this->GetImageURLInSet($_setcode);

		$set->count = $_count;
		$set->symbolurl = "images/exp/".$_setcode.'_'.$_rarity.'_small.jpg';
		$this->sets[] = $set;
	}
}

class Set
{
	public $code;
	public $name;
	public $imageurl;
	public $rarity;
	public $cnum;
	public $artist;
	public $count;
	public $symbolurl;
};

?>