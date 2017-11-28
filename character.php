<?php
/***************************************************************************
 *
 *   This program is free software; you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation; either version 2 of the License, or
 *   (at your option) any later version.
 *
 *   Portions of this program are derived from publicly licensed software
 *   projects including, but not limited to phpBB, Magelo Clone, 
 *   EQEmulator, EQEditor, and Allakhazam Clone.
 *
 *                                  Author:
 *                           Maudigan(Airwalking) 
 *
 *   February 25, 2014 - added heroic stats/augs (Maudigan c/o Kinglykrab) 
 *   September 26, 2014 - Maudigan
 *      made STR/STA/DEX/etc lowercase to match the db column names
 *      Updated character table name
 *      rewrote the code that pulls guild name/rank
 *   September 28, 2014 - Maudigan
 *      added code to monitor database performance
 *      altered character profile initialization to remove redundant query
 ***************************************************************************/
 
 
 
 
define('INCHARBROWSER', true);
include_once("include/config.php");
include_once("include/global.php");
include_once("include/language.php");
include_once("include/functions.php");
include_once("include/profile.php");
include_once("include/itemclass.php");
include_once("include/statsclass.php");
include_once("include/calculatestats.php");

																							
//if character name isnt provided post error message and exit
if(!$_GET['char']) message_die($language['MESSAGE_ERROR'],$language['MESSAGE_NO_CHAR']);
else $charName = $_GET['char'];
    

//character initializations - rewritten 9/28/2014
$char = new profile($charName); //the profile class will sanitize the character name
$charID = $char->char_id(); 
$mypermission = GetPermissions($char->GetValue('gm'), $char->GetValue('anon'), $char->char_id());

//block view if user level doesnt have permission
if ($mypermission['inventory']) message_die($language['MESSAGE_ERROR'],$language['MESSAGE_ITEM_NO_VIEW']);


//load profile information for the character
$name 		= $char->GetValue('name');
$last_name 	= $char->GetValue('last_name');
$title 		= $char->GetValue('title');
$level 		= $char->GetValue('level');
$deity 		= $char->GetValue('deity');
$baseSTR 	= $char->GetValue('str'); //changed stats to lowercase 9/26/2014
$baseSTA 	= $char->GetValue('sta');
$baseAGI 	= $char->GetValue('agi');
$baseDEX 	= $char->GetValue('dex');
$baseWIS 	= $char->GetValue('wis');
$baseINT 	= $char->GetValue('int');
$baseCHA 	= $char->GetValue('cha');
$defense 	= $char->GetValue('defense'); //TODO multi row table
$offense 	= $char->GetValue('offense'); //TODO multi row table
$race 		= $char->GetValue('race');
$class 		= $char->GetValue('class');
$pp 		= $char->GetValue('platinum');
$gp 		= $char->GetValue('gold');
$sp 		= $char->GetValue('silver');
$cp 		= $char->GetValue('copper');
$bpp 		= $char->GetValue('platinum_bank');
$bgp 		= $char->GetValue('gold_bank');
$bsp 		= $char->GetValue('silver_bank');
$bcp 		= $char->GetValue('copper_bank'); 

//load guild name
//rewritten because the guild id was removed from the profile 9/26/2014
$query = "SELECT guilds.name, guild_members.rank 
          FROM guilds
          JOIN guild_members
          ON guilds.id = guild_members.guild_id
          WHERE guild_members.char_id = $charID LIMIT 1";
if (defined('DB_PERFORMANCE')) dbp_query_stat('query', $query); //added 9/28/2014
$results = mysql_query($query);
if(mysql_num_rows($results) != 0)
{ 
   $row = mysql_fetch_array($results);
   $guild_name = $row['name'];
   $guild_rank = $guildranks[$row['rank']];
}

// place where all the items stats are added up
$itemstats = new stats();

// holds all of the items and info about them
$allitems = array();

// pull characters inventory slotid is loaded as
// "myslot" since items table also has a slotid field.
$query = "SELECT items.*, character_inventory.slotid AS myslot from items, character_inventory where character_inventory.id = '$charID' AND  items.id = character_inventory.itemid";
if (defined('DB_PERFORMANCE')) dbp_query_stat('query', $query); //added 9/28/2014
$results = mysql_query($query);
// loop through inventory results saving Name, Icon, and preload HTML for each
// item to be pasted into its respective div later
while ($row = mysql_fetch_array($results)) {
  $tempitem = new item($row);

  if ($tempitem->type() == EQUIPMENT)
    $itemstats->additem($row);
  
  if ($tempitem->type() == EQUIPMENT || $tempitem->type() == INVENTORY)
    $itemstats->addWT($row['weight']);
  
  $allitems[$tempitem->slot()] = $tempitem;
}



//drop page
$d_title = " - ".$name.$language['PAGE_TITLES_CHARACTER'];
include("include/header.php");


//build body template
$template->set_filenames(array(
  'character' => 'character_body.tpl')
);

$template->assign_vars(array(  
  'HIGHLIGHT_GM' => (($highlightgm && $gm)? "GM":""),
  'REGEN' => $itemstats->regen(),
  'FT' => $itemstats->FT(),
  'DS' => $itemstats->DS(),
  'HASTE' => $itemstats->haste(),
  'FIRST_NAME' => $name,
  'LAST_NAME' => $last_name,
  'TITLE' => $title,
  'GUILD_NAME' => $guild_name,
  'LEVEL' => $level,
  'CLASS' => $dbclassnames[$class],
  'RACE' => $dbracenames[$race],
  'CLASS_NUM' => $class,
  'DEITY' => $dbdeities[$deity],
  'HP' => GetMaxHP($level,$class,($baseSTA+$itemstats->STA()),$itemstats->hp()),
  'MANA' => GetMaxMana($level,$class,($baseINT+$itemstats->INT()),($baseWIS+$itemstats->WIS()),+$itemstats->mana()),
  'ENDR' => GetMaxEndurance(($baseSTR+$itemstats->STR()),($baseSTA+$itemstats->STA()),($baseDEX+$itemstats->DEX()),($baseAGI+$itemstats->AGI()),$level,$itemstats->endurance()),
  'AC' => GetMaxAC(($baseAGI+$itemstats->AGI()), $level, $defense, $class, $itemstats->AC(), $race),
  'ATK' => GetMaxAtk($itemstats->attack(), ($baseSTR+$itemstats->STR()), $offense),
  'STR' => ($baseSTR+$itemstats->STR()),
  'STA' => ($baseSTA+$itemstats->STA()),
  'DEX' => ($baseDEX+$itemstats->DEX()),
  'AGI' => ($baseAGI+$itemstats->AGI()),
  'INT' => ($baseINT+$itemstats->INT()),
  'WIS' => ($baseWIS+$itemstats->WIS()),
  'CHA' => ($baseCHA+$itemstats->CHA()),
  'HSTR' => ($itemstats->HSTR()),  //added 7 lines 2/25/2014
  'HSTA' => ($itemstats->HSTA()),  
  'HDEX' => ($itemstats->HDEX()),  
  'HAGI' => ($itemstats->HAGI()),  
  'HINT' => ($itemstats->HINT()),  
  'HWIS' => ($itemstats->HWIS()),  
  'HCHA' => ($itemstats->HCHA()), 
  'POISON' => (PRbyRace($race)+$PRbyClass[$class]+$itemstats->PR()),
  'FIRE' => (FRbyRace($race)+$FRbyClass[$class]+$itemstats->FR()),
  'MAGIC' => (MRbyRace($race)+$MRbyClass[$class]+$itemstats->MR()),
  'DISEASE' => (DRbyRace($race)+$DRbyClass[$class]+$itemstats->DR()),
  'COLD' => (CRbyRace($race)+$CRbyClass[$class]+$itemstats->CR()),
  'HPOISON' => $itemstats->HPR(),   //added 5 lines 2/25/2014
  'HFIRE' => $itemstats->HFR(), 
  'HMAGIC' => $itemstats->HMR(), 
  'HDISEASE' => $itemstats->HDR(), 
  'HCOLD' => $itemstats->HCR(), 
  'WEIGHT' => round($itemstats->WT()/10),
  'PP' => (($mypermission['coininventory'])?$language['MESSAGE_DISABLED']:$pp),
  'GP' => (($mypermission['coininventory'])?$language['MESSAGE_DISABLED']:$gp),
  'SP' => (($mypermission['coininventory'])?$language['MESSAGE_DISABLED']:$sp),
  'CP' => (($mypermission['coininventory'])?$language['MESSAGE_DISABLED']:$cp),
  'BPP' => (($mypermission['coinbank'])?$language['MESSAGE_DISABLED']:$bpp),
  'BGP' => (($mypermission['coinbank'])?$language['MESSAGE_DISABLED']:$bgp),
  'BSP' => (($mypermission['coinbank'])?$language['MESSAGE_DISABLED']:$bsp),
  'BCP' => (($mypermission['coinbank'])?$language['MESSAGE_DISABLED']:$bcp),

  
 
  'L_HEADER_INVENTORY' => $language['CHAR_INVENTORY'],
  'L_HEADER_BANK' => $language['CHAR_BANK'],
  'L_REGEN' => $language['CHAR_REGEN'],
  'L_FT' => $language['CHAR_FT'],
  'L_DS' => $language['CHAR_DS'],
  'L_HASTE' => $language['CHAR_HASTE'],
  'L_HP' => $language['CHAR_HP'],
  'L_MANA' => $language['CHAR_MANA'],
  'L_ENDR' => $language['CHAR_ENDR'],
  'L_AC' => $language['CHAR_AC'],
  'L_ATK' => $language['CHAR_ATK'],
  'L_STR' => $language['CHAR_STR'],
  'L_STA' => $language['CHAR_STA'],
  'L_DEX' => $language['CHAR_DEX'],
  'L_AGI' => $language['CHAR_AGI'],
  'L_INT' => $language['CHAR_INT'],
  'L_WIS' => $language['CHAR_WIS'],
  'L_CHA' => $language['CHAR_CHA'],
  'L_HSTR' => $language['CHAR_HSTR'],   //added 7 lines 2/25/2014
  'L_HSTA' => $language['CHAR_HSTA'], 
  'L_HDEX' => $language['CHAR_HDEX'], 
  'L_HAGI' => $language['CHAR_HAGI'], 
  'L_HINT' => $language['CHAR_HINT'], 
  'L_HWIS' => $language['CHAR_HWIS'], 
  'L_HCHA' => $language['CHAR_HCHA'], 
  'L_POISON' => $language['CHAR_POISON'],
  'L_MAGIC' => $language['CHAR_MAGIC'],
  'L_DISEASE' => $language['CHAR_DISEASE'],
  'L_FIRE' => $language['CHAR_FIRE'],
  'L_COLD' => $language['CHAR_COLD'],
  'L_HPOISON' => $language['CHAR_HPOISON'],    //added 5 lines 2/25/2014
  'L_HMAGIC' => $language['CHAR_HMAGIC'], 
  'L_HDISEASE' => $language['CHAR_HDISEASE'], 
  'L_HFIRE' => $language['CHAR_HFIRE'], 
  'L_HCOLD' => $language['CHAR_HCOLD'], 
  'L_WEIGHT' => $language['CHAR_WEIGHT'],
  'L_AAS' => $language['BUTTON_AAS'],
  'L_KEYS' => $language['BUTTON_KEYS'],
  'L_FLAGS' => $language['BUTTON_FLAGS'],
  'L_SKILLS' => $language['BUTTON_SKILLS'],
  'L_CORPSE' => $language['BUTTON_CORPSE'],
  'L_INVENTORY' => $language['BUTTON_INVENTORY'],
  'L_FACTION' => $language['BUTTON_FACTION'],
  'L_BOOKMARK' => $language['BUTTON_BOOKMARK'],
  'L_CHARMOVE' => $language['BUTTON_CHARMOVE'],
  'L_CONTAINER' => $language['CHAR_CONTAINER'],
  'L_DONE' => $language['BUTTON_DONE'])
);



//dump inventory items ICONS
foreach ($allitems as $value) {
  if ($value->type() == INVENTORY && $mypermission['bags']) continue; 
  if ($value->type() == EQUIPMENT || $value->type() == INVENTORY)
    $template->assign_block_vars("invitem", array( 
      'SLOT' => $value->slot(),	   
      'ICON' => $value->icon(),
      'ISBAG' => (($value->slotcount() > 0) ? "true":"false"))
    );
}



//dump bags windows
foreach ($allitems as $value) {
  if ($value->type() == INVENTORY && $mypermission['bags']) continue; 
  if ($value->type() == BANK && $mypermission['bank']) continue;
  if ($value->slotcount() > 0)  {
  
    $template->assign_block_vars("bags", array( 
      'SLOT' => $value->slot(),	   
      'ROWS' => floor($value->slotcount()/2))
    );
    
    for ($i = 1;$i <= $value->slotcount(); $i++) 
      $template->assign_block_vars("bags.bagslots", array( 
        'BS_SLOT' => $i)
      );
      
    foreach ($allitems as $subvalue) 
  	 	if ($subvalue->type() == $value->slot()) 
  	 	  $template->assign_block_vars("bags.bagitems", array( 
        	    'BI_SLOT' => $subvalue->slot(),
        	    'BI_RELATIVE_SLOT' => $subvalue->vslot(),
        	    'BI_ICON' => $subvalue->icon())
      		  );
  } 
}


//dump bank items ICONS
if (!$mypermission['bank']) {
	foreach ($allitems as $value) {
	  if ($value->type() == BANK) 
	    $template->assign_block_vars("bankitem", array( 
	      'SLOT' => $value->slot(),	   
	      'ICON' => $value->icon(),
	      'ISBAG' => (($value->slotcount() > 0) ? "true":"false"))
	    );
	}
}

//dump items WINDWOS
foreach ($allitems as $value) {
  if ($value->type() == INVENTORY && $mypermission['bags']) continue; 
  if ($value->type() == BANK && $mypermission['bank']) continue;
    $template->assign_block_vars("item", array(
      'SLOT' => $value->slot(),	   
      'NAME' => $value->name(),
      'ID' => $value->id(),
      'HTML' => $value->html())
    );
    /*for ( $i = 0 ; $i < $value->augcount() ; $i++ ) {
      $template->assign_block_vars("item.augment", array( 	   
        'AUG_NAME' => $value->augname($i),
        'AUG_ID' => $value->augid($i),
        'AUG_HTML' => $value->aughtml($i))
      );
    }*/
}



$template->pparse('character');

$template->destroy;


//added to monitor database performance 9/28/2014
if (defined('DB_PERFORMANCE')) print dbp_dump_buffer('query');


include("include/footer.php");


?>