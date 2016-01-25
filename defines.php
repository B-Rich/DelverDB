<?php

namespace ddb;

include_once "passwords.php";

define('__DEBUG__', true);
define('__ERROR_REPORTING__', __DEBUG__ ? -1 : 0);
error_reporting( __ERROR_REPORTING__ );
ini_set('display_errors', __DEBUG__ );
ini_set('log_errors', 1);

abstract class Defines
{
    private static $DelverDBLink = null;
    
    private static $setList = null;
    private static $blockList = null;
    
    private static $typeList = null;
    private static $subtypeList = null;
    
    public static $colourList;
    public static $rarityList;
    
    private static function openConnection()
    {
        global $SQLUsers;
        
        if ( Defines::$DelverDBLink != null )
        {
            return;    
        }
        
        $user = $SQLUsers['oracle_search'];
        Defines::$DelverDBLink = new \mysqli("localhost", $user->username, $user->password, "delverdb");
        if ( Defines::$DelverDBLink->connect_errno )
        {
            $DBLog->err( "Connection error (" . Defines::$DelverDBLink->connect_errno . ") " . Defines::$DelverDBLink->connect_error );
            die( "Connection error" );
        }
    }
    
    public static function getSetList()
    {
        if ( Defines::$setList != null )
        {
            return Defines::$setList;
        }
        
        Defines::openConnection();
        
        $stmt = Defines::$DelverDBLink->prepare( "SELECT code, name, release_date FROM sets ORDER BY release_date ASC" )
             or die( Defines::$DelverDBLink->error );
        
        $stmt->execute();
        $result = $stmt->get_result();
        Defines::$setList = array();
        
        while ( $row = $result->fetch_assoc() )
        {
            $set = new Set();
            $set->code = $row['code'];
            $set->name = $row['name'];
            $set->release_date = $row['release_date'];
            Defines::$setList[$set->code] = $set;
        }
        
        return Defines::$setList;
    }
    
    public static function getBlockList()
    {
        if ( Defines::$blockList != null )
        {
            return $Defines::$blockList;
        }
        
        Defines::openConnection();
        
        $stmt = Defines::$DelverDBLink->prepare( "
SELECT 
b.id
blockid, 
COALESCE( b.name, 'Miscellaneous' ) name,
s.code setcode
FROM sets s
LEFT JOIN blocks b
ON s.blockid = b.id
ORDER BY -b.id DESC, s.id ASC
        " ) or die( Defines::$DelverDBLink->error );
        $stmt->execute();
        $result = $stmt->get_result();
        
        Defines::$blockList = array();
        
        $sets = Defines::getSetList();
        
        while ( $row = $result->fetch_assoc() )
        {
            $setcode = $row['setcode'];
            $blockid = $row['blockid'];
            $block = null;
            
            if ( array_key_exists( $blockid, Defines::$blockList ) )
            {
                $block = Defines::$blockList[$blockid];
            }
            else
            {
                $block = new Block();
                $block->blockid = $blockid;
                $block->name = $row['name'];    
                Defines::$blockList[$blockid] = $block;
            }
            
            if ( array_key_exists( $setcode, $sets ) )
            {
                $block->sets[] = $sets[$setcode];
            }
        }
        
        return Defines::$blockList;
    }
    
    public static function getTypeList()
    {
        if ( Defines::$typeList != null )
        {
            return $Defines::$typeList;
        }
    
        Defines::openConnection();
        $stmt = Defines::$DelverDBLink->prepare( "SELECT name FROM types ORDER BY name ASC" )
            or die( Defines::$DelverDBLink->error );
        $stmt->execute();
        $result = $stmt->get_result();
        Defines::$typeList = array();
    
        while ( $row = $result->fetch_assoc() )
        {
            Defines::$typeList[] = $row['name'];
        }
    
        return Defines::$typeList;
    }
    
    public static function getSubtypeList()
    {
        if ( Defines::$subtypeList != null )
        {
            return $Defines::$subtypeList;
        }
    
        Defines::openConnection();
        $stmt = Defines::$DelverDBLink->prepare( "SELECT name FROM subtypes ORDER BY name ASC" )
            or die( Defines::$DelverDBLink->error );
        $stmt->execute();
        $result = $stmt->get_result();
        Defines::$subtypeList = array();
    
        while ( $row = $result->fetch_assoc() )
        {
            Defines::$subtypeList[] = $row['name'];
        }
    
        return Defines::$subtypeList;
    }
}

class Set
{
    public $code;
    public $name;
    public $release_date;
}

class Block
{
    public $blockid;
    public $name;
    public $sets = array();    
}

class Colour
{
    public $flag;
    public $name;    

    function __construct( $_name, $_flag )
    {
        $this->flag = $_flag;
        $this->name = $_name;    
    }
}

Defines::$colourList = array(
    'W' => new Colour( 'White', 1 ),
    'U' => new Colour( 'Blue', 2 ),
    'B' => new Colour( 'Black', 4 ),
    'R' => new Colour( 'Red', 8 ),
    'G' => new Colour( 'Green', 16 ),
);

Defines::$rarityList = array (
'L' => 'Land',
'C' => 'Common',
'U' => 'Uncommon',
'R' => 'Rare',
'M' => 'Mythic',
'B' => 'Bonus',
'S' => 'Special',
);


/*
class Defines
{
    static $SetCodeToNameMap;
    static $SetNameToCodeMap;
    static $CardFormats;
    static $SetcodeOrder;
    static $SetcodeToIndices;
    static $CardBlocksToSetCodes;
    static $ColourNamesToSymbols;
    static $ColourSymbolsToNames;
    
    static $ColourSymbolsToInt;
    static $Types;
    static $Subtypes;
    
    static $RarityNameToSymbol;
    static $RaritySymbolToName;
};

Defines::$SetcodeOrder = array(
    'VAN',
    'PPR',
    'LEA',
    'LEB',
    '2ED',
    'ARN',
    'ATQ',
    '3ED',
    'LEG',
    'DRK',
    'FEM',
    '4ED',
    'ICE',
    'CHR',
    'HML',
    'ALL',
    'MIR',
    'VIS',
    '5ED',
    'POR',
    'WTH',
    'TMP',
    'STH',
    'P02',
    'EXO',
    'UGL',
    'USG',
    'ULG',
    '6ED',
    'PTK',
    'UDS',
    'S99',
    'MMQ',
    'BRB',
    'NMS',
    'PCY',
    'S00',
    'INV',
    'BTD',
    'PLS',
    '7ED',
    'APC',
    'ODY',
    'TOR',
    'JUD',
    'ONS',
    'LGN',
    'SCG',
    '8ED',
    'MRD',
    'DST',
    '5DN',
    'CHK',
    'UNH',
    'BOK',
    'SOK',
    '9ED',
    'RAV',
    'GPT',
    'DIS',
    'CSP',
    'TSP',
    'TSB',
    'PLC',
    'FUT',
    '10E',
    'MED',
    'LRW',
    'EVG',
    'MOR',
    'SHM',
    'EVE',
    'DRB',
    'ME2',
    'ALA',
    'DD2',
    'CON',
    'DDC',
    'ARB',
    'M10',
    'V09',
    'HOP',
    'ME3',
    'ZEN',
    'DDD',
    'H09',
    'WWK',
    'DDE',
    'ROE',
    'DPA',
    'DPD',
    'ARC',
    'M11',
    'V10',
    'DDF',
    'SOM',
    'PD2',
    'ME4',
    'MBS',
    'DDG',
    'NPH',
    'CMD',
    'M12',
    'V11',
    'DDH',
    'ISD',
    'PD3',
    'DKA',
    'DDI',
    'AVR',
    'PC2',
    'M13',
    'V12',
    'DDJ',
    'RTR',
    'GTC',
    'CM1',
    'DDK',
    'DGM',
    'MMA',
    'M14',
    'V13',
    'DDL',
    'THS',
    'C13',
    'BNG',
    'DDM',
    'JOU',
    'VMA',
    'CNS',
    'M15',
    'V14',
    'DDN',
    'KTK',
    'C14',
    'FRF',
    'DDO',
    'DTK',
    'MM2',
    'ORI',
);

Defines::$SetcodeToIndices = array_flip(Defines::$SetcodeOrder);

Defines::$SetCodeToNameMap = array(
    'LEA' => "Limited Edition Alpha",
    'LEB' => "Limited Edition Beta",
    '2ED' => "Unlimited Edition",
    'ARN' => "Arabian Nights",
    'ATQ' => "Antiquities",
    '3ED' => "Revised Edition",
    'LEG' => "Legends",
    'DRK' => "The Dark",
    'FEM' => "Fallen Empires",
    '4ED' => "Fourth Edition",
    'ICE' => "Ice Age",
    'CHR' => "Chronicles",
    'HML' => "Homelands",
    'ALL' => "Alliances",
    'MIR' => "Mirage",
    'VIS' => "Visions",
    '5ED' => "Fifth Edition",
    'POR' => "Portal",
    'WTH' => "Weatherlight",
    'TMP' => "Tempest",
    'STH' => "Stronghold",
    'P02' => "Portal Second Age",
    'EXO' => "Exodus",
    'UGL' => "Unglued",
    'USG' => "Urza's Saga",
    'ULG' => "Urza's Legacy",
    '6ED' => "Classic Sixth Edition",
    'PTK' => "Portal Three Kingdoms",
    'UDS' => "Urza's Destiny",
    'S99' => "Starter 1999",
    'MMQ' => "Mercadian Masques",
    'BRB' => "Battle Royale Box Set",
    'NMS' => "Nemesis",
    'PCY' => "Prophecy",
    'S00' => "Starter 2000",
    'INV' => "Invasion",
    'BTD' => "Beatdown Box Set",
    'PLS' => "Planeshift",
    '7ED' => "Seventh Edition",
    'APC' => "Apocalypse",
    'ODY' => "Odyssey",
    'TOR' => "Torment",
    'JUD' => "Judgment",
    'ONS' => "Onslaught",
    'LGN' => "Legions",
    'SCG' => "Scourge",
    '8ED' => "Eighth Edition",
    'MRD' => "Mirrodin",
    'DST' => "Darksteel",
    '5DN' => "Fifth Dawn",
    'CHK' => "Champions of Kamigawa",
    'UNH' => "Unhinged",
    'BOK' => "Betrayers of Kamigawa",
    'SOK' => "Saviors of Kamigawa",
    '9ED' => "Ninth Edition",
    'RAV' => "Ravnica: City of Guilds",
    'GPT' => "Guildpact",
    'DIS' => "Dissension",
    'CSP' => "Coldsnap",
    'TSP' => "Time Spiral",
    'TSB' => "Time Spiral Timeshifted",
    'PLC' => "Planar Chaos",
    'FUT' => "Future Sight",
    '10E' => "Tenth Edition",
    'MED' => "Masters Edition",
    'LRW' => "Lorwyn",
    'EVG' => "Duel Decks: Elves vs. Goblins",
    'MOR' => "Morningtide",
    'SHM' => "Shadowmoor",
    'EVE' => "Eventide",
    'DRB' => "From the Vault: Dragons",
    'ME2' => "Masters Edition II",
    'ALA' => "Shards of Alara",
    'DD2' => "Duel Decks: Jace vs. Chandra",
    'CON' => "Conflux",
    'DDC' => "Duel Decks: Divine vs. Demonic",
    'ARB' => "Alara Reborn",
    'M10' => "Magic 2010",
    'V09' => "From the Vault: Exiled",
    'HOP' => "Planechase",
    'ME3' => "Masters Edition III",
    'ZEN' => "Zendikar",
    'DDD' => "Duel Decks: Garruk vs. Liliana",
    'H09' => "Premium Deck Series: Slivers",
    'WWK' => "Worldwake",
    'DDE' => "Duel Decks: Phyrexia vs. the Coalition",
    'ROE' => "Rise of the Eldrazi",
    'DPA' => "Duel of the Planeswalkers Decks",
    'ARC' => "Archenemy",
    'M11' => "Magic 2011",
    'V10' => "From the Vault: Relics",
    'DDF' => "Duel Decks: Elspeth vs. Tezzeret",
    'SOM' => "Scars of Mirrodin",
    'PD2' => "Premium Deck Series: Fire and Lightning",
    'ME4' => "Masters Edition IV",
    'MBS' => "Mirrodin Besieged",
    'DDG' => "Duel Decks: Knights vs. Dragons",
    'NPH' => "New Phyrexia",
    'CMD' => "Magic: The Gathering-Commander",
    'M12' => "Magic 2012",
    'V11' => "From the Vault: Legends",
    'DDH' => "Duel Decks: Ajani vs. Nicol Bolas",
    'ISD' => "Innistrad",
    'PD3' => "Premium Deck Series: Graveborn",
    'DKA' => "Dark Ascension",
    'DDI' => "Duel Decks: Venser vs. Koth",
    'AVR' => "Avacyn Restored",
    'PC2' => "Planechase 2012 Edition",
    'M13' => "Magic 2013",
    'V12' => "From the Vault: Realms",
    'DDJ' => "Duel Decks: Izzet vs. Golgari",
    'RTR' => "Return to Ravnica",
    'GTC' => "Gatecrash",
    'CM1' => "Commander's Arsenal",
    'DGM' => "Dragon's Maze",
    'DDK' => "Duel Decks: Sorin vs. Tibalt",
    'MMA' => "Modern Masters",
    'VAN' => "Vanguard",
    'PPR' => "Promo set for Gatherer",
    'M14' => "Magic 2014",
    'V13' => "From the Vaults: Twenty",
    'DDL' => "Duel Decks: Heroes vs. Monsters",
    'THS' => "Theros",
    'C13' => "Commander 2013",
    'BNG' => "Born of the Gods",
    'DDM' => "Duel Decks: Jace vs. Vraska",
    'JOU' => "Journey into Nyx",
    'VMA' => "Vintage Masters",
    'CNS' => "Conspiracy",
    'M15' => "Magic 2015",
    'V14' => "From the Vaults: Annihilation",
    'DDN' => "Duel Decks: Speed vs. Cunning",
    'KTK' => "Khans of Tarkir",
    'C14' => "Commander 2014",
    'FRF' => "Fate Reforged",
    'DDO' => "Duel Decks: Kiora vs. Elspeth",
    'DTK' => "Dragons of Tarkir",
    'MM2' => "Modern Masters 2015",
    'ORI' => "Magic Origins",
);

Defines::$SetNameToCodeMap = array_flip(Defines::$SetCodeToNameMap);

Defines::$CardBlocksToSetCodes = array(
    "Khans Block"                => array( 'KTK', 'FRF', 'DTK', ),
    "Theros Block"                => array( 'THS', 'BNG', 'JOU', ),
    "Return to Ravnica Block"    => array( 'RTR', 'GTC', 'DGM', ),
    "Innistrad Block"            => array( 'ISD', 'DKA', 'AVR', ),
    "Scars of Mirrodin Block"    => array( 'SOM', 'MBS', 'NPH', ),
    "Zendikar Block"            => array( 'ZEN', 'WWK', 'ROE', ),
    "Shards of Alara Block"        => array( 'ALA', 'CON', 'ARB', ),
    "Shadowmoor Block"            => array( 'SHM', 'EVE', ),
    "Lorwyn Block"                => array( 'LRW', 'MOR', ),
    "Time Spiral Block"            => array( 'TSP', 'TSB', 'PLC', 'FUT', ),
    "Ravnica Block"                => array( 'RAV', 'GPT', 'DIS', ),
    "Kamigawa Block"            => array( 'CHK', 'BOK', 'SOK', ),
    "Mirrodin Block"            => array( 'MRD', 'DST', '5DN', ),
    "Onslaught Block"            => array( 'ONS', 'LGN', 'SCG', ),
    "Odyssey Block"                => array( 'ODY', 'TOR', 'JUD', ),
    "Invasion Block"            => array( 'INV', 'PLS', 'APC', ),
    "Masques Block"                => array( 'MMQ', 'NMS', 'PCY', ),
    "Urza's Block"                => array( 'USG', 'ULG', 'UDS', ),
    "Rath Block"                => array( 'TMP', 'STH', 'EXO', ),
    "Mirage Block"                => array( 'MIR', 'VIS', 'WTH', ),
    "Ice Age Block"                => array( 'ICE', 'ALL', 'CSP', ),
    "Early Expansions"            => array( 'ARN', 'ATQ', 'LEG', 'DRK', 'FEM', ),
    "Core Sets"                    => array( 'LEA', 'LEB', '2ED', '3ED', '4ED', '5ED', '6ED', '7ED', '8ED', '9ED', '10E', 'M10', 'M11', 'M12', 'M13', 'M14', 'M15', 'ORI', ),
    "Duel Decks"                => array( 'EVG', 'DD2', 'DDC', 'DDD', 'DDE', 'DDF', 'DDG', 'DDH', 'DDI', 'DDJ', 'DDK', 'DDL', 'DDM', 'DDN', 'DDO', ),
    "From the Vault"            => array( 'DRB', 'V09', 'V10', 'V11', 'V12', 'V13', ),
    "Un- Sets"                    => array( 'UGL', 'UNH', ),
    "Portal / Starter Sets"        => array( 'POR', 'P02', 'PTK', 'S99', 'S00', ),
    "Casual Supplements"        => array( 'VAN', 'HOP', 'ARC', 'CMD', 'PC2', 'C13', 'CNS', 'C14', ),
    "Premium Decks"                => array( 'H09', 'PD2', 'PD3', ),
    "Miscellaneous"                => array( 'PPR', 'ME3', 'ME2', 'MED', 'CHR', 'BTD', 'BRB', 'MMA', 'VMA',),
);


Defines::$CardFormats = array(
    "Standard"                    => array( 'THS', 'BNG', 'JOU', 'M15', 'KTK', 'FRF', 'DTK', ),
    //"Extended"                    => array("SOM", "MBS", "NPH", "M12", "ISD", "DKA", "AVR", "M13", "RTR", "GTC", 'DGM', 'M14', 'THS', 'BNG', 'JOU', 'M15', 'KTK', 'FRF', 'DTK', ),
    "Modern"                    => array("8ED", "MRD", "DST", "5DN", "CHK", "BOK", "SOK", "9ED", "RAV", "GPT", "DIS", "CSP", "TSP", "TSB", "PLC", "FUT", "10E", "LRW", "MOR", "SHM", "EVE", "ALA", "CON", "ARB", "M10", "ZEN", "WWK", "ROE", "M11", "SOM", "MBS", "NPH", "M12", "ISD", "DKA", "AVR", "M13", "RTR", "GTC", 'DGM', 'M14', 'THS', 'BNG', 'JOU', 'M15', 'KTK', 'FRF', 'DTK', 'MM2', ),
    "Core Sets"                    => array('LEA', 'LEB', '2ED', '3ED', '4ED', '5ED', '6ED', '7ED', '8ED', '9ED', '10E', 'M10', 'M11', 'M12', 'M13', 'M14', 'M15', 'ORI',),
    "Ice Age Block"                => array("ICE", "ALL", "CSP" ),
    "Innistrad Block"            => array("ISD", "DKA", "AVR" ),
    "Invasion Block"            => array("INV", "PLS", "APC" ),
    "Kamigawa Block"            => array("CHK", "BOK", "SOK" ),
    "Lorwyn-Shadowmoor Block"    => array("LRW", "MOR", "SHM", "EVE" ),
    "Masques Block"                => array("MMQ", "NMS", "PCY" ),
    "Mirage Block"                => array("MIR", "VIS", "WTH" ),
    "Mirrodin Block"            => array("MRD", "DST", "5DN" ),
    "Odyssey Block"                => array("ODY", "TOR", "JUD" ),
    "Onslaught Block"            => array("ONS", "LGN", "SCG" ),
    "Ravnica Block"                => array("RAV", "GPT", "DIS" ),
    "Return to Ravnica Block"    => array("RTR", "GTC", "DGM" ),
    "Scars of Mirrodin Block"    => array("SOM", "MBS", "NPH" ),
    "Shards of Alara Block"        => array("ALA", "CON", "ARB" ),
    "Tempest Block"                => array("TMP", "STH", "EXO" ),
    "Time Spiral Block"            => array("TSP", "TSB", "PLC", "FUT" ),
    "Theros Block"                => array('THS', 'BNG', ),
    "Un-Sets"                    => array("UGL", "UNH" ),
    "Urza Block"                => array("USG", "ULG", "UDS" ),
    "Zendikar Block"            => array("ZEN", "WWK", "ROE" ),
);


Defines::$ColourNamesToSymbols = array (
    'White'    => 'W',
    'Blue'    => 'U',
    'Black'    => 'B',
    'Red'    => 'R',
    'Green'    => 'G',
);
Defines::$ColourSymbolsToNames = array_flip(Defines::$ColourNamesToSymbols);

Defines::$ColourSymbolsToInt = array (
    'W' => 1,
    'U' => 2,
    'B' => 4,
    'R' => 8,
    'G' => 16,
);

Defines::$Types = array(
    'Artifact', 'Basic', 'Creature', 'Enchantment', 'Instant', 'Land', 'Legendary', 'Ongoing',
    'Phenomenon', 'Plane', 'Planeswalker', 'Snow', 'Sorcery', 'Tribal', 'World',
);

Defines::$Subtypes = array(

    "Advisor", "Ajani", "Alara", "Ally", "Angel", "Anteater", "Antelope", "Ape", "Arcane", 
    "Archer", "Archon", "Arkhos", "Artificer", "Ashiok", "Assassin", "Assembly-Worker", "Atog", 
    "Aura", "Aurochs", "Avatar", "Azgol", "Baddest,", "Badger", "Barbarian", "Basilisk", "Bat", 
    "Bear", "Beast", "Beeble", "Belenon", "Berserker", "Biggest,", "Bird", "Boar", "Bolas", "Bolas’s",
     "Bringer", "Brushwagg", "Bureaucrat", "Camel", "Carrier", "Cat", "Centaur", "Cephalid", 
     "Chandra", "Chicken", "Child", "Chimera", "Clamfolk", "Cleric", "Cockatrice", "Construct", 
     "Cow", "Crab", "Creature", "Crocodile", "Curse", "Cyclops", "Dack", "Dauthi", "Demon", "Desert",
      "Designer", "Devil", "Dinosaur", "Djinn", "Dominaria", "Domri", "Donkey", "Dragon", "Drake", 
      "Dreadnought", "Drone", "Druid", "Dryad", "Dwarf", "Efreet", "Egg", "Elder", "Eldrazi", 
      "Elemental", "Elephant", "Elf", "Elk", "Elspeth", "Elves", "Equilor", "Equipment", "Ergamon", 
      "Etiquette", "Ever", "Eye", "Fabacin", "Faerie", "Ferret", "Fish", "Flagbearer", "Forest", 
      "Fortification", "Fox", "Frog", "Fungus", "Gamer", "Gargoyle", "Garruk", "Gate", "Giant", 
      "Gideon", "Gnome", "Goat", "Goblin", "Goblins", "God", "Golem", "Gorgon", "Gremlin", 
      "Griffin", "Gus", "Hag", "Harpy", "Hellion", "Hero", "Hippo", "Hippogriff", "Homarid", 
      "Homunculus", "Horror", "Horse", "Hound", "Human", "Hydra", "Hyena", "Igpay", "Illusion", 
      "Imp", "Incarnation", "Innistrad", "Insect", "Iquatana", "Ir", "Island", "Jace", "Jellyfish", 
      "Juggernaut", "Kaldheim", "Kamigawa", "Karn", "Kavu", "Kephalai", "Kiora", "Kirin", "Kithkin", 
      "Knight", "Kobold", "Kolbahan", "Kor", "Koth", "Kraken", "Kyneth", "Lady", "Lair", "Lamia", 
      "Lammasu", "Leech", "Legend", "Leviathan", "Lhurgoyf", "Licid", "Liliana", "Lizard", "Locus", 
      "Lord", "Lorwyn", "Manticore", "Masticore", "Meditation", "Mercadia", "Mercenary", "Merfolk", 
      "Metathran", "Mime", "Mine", "Minion", "Minotaur", "Mirrodin", "Moag", "Monger", "Mongoose", 
      "Mongseng", "Monk", "Moonfolk", "Mountain", "Mummy", "Muraganda", "Mutant", "Myr", "Mystic", 
      "Naga", "Nastiest,", "Nautilus", "Nephilim", "New", "Nightmare", "Nightstalker", "Ninja", 
      "Nissa", "Noggle", "Nomad", "Nymph", "Octopus", "Ogre", "Ooze", "Orc", "Orgg", "Ouphe", "Ox", 
      "Oyster", "Paratrooper", "Pegasus", "Pest", "Phelddagrif", "Phoenix", "Phyrexia", "Pirate", 
      "Plains", "Plant", "Power-Plant", "Praetor", "Proper", "Rabbit", "Rabiah", "Ral", "Rat", 
      "Rath", "Ravnica", "Realm", "Rebel", "Regatha", "Rhino", "Rigger", "Rogue", "Sable", 
      "Salamander", "Samurai", "Saproling", "Sarkhan", "Satyr", "Scarecrow", "Scariest,", 
      "Scorpion", "Scout", "See", "Segovia", "Serpent", "Serra’s", "Shade", "Shadowmoor", 
      "Shaman", "Shandalar", "Shapeshifter", "Sheep", "Ship", "Shrine", "Siren", "Skeleton", 
      "Slith", "Sliver", "Slug", "Snake", "Soldier", "Soltari", "Sorin", "Spawn", "Specter", 
      "Spellshaper", "Sphinx", "Spider", "Spike", "Spirit", "Sponge", "Squid", "Squirrel", 
      "Starfish", "Surrakar", "Swamp", "Tamiyo", "Tezzeret", "Thalakos", "The", "Thopter", 
      "Thrull", "Tibalt", "Tower", "Townsfolk", "Trap", "Treefolk", "Troll", "Turtle", "Ulgrotha", 
      "Unicorn", "Urza’s", "Valla", "Vampire", "Vedalken", "Venser", "Viashino", "Volver", "Vraska", 
      "Vryn", "Waiter", "Wall", "Warrior", "Weird", "Werewolf", "Whale", "Wildfire", "Wizard", 
      "Wolf", "Wolverine", "Wombat", "Worm", "Wraith", "Wurm", "Xenagos", "Xerex", "Yeti", "You'll", 
      "Zendikar", "Zombie", "Zubera",
);

Defines::$RarityNameToSymbol = array (
    'Land' => 'L',
    'Common' => 'C',
    'Uncommon' => 'U',
    'Rare' => 'R',
    'Mythic' => 'M',
    'Bonus' => 'B',
    'Special' => 'S',
);

Defines::$RaritySymbolToName = array_flip( Defines::$RarityNameToSymbol );*/
?>