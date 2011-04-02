<?php

require_once('libimob.php');


#$db = new mysqli('localhost', 'lol0', 'dongs', 'dongllah');
require_once('db.php');

function updateUser($v) {
    global $db, $imob;

    //check to make sure its another player
    if($v['Profile'] == 'profile') return;
    $do_update_user = true;

    $sql = sprintf("SELECT `LastEdited` FROM `Player` WHERE `ProfileID` = '%d' LIMIT 1", (int)$v['ProfileID']);
    $result = $db->query($sql) or $imob->Log($db->error, 'W');
    if($result->num_rows == 1) {
        $row = $result->fetch_assoc();
        if(date('U') - strtotime($row['LastEdited'] < 79800)) { // at least 1 day
            $users[] = $v['ProfileID'];
            $result->free();
            $do_update_user = false;
        }
    }
    if($do_update_user) {
        $user = $imob->UserInfo($v['Profile']);
        if(array_key_exists('MobSize', $v)) {
            $sql = sprintf("REPLACE INTO `Player` (`ClassName`, `LastEdited`, `UserName`, `Level`, `Class`, `Game`, `ProfileID`, `MobSize`) VALUES".
                    "('Player', NOW(), '%s', '%d', '%s', 'iMobsters', '%d', '%d');", $db->real_escape_string($user['Name']),
                    (int)$user['Level'], $db->real_escape_string($user['Class']), (int)$user['ProfileID'], (int)$v['MobSize']);        
        } else {
            $sql = sprintf("REPLACE INTO `Player` (`ClassName`, `LastEdited`, `UserName`, `Level`, `Class`, `Game`, `ProfileID`) VALUES".
                    "('Player', NOW(), '%s', '%d', '%s', 'iMobsters', '%d');", $db->real_escape_string($user['Name']),
                    (int)$user['Level'], $db->real_escape_string($user['Class']), (int)$user['ProfileID']);
        }
        $db->query($sql) or $imob->Log($db->error, 'W');
        return $user;
    }
    return $v;
}


if($imob) { //we haz ignition, rage engage.
    $energysleeptil = 0;
    //fuck bitches get money
    $tresleeps = false;

    while(1) {
        //reset some loopy stuff
        $users = array();
        //all the people who were mean to you in high school.
        $hitlist = true;

        if($imob->energy == $imob->maxenergy 
            || $imob->energy > ($imob->levelexp - $imob->exp) //try to levelup instead of wait
            || ($imob->maxenergy - $imob->energy) * $imob->energyrate < $imob->timeleft) { //run to the nearest tick before refil, ensures cash
            //do some missions to progress
            do {
                if($mission = $imob->MissionBestExp(true)) {
                    $imob->DoMission($mission);
                }
            } while($imob->energy > 0 && $mission !== false);

            //do some missions for xp
            while($imob->energy > 0) {
                $imob->DoMission($imob->MissionBestExp());
            }
        }

        //dont waste cash on healing for 1 stamina etc
        if($imob->stamina > 10 && $imob->health < $imob->maxhealth) $imob->Heal();

        //fight for some cash
        while($imob->stamina > 0 && $imob->health > 30) {
            $t = $imob->Targets();
            foreach($t as $k => $v) {
            if(in_array($v['ProfileID'], $users)) continue;
                $u = updateUser($v);
                $users[] = $u['ProfileID'];
                $imob->Fight($v);
            }
        }

        if($tresleeps !== false) { //should skip first run
            if(($imob->cash + $imob->bankbalance) > $tre['NewCost']) {
                //spend that cash
                $needed = $tre['NewCost'] - $imob->cash;
                if($needed > 0) { 
                    $imob->BankWithdraw($needed);
                }
                $imob->TopRealEstate(true, true);
            }
        } 

        if($imob->income > 2000000) { //low income players shouldnt bother
            //process the (s)hitlist
            $result = $db->query("SELECT * FROM `Player` WHERE `Hitlist` = 1") or $imob->Log($db->error, 'W');
            if($result->num_rows > 0) {
                while(($imob->cash + $imob->bankbalance) > 10000 && ($player = $result->fetch_assoc())) {
//                    if($imob->cash < 10000) $imob->BankWithdraw(10000 - $imob->cash);
                    if($imob->DoHitlist($player) == false) break;
                }
            }
        }

        /*
         * determine how many sleeps till the easter bunny comes. 
         * loose estimate, cant predict attacks etc, money made, lost, or upkeep changes
         * 11% of income (per tick) for a bit of buffer..?. probably needs to be higher to compensate for 10% deposit fee
         * healing is also kind of expensive..
         */
        $tre = $imob->TopRealEstate(false, false);
        $target = $tre['NewCost'] - ($imob->bankbalance + $imob->cash);
        $tresleeps = ceil($target / $imob->income);
        //plus 11% per tick...
        $add = (($imob->income / 100) * ($tresleeps * 11));
        if($add > 0) $tresleeps += ceil($target / $add);

        $imob->Log(sprintf('Buying Real Estate in %d sleeps, aiming to buy %s for $%d ($%d)', $tresleeps, $tre['Name'], $tre['NewCost'], $tre['Income']), 'I');

        if($tresleeps <= 0) continue;

        //crawl some comments and profiles
       $skip_comments = false;            

       foreach($imob->Comments() as $k => $v) {
            if($v['Profile'] == 'profile') continue; //fuck that its user posting to them self
            if(in_array($v['ProfileID'], $users)) continue;
            
            $u = updateUser($v);
            $users[] = $v['ProfileID'];

            if(!$skip_comments) {

                $sql = sprintf("SELECT `ID` FROM `GameComment` WHERE `FromID` = '%s' AND `Text` = '%s' LIMIT 1", (int)$v['ProfileID'], $db->real_escape_string($v['Comment']));
                $result = $db->query($sql) or $imob->Log($db->error, 'W');
                if($result->num_rows == 0) {
                    $sql = sprintf("INSERT INTO `GameComment` (`ClassName`, `Created`, `LastEdited`, `Text`, `FromID`) VALUES ('GameComment', NOW(), NOW(), '%s', '%d');", 
                       $db->real_escape_string($v['Comment']), (int)$v['ProfileID']);
                    $db->query($sql) or $imob->Log($db->error, 'W');
                } else {
                    //assume this shit is old and skip moar inserts
//                    $skip_comments = true;
                }
                $result->free();
            }
        }

        foreach($imob->Fights() as $k => $v) {
            if(in_array($v['ProfileID'], $users)) continue;
            $u = updateUser($v);
            $users[] = $u['ProfileID'];
        }

        $imob->RespondToInvites();

        //see if it really is time to nap or can we keep playing?
        if($imob->health > 30 && $imob->stamina > 0) continue;

        if($imob->cash > 10) $imob->BankDeposit();

        //prevent cash regen after deposit. it happens -_-
        if($imob->cash > 0) continue;

        //get the kids ready for bed, no smokes or alcohol after 8pm ya little shits.
        $now = date('U');

        $energysleeptil = ($imob->maxenergy - $imob->energy) * $imob->energyrate + $now;
        $sleeptil = $now + $imob->timeleft+10;
        $timelog = true;
        $si=0;

        while($sleeptil > $now) {
            if($energysleeptil < $now || floor(($energysleeptil - $now) / $imob->energyrate) + $imob->energy > ($imob->levelexp - $imob->exp)) break;
            $now = date('U');
            $min = ($sleeptil-$now)/60;
            $energymin = ($energysleeptil-$now)/60;
            $s = array('-', '\\', '|', '/');
            if($si == 4) $si = 0;
            $sym = $s[$si++];
            if($timelog == true) {
                $imob->Log(sprintf("Sleeping for %d minutes (until %s)\n\tenergy restored in %d minutes\n\treal estate in %d sleeps", $min, date('d-M-Y G:i:s', $sleeptil), $energymin, $tresleeps), 'N');
                $timelog = false;
            }
            printf("\r[%s] Notice: Sleeping for %d minutes (until %s), energy restored in %d minutes", $sym, $min, date('d-M-Y G:i:s', $sleeptil), $energymin);
            sleep(2);
        }
        echo "\n";
        $imob->Auth();
    }
}
