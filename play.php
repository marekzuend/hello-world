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
        $sql = sprintf("REPLACE INTO `Player` (`ClassName`, `LastEdited`, `UserName`, `Level`, `Class`, `Game`, `ProfileID`) VALUES".
                "('Player', NOW(), '%s', '%d', '%s', 'iMobsters', '%d');", $db->real_escape_string($user['Name']),
                (int)$user['Level'], $db->real_escape_string($user['Class']), (int)$user['ProfileID']);
        $db->query($sql) or $imob->Log($db->error, 'W');
        return $user;
    }
    return $v;
}


if($imob) { //we haz ignition, rage engage.
    $energysleeptil = 0;
    $sc = 0; //sleeps counter, fuck bitches get money
    $tresleeps = false;

    while(1) {
        //reset some loopy stuff
        $users = array();
        $skip_sc = false;

        if($imob->energy == $imob->maxenergy || $imob->energy > ($imob->levelexp - $imob->exp)) {
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

        if($imob->stamina > 0 && $imob->health < $imob->maxhealth) $imob->Heal();

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

        if($tresleeps !== false && $sc >= $tresleeps) { //should skip first run
            //spend that cash
            $needed = $tre['NewCost'] - $imob->cash;
            if($needed > 0) { 
                $imob->BankWithdraw($needed);
            }
            $imob->Auth(); //bank withdrawals seem to fuck with future posts... this is a bandaid.

            $buy = $imob->TopRealEstate(true, true);
            $sc = 0;
        } 

        /*
         * determine how many sleeps till the easter bunny comes. 
         * loose estimate, cant predict attacks etc, money made, lost, or upkeep changes
         * 11% of income (per tick) for a bit of buffer..?. probably needs to be higher to compensate for 10% deposit fee
         * healing is also kind of expensive..
         */
        $tre = $imob->TopRealEstate(false, false);
        $target = $tre['NewCost'] - ($imob->BankBalance() + $imob->cash);
        $tresleeps = ceil($target / $imob->income);
        //plus 11% per tick...
        $add = (($imob->income / 100) * ($tresleeps * 11));
        if($add != 0) $tresleeps += ceil($target / $add);

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

        //see if it reall is time to nap or can we keep playing?
        if($imob->health > 30 && $imob->stamina > 0) continue;

        $imob->BankDeposit();

        //prevent cash regen after deposit. it happens -_-
        if($imob->cash > 0) { 
            $sc++;
            continue;
        }

        //get the kids ready for bed, no smokes or alcohol after 8pm ya little shits.
        $now = date('U');

        $energysleeptil = ($imob->maxenergy - $imob->energy) * $imob->energyrate + $now;
        $sleeptil = $now + $imob->timeleft+10;
        $tretogo = $tresleeps - $sc;

        $timelog = true;
        $si=0;

        while($sleeptil > $now) {
            if($imob->energy == $imob->maxenergy || $imob->energy > ($imob->levelexp - $imob->exp)) {
                $skip_sc = true;
                break;
            }
            $now = date('U');
            $min = ($sleeptil-$now)/60;
            $energymin = ($energysleeptil-$now)/60;
            $s = array('-', '\\', '|', '/');
            if($si == 4) $si = 0;
            $sym = $s[$si++];
            if($timelog == true) {
                $imob->Log(sprintf("Sleeping for %d minutes (until %s)\n\tenergy restored in %d minutes\n\treal estate in %d sleeps", $min, date('d-M-Y G:i:s', $sleeptil), $energymin, $tretogo), 'N');
                $timelog = false;
            }
            printf("\r[%s] Notice: Sleeping for %d minutes (until %s), energy restored in %d minutes", $sym, $min, date('d-M-Y G:i:s', $sleeptil), $energymin);
            sleep(2);
        }
        if(!$skip_sc) $sc++;
        echo "\n";
        $imob->Auth();
    }
}
